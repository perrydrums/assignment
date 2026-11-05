# Remindo Exam Analytics

A Symfony application that reads exam results from an Excel workbook and reports, per student, the
score, grade and pass/fail result, and per question the P'-value and rit-value. It has a console
command and a web UI; both run the same analysis code.

## Requirements

- PHP 8.4+ with the `zip`, `xml`, `xmlreader`, `gd`, `mbstring` and `intl` extensions (`intl` is used for translations and number formatting; PhpSpreadsheet needs the others)
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

After `ddev start`, open <https://assignment.ddev.site>. The site is in English or Dutch
(`/en` and `/nl`); `/` follows the browser language. Upload a RemindoTest Excel export, check the
grading scale (the standard scale is 70% = 5.5) and select **Analyse results**. If `Assignment.xlsx`
is in the project root, **View an example analysis** runs it in one click.

The results page is written for teachers:

- Pass rate (and how it changed), average grade, reliability (Cronbach's alpha) and how many
  questions to review.
- Short notes on what stands out, each linking to the students or the question.
- A grade distribution. Selecting a bar lists those students.
- A question map with shaded guideline zones. Selecting a question opens its detail.
- Sortable, searchable tables. A student row shows the score on each question; a question row shows
  the score distribution, the strong and weak groups, and reliability if the question is left out.
- **Adjust analysis** changes the grading scale, leaves a question out, or gives everyone full marks,
  and recalculates without uploading again.
- **Download Excel** (summary, grades and questions) and **Print summary** (the figures, notes and
  charts, without the student and question tables).

A theme switcher next to the language switch recolours the UI in the style of certify360.com
(**Default**) or of one of the eight Certify360 brands (Lindenhaeghe, CS Opleidingen, Nederlands
Compliance Instituut, WWZ Academie, FOI, Learncare, Examenadviesburo, COEL toetscentra). The header
then shows that brand's logo next to the product name. Themes change colours and the logo only. The
choice is kept in a cookie.

Uploaded workbooks are analysed from PHP's temporary upload file and never stored. Adjusting the
analysis sends the same data back with the page; nothing is written to disk or a database. The UI
has no authentication. It is meant to run locally; add authentication before deploying it anywhere
shared, because the workbooks contain student data.

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
├── Controller/       Upload, results, Excel export, help, and the language redirect
├── Form/             Upload form, analysis settings and the embedded-exam transformer
├── Application/      ExamAnalyzer, AnalysisRunner, AnalysisSettings, CaesuraNotation
├── Domain/           Pure PHP business logic (no Symfony / PhpSpreadsheet)
│   ├── Exam/         Exam, Question, Student, QuestionAdjustment
│   ├── Grade/        Caesura, GradeCalculator
│   └── Analytics/    P', rit, Cronbach's alpha, quality guidelines
└── Infrastructure/
    ├── Excel/        ExcelResultImporter and ExcelResultExporter (PhpSpreadsheet)
    ├── Console/      ExamResultPresenter (CLI report)
    ├── Web/          ExamAnalysisView, ExamDataCodec, translated errors, Theme and ThemeResolver
    └── ScoreFormatter.php  locale-aware score formatting (console and the Twig |score filter)
templates/            Twig pages and partials
translations/         English and Dutch catalogues
assets/               Stimulus controllers, CSS (themes.css holds the colour themes), fonts, brand logos
```

See [docs/technical-decisions.md](docs/technical-decisions.md) for the reasoning behind the main
choices, [docs/implementation-plan.md](docs/implementation-plan.md) for the inspected workbook
structure and plan, and [docs/ai-development-log.md](docs/ai-development-log.md) for how AI was used.
