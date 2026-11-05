<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Application\AnalysisOutcome;
use App\Application\AnalysisSettings;
use App\Domain\Exam\Exam;
use App\Domain\Exam\QuestionAdjustment;
use App\Domain\Grade\GradeCalculator;

final readonly class ExamAnalysisViewFactory
{
    public function __construct(
        private GradeCalculator $gradeCalculator,
    ) {
    }

    public function create(AnalysisOutcome $outcome, string $fileName, AnalysisSettings $settings, Exam $analyzedExam): ExamAnalysisView
    {
        $originalIndexes = [];
        foreach ($settings->adjustments as $index => $adjustment) {
            if (QuestionAdjustment::Remove !== $adjustment) {
                $originalIndexes[] = $index;
            }
        }

        $questionScores = [];
        foreach ($analyzedExam->questions as $index => $question) {
            $questionScores[] = $analyzedExam->scoresForQuestion($index);
        }

        return new ExamAnalysisView(
            $outcome,
            $fileName,
            $this->gradeCalculator->calculate($settings->caesura->passPercentage, 100.0, $settings->caesura)->roundedGrade(),
            $settings->guidelines,
            $originalIndexes,
            $questionScores,
        );
    }
}
