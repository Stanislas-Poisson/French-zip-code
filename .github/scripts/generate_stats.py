#!/usr/bin/env python3
"""Builds the statistics cards of the README.

Reads the public data.gouv.fr dataset and the GitHub repository, then writes
`stats.svg` (usage), `dataset.svg` (content and downloads by format) and `stats.json` (history) in OUT_DIR.

GitHub keeps the traffic (views and clones) for 14 days only: the daily values
are merged into the `stats.json` found in OUT_DIR so the totals keep growing.

Environment:
    GH_REPO     owner/name of the repository (default: GITHUB_REPOSITORY)
    GH_TOKEN    token with push access, needed for the traffic endpoints
    DATASET     data.gouv.fr dataset identifier (the slug changes with the title, the identifier never does)
    OUT_DIR     directory holding the previous stats.json and the output
"""

from __future__ import annotations

import base64
import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from html import escape
from pathlib import Path

REPO = os.environ.get("GH_REPO") or os.environ.get("GITHUB_REPOSITORY", "Stanislas-Poisson/French-Postal-Code")
TOKEN = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN", "")
DATASET = os.environ.get("DATASET", "5a2d54f288ee382771f2cf4f")
OUT_DIR = Path(os.environ.get("OUT_DIR", "stats"))

DATA_GOUV_LOGO_URL = "https://www.data.gouv.fr/nuxt_images/favicon.svg"
GITHUB_ICON = (
    "M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04"
    "-3.338.724-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.084-.729.084-.729 1.205.084 1.838 "
    "1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-"
    "1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 "
    "2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-"
    "2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 "
    "17.592 24 12.297c0-6.627-5.373-12-12-12"
)


def fetch(url: str, *, auth: bool = False, binary: bool = False):
    """Returns the decoded JSON (or the raw bytes) of a URL, None when it cannot be read."""
    headers = {"User-Agent": "french-postal-code-stats", "Accept": "application/vnd.github+json"}
    if auth and TOKEN:
        headers["Authorization"] = f"Bearer {TOKEN}"
    try:
        with urllib.request.urlopen(urllib.request.Request(url, headers=headers), timeout=30) as response:
            body = response.read()
    except (urllib.error.URLError, TimeoutError) as error:
        print(f"warning: cannot read {url}: {error}", file=sys.stderr)
        return None
    return body if binary else json.loads(body)


def github(path: str):
    return fetch(f"https://api.github.com/repos/{REPO}{path}", auth=True)


def compact(value: float | None) -> str:
    """49806 -> 49.8K, 1147 -> 1.1K, 3 -> 3."""
    if value is None:
        return "–"
    value = int(value)
    if value < 1000:
        return str(value)
    for limit, suffix in ((1_000_000_000, "B"), (1_000_000, "M"), (1_000, "K")):
        if value >= limit:
            text = f"{value / limit:.1f}".rstrip("0").rstrip(".")
            return f"{text}{suffix}"
    return str(value)


def month_year(iso: str | None) -> str:
    if not iso:
        return "–"
    return datetime.fromisoformat(iso.replace("Z", "+00:00")).strftime("%b %Y")


def data_uri(content: bytes, mime: str) -> str:
    return f"data:{mime};base64,{base64.b64encode(content).decode()}"


def collect_data_gouv() -> dict:
    dataset = fetch(f"https://www.data.gouv.fr/api/1/datasets/{DATASET}/") or {}
    metrics = dataset.get("metrics", {})
    quality = dataset.get("quality", {}).get("score")
    formats: dict[str, int] = {}
    for resource in dataset.get("resources", []):
        match = re.match(r"\[(\w+)", resource.get("title", ""))
        if match:
            formats[match.group(1)] = formats.get(match.group(1), 0) + (resource.get("metrics", {}).get("views") or 0)
    return {
        "formats": formats,
        "downloads": metrics.get("resources_downloads"),
        "views": metrics.get("views"),
        "reuses": metrics.get("reuses"),
        "discussions": metrics.get("discussions_open"),
        "quality": None if quality is None else round(quality * 100),
        "last_update": dataset.get("last_update"),
    }


def collect_dataset() -> dict | None:
    """Reads `statistics.json`, attached to the latest release by `make export`."""
    release = github("/releases/latest") or {}
    for asset in release.get("assets", []):
        if asset["name"] == "statistics.json":
            return fetch(asset["browser_download_url"])
    return None


def merge_traffic(history: dict, kind: str, payload: dict | None) -> None:
    """Merges the daily values of a traffic endpoint into the history, one entry per day."""
    if not payload:
        return
    days = history.setdefault(kind, {})
    for entry in payload.get(kind, []):
        days[entry["timestamp"][:10]] = {"count": entry["count"], "uniques": entry["uniques"]}


def collect_github(previous: dict) -> tuple[dict, dict]:
    history = previous.get("history", {"views": {}, "clones": {}})
    views = github("/traffic/views")
    merge_traffic(history, "views", views)
    merge_traffic(history, "clones", github("/traffic/clones"))

    releases = github("/releases?per_page=100") or []
    downloads = sum(asset["download_count"] for release in releases for asset in release.get("assets", []))
    issues = fetch(f"https://api.github.com/search/issues?q=repo:{REPO}+is:issue+is:open", auth=True) or {}
    commits = github("/commits?per_page=1") or [{}]

    stats = {
        "release_downloads": downloads,
        "visitors_14d": (views or {}).get("uniques", previous.get("github", {}).get("visitors_14d")),
        "views_total": sum(day["count"] for day in history.get("views", {}).values()) or None,
        "clones_total": sum(day["count"] for day in history.get("clones", {}).values()) or None,
        "tracked_since": min(history.get("views", {}) | history.get("clones", {}), default=None),
        "open_issues": issues.get("total_count"),
        "last_commit": commits[0].get("commit", {}).get("committer", {}).get("date"),
    }
    return stats, history


STYLE = """
  .card { fill:#ffffff; stroke:#d0d7de; }
  .tile { fill:#f6f8fa; }
  text { font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; fill:#1f2328; }
  .title { font-size:15px; font-weight:600; }
  .value { font-size:22px; font-weight:700; }
  .label, .note { fill:#656d76; }
  .label { font-size:11.5px; }
  .note  { font-size:11px; }
  .icon { fill:#1f2328; }
  .frame { fill:#ffffff; stroke:#d0d7de; }
  .bar { fill:#000091; }
  @media (prefers-color-scheme: dark) {
    .card { fill:#0d1117; stroke:#30363d; }
    .tile { fill:#161b22; }
    text { fill:#e6edf3; }
    .label, .note { fill:#8b949e; }
    .icon { fill:#e6edf3; }
    .frame { stroke:#30363d; }
    .bar { fill:#8585f6; }
  }
"""


def tile(x: int, y: int, value: str, label: str) -> str:
    return (
        f'<rect x="{x}" y="{y}" width="120" height="62" rx="8" class="tile"/>'
        f'<text x="{x + 14}" y="{y + 30}" class="value">{escape(value)}</text>'
        f'<text x="{x + 14}" y="{y + 48}" class="label">{escape(label)}</text>'
    )


def tiles(x: int, items: list[tuple[str, str]]) -> str:
    return "".join(
        tile(x + 20 + (index % 3) * 128, 62 + (index // 3) * 70, value, label) for index, (value, label) in enumerate(items)
    )


def panel(x: int, title: str, icon: str, body: str, note: str) -> str:
    return (
        f'<rect x="{x}" y="0" width="408" height="238" rx="10" class="card"/>'
        f'{icon}<text x="{x + 56}" y="35" class="title">{escape(title)}</text>{body}'
        f'<text x="{x + 20}" y="218" class="note">{escape(note)}</text>'
    )


def stats_svg(data_gouv: dict, hub: dict, logo: bytes | None) -> str:
    logo_icon = (
        f'<rect x="19.5" y="15.5" width="27" height="27" rx="6.5" class="frame"/>'
        f'<clipPath id="logo"><rect x="20" y="16" width="26" height="26" rx="6"/></clipPath>'
        f'<image x="20" y="16" width="26" height="26" clip-path="url(#logo)" href="{data_uri(logo, "image/svg+xml")}"/>'
        if logo
        else ""
    )
    github_icon = f'<g transform="translate({428 + 20} 16) scale(1.08)"><path class="icon" d="{GITHUB_ICON}"/></g>'
    left = panel(
        0,
        "data.gouv.fr",
        logo_icon,
        tiles(0, [
            (compact(data_gouv["downloads"]), "downloads"),
            (compact(data_gouv["views"]), "views"),
            (compact(data_gouv["reuses"]), "reuses"),
            (compact(data_gouv["discussions"]), "open discussions"),
            ("–" if data_gouv["quality"] is None else f'{data_gouv["quality"]}%', "metadata quality"),
            (month_year(data_gouv["last_update"]), "last update"),
        ]),
        "Public figures of the dataset page.",
    )
    since = month_year(hub["tracked_since"]) if hub["tracked_since"] else None
    right = panel(
        428,
        "GitHub",
        github_icon,
        tiles(428, [
            (compact(hub["release_downloads"]), "release downloads"),
            (compact(hub["views_total"]), "repository views"),
            (compact(hub["clones_total"]), "git clones"),
            (compact(hub["open_issues"]), "open issues"),
            (compact(hub["visitors_14d"]), "visitors (14 days)"),
            (month_year(hub["last_commit"]), "last commit"),
        ]),
        f"Views and clones counted since {since}." if since else "Views and clones need a token with push access.",
    )
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 838 240" width="838" height="240" role="img" aria-label="Usage statistics">'
        f"<style>{STYLE}</style>{left}{right}</svg>\n"
    )


def dataset_svg(dataset: dict | None, formats: dict[str, int]) -> str:
    box_icon = (
        '<g transform="translate(20 15) scale(1.1)"><path class="icon" '
        'd="M12 1 3 5.5v9L12 19l9-4.5v-9zm0 2.2 5.9 2.9L12 9 6.1 6.1zM5 7.7l6 3v6.5l-6-3zm14 0v6.5l-6 3v-6.5z"/></g>'
    )
    bars_icon = (
        '<g transform="translate(448 15) scale(1.1)"><path class="icon" d="M4 20V10h4v10zm6 0V4h4v16zm6 0v-7h4v7z"/></g>'
    )
    if dataset:
        by_source = dataset.get("cities_by_source", {})
        total = sum(by_source.values()) or 1
        items = [
            (compact(dataset.get("regions")), "regions"),
            (compact(dataset.get("departments")), "departments"),
            (compact(dataset.get("communes")), "communes"),
            (compact(dataset.get("cities")), "postal entries"),
            (compact(dataset.get("successions")), "code successions"),
            (f"{round(100 * by_source.get('ban', 0) / total)}%", "BAN points"),
        ]
        note = f"INSEE COG {dataset.get('cog_vintage') or '?'}, La Poste {dataset.get('laposte_version') or '?'}."
    else:
        items = [("–", label) for label in ("regions", "departments", "communes", "postal entries", "code successions", "BAN points")]
        note = "Figures appear with the next release."

    peak = max(formats.values(), default=0) or 1
    rows = []
    for index, (name, count) in enumerate(sorted(formats.items(), key=lambda item: -item[1])[:4]):
        y = 66 + index * 36
        width = max(4, round(250 * count / peak))
        rows.append(
            f'<text x="{448}" y="{y + 14}" class="label">{escape(name)}</text>'
            f'<rect x="{500}" y="{y}" width="{width}" height="18" rx="4" class="bar"/>'
            f'<text x="{506 + width}" y="{y + 14}" class="label">{compact(count)}</text>'
        )

    logo = panel(0, "Dataset", box_icon, tiles(0, items), note)
    downloads = panel(428, "Downloads by format", bars_icon, "".join(rows), "All archives of a format together, from data.gouv.fr.")
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 838 240" width="838" height="240" role="img" aria-label="Dataset content and downloads by format">'
        f"<style>{STYLE}</style>{logo}{downloads}</svg>\n"
    )


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    stats_file = OUT_DIR / "stats.json"
    previous = json.loads(stats_file.read_text()) if stats_file.exists() else {}

    data_gouv = collect_data_gouv()
    hub, history = collect_github(previous)
    logo = fetch(DATA_GOUV_LOGO_URL, binary=True)

    dataset = collect_dataset()

    (OUT_DIR / "stats.svg").write_text(stats_svg(data_gouv, hub, logo), encoding="utf-8")
    (OUT_DIR / "dataset.svg").write_text(dataset_svg(dataset, data_gouv["formats"]), encoding="utf-8")
    stats_file.write_text(
        json.dumps(
            {
                "updated_at": datetime.now(timezone.utc).strftime("%Y-%m-%d"),
                "data_gouv": data_gouv,
                "github": hub,
                "dataset": dataset,
                "history": history,
            },
            indent=2,
            sort_keys=True,
        )
        + "\n",
        encoding="utf-8",
    )
    print(f"stats written to {OUT_DIR}")


if __name__ == "__main__":
    main()
