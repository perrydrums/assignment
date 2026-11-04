# AI Development Brief --- Remindo Exam Analytics

## Purpose

Build a small, maintainable PHP application that analyses exam results
from the supplied Excel workbook.

This is a time-boxed job-application assignment. The goal is not to
build a large product or demonstrate every possible abstraction. The
goal is to show sound engineering judgement, clear decisions, correct
calculations, useful tests, and responsible agentic development.

**Target effort: 2--4 hours.** Keep the implementation deliberately
small. Prefer a complete, understandable vertical slice over speculative
features.

## Source material

The repository should contain or have access to:

-   `Assignment.pdf` --- the assignment brief. Treat this as the source
    of truth for business requirements.
-   `Assignment.xlsx` --- the supplied exam data. Treat this as sample
    input, not as a file to hardcode into the application.

Do not commit real student data to a public repository. Keep the
workbook outside version control or add it to `.gitignore`.

## What the assignment requires

The application is a Symfony Console application, not a web application.

It must be able to:

1.  Read an Excel workbook containing student scores.
2.  Calculate a grade and pass/fail result for every student.
3.  Calculate the P'-value for every question.
4.  Calculate the rit-value for every question.
5.  Work with another workbook in the same format, with a different
    number of students and questions.
6.  Keep the grade calculation reusable by accepting the grade/caesura
    as input.

The assignment explicitly values maintainability, readable output,
problem-solving, and the developer's approach. It does not require an
elaborate UI or production infrastructure.

## Observed workbook structure

The supplied workbook currently has:

-   One worksheet named `Sheet0`.
-   454 rows and 50 columns.
-   Row 1: `ID` followed by question-score column headers.
-   Row 2: maximum possible score for each question. The first cell
    contains `Max question score:`.
-   Rows 3 onward: one student per row, with an ID followed by their
    question scores.
-   452 student rows and 49 question columns.

The supplied data includes different maximum scores and decimal/partial
scores. Do not assume that every question is worth one point or that
scores are integers.

For this particular workbook, the maximum question scores are
distributed as follows:

-   30 questions worth 1 point.
-   7 questions worth 2 points.
-   7 questions worth 3 points.
-   5 questions worth 5 points.
-   Total maximum exam score: 90 points.

These are observations about the supplied sample, **not constants to
hardcode**. Derive the number of questions, students, each question's
maximum score, and the total maximum exam score from the workbook.

## Business rules and calculations

### Grade and caesura

The supplied caesura has these reference points:

    Percentage of maximum score   Grade
  ----------------------------- -------
                    20% or less     1.0
                            70%     5.5
                           100%    10.0

-   Grades range from 1.0 to 10.0.
-   Display grades with one decimal.
-   A student passes when their score is at least 70% of the maximum
    possible exam score.
-   Use piecewise linear interpolation between the reference points.
-   Clamp grades at 1.0 below or at 20%, and at 10.0 at or above 100%.
-   Determine pass/fail from the unrounded score ratio, not from the
    rounded grade.
-   Avoid hardcoding the caesura inside the calculation algorithm.
    Represent it as input/configuration so the calculator can be reused
    with another caesura.
-   Validate invalid configurations, such as duplicate or non-increasing
    percentage points, and a maximum score of zero.

The pass threshold is currently 70%. Keep its relationship to the
caesura explicit and documented. Do not infer pass/fail solely from a
rounded grade.

### P'-value

For question `i`:

`P'_i = average score achieved on question i / maximum possible score for question i`

-   Calculate using all valid student results.
-   Use the maximum score for that specific question.
-   The result should be between 0 and 1 for valid input.
-   Do not round intermediate values. Round only for display or export.
-   A question with a maximum score of zero is invalid or undefined;
    handle it explicitly rather than dividing by zero.

### rit-value

The assignment defines rit as the Pearson correlation coefficient
between:

-   `x`: each student's score on a particular question.
-   `y`: each student's total exam score.

The Pearson coefficient ranges from -1 to +1.

-   Follow the assignment's stated definition for the primary
    implementation: correlate the question score with the student's
    total exam score, including that question.
-   Explicitly document this choice. A corrected item-total correlation,
    where the question is excluded from the total, is a possible
    alternative but is not the primary interpretation of the supplied
    brief.
-   Implement the calculation in a focused, independently testable
    class. A small, transparent implementation is appropriate; do not
    add a statistics dependency solely for this formula.
-   Handle insufficient observations and zero variance in either
    variable. In these cases the correlation is undefined; represent it
    explicitly (for example, `null`), not as `0`.
-   Do not round intermediate values.

## Input handling and validation

Build the importer around the observed file format, but avoid hardcoding
the current number of rows, columns, students, or questions.

At minimum, validate:

-   The workbook can be opened and contains a worksheet.
-   The header has an ID column and question columns.
-   The maximum-score row has a valid numeric maximum for every
    question.
-   Student IDs are present and unique.
-   Student score cells are numeric and not silently converted to zero.
-   Scores are not negative and do not exceed the corresponding question
    maximum.
-   There is at least one student and one question.
-   The total maximum score is greater than zero.

The supplied workbook appears clean, but the program should fail clearly
and helpfully on malformed input. Do not silently skip invalid rows or
cells. If you choose a missing-value policy, document it and test it.

Preserve decimal scores. Do not cast scores to integers.

## Suggested implementation shape

Use a modest separation of responsibilities. Adapt this if a simpler
structure is clearly better; do not create classes merely to match this
example.

``` text
src/
├── Command/
│   └── AnalyzeExamCommand.php
├── Application/
│   └── ExamAnalyzer.php
├── Domain/
│   ├── Exam/
│   │   ├── Exam.php
│   │   ├── Question.php
│   │   └── Student.php
│   ├── Grade/
│   │   ├── Caesura.php
│   │   └── GradeCalculator.php
│   └── Analytics/
│       ├── PValueCalculator.php
│       └── RitValueCalculator.php
└── Infrastructure/
    ├── Excel/
    │   └── ExcelResultImporter.php
    └── Console/
        └── ExamResultPresenter.php
```

Responsibilities:

-   **Command:** parse CLI arguments/options, invoke the application
    service, display errors and set an appropriate exit code.
-   **Importer:** translate spreadsheet rows into application/domain
    data and validate the file structure.
-   **Domain/calculators:** contain business rules and calculations; do
    not depend on Symfony or PhpSpreadsheet.
-   **Application service:** orchestrate importing, calculations and
    result assembly.
-   **Presenter:** format results for humans. Keep formatting out of
    calculation classes.

Do not overengineer this. A few well-named classes are better than a
large architecture.

## Suggested technology

First inspect the installed PHP and Composer versions. Use compatible
stable dependencies.

Likely choices:

-   Symfony Console for the CLI.
-   PhpSpreadsheet for reading Excel.
-   PHPUnit for automated tests.
-   PHPStan for static analysis.
-   PHP-CS-Fixer only if setup is quick and useful.

Do not build a web application, database, authentication, queue, API, or
deployment pipeline.

## CLI expectations

Provide a simple command, for example:

``` bash
php bin/console app:analyze-exam path/to/Assignment.xlsx
```

The output should be human-readable and include:

-   Number of students and questions.
-   Total maximum exam score.
-   Student ID, raw score, grade and pass/fail status.
-   Overall pass/fail counts and average grade, if straightforward.
-   Per-question maximum score, P'-value and rit-value.
-   A clear representation for undefined rit-values.
-   A helpful error message for invalid input.

A JSON export option is optional. Only implement it if the core
requirements, tests and documentation are already in good shape.

## Testing expectations

Prioritise focused unit tests for the business logic:

### Grade calculator

-   Every caesura reference point.
-   Values between reference points.
-   Values below the lower bound and above the upper bound.
-   Custom caesura.
-   Zero maximum score / invalid configuration.
-   Pass/fail boundary at exactly 70%, including a case where displayed
    rounding could otherwise mislead.

### P'-value calculator

-   Normal calculation.
-   Different maximum scores per question.
-   Decimal/partial scores.
-   Zero maximum score.

### rit calculator

-   Perfect positive correlation (`+1`).
-   Perfect negative correlation (`-1`).
-   No correlation (approximately `0`).
-   Zero variance in either input.
-   Insufficient observations.
-   Decimal values.

### Importer / integration

-   Correctly reads the supplied workbook.
-   Derives question and student counts dynamically.
-   Preserves decimal scores.
-   Rejects malformed headers, duplicate IDs, missing/non-numeric values
    and out-of-range scores.

Use small, known datasets for formula tests. Do not rely only on
checking the supplied workbook's output.

## Quality and documentation

Provide:

-   A concise README with requirements, installation, test and usage
    commands.
-   Meaningful automated tests.
-   Clear exceptions and useful CLI error messages.
-   Strict types and readable naming.
-   A short `docs/technical-decisions.md` explaining the most important
    decisions and assumptions.
-   A short `docs/ai-development-log.md` describing how AI was used,
    what was reviewed, and what was changed.

Run the test suite and static analysis before declaring the task
complete. Do not claim checks passed unless you actually ran them.

## Timebox and priorities

Target 2--4 hours. Prioritise in this order:

1.  Inspect the source files and verify the workbook structure.
2.  Set up a minimal Symfony Console project.
3.  Implement the importer and domain representation.
4.  Implement and test grade calculation.
5.  Implement and test P'-value.
6.  Implement and test rit.
7.  Add CLI output and input validation.
8.  Add concise documentation and run quality checks.

If time is running out, leave optional JSON export and cosmetic
improvements out. Do not sacrifice correctness or tests to add features.

## Important working principles for the AI agent

-   Read the assignment PDF and inspect the actual workbook before
    designing the implementation.
-   Treat the PDF as the source of truth. If something is ambiguous,
    identify it and state the assumption rather than silently inventing
    a rule.
-   Do not hardcode facts that should be derived from the workbook.
-   Explain significant trade-offs briefly.
-   Work in small, reviewable steps.
-   Keep the developer informed about files created, commands run, test
    results and unresolved issues.
-   Do not overwrite or delete user files, source documents or existing
    project work.
-   Do not add dependencies without a clear reason.
-   Do not invent test results or claim code was executed if it was not.
-   The developer must be able to explain and own every important
    decision and line of business logic.
