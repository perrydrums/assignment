<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\ExamAnalyzer;
use App\Domain\Analytics\CronbachAlpha;
use App\Domain\Analytics\PearsonCorrelation;
use App\Domain\Analytics\PValueCalculator;
use App\Domain\Exam\Exam;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\GradeCalculator;
use PHPUnit\Framework\TestCase;

final class ExamAnalyzerTest extends TestCase
{
    public function testAnalyzesStudentsAndQuestions(): void
    {
        // Max score 10. Totals: A = 10 (100%), B = 7 (70%), C = 2 (20%).
        $exam = new Exam(
            [new Question('Q1', 5.0), new Question('Q2', 4.0), new Question('Q3', 1.0)],
            [
                new Student('A', [5.0, 4.0, 1.0]),
                new Student('B', [3.0, 3.0, 1.0]),
                new Student('C', [0.0, 1.0, 1.0]),
            ],
        );
        $caesura = new Caesura([new CaesuraPoint(20.0, 1.0), new CaesuraPoint(70.0, 5.5), new CaesuraPoint(100.0, 10.0)], 70.0);

        $analysis = (new ExamAnalyzer(new GradeCalculator(), new PValueCalculator(), new PearsonCorrelation(), new CronbachAlpha()))->analyze($exam, $caesura);

        self::assertSame(10.0, $analysis->maxScore);
        self::assertSame(['A', 'B', 'C'], array_map(static fn ($student): string => $student->studentId, $analysis->students));
        self::assertSame([10.0, 7.0, 2.0], array_map(static fn ($student): float => $student->totalScore, $analysis->students));
        self::assertSame([10.0, 5.5, 1.0], array_map(static fn ($student): float => $student->grade->roundedGrade(), $analysis->students));
        self::assertSame(2, $analysis->passedCount());
        self::assertSame(1, $analysis->failedCount());
        self::assertEqualsWithDelta((10.0 + 5.5 + 1.0) / 3, $analysis->averageGrade(), 1e-12);

        $q1 = $analysis->questions[0];
        self::assertSame('Q1', $q1->label);
        self::assertEqualsWithDelta(8.0 / 3 / 5, $q1->pValue, 1e-12);
        // Q1 scores (5, 3, 0) against totals (10, 7, 2), the total including Q1 itself.
        self::assertEqualsWithDelta((new PearsonCorrelation())->calculate([5.0, 3.0, 0.0], [10.0, 7.0, 2.0]), $q1->ritValue, 1e-12);

        $q3 = $analysis->questions[2];
        self::assertSame(1.0, $q3->pValue);
        self::assertNull($q3->ritValue, 'Everyone scored 1 on Q3, so its rit-value is undefined.');
    }
}
