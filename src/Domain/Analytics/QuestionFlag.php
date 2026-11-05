<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * A reason to look at a question again. The order is the order in which flags are reported.
 */
enum QuestionFlag: string
{
    case NegativeDiscrimination = 'negative';
    case NoVariation = 'no_variation';
    case VeryDifficult = 'very_difficult';
    case VeryEasy = 'very_easy';
    case WeakDiscrimination = 'weak';

    public function needsReview(): bool
    {
        return true;
    }
}
