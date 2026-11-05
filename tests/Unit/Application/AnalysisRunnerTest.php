<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\AnalysisRunner;
use App\Application\AnalysisSettings;
use App\Application\CaesuraNotation;
use App\Application\ExamAnalyzer;
use App\Domain\Analytics\CronbachAlpha;
use App\Domain\Analytics\PearsonCorrelation;
use App\Domain\Analytics\PValueCalculator;
use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Exam\Exam;
use App\Domain\Exam\Question;
use App\Domain\Exam\QuestionAdjustment;
use App\Domain\Exam\Student;
use App\Domain\Grade\GradeCalculator;
use PHPUnit\Framework\TestCase;

final class AnalysisRunnerTest extends TestCase
{
    public function testRemovingAQuestionCanChangeWhoPasses(): void
    {
        $caesura = (new CaesuraNotation())->parseDefault();
        $exam = new Exam(
            [new Question('Q1', 10.0), new Question('Q2', 10.0)],
            [new Student('A', [10.0, 0.0]), new Student('B', [10.0, 10.0])],
        );
        $settings = new AnalysisSettings(
            $caesura,
            $caesura,
            [QuestionAdjustment::Keep, QuestionAdjustment::Remove],
            new QualityGuidelines(),
        );

        $outcome = self::runner()->run($exam, $settings);

        self::assertTrue($outcome->isAdjusted());
        self::assertSame(1, $outcome->passCountDelta());
        self::assertSame([true, false], $outcome->resultChanged);
        self::assertTrue($outcome->analysis->students[0]->grade->passed);
        self::assertFalse($outcome->baseline?->students[0]->grade->passed);
    }

    public function testAnUnchangedAnalysisHasNoBaseline(): void
    {
        $caesura = (new CaesuraNotation())->parseDefault();
        $exam = new Exam([new Question('Q1', 10.0)], [new Student('A', [8.0])]);

        $outcome = self::runner()->run($exam, AnalysisSettings::initial($caesura, 1));

        self::assertFalse($outcome->isAdjusted());
        self::assertSame(0, $outcome->passCountDelta());
    }

    private static function runner(): AnalysisRunner
    {
        return new AnalysisRunner(new ExamAnalyzer(
            new GradeCalculator(),
            new PValueCalculator(),
            new PearsonCorrelation(),
            new CronbachAlpha(),
        ));
    }
}
