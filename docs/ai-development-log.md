# AI development log

Tooling: Cursor agent (Claude). The developer wrote `AI_DEVELOPMENT_BRIEF.md` and a task prompt
fixing the scope, priorities, stack (Symfony Console, PhpSpreadsheet, PHPUnit, PHPStan) and key
rules (rit including the item, `null` for undefined correlations, pass/fail independent of
rounding, no hardcoded workbook facts, strict validation).

## What the AI did

- Inspected the PDF (text extraction via `pypdf` in a throwaway venv) and the workbook (PhpSpreadsheet
  and openpyxl), and confirmed the observed structure in the brief instead of assuming it.
- Created the project (`symfony/skeleton` 8.1, PhpSpreadsheet 5.10, PHPUnit 13.4, PHPStan 2.3,
  PHP-CS-Fixer 3.95) and generated all source code, tests and documentation in this repository.
- Proposed the decisions in `technical-decisions.md`; they are open for the developer to review.

## Incorrect assumptions and suggestions caught

| What | How it was caught | Resolution |
| --- | --- | --- |
| A test assumed `round(5.45, 1)` = 5.5 for a grade computed by interpolation. The computed value was 5.4499999999999993, which rounds to 5.4. | Unit test failed. | Float noise removed before rounding (`round(round($g, 9), 1)`); test kept as a regression test. |
| The AI wrote an expected Pearson value (0.8462) into a test, labelled "verified with Python", *before* verifying it. The correct value is 0.8607. | Running Python's `statistics.correlation` and the PHP test; confirmed by hand (Σdxdy = 16, Σdx² = 1.2, Σdy² = 288). | Test now asserts the hand-derived formula `16 / √345.6`. Lesson: never write "verified" before verifying. |
| A naive pass check `score >= 0.7 * max` would compare against 62.99999999999999 for max 90, and float sums of decimal scores can land just below 70%. Relevant: 14 students score exactly 70%. | Probing PHP float behaviour during the workbook inspection. | Pass comparison on the percentage with a 1e-9 tolerance; tested with float sums. |
| PhpSpreadsheet's `IOFactory` would accept any text file (as CSV). | Reading the `IOFactory` source. | Readers restricted to Xlsx/Xls; tested with a text file named `.xlsx`. |
| A closure-based test data provider was too clever: PHPStan (level max) could not type it and it was hard to read. | PHPStan. | Replaced by plain row data plus a `validRowsWith()` helper. |
| `min()`/`max()` for zero-variance detection was not provably safe for empty input. | PHPStan. | Replaced by an explicit `isConstant()` check. |

## Verification

- **Independent recomputation** of the full supplied workbook in Python (openpyxl, exact
  `Fraction` arithmetic for grades, `statistics.correlation` for rit), compared with the PHP output:
  all 452 rounded grades and pass/fail results identical (68 pass); max deviation of unrounded
  grades 2.7e-15, of P' 0, of rit 2.2e-15; the same question (Q24, everyone scored 1) has an
  undefined rit in both.
- **Unit tests** with small, hand-derivable datasets for grade, caesura, P' and Pearson; importer
  tests on generated workbooks for every validation rule; command tests through the Symfony kernel.
- **Manual CLI runs**: supplied workbook, custom caesura, invalid caesura, missing file, and copies
  of the workbook corrupted with a text score and a duplicate ID (cell coordinates reported).
- PHPUnit, PHPStan (level max) and PHP-CS-Fixer run clean (see README for commands).

## Web UI (follow-up request)

The developer asked for a visual web UI that runs the analysis, with interactive tables and a
design in the style of certify360.com. The AI did the following:

- Measured the site's design tokens in a browser (computed styles: fonts, colours, radii, buttons),
  then built the UI: a Symfony controller and form, Twig templates, Stimulus controllers and CSS.
  The UI reuses the existing services.
- Added unit tests for the view model and functional tests for the controller (`WebTestCase`):
  upload, custom caesura, validation errors, cross-site submission and the sample workbook.
- Verified the UI in a browser at desktop and phone widths, clicking through sorting, filtering,
  histogram selection, item-map selection and tabs. It also POSTed real uploads to the DDEV site
  with curl (valid, corrupted and invalid caesura).

| What | How it was caught | Resolution |
| --- | --- | --- |
| Validation errors for the caesura and CSRF were attached to the form but **never shown**. Compound fields bubble errors to the root form, which the template did not render. | End-to-end POSTs with curl returned 422 without a visible message. | `error_bubbling: false` on the collection, plus a root-errors slot for CSRF and upload-size errors. Functional tests now assert the messages. |
| The caesura preview sorted points, but the server required them in order, so the preview and the server could disagree. | Review of the editor's behaviour. | The form model sorts points before the domain validates them. |
| Number counters animating from 0 were read as "0.0" by screen readers. | Browser accessibility tree during the animation. | The animated digits are `aria-hidden`; the final value is in a visually hidden span. |
| The tab bar overflowed on a 390px viewport (page width 450px). | Measuring element bounds in the browser. | The tab bar scrolls horizontally on small screens. |
| Test assumptions: an expected average was miscalculated (5.0 instead of 5.475); a selector matched an empty client-side error placeholder; the "cross-site" test sent a same-origin `Referer`, which Symfony accepts. | Failing tests, then reading `SameOriginCsrfTokenManager`. | Expectations fixed; the cross-site test now sends what a browser sends (`Sec-Fetch-Site: cross-site`). |
| A shell loop variable named `path` wiped `PATH` in zsh during verification. | Commands suddenly not found. | Renamed; no impact on the code. |
| `composer audit` reported 10 advisories, published that day, for Symfony < 8.1.11. | Composer output while installing packages. | Updated to 8.1.11; audit clean. |

## Teacher-focused redesign

The developer asked to aim the web UI at teachers, in English and Dutch, and to drop developer-facing
content. Chosen features: question-quality flags, an Excel download, a printable report, detail
panels, Cronbach's alpha, and what-if recalculation. Borderline-student reporting was not selected.
Nothing is stored on the server.

The AI added the domain and application types (`CronbachAlpha`, `QualityGuidelines`,
`QuestionAdjustment`, `AnalysisRunner`), the settings form with the exam embedded as JSON, the
results page, the help page, and the Excel exporter. It removed the console tab, the CLI command
line and the CSV export from the web UI. The CLI itself is unchanged.

| What | How it was caught | Resolution |
| --- | --- | --- |
| XLIFF units used `id` as the key. Symfony's loader uses `resname`, otherwise the source text, so every translation was missing. | `debug:translation` and a page that showed raw keys such as `results.of_passed`. | `resname` set to the message id. |
| Parameter values turned into `%B3%`. The non-ICU formatter replaces substrings, so a parameter named `cell` rewrote the inside of `%cell%`. | The importer functional test. | The web translator passes `%cell%` as the parameter name. |
| A boolean score was reported as `got 1` because `(string) true` is `"1"`. | The existing importer test expecting `got bool`. | The type branch uses `get_debug_type()`. |
| ICU messages printed the pattern when loaded as ordinary messages, and `{total}` in the table counter would be eaten if it lived in the ICU file. | A manual translator probe, and the plan's note about the counter. | Plurals and `{name}` placeholders are in `messages+intl-icu`; the counter patterns stay in the plain catalogue. |

Verification for this change: 129 PHPUnit tests, PHPStan level max, PHP-CS-Fixer, `lint:twig`,
`lint:xliff`, `lint:container`, `debug:translation` with no missing keys for `en` or `nl`, and
`node --check` on the Stimulus controllers. Cronbach's alpha on `Assignment.xlsx` is
0.71659266620098 in PHP and 0.7165926662009835 in an independent Python calculation (49 questions,
452 students). The example analysis was also opened in a browser in English and Dutch, at a desktop
width and at 390px, including the student and question drawers and a print-media emulation.

## Print summary and drawer polish

The developer asked for a "Print summary" without the table options, no page breaks inside
components, styled dropdowns, a collapsed question list below the guidelines, a visible submit
button in the drawer, and detail drawers that match the settings drawer.

| What | How it was caught | Resolution |
| --- | --- | --- |
| The drawer's submit button was cut off. The dialog kept the browser's `overflow: auto` and was 42px taller than the viewport, so it scrolled as well as its body. | Measuring the open dialog: height 900, scroll height 942. | The open drawer is a full-height flex column; only the body scrolls. |
| The detail drawers were short boxes in the top corner, and their table headers ran together because the shared header style relies on sort buttons for padding. | Screenshots at desktop width. | All drawers share the head, sections and foot; drawer tables have their own header padding. |
| Sticky table headers in a drawer stopped 24px below the top, because sticky positioning stops at the scroller's padding edge. | Measuring the header against the body after scrolling. | The header is offset by the body's top padding. |
| A real PDF of the old print split "What stands out" and the question map across pages, and printed the file name mid-animation (invisible). | Printing the example with headless Chrome and rendering the pages. | `break-inside: avoid` on each card, the tables left out, animated values pinned in print. |
| Resetting `animation` in print would replay every entrance animation after printing. | Toggling print media in the browser: 10 animations restarted with `animation: none`, none with pinned values. | Final values are pinned with `!important`, which outranks animations without restarting them. |
| On A4 the charts panel left an empty box at the bottom of page 1 when its first chart moved to page 2. | The rendered A4 PDF. | The charts panel has no background or padding in print. |

Verification: 131 PHPUnit tests, PHPStan level max, PHP-CS-Fixer, `lint:twig`, `lint:xliff` and
`node --check`. PDFs of the example in English and Dutch, on A4 and US Letter, with and without an
adjustment, each print on two pages with no card split.

## Brand themes

The developer asked for a theme switcher with a theme for each of the eight brands under "Our
Brands" on certify360.com, plus the current style as Default. Each theme uses its brand's colours and
logo, with no other changes.

The AI first collected each site's computed colours and logo files in a browser. At the developer's
request, it then ran one subagent per brand in parallel. Each subagent received the same token
contract with WCAG targets and returned a complete palette with contrast ratios and a checked logo.
In parallel, the AI renamed the colour tokens to role names, replaced the remaining hard-coded colours
with tokens, and built the switcher, the cookie-based server rendering and the tests.

| What | How it was caught | Resolution |
| --- | --- | --- |
| WWZ Academie and COEL have no colour dark enough for a header with white text, and WWZ's yellow button fails contrast with white (1.9:1). | The subagents' contrast checks. | A deep rust (WWZ) and a deep sky blue (COEL) derived from the brand colours; WWZ buttons use its deeper orange. |
| Several brand accents fail as fills or text on white (FOI blue 2.5:1, Learncare orange 2.3:1, Examenadviesburo green 2.2:1). | Contrast checks against the token targets. | Darkened at the same hue; the original colour is kept where it passes, for example on the dark header. |
| COEL only publishes a black logo, invisible on a dark header. | Rendering every logo on the header colour. | Recoloured the black lettering to white and kept the sky-blue mark, with no dark halos. |
| The embedded browser stopped returning screenshots for the last checks. | Repeated screenshot timeouts. | Screenshots taken with headless Chrome over the DevTools protocol, with the theme cookie set. |

Verification: 132 PHPUnit tests (one renders the theme from the cookie and rejects an unknown value),
PHPStan level max, PHP-CS-Fixer, the Twig, XLIFF and container linters, `node --check`, and `composer
audit`. Every theme was viewed on the results page at desktop width, and Default and NCI at 390px.
Default's computed colours were compared with the previous values (header, accents, chips, shadows).

## For the developer to review / own

- The open questions in `technical-decisions.md` (5.5-but-failed, rit vs grade, rir).
- The strict "no missing values" policy.
- The web UI is beyond the original brief (which asked for a CLI); decide whether to present it as
  an extra.
- The brand logos and colours belong to the Certify360 brands; confirm they may be used before
  sharing the app outside the application process.
- Time spent (the assignment asks to report it).
