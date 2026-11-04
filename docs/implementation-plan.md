# Implementation plan

## Verified input format (`Assignment.xlsx`)

Inspected with PhpSpreadsheet and independently with Python/openpyxl:

| Item | Observation |
| --- | --- |
| Worksheets | 1 (`Sheet0`), range `A1:AX454` |
| Row 1 | `ID`, then `Score Question 1` … `Score Question 49` |
| Row 2 | `Max question score:`, then the maximum per question |
| Rows 3–454 | 452 students: string ID (`Student 0001`…), then numeric scores |
| Max scores | 30×1, 7×2, 7×3, 5×5 → total 90 |
| Cell types | 22 071 integers, 77 floats, no blanks, no formulas, no text in score cells |
| Decimal scores | All 77 on question 39 (max 3): 1.2, 2.1, 0.6, 0.9, 1.5, 1.8, 0.3, 2.4 |
| Range | No negative scores, no scores above the question maximum; IDs unique |
| Boundary | 14 students score exactly 63/90 = 70% — the pass boundary is hit by real data |

None of these numbers are hardcoded; they are derived at runtime.

## Architecture

```
src/
├── Command/AnalyzeExamCommand.php          CLI: arguments, options, error handling, exit codes
├── Application/
│   ├── ExamAnalyzer.php                    Orchestrates the calculations for an Exam
│   ├── ExamAnalysis.php                    Result + summary (counts, average grade)
│   ├── StudentResult.php / QuestionResult.php
├── Domain/                                 Pure PHP, no Symfony/PhpSpreadsheet
│   ├── Exam/  Exam, Question, Student, InvalidExamException
│   ├── Grade/ Caesura, CaesuraPoint, GradeCalculator, GradeResult, InvalidCaesuraException
│   └── Analytics/ PValueCalculator, PearsonCorrelation
└── Infrastructure/
    ├── Excel/ExcelResultImporter.php       Workbook → Exam, with cell-level validation messages
    └── Console/ExamResultPresenter.php, CaesuraParser.php
```

The command wires importer → analyzer → presenter. The analyzer takes an `Exam` (not a
file path), so the application layer does not depend on PhpSpreadsheet.

## Key decisions

- **Caesura is input.** `Caesura` = ordered (percentage → grade) points + explicit pass
  percentage. CLI options `--caesura=20:1.0,70:5.5,100:10.0` and `--pass-percentage=70`
  default to the supplied caesura. Piecewise linear interpolation, clamped outside the
  first/last point.
- **Pass/fail from the unrounded percentage**, never from the rounded grade. Comparison
  uses a tiny tolerance (1e-9) because e.g. `0.7 * 90 = 62.99999999999999` in IEEE floats.
- **Grades** are kept unrounded internally; the official grade is rounded to one decimal
  (half up) for display only.
- **rit** = Pearson(question score, total score *including* that question), per the PDF.
  Undefined (`null`) for < 2 observations or zero variance (detected exactly via min = max,
  not via a float tolerance).
- **Strict import.** No missing-value policy: blank, text, or out-of-range score cells are
  rejected with the cell coordinate. Nothing is skipped or converted to 0.

## Ambiguities (documented, not silently decided)

1. PDF mentions both "result on the test" and "grade" for rit. Using the total score
   (standard rit definition; the grade is a non-linear transform of it).
2. Grades in [~69.44%, 70%) round to 5.5 but fail. Following the brief: pass/fail from
   the unrounded score. Flag to the product owner.
3. Rounding mode for grades is unspecified: using half-up.
4. Average grade: average of unrounded grades (no rounding of intermediates).

## Order

1. Project setup (done: Symfony 8.1 skeleton, PhpSpreadsheet, PHPUnit, PHPStan, CS-Fixer).
2. Domain exam model + importer + importer tests (generated workbooks).
3. Caesura + grade calculator + tests.
4. P'-value + tests. 5. Pearson/rit + tests.
6. Analyzer, presenter, command + command test; verify against the supplied workbook
   and an independent Python calculation.
7. README, technical decisions, AI log; run PHPUnit, PHPStan, CS-Fixer.

## Later additions

A web UI was added after this plan was carried out. `CaesuraParser` became
`Application/CaesuraNotation`, shared by the CLI and the web UI. See the "Web UI" section of
[technical-decisions.md](technical-decisions.md).
