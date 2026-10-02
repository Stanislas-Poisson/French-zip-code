# Publishing a release

The dataset files are produced by `make export` and attached to the GitHub release. They are not committed to the repository.

## Steps

1. Update the dataset and check the report.

   ```bash
   make update-sync
   make status
   ```

   The report must show `"complete":true`: every city has a point, every department was processed and no job failed.

2. Export the files.

   ```bash
   make export
   ```

3. Create the version tag, reserved to maintainers and prefixed with `v` (`v4.0.0`), on `main`, once the CI is green.

4. Create the GitHub release with the files from `storage/app/exports` as attachments.

   ```bash
   gh release create v4.0.0 --generate-notes storage/app/exports/sql/dataset.sql storage/app/exports/statistics.json
   ```

   The notes are generated from the Conventional Commits between two tags, which is why their format matters.

5. Publish the same files on data.gouv.fr.

## Versioning

The version number follows SemVer. A change to the schema of the files, for example removing a column, is a major change.

## Statistics cards

The cards of the README (`stats.svg` and `dataset.svg`) are built every Monday by the `Update stats` workflow and pushed to the `stats` branch. It can also be started by hand from the Actions tab.

GitHub keeps the traffic (views and clones) for 14 days only, so the daily values are accumulated in `stats.json` on that branch. Reading the traffic needs push access: add a repository secret named `STATS_TOKEN` (a token of a maintainer). Without it, the workflow still runs but leaves the views and clones out.

The dataset card reads `statistics.json` (volumes, source of the GPS points, versions of the sources), which `make export` writes and which must be attached to each release. Until a release carries it, the card shows dashes. The downloads by format come from data.gouv.fr.
