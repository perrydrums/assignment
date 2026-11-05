<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exam;

use App\Domain\Exam\Exam;
use App\Domain\Exam\InvalidExamException;
use App\Domain\Exam\Question;
use App\Domain\Exam\QuestionAdjustment;
use App\Domain\Exam\Student;
use PHPUnit\Framework\TestCase;

final class ExamAdjustmentTest extends TestCase
{
    public function testRemovingAQuestionLowersTheMaximum(): void
    {
        $adjusted = self::exam()->adjusted([QuestionAdjustment::Remove, QuestionAdjustment::Keep]);

        self::assertSame(['Q2'], array_map(static fn (Question $question): string => $question->label, $adjusted->questions));
        self::assertSame(6.0, $adjusted->maxScore());
        self::assertSame([6.0], $adjusted->students[0]->scores);
        self::assertSame([1.5], $adjusted->students[2]->scores);
    }

    public function testFullMarksGivesEveryoneTheMaximumOnThatQuestion(): void
    {
        $adjusted = self::exam()->adjusted([QuestionAdjustment::FullMarks, QuestionAdjustment::Keep]);

        self::assertSame(10.0, $adjusted->maxScore());
        self::assertSame([4.0, 6.0], $adjusted->students[0]->scores);
        self::assertSame([4.0, 1.5], $adjusted->students[2]->scores);
    }

    public function testRefusesToRemoveEveryQuestion(): void
    {
        $this->expectException(InvalidExamException::class);

        self::exam()->adjusted([QuestionAdjustment::Remove, QuestionAdjustment::Remove]);
    }

    private static function exam(): Exam
    {
        return new Exam(
            [new Question('Q1', 4.0), new Question('Q2', 6.0)],
            [new Student('S1', [4.0, 6.0]), new Student('S2', [3.0, 4.0]), new Student('S3', [1.0, 1.5])],
        );
    }
}
