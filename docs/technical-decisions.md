# Technical decisions

## Architecture

- **Domain without framework dependencies.** `Domain/` (exam model, caesura, grade, P', Pearson)
  depends on nothing but PHP. Calculators take plain `list<float>` input, so they are reusable
  and testable with tiny hand-checkable datasets.
- **Analyzer takes an `Exam`, not a file.** The command composes importer → analyzer → presenter.
  The application layer therefore has no PhpSpreadsheet dependency, and another source (CSV,
  Remindo API) only needs a new importer.
- **Symfony skeleton kept stock.** Services are autowired; the command is an invokable
  `#[AsCommand]` with `#[Argument]`/`#[Option]` attributes. No custom configuration files.
- **No interfaces** for the calculators or importer: there is one implementation of each and no
  concrete need to swap them. Easy to extract later.
- **No statistics library**: Pearson is ~20 lines and fully tested.

## Business rules

| Topic | Decision |
| --- | --- |
| Caesura | Value object: ordered `percentage → grade` points + explicit pass percentage. Linear interpolation; clamped to the first/last grade outside the range. Validated: ≥ 2 points, percentages 0–100 and strictly increasing, grades non-decreasing, pass percentage in (0, 100]. |
| Caesura as input | `--caesura` / `--pass-percentage` CLI options. The supplied caesura is only the *default value* of those options, not part of the algorithm. |
| Pass/fail | `percentage ≥ passPercentage` on the **unrounded** score percentage, with a 1e-9 tolerance because float sums can turn exactly 70% into 69.99999999999999% (14 students in the supplied data score exactly 70%). |
| Grade rounding | Kept unrounded internally; the official grade is rounded half-up to one decimal. Float noise is removed first (`round(round($g, 9), 1)`) — otherwise a grade of exactly 5.45 is computed as 5.4499999… and rounds to 5.4. |
| Average grade | Mean of the unrounded grades. |
| P'-value | `mean(scores) / question max`, over all students. Max score ≤ 0 is rejected. |
| rit-value | Pearson(question score, total score **including** that question), as the assignment defines it. `null` (shown as `n/a`) for < 2 students or zero variance. Zero variance is detected exactly (all values identical), not via a float threshold. Result is clamped to [-1, 1] against rounding. |

## Input validation

Strict, with the offending cell in every message (e.g. `Cell K100: … got text "abc"`):

- Only Excel formats (Xlsx, Xls); otherwise PhpSpreadsheet would accept any text file as CSV.
- First worksheet only. `A1` must be `ID`; `A2` must be the max-score label (contains "max") —
  this guards against a file without the max-score row, whose first student would otherwise be
  read as maxima.
- Every column that holds data needs a header; every max score must be a number > 0.
- Student IDs required and unique (text or integer).
- Scores must be numbers within `[0, max]`. **No missing-value policy**: blank, text (e.g. `"2,1"`)
  and boolean cells are errors, because blank could mean 0 or "not answered" and the program
  cannot decide that. Formulas are evaluated.
- Blank rows between students are errors (not skipped).

The domain objects (`Exam`, `Question`, `Caesura`) enforce the same invariants independently, so
they cannot be constructed in an invalid state by other callers. That is a small, deliberate
duplication: the importer adds location context, the domain guarantees correctness.

## Web UI

The web UI was added after the CLI. The original brief asked for a console application only.

- **Same use case, different delivery.** The controller calls the same `ExcelResultImporter`,
  `ExamAnalyzer` and caesura rules as the command; it does not shell out to `bin/console` or parse
  text output. The "Console" tab shows the command's report, produced by the same
  `ExamResultPresenter`.
- **Server-rendered first.** Twig renders the tables and the charts (SVG). Stimulus controllers add
  sorting, filtering, search, CSV export, tooltips and linking between charts and tables. Without
  JavaScript, everything is still readable. AssetMapper serves the assets, so there is no Node build.
- **Presentation logic in PHP, not in templates.** `ExamAnalysisView` computes the summary figures,
  histogram bins (rounded grades, split into passed and failed, with the bin width derived from the
  caesura's grade range) and item-map coordinates. These are unit-tested.
- **Shared, refactored code.**
  - `CaesuraParser` became `Application\CaesuraNotation`, with `parse()`, `format()` and the default
    caesura as the single source of truth. The web UI uses it to show the equivalent CLI command.
  - Score formatting moved to `ScoreFormatter`, which serves the console and the Twig `|score` filter.
- **Importer messages no longer contain the file path.** Each caller adds its own context: the CLI
  names the argument, the web UI the uploaded file. This keeps PHP's temporary upload path out of
  messages.
- **Caesura editor.** Points can be entered in any order and are sorted by percentage before the
  domain validates them. The CLI notation stays strict. Domain errors are reused as form errors
  through an `Assert\Callback`, so the CLI and web report the same messages.
- **Uploads.** Only `.xlsx` and `.xls` are accepted (extension plus MIME check) up to 10 MB; the
  importer's format check remains the decisive one. Files are analysed straight from the temporary
  upload and never moved or stored. The POST response renders the results directly. Post/redirect/get
  would need the analysis to be stored somewhere.
- **CSRF.** Symfony's stateless, same-origin CSRF protection from the Flex recipe applies. A
  functional test covers a cross-site submission.
- **No authentication.** It is a local tool. Before sharing a deployment, put it behind
  authentication, because the workbooks contain student data.
- **Design.** The palette, typography (Plus Jakarta Sans and DM Sans), pill buttons and large radii
  follow certify360.com. The fonts are self-hosted (SIL OFL), so visitors' IP addresses are not sent
  to Google. Animations respect `prefers-reduced-motion`. Charts and tables are keyboard accessible.
- **CSV export.** The export is built client-side from the visible rows. Text cells that start with
  `= + - @` are prefixed to prevent spreadsheet formula injection.
- **Security patches.** Symfony was updated to 8.1.11 for advisories published on the day of this
  work; `composer audit` is clean.

## Open questions for the product owner

1. **Displayed 5.5 but failed.** Scores in [69.44%, 70%) yield a grade that rounds to 5.5 while
   the student fails (pass is decided on the score, as the brief requires). In the Dutch system
   5.5 normally reads as a pass; a common fix is to cap failing grades at 5.4. Not implemented
   because it is not in the specification. Does not occur in the supplied data.
2. **rit against total score or grade?** The PDF mentions both "the result on the test" and "the
   grade". The total score is used (standard definition); the grade is a non-linear (piecewise,
   clamped) transformation and would give slightly different correlations.
3. **Corrected item-total correlation (rir)**, which excludes the question from the total, is
   psychometrically more informative — especially with few questions — but not what was asked.
