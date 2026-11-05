<?php

declare(strict_types=1);

namespace App\Application;

/**
 * The analysis as shown, plus the original analysis when the teacher changed something.
 *
 * Students are listed in the same order in both analyses. A result change means the pass/fail
 * decision differs from the analysis of the uploaded file with its original grading scale.
 */
final readonly class AnalysisOutcome
{
    /**
     * @param list<bool> $resultChanged true when that student's pass/fail result differs from the baseline
     */
    public function __construct(
        public ExamAnalysis $analysis,
        public ?ExamAnalysis $baseline,
        public array $resultChanged,
    ) {
    }

    public function isAdjusted(): bool
    {
        return null !== $this->baseline;
    }

    public function changedCount(): int
    {
        return \count(array_filter($this->resultChanged));
    }

    /**
     * Students who failed on the original analysis and pass now. Negative when the change does the opposite.
     */
    public function passCountDelta(): int
    {
        if (null === $this->baseline) {
            return 0;
        }

        return $this->analysis->passedCount() - $this->baseline->passedCount();
    }
}
