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
  text output. The web UI does not mention the command. The CLI still prints its own report through
  `ExamResultPresenter`.
- **Server-rendered first.** Twig renders the tables and the charts (SVG). Stimulus controllers add
  sorting, filtering, search, the detail drawers and linking between charts and tables. Without
  JavaScript, the summary, charts and tables are still readable. AssetMapper serves the assets, so
  there is no Node build.
- **Presentation logic in PHP, not in templates.** `ExamAnalysisView` computes the summary figures,
  histogram bins (rounded grades, split into passed and failed, with the bin width derived from the
  caesura's grade range) and item-map coordinates. These are unit-tested.
- **Shared, refactored code.**
  - `CaesuraParser` became `Application\CaesuraNotation`, with `parse()`, `format()` and the default
    caesura as the single source of truth for the CLI and both forms.
  - Score formatting moved to `ScoreFormatter`, which serves the console and the Twig `|score`
    filter. With a web request it follows the locale (a Dutch page uses a decimal comma); the CLI
    has no request and keeps a dot.
- **Importer messages no longer contain the file path.** Each caller adds its own context: the CLI
  names the argument, the web UI the uploaded file. This keeps PHP's temporary upload path out of
  messages.
- **Caesura editor.** Points can be entered in any order and are sorted by percentage before the
  domain validates them. The CLI notation stays strict. Domain errors are reused as form errors
  through an `Assert\Callback`. `getMessage()` stays English for the CLI and the logs. The web
  translates `messageKey()` from the `errors` catalogue.
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
- **Security patches.** Symfony was updated to 8.1.11 for advisories published on the day of this
  work; `composer audit` is clean.

## Teacher-focused redesign

The web UI is aimed at teachers reading their own test, not at developers running the command.

- **Language.** Routes are prefixed with `/{_locale}` (`en` or `nl`). `/` redirects using
  `Request::getPreferredLanguage()`. Catalogues are XLIFF. Real plurals live in the ICU files
  (`messages+intl-icu`); strings that the page later fills in with JavaScript stay in the plain
  catalogue so `{total}` is not treated as an ICU placeholder. The language switch on the results
  page submits the settings form, so the analysis is not lost.
- **Nothing is stored.** The results page embeds the original exam as JSON in a hidden field
  (`ExamDataCodec`, at most 5,000 students and 500 questions). Recalculation, the Excel download and
  the language switch all post that form. Refreshing the results page asks the browser to resubmit;
  that follows from never storing the data.
- **What-if.** `QuestionAdjustment` is keep, remove or full marks. `Exam::adjusted()` drops a removed
  question from the maximum and from every student, or gives everyone that question's maximum.
  `AnalysisRunner` analyses the adjusted exam and, when something changed, the original exam too, and
  records which students changed from fail to pass or the other way.
- **Reliability.** `CronbachAlpha` uses population variances; the divisor cancels, so it matches the
  usual sample definition. It is null when there are fewer than two questions, fewer than two
  students, or no variation in the total. Alpha-if-removed is computed the same way. On
  `Assignment.xlsx` the value is 0.71659266620098, matching an independent Python calculation to
  about 1e-15. Bands of 0.80 (good) and 0.70 (acceptable) are labels, not business rules.
- **Review flags.** `QualityGuidelines` defaults are P' below 0.30, P' above 0.90 and rit below 0.20.
  A negative rit and a question with no variation are always flagged. The boundaries can be changed
  on the page and are described as indicative.
- **Excel.** `ExcelResultExporter` writes Summary, Grades and Questions sheets with translated
  headers and numeric cells, so Dutch Excel does not misread decimals. Sheet names and headers follow
  the locale of the page.
- **Print.** **Print summary** prints the header (file name, date, grading scale), the summary
  cards, the notes and the charts. The tables are left out; the Excel download has the full lists.
  Each card avoids a page break inside it. Animated values are pinned to their end state with
  `!important` rather than by removing the animations, because changing `animation` for print would
  replay them when the print dialog closes.

## Brand themes

The theme switcher recolours the UI for certify360.com (Default) or one of its eight brands.

- **Colour tokens.** Every brand-dependent colour in the stylesheets is a custom property with a role
  name (`--dark`, `--accent`, `--accent-button`, `--surface`, …). Alpha and lighter variants are
  `color-mix()` expressions of those tokens. The old palette names (`--lavender`, `--navy`, `--mist`,
  `--cream`) were renamed, because a FOI theme setting `--lavender` to sky blue would be misleading.
  Status colours (red, amber, green) and the heading grey stay fixed, so "failed" and "fine" mean the
  same in every theme.
- **Default is the previous style.** The Default values are the old ones. A few very light borders
  and the table header tint now come from `color-mix()` of a token and differ by one or two RGB steps.
- **One source of truth.** The colours live only in `assets/styles/themes.css`, under
  `[data-theme='…']`. The attribute on `<html>` sets the page theme. On a switcher swatch, the same
  attribute scopes that theme to the swatch, so the menu previews nine palettes without repeating a
  hex value in Twig, PHP or JavaScript. `App\Infrastructure\Web\Theme` only lists the themes, brand
  names and logos.
- **No flash, no reload.** The choice is kept in a `theme` cookie that `ThemeResolver` reads on every
  request, so the server renders the chosen theme from the first byte. Unknown values fall back to
  Default. Switching is client-side (Stimulus), so the analysis on the results page, which is not
  stored anywhere, survives a theme change.
- **Palettes from the brands' own sites.** Each brand site was analysed in parallel (CSS custom
  properties, computed button and header colours, screenshots). The palettes were then checked against
  WCAG contrast: white on the header at least 7:1, accent text at least 4.5:1, chart fills at least 3:1.
  Brand colours that failed were darkened at the same hue (FOI, Learncare, Examenadviesburo). WWZ
  Academie and COEL have no dark colour for a white-text header, so theirs is derived from the brand
  orange and sky blue. Lindenhaeghe keeps its exact orange button colour, as on its site. White text on
  it is 3.5:1, the one pair below 4.5:1.
- **Logos.** The logos are the brands' own files for dark backgrounds (white or inverted), stored under
  `assets/images/brands/`. COEL only publishes a black logo, so its lettering was recoloured white with
  the mark kept. Examenadviesburo's SVG was cropped to its artwork. The SVGs were checked for scripts
  and external references. They are shown with `<img>`, so any script inside would not run anyway.

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
