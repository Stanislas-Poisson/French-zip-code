# Contributing

Thank you for helping. The sections below describe the workflow of this repository.

---

## Development workflow

| Step | Command / Action | Description |
| :--- | :--- | :--- |
| **1. Install** | `make start` | Start the containers, install the dependencies and migrate the database. |
| **2. Branch** | `git checkout -b feature/#TICKET-name develop` | Create a branch from `develop`. |
| **3. Code** | *(your IDE)* | Write the change and its tests. |
| **4. Quality** | `make quality` | Run the full quality gate. |
| **5. Test** | `make test` | Make sure all the tests pass. |
| **6. Commit** | `git commit -m "type(scope): #TICKET subject"` | Use the [Conventional Commits][conventional-commits] format, in English, 72 characters at most. |
| **7. Rebase** | `git rebase develop` | Keep the branch up to date with `develop`. |
| **8. Push** | `git push origin feature/#TICKET-name` | Push and open a pull request to `develop`. |

A pull request needs a review and a green `ci` check, and is merged with a merge commit.

---

## Quality targets

| Command | Tool | Description |
| :--- | :--- | :--- |
| `make quality` | All | Full static analysis and formatting gate. |
| `make quality-fix` | Rector, Pint | Fix what can be fixed automatically. |
| `make phpstan` | PHPStan | Static analysis at the maximum level, without a baseline. |
| `make pint` | Pint | Check the code style. |
| `make rector` | Rector | Check the modernization opportunities. |
| `make markdownlint` | Markdownlint | Validate the Markdown documentation. |
| `make test` | PHPUnit | Run all the tests. |
| `make test-coverage` | PHPUnit | Run the tests with a coverage report. |

The code follows `declare(strict_types=1)`, `final` classes and explicit types. The tests never call the network: use `Http::fake()` and small sample files. The architecture is described in [`docs/architecture.md`](docs/architecture.md).

[conventional-commits]: https://www.conventionalcommits.org/
