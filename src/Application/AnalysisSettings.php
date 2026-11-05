<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Exam\QuestionAdjustment;
use App\Domain\Grade\Caesura;

/**
 * The choices a teacher makes for one analysis: the grading scale, question changes and review guidelines.
 */
final readonly class AnalysisSettings
{
    /**
     * @param list<QuestionAdjustment> $adjustments one per question of the original exam
     */
    public function __construct(
        public Caesura $caesura,
        public Caesura $baselineCaesura,
        public array $adjustments,
        public QualityGuidelines $guidelines,
    ) {
    }

    /**
     * @param int $questionCount questions in the uploaded exam
     */
    public static function initial(Caesura $caesura, int $questionCount): self
    {
        return new self($caesura, $caesura, array_fill(0, $questionCount, QuestionAdjustment::Keep), new QualityGuidelines());
    }

    public function isAdjusted(): bool
    {
        if ($this->caesura != $this->baselineCaesura) {
            return true;
        }

        foreach ($this->adjustments as $adjustment) {
            if ($adjustment->changesTheExam()) {
                return true;
            }
        }

        return false;
    }

    public function removedCount(): int
    {
        return \count(array_filter($this->adjustments, static fn (QuestionAdjustment $adjustment): bool => QuestionAdjustment::Remove === $adjustment));
    }

    public function fullMarksCount(): int
    {
        return \count(array_filter($this->adjustments, static fn (QuestionAdjustment $adjustment): bool => QuestionAdjustment::FullMarks === $adjustment));
    }
}
