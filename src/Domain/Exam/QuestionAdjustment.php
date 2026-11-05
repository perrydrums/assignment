<?php

declare(strict_types=1);

namespace App\Domain\Exam;

/**
 * What to do with a question when trying out a change to the analysis.
 */
enum QuestionAdjustment: string
{
    /** Keep the question as scored. */
    case Keep = 'keep';
    /** Leave the question out, so it no longer counts towards the maximum or the grades. */
    case Remove = 'remove';
    /** Give every student full marks, for example when the answer key was wrong. */
    case FullMarks = 'full_marks';

    public function changesTheExam(): bool
    {
        return self::Keep !== $this;
    }
}
