# Table Schemas of the dataset files

One [Table Schema](https://specs.frictionlessdata.io/table-schema/) per CSV file of the release `4.0.0`.
They describe the columns, their types and their allowed values, and are used by data.gouv.fr to validate and document the files.

| File | Schema |
| :--- | :--- |
| `regions.csv` | [`regions.schema.json`](regions.schema.json) |
| `departments.csv` | [`departments.schema.json`](departments.schema.json) |
| `communes.csv` | [`communes.schema.json`](communes.schema.json) |
| `cities.csv` | [`cities.schema.json`](cities.schema.json) |
| `commune_successions.csv` | [`commune_successions.schema.json`](commune_successions.schema.json) |
| `reference_changes.csv` | [`reference_changes.schema.json`](reference_changes.schema.json) |

Every row of the exported files was checked against these schemas: no violation.

The schemas follow the layout of the files, which only changes with a major version of the dataset.
The code and the documentation are on the [`main` branch](https://github.com/Stanislas-Poisson/French-Postal-Code).
