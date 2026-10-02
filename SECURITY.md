# Security Policy

## Reporting vulnerabilities

| Channel | Description | Contact / Link |
| :--- | :--- | :--- |
| **Private report** | Preferred channel for sensitive reports. | [Report a vulnerability][advisories] |
| **Issues** | Non-sensitive problems, such as a wrong value in the dataset. | [Open an issue][issues] |
| **Email** | Alternative contact. | `security@the-white-rabbits.fr` |

Please **do not disclose a vulnerability publicly** until it has been reviewed and fixed.

---

## Security features

| Layer | Protection |
| :--- | :--- |
| **Static analysis** | PHPStan at the maximum level, Rector and PHPInsights on every change. |
| **Tests** | PHPUnit with 100 % coverage and no real network call. |
| **Git hooks** | Quality checks run before each commit and push (`.githooks`). |
| **CI** | The `ci` check must pass before a change reaches `develop` or `main`. |
| **Dependencies** | Dependabot proposes updates of Composer packages and GitHub Actions. |

---

## Scope

- The project is a command line tool: it has no web server, no authentication and no user account.
- It downloads public files over HTTPS from INSEE, La Poste, geo.api.gouv.fr, the BAN and Nominatim. It needs no API key and stores no secret.
- It writes the downloaded files and the exports under `storage/` and its tables in the project database.
- The dataset holds no personal data: only administrative areas, postal codes and GPS points.

[advisories]: https://github.com/Stanislas-Poisson/French-Postal-Code/security/advisories/new
[issues]: https://github.com/Stanislas-Poisson/French-Postal-Code/issues
