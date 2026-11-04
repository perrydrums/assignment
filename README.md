# Remindo Exam Analytics

A Symfony application that reads exam results from an Excel workbook and reports, per student, the
score, grade and pass/fail result, and per question the P'-value and rit-value. It has a console
command and a web UI; both run the same analysis code.

## Requirements

- PHP 8.4+ with the `zip`, `xml`, `xmlreader`, `gd` and `mbstring` extensions (needed by PhpSpreadsheet)
- Composer 2

[DDEV](https://ddev.com/) is the local environment. It provides PHP 8.4 and Composer inside a container, so those do not have to be installed on the host. Docker and DDEV are required to use it.

## Installation

```bash
ddev start
ddev composer install
```

## Usage

```bash
ddev exec php bin/console app:analyze-exam path/to/results.xlsx
```

The same commands work with host PHP if you already have it installed (`composer install`, then `php bin/console ...`). Paths are relative to the project root in both cases.

The supplied caesura (20% → 1.0, 70% → 5.5, 100% → 10.0, pass from 70%) is the default. Another
caesura can be passed as input:

```bash
ddev exec php bin/console app:analyze-exam results.xlsx --caesura="0:1.0,55:6.0,100:10.0" --pass-percentage=55
```

Exit codes: `0` success, `1` the workbook could not be imported (the message names the offending
cell), `2` invalid caesura.

### Web UI

After `ddev start`, open <https://assignment.ddev.site>. Upload a workbook (drag and drop or browse),
check or edit the caesura, which has a live preview of the grade curve, and select **Analyse results**.
If `Assignment.xlsx` is in the project root, **Try the sample workbook** analyses it in one click.

The results page shows:

- Summary cards: the pass rate, average grade, average score and average P'-value.
- A grade distribution split into passed and failed. Selecting a bar lists those students.
- An item map that plots P' (difficulty) against rit (discrimination). Selecting a question jumps
  to it in the table.
- Sortable, searchable and filterable tables for students and questions, with CSV export.
- The exact report the console command prints, plus the equivalent CLI command to copy.

Uploaded workbooks are analysed from PHP's temporary upload file and never stored. The UI has no
authentication. It is meant to run locally; add authentication before deploying it anywhere shared,
because the workbooks contain student data.

### Input format

First worksheet of an `.xlsx`/`.xls` file. Any number of questions (columns) and students (rows):

| A                     | B                  | C                  | … |
| --------------------- | ------------------ | ------------------ | - |
| `ID`                  | `Score Question 1` | `Score Question 2` | … |
| `Max question score:` | 1                  | 3                  | … |
| `Student 0001`        | 1                  | 1.2                | … |

Every score must be a number between 0 and the question maximum; blank or text cells are rejected
rather than treated as 0.

## Development

```bash
ddev composer test      # PHPUnit
ddev composer analyse   # PHPStan (level max)
ddev composer cs        # PHP-CS-Fixer (@Symfony), dry run
ddev composer check     # all of the above
```

The supplied `Assignment.xlsx` contains student data and is git-ignored. Place it in the project
root to also run the integration tests against it; otherwise those tests are skipped.

The front end uses Twig, Stimulus and AssetMapper, so there is no Node build step. `composer install`
downloads the JavaScript dependencies (`importmap:install`). Templates and config can be linted with
`ddev exec php bin/console lint:twig templates/` and `lint:container`.

## Structure

```
src/
├── Command/          AnalyzeExamCommand: CLI input, error messages, exit codes
├── Controller/       ExamAnalysisController: upload form and results page
├── Form/             Upload form, its input model and validation
├── Application/      ExamAnalyzer, ExamAnalysis, CaesuraNotation ("20:1.0,70:5.5,100:10.0")
├── Domain/           Pure PHP business logic (no Symfony / PhpSpreadsheet)
│   ├── Exam/         Exam, Question, Student
│   ├── Grade/        Caesura, GradeCalculator
│   └── Analytics/    PValueCalculator, PearsonCorrelation
└── Infrastructure/
    ├── Excel/        ExcelResultImporter (PhpSpreadsheet)
    ├── Console/      ExamResultPresenter (CLI report)
    ├── Web/          ExamAnalysisView(Factory): summary figures and chart geometry
    └── ScoreFormatter.php  shared score formatting (console and the Twig |score filter)
templates/            Twig pages and partials
assets/               Stimulus controllers, CSS and self-hosted fonts
```

See [docs/technical-decisions.md](docs/technical-decisions.md) for the reasoning behind the main
choices, [docs/implementation-plan.md](docs/implementation-plan.md) for the inspected workbook
structure and plan, and [docs/ai-development-log.md](docs/ai-development-log.md) for how AI was used.
