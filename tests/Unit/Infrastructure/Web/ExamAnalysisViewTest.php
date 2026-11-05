<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Web;

use App\Application\AnalysisOutcome;
use App\Application\ExamAnalysis;
use App\Application\QuestionResult;
use App\Application\StudentResult;
use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Analytics\QuestionFlag;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\GradeResult;
use App\Infrastructure\Web\ExamAnalysisView;
use App\Infrastructure\Web\GradeHistogramBin;
use App\Infrastructure\Web\Insight;
use PHPUnit\Framework\TestCase;

final class ExamAnalysisViewTest extends TestCase
{
    public function testSummarisesTheAnalysis(): void
    {
        $view = self::view([[1.0, false], [5.5, true], [10.0, true], [5.4, false]]);

        self::assertSame(0.5, $view->passRate());
        self::assertEqualsWithDelta((1.0 + 5.5 + 10.0 + 5.4) / 4, $view->averageScore(), 1e-12);
        self::assertEqualsWithDelta((0.5 + 0.25 + 0.75) / 3, $view->averagePValue(), 1e-12);
    }

    public function testBinsRoundedGradesInHalfPointsForTheAssignmentCaesura(): void
    {
        $bins = self::view([[1.0, false], [5.4, false], [5.5, true], [10.0, true]])->gradeHistogram();

        self::assertCount(18, $bins);
        self::assertSame([1.0, 1.5], [$bins[0]->lowerGrade, $bins[0]->upperGrade]);
        self::assertSame([9.5, 10.0], [$bins[17]->lowerGrade, $bins[17]->upperGrade]);
        self::assertSame('1.0–1.4', $bins[0]->label());
        self::assertSame('9.5–10.0', $bins[17]->label());
        self::assertTrue($bins[17]->includesUpperGrade);

        self::assertSame([0, 1], [$bins[0]->passed, $bins[0]->failed]);
        self::assertSame([0, 1], [$bins[8]->passed, $bins[8]->failed], '5.4 falls in 5.0–5.4');
        self::assertSame([1, 0], [$bins[9]->passed, $bins[9]->failed], '5.5 falls in 5.5–5.9');
        self::assertSame([1, 0], [$bins[17]->passed, $bins[17]->failed], '10.0 falls in the last bin');
        self::assertSame(4, array_sum(array_map(static fn (GradeHistogramBin $bin): int => $bin->total(), $bins)));
    }

    public function testCountsAFailedStudentWhoseGradeRoundsToTheBoundaryAsFailed(): void
    {
        // 69.44% gives an unrounded grade of 5.45, displayed as 5.5, but the student did not pass.
        $bins = self::view([[5.45, false]])->gradeHistogram();

        self::assertSame([0, 1], [$bins[9]->passed, $bins[9]->failed]);
    }

    public function testAdaptsTheBinWidthToTheGradeScaleOfTheCaesura(): void
    {
        $caesura = new Caesura([new CaesuraPoint(0.0, 0.0), new CaesuraPoint(100.0, 100.0)], 55.0);
        $bins = self::view([[50.0, false]], $caesura)->gradeHistogram();

        self::assertCount(20, $bins);
        self::assertSame([50.0, 55.0], [$bins[10]->lowerGrade, $bins[10]->upperGrade]);
        self::assertSame(1, $bins[10]->failed);
    }

    public function testRoundsTheCountAxisUpToANiceNumber(): void
    {
        $students = array_fill(0, 7, [3.0, false]);

        self::assertSame([0, 2, 4, 6, 8], self::view($students)->histogramTicks());
    }

    public function testPositionsQuestionsByPValueAndRitValue(): void
    {
        $view = self::view([[5.0, false]]);

        self::assertSame([-0.1, 0.0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6], $view->ritTicks());

        $points = $view->itemMap();
        self::assertCount(2, $points);
        self::assertSame(1, $points[0]->number);
        self::assertSame(0.5, $points[0]->x);
        self::assertEqualsWithDelta((0.53 + 0.1) / 0.7, $points[0]->y, 1e-12);
        self::assertSame(2, $points[1]->number);
        self::assertEqualsWithDelta((-0.05 + 0.1) / 0.7, $points[1]->y, 1e-12);

        self::assertSame([3], array_keys($view->questionsWithoutRitValue()));
    }

    public function testExplainsWhichQuestionsToReview(): void
    {
        $view = self::view([[1.0, false], [1.0, false], [10.0, true], [1.0, false]]);

        self::assertSame([QuestionFlag::NegativeDiscrimination, QuestionFlag::VeryDifficult], $view->flags($view->analysis()->questions[1]));
        self::assertSame([QuestionFlag::NoVariation], $view->flags($view->analysis()->questions[2]));
        self::assertSame(2, $view->reviewCount());

        $keys = array_map(static fn (Insight $insight): string => $insight->messageKey, $view->insights());
        self::assertContains('insight.negative_rit', $keys);
        self::assertContains('insight.low_pass_rate', $keys);
    }

    public function testDescribesEachQuestionForTheDetailDrawer(): void
    {
        $details = self::view(
            [[1.0, false], [5.5, true], [10.0, true], [5.4, false]],
            null,
            [[0.0, 1.0, 2.0, 1.0], [1.0, 1.0, 2.0, 0.0], [3.0, 3.0, 3.0, 3.0]],
        )->questionDetails();

        self::assertSame(0.5, $details[0]['pValue']);
        self::assertSame(0.53, $details[0]['ritValue']);
        self::assertSame(0.61, $details[0]['alphaIfRemoved']);
        self::assertNull($details[2]['ritValue']);
        self::assertSame([['score' => 0.0, 'count' => 1], ['score' => 1.0, 'count' => 2], ['score' => 2.0, 'count' => 1]], $details[0]['distribution']);
        // Totals are 1, 5.5, 10 and 5.4: the strongest student (10) scored 2 on Q1, the weakest (1) scored 0.
        self::assertSame(['size' => 1, 'high' => 2.0, 'low' => 0.0], $details[0]['group']);
    }

    /**
     * @param list<array{float, bool}> $grades         unrounded grade and pass/fail per student
     * @param list<list<float>>        $questionScores student scores per question
     */
    private static function view(array $grades, ?Caesura $caesura = null, array $questionScores = []): ExamAnalysisView
    {
        $students = [];
        foreach ($grades as $index => [$grade, $passed]) {
            $students[] = new StudentResult('S'.($index + 1), $grade, new GradeResult($grade * 10, $grade, $passed));
        }

        $analysis = new ExamAnalysis(
            10.0,
            $caesura ?? new Caesura([new CaesuraPoint(20.0, 1.0), new CaesuraPoint(70.0, 5.5), new CaesuraPoint(100.0, 10.0)], 70.0),
            $students,
            [
                new QuestionResult('Q1', 2.0, 0.5, 0.53, 0.61),
                new QuestionResult('Q2', 4.0, 0.25, -0.05),
                new QuestionResult('Q3', 4.0, 0.75, null),
            ],
        );

        return new ExamAnalysisView(new AnalysisOutcome($analysis, null, array_fill(0, \count($grades), false)), 'results.xlsx', 5.5, new QualityGuidelines(), [0, 1, 2], $questionScores);
    }
}
