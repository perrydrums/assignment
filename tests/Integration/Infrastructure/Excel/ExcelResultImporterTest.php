<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Excel;

use App\Infrastructure\Excel\ExcelResultImporter;
use App\Infrastructure\Excel\InvalidWorkbookException;
use App\Tests\Support\TemporaryWorkbooks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExcelResultImporterTest extends TestCase
{
    private const string SUPPLIED_WORKBOOK = __DIR__.'/../../../../Assignment.xlsx';

    private ExcelResultImporter $importer;
    private TemporaryWorkbooks $workbooks;

    protected function setUp(): void
    {
        $this->importer = new ExcelResultImporter();
        $this->workbooks = new TemporaryWorkbooks();
    }

    protected function tearDown(): void
    {
        $this->workbooks->removeAll();
    }

    public function testImportsQuestionsAndStudents(): void
    {
        $exam = $this->importer->import($this->workbooks->create(self::validRows()));

        self::assertCount(3, $exam->questions);
        self::assertSame('Q1', $exam->questions[0]->label);
        self::assertSame([1.0, 2.0, 3.0], array_map(static fn ($question): float => $question->maxScore, $exam->questions));
        self::assertSame(6.0, $exam->maxScore());

        self::assertCount(2, $exam->students);
        self::assertSame('Student A', $exam->students[0]->id);
        self::assertSame([1.0, 0.5, 2.1], $exam->students[0]->scores);
        self::assertSame([0.0, 2.0, 1.2], $exam->students[1]->scores);
    }

    public function testDerivesTheShapeFromTheWorkbook(): void
    {
        $rows = [['ID', 'Q1'], ['Max question score:', 4]];
        for ($i = 1; $i <= 7; ++$i) {
            $rows[] = ['S'.$i, $i % 5];
        }

        $exam = $this->importer->import($this->workbooks->create($rows));

        self::assertCount(1, $exam->questions);
        self::assertCount(7, $exam->students);
        self::assertSame(4.0, $exam->maxScore());
    }

    public function testAcceptsNumericStudentIdsAndFormulaScores(): void
    {
        $rows = self::validRows();
        $rows[2][0] = 1001;
        $rows[2][3] = '=1+1.1';

        $exam = $this->importer->import($this->workbooks->create($rows));

        self::assertSame('1001', $exam->students[0]->id);
        self::assertEqualsWithDelta(2.1, $exam->students[0]->scores[2], 1e-12);
    }

    public function testImportsTheSuppliedWorkbook(): void
    {
        if (!is_file(self::SUPPLIED_WORKBOOK)) {
            self::markTestSkipped('Assignment.xlsx is not in the repository (student data); place it in the project root to run this test.');
        }

        $exam = $this->importer->import(self::SUPPLIED_WORKBOOK);

        self::assertCount(49, $exam->questions);
        self::assertCount(452, $exam->students);
        self::assertSame(90.0, $exam->maxScore());
        self::assertSame('Student 0001', $exam->students[0]->id);
        self::assertSame('Student 0452', $exam->students[451]->id);
        self::assertSame('Score Question 39', $exam->questions[38]->label);

        $decimalScores = array_filter($exam->scoresForQuestion(38), static fn (float $score): bool => floor($score) !== $score);
        self::assertCount(77, $decimalScores);
        self::assertContains(1.2, $decimalScores);
    }

    /**
     * @return iterable<string, array{array<int, array<int, mixed>>, string}>
     */
    public static function supplyMalformedWorkbooks(): iterable
    {
        [$header, $maxScores, $studentA, $studentB] = self::validRows();

        yield 'wrong ID header' => [self::validRowsWith(0, 0, 'Name'), 'Cell A1: expected the header "ID", got "Name".'];
        yield 'no question columns' => [[['ID'], ['Max question score:'], ['S1']], 'does not contain any question columns'];
        yield 'missing maximum-score row' => [[$header, $studentA, $studentB], 'Cell A2: expected the maximum-score row label'];
        yield 'empty question header' => [self::validRowsWith(0, 2, null), 'Cell C1: the question header is empty'];
        yield 'zero maximum score' => [self::validRowsWith(1, 2, 0), 'Cell C2: the maximum score of "Q2" must be greater than 0'];
        yield 'text maximum score' => [self::validRowsWith(1, 2, 'two'), 'Cell C2: the maximum score of "Q2" must be a number, got text "two".'];
        yield 'no students' => [[$header, $maxScores], 'does not contain any student rows'];
        yield 'missing student ID' => [self::validRowsWith(3, 0, null), 'Cell A4: the student ID is missing.'];
        yield 'duplicate student ID' => [self::validRowsWith(3, 0, 'Student A'), 'Cell A4: student ID "Student A" already occurs in row 3.'];
        yield 'missing score' => [self::validRowsWith(3, 2, null), 'Cell C4: the score of student "Student B" on "Q2" is missing.'];
        yield 'text score' => [self::validRowsWith(2, 3, '2,1'), 'Cell D3: the score of student "Student A" on "Q3" must be a number, got text "2,1".'];
        yield 'boolean score' => [self::validRowsWith(2, 1, true), 'Cell B3: the score of student "Student A" on "Q1" must be a number, got bool.'];
        yield 'negative score' => [self::validRowsWith(2, 1, -1), 'Cell B3: score -1 of student "Student A" on "Q1" is outside the allowed range 0 to 1.'];
        yield 'score above maximum' => [self::validRowsWith(3, 3, 3.5), 'Cell D4: score 3.5 of student "Student B" on "Q3" is outside the allowed range 0 to 3.'];
        yield 'blank row between students' => [[$header, $maxScores, $studentA, [], $studentB], 'Cell A4: the student ID is missing.'];
        yield 'data in a column without header' => [self::validRowsWith(2, 4, 1), 'Cell E1: the question header is empty'];
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     */
    #[DataProvider('supplyMalformedWorkbooks')]
    public function testRejectsMalformedWorkbooks(array $rows, string $expectedMessage): void
    {
        $path = $this->workbooks->create($rows);

        $this->expectException(InvalidWorkbookException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->importer->import($path);
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(InvalidWorkbookException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $this->importer->import('/non/existent/results.xlsx');
    }

    public function testRejectsAFileThatIsNotAnExcelWorkbook(): void
    {
        $path = $this->workbooks->createTextFile("ID,Q1\nMax,1\nS1,1\n", '.xlsx');

        $this->expectException(InvalidWorkbookException::class);
        $this->expectExceptionMessage('could not be read as an Excel workbook');

        $this->importer->import($path);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function validRowsWith(int $row, int $column, mixed $value): array
    {
        $rows = self::validRows();
        $rows[$row][$column] = $value;

        return $rows;
    }

    /**
     * @return array{list<mixed>, list<mixed>, list<mixed>, list<mixed>}
     */
    private static function validRows(): array
    {
        return [
            ['ID', 'Q1', 'Q2', 'Q3'],
            ['Max question score:', 1, 2, 3],
            ['Student A', 1, 0.5, 2.1],
            ['Student B', 0, 2, 1.2],
        ];
    }
}
