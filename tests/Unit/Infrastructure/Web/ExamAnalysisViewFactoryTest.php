<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Web;

use App\Application\AnalysisOutcome;
use App\Application\AnalysisSettings;
use App\Application\CaesuraNotation;
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
use App\Infrastructure\Web\ExamAnalysisView;
use App\Infrastructure\Web\ExamAnalysisViewFactory;
use PHPUnit\Framework\TestCase;

final class ExamAnalysisViewFactoryTest extends TestCase
{
    public function testUsesThePassGradeOfTheChosenScale(): void
    {
        $view = self::createView((new CaesuraNotation())->parseDefault(), 'Assignment.xlsx');

        self::assertSame(5.5, $view->passGrade);
        self::assertSame('Assignment.xlsx', $view->fileName);
    }

    public function testUsesACustomPassGrade(): void
    {
        $caesura = new Caesura([new CaesuraPoint(0.0, 1.0), new CaesuraPoint(60.0, 6.0), new CaesuraPoint(100.0, 10.0)], 60.0);
        $view = self::createView($caesura, 'Exam 2026 (resit).xlsx');

        self::assertSame(6.0, $view->passGrade);
    }

    private static function createView(Caesura $caesura, string $fileName): ExamAnalysisView
    {
        $exam = new Exam([new Question('Q1', 10.0)], [new Student('A', [10.0]), new Student('B', [4.0])]);
        $analysis = (new ExamAnalyzer(new GradeCalculator(), new PValueCalculator(), new PearsonCorrelation(), new CronbachAlpha()))->analyze($exam, $caesura);
        $settings = AnalysisSettings::initial($caesura, 1);

        return (new ExamAnalysisViewFactory(new GradeCalculator()))->create(new AnalysisOutcome($analysis, null, [false, false]), $fileName, $settings, $exam);
    }
}
