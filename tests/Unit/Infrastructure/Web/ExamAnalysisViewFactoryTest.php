<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Web;

use App\Application\CaesuraNotation;
use App\Application\ExamAnalyzer;
use App\Domain\Analytics\PearsonCorrelation;
use App\Domain\Analytics\PValueCalculator;
use App\Domain\Exam\Exam;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\GradeCalculator;
use App\Infrastructure\Console\ExamResultPresenter;
use App\Infrastructure\ScoreFormatter;
use App\Infrastructure\Web\ExamAnalysisView;
use App\Infrastructure\Web\ExamAnalysisViewFactory;
use PHPUnit\Framework\TestCase;

final class ExamAnalysisViewFactoryTest extends TestCase
{
    public function testDescribesTheAssignmentCaesuraWithTheShortestCommand(): void
    {
        $view = self::createView((new CaesuraNotation())->parseDefault(), 'Assignment.xlsx');

        self::assertSame(5.5, $view->passGrade);
        self::assertSame('php bin/console app:analyze-exam Assignment.xlsx', $view->cliCommand);
    }

    public function testIncludesACustomCaesuraInTheCommand(): void
    {
        $caesura = new Caesura([new CaesuraPoint(0.0, 1.0), new CaesuraPoint(60.0, 6.0), new CaesuraPoint(100.0, 10.0)], 60.0);
        $view = self::createView($caesura, 'Exam 2026 (resit).xlsx');

        self::assertSame(6.0, $view->passGrade);
        self::assertSame("php bin/console app:analyze-exam 'Exam 2026 (resit).xlsx' --caesura=0:1.0,60:6.0,100:10.0 --pass-percentage=60", $view->cliCommand);
    }

    public function testRendersTheConsoleReport(): void
    {
        $report = self::createView((new CaesuraNotation())->parseDefault(), 'results.xlsx')->consoleOutput;

        self::assertStringContainsString('Exam analysis', $report);
        self::assertMatchesRegularExpression('/A\s+10\s+100\.0%\s+10\.0\s+passed/', $report);
    }

    private static function createView(Caesura $caesura, string $fileName): ExamAnalysisView
    {
        $exam = new Exam([new Question('Q1', 10.0)], [new Student('A', [10.0]), new Student('B', [4.0])]);
        $analysis = (new ExamAnalyzer(new GradeCalculator(), new PValueCalculator(), new PearsonCorrelation()))->analyze($exam, $caesura);

        $factory = new ExamAnalysisViewFactory(new GradeCalculator(), new CaesuraNotation(), new ExamResultPresenter(new ScoreFormatter()));

        return $factory->create($analysis, $fileName);
    }
}
