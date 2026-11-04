<?php

declare(strict_types=1);

namespace App\Infrastructure\Excel;

use App\Domain\Exam\Exam;
use App\Domain\Exam\InvalidExamException;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads exam results from the first worksheet of an Excel workbook.
 *
 * Expected layout (the number of questions and students is free):
 *
 *   Row 1:   "ID"                  | <question label> | <question label> | ...
 *   Row 2:   "Max question score:" | <max score>      | <max score>      | ...
 *   Row 3+:  <student ID>          | <score>          | <score>          | ...
 *
 * Every cell is validated; nothing is skipped or silently converted to zero.
 */
final class ExcelResultImporter
{
    private const int HEADER_ROW = 1;
    private const int MAX_SCORE_ROW = 2;
    private const int FIRST_STUDENT_ROW = 3;
    private const int ID_COLUMN = 1;
    private const int FIRST_QUESTION_COLUMN = 2;
    private const array SUPPORTED_READERS = [IOFactory::READER_XLSX, IOFactory::READER_XLS];

    public function import(string $path): Exam
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidWorkbookException('The file does not exist or is not readable.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path, self::SUPPORTED_READERS);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (SpreadsheetException $e) {
            throw new InvalidWorkbookException('The file could not be read as an Excel workbook (.xlsx or .xls): '.$e->getMessage(), previous: $e);
        }

        try {
            if (0 === $spreadsheet->getSheetCount()) {
                throw new InvalidWorkbookException('The workbook does not contain a worksheet.');
            }

            return $this->readExam($spreadsheet->getSheet(0));
        } catch (SpreadsheetException $e) {
            throw new InvalidWorkbookException('The workbook could not be processed: '.$e->getMessage(), previous: $e);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function readExam(Worksheet $sheet): Exam
    {
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow = $sheet->getHighestDataRow();

        $questions = $this->readQuestions($sheet, $lastColumn);
        $students = $this->readStudents($sheet, $questions, $lastRow);

        try {
            return new Exam($questions, $students);
        } catch (InvalidExamException $e) {
            throw new InvalidWorkbookException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return list<Question>
     */
    private function readQuestions(Worksheet $sheet, int $lastColumn): array
    {
        $idHeader = $this->readText($sheet, self::ID_COLUMN, self::HEADER_ROW);
        if (0 !== strcasecmp($idHeader, 'ID')) {
            throw $this->cellError(self::ID_COLUMN, self::HEADER_ROW, \sprintf('expected the header "ID", got "%s".', $idHeader));
        }

        if ($lastColumn < self::FIRST_QUESTION_COLUMN) {
            throw new InvalidWorkbookException('The header row does not contain any question columns.');
        }

        $maxScoreLabel = $this->readText($sheet, self::ID_COLUMN, self::MAX_SCORE_ROW);
        if (false === stripos($maxScoreLabel, 'max')) {
            throw $this->cellError(self::ID_COLUMN, self::MAX_SCORE_ROW, \sprintf('expected the maximum-score row label (e.g. "Max question score:"), got "%s".', $maxScoreLabel));
        }

        $questions = [];
        for ($column = self::FIRST_QUESTION_COLUMN; $column <= $lastColumn; ++$column) {
            $label = $this->readText($sheet, $column, self::HEADER_ROW);
            if ('' === $label) {
                throw $this->cellError($column, self::HEADER_ROW, 'the question header is empty, but the column contains data.');
            }

            $maxScore = $this->readNumber($sheet, $column, self::MAX_SCORE_ROW, \sprintf('maximum score of "%s"', $label));
            if ($maxScore <= 0.0) {
                throw $this->cellError($column, self::MAX_SCORE_ROW, \sprintf('the maximum score of "%s" must be greater than 0, got %s.', $label, $maxScore));
            }

            $questions[] = new Question($label, $maxScore);
        }

        return $questions;
    }

    /**
     * @param list<Question> $questions
     *
     * @return list<Student>
     */
    private function readStudents(Worksheet $sheet, array $questions, int $lastRow): array
    {
        if ($lastRow < self::FIRST_STUDENT_ROW) {
            throw new InvalidWorkbookException(\sprintf('The worksheet does not contain any student rows (expected from row %d).', self::FIRST_STUDENT_ROW));
        }

        $students = [];
        $rowOfStudentId = [];
        for ($row = self::FIRST_STUDENT_ROW; $row <= $lastRow; ++$row) {
            $id = $this->readText($sheet, self::ID_COLUMN, $row);
            if ('' === $id) {
                throw $this->cellError(self::ID_COLUMN, $row, 'the student ID is missing.');
            }

            if (isset($rowOfStudentId[$id])) {
                throw $this->cellError(self::ID_COLUMN, $row, \sprintf('student ID "%s" already occurs in row %d.', $id, $rowOfStudentId[$id]));
            }
            $rowOfStudentId[$id] = $row;

            $scores = [];
            foreach ($questions as $index => $question) {
                $column = self::FIRST_QUESTION_COLUMN + $index;
                $score = $this->readNumber($sheet, $column, $row, \sprintf('score of student "%s" on "%s"', $id, $question->label));
                if (!$question->isValidScore($score)) {
                    throw $this->cellError($column, $row, \sprintf('score %s of student "%s" on "%s" is outside the allowed range 0 to %s.', $score, $id, $question->label, $question->maxScore));
                }
                $scores[] = $score;
            }

            $students[] = new Student($id, $scores);
        }

        return $students;
    }

    private function readText(Worksheet $sheet, int $column, int $row): string
    {
        $value = $sheet->getCell([$column, $row])->getCalculatedValue();

        return match (true) {
            null === $value => '',
            \is_string($value) => trim($value),
            \is_int($value) => (string) $value,
            default => throw $this->cellError($column, $row, \sprintf('expected text, got %s.', get_debug_type($value))),
        };
    }

    private function readNumber(Worksheet $sheet, int $column, int $row, string $description): float
    {
        $value = $sheet->getCell([$column, $row])->getCalculatedValue();

        return match (true) {
            \is_int($value), \is_float($value) => (float) $value,
            null === $value, '' === $value => throw $this->cellError($column, $row, \sprintf('the %s is missing.', $description)),
            \is_string($value) => throw $this->cellError($column, $row, \sprintf('the %s must be a number, got text "%s".', $description, $value)),
            default => throw $this->cellError($column, $row, \sprintf('the %s must be a number, got %s.', $description, get_debug_type($value))),
        };
    }

    private function cellError(int $column, int $row, string $message): InvalidWorkbookException
    {
        return new InvalidWorkbookException(\sprintf('Cell %s%d: %s', Coordinate::stringFromColumnIndex($column), $row, $message));
    }
}
