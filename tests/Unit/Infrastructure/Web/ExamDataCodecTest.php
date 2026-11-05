<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Web;

use App\Domain\Exam\Exam;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use App\Infrastructure\Web\ExamDataCodec;
use App\Infrastructure\Web\InvalidExamDataException;
use PHPUnit\Framework\TestCase;

final class ExamDataCodecTest extends TestCase
{
    public function testRoundTripsAnExam(): void
    {
        $codec = new ExamDataCodec();
        $exam = new Exam(
            [new Question('Question 1', 2.5)],
            [new Student('S1', [1.25]), new Student('S2', [2.5])],
        );

        $decoded = $codec->decode($codec->encode($exam));

        self::assertSame('Question 1', $decoded->questions[0]->label);
        self::assertSame(2.5, $decoded->questions[0]->maxScore);
        self::assertSame([1.25], $decoded->students[0]->scores);
    }

    public function testRejectsMalformedData(): void
    {
        $codec = new ExamDataCodec();

        $this->expectException(InvalidExamDataException::class);
        $codec->decode('{');
    }

    public function testRejectsMoreQuestionsThanTheLimit(): void
    {
        $questions = [];
        $scores = [];
        for ($i = 0; $i < ExamDataCodec::MAX_QUESTIONS + 1; ++$i) {
            $questions[] = ['label' => 'Q'.$i, 'maxScore' => 1];
            $scores[] = 1;
        }

        $this->expectException(InvalidExamDataException::class);
        (new ExamDataCodec())->decode((string) json_encode([
            'questions' => $questions,
            'students' => [['id' => 'S1', 'scores' => $scores]],
        ]));
    }
}
