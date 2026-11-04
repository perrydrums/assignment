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

## For the developer to review / own

- The open questions in `technical-decisions.md` (5.5-but-failed, rit vs grade, rir).
- The strict "no missing values" policy.
- The web UI is beyond the original brief (which asked for a CLI); decide whether to present it as
  an extra.
- Time spent (the assignment asks to report it).
