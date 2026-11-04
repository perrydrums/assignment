<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exam;

use App\Domain\Exam\Exam;
use App\Domain\Exam\InvalidExamException;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use PHPUnit\Framework\TestCase;

final class ExamTest extends TestCase
{
    public function testDerivesTotalsFromQuestionsAndScores(): void
    {
        $exam = new Exam(
            [new Question('Q1', 1.0), new Question('Q2', 3.0), new Question('Q3', 5.0)],
            [new Student('A', [1.0, 1.2, 5.0]), new Student('B', [0.0, 2.1, 2.5])],
        );

        self::assertSame(9.0, $exam->maxScore());
        self::assertSame([1.2, 2.1], $exam->scoresForQuestion(1));
        self::assertEqualsWithDelta(7.2, $exam->totalScores()[0], 1e-12);
        self::assertEqualsWithDelta(4.6, $exam->totalScores()[1], 1e-12);
    }

    public function testRejectsAnExamWithoutQuestions(): void
    {
        $this->expectException(InvalidExamException::class);

        new Exam([], [new Student('A', [])]);
    }

    public function testRejectsAnExamWithoutStudents(): void
    {
        $this->expectException(InvalidExamException::class);

        new Exam([new Question('Q1', 1.0)], []);
    }

    public function testRejectsDuplicateStudentIds(): void
    {
        $this->expectException(InvalidExamException::class);
        $this->expectExceptionMessage('Student ID "A" occurs more than once.');

        new Exam([new Question('Q1', 1.0)], [new Student('A', [1.0]), new Student('A', [0.0])]);
    }

    public function testRejectsAStudentWithoutAScoreForEveryQuestion(): void
    {
        $this->expectException(InvalidExamException::class);

        new Exam([new Question('Q1', 1.0), new Question('Q2', 1.0)], [new Student('A', [1.0])]);
    }

    public function testRejectsAScoreAboveTheQuestionMaximum(): void
    {
        $this->expectException(InvalidExamException::class);

        new Exam([new Question('Q1', 2.0)], [new Student('A', [2.5])]);
    }

    public function testRejectsAQuestionWithAZeroMaximumScore(): void
    {
        $this->expectException(InvalidExamException::class);

        new Question('Q1', 0.0);
    }
}
