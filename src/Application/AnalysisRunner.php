<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exam\Exam;

/**
 * Runs an analysis and, when the teacher changed the scale or the questions, the original analysis too.
 */
final readonly class AnalysisRunner
{
    public function __construct(
        private ExamAnalyzer $analyzer,
    ) {
    }

    public function run(Exam $exam, AnalysisSettings $settings): AnalysisOutcome
    {
        $current = $this->analyzer->analyze($exam->adjusted($settings->adjustments), $settings->caesura);
        if (!$settings->isAdjusted()) {
            return new AnalysisOutcome($current, null, array_fill(0, \count($current->students), false));
        }

        $baseline = $this->analyzer->analyze($exam, $settings->baselineCaesura);
        $changed = [];
        foreach ($current->students as $index => $student) {
            $changed[] = $student->grade->passed !== $baseline->students[$index]->grade->passed;
        }

        return new AnalysisOutcome($current, $baseline, $changed);
    }
}
