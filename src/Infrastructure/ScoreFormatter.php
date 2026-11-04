<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Twig\Attribute\AsTwigFilter;

/**
 * Formats scores and percentages for people, in the console as well as in Twig (`|score`).
 */
final class ScoreFormatter
{
    /**
     * Shows up to two decimals without trailing zeros, so 63.0 shows as "63" and 62.1 as "62.1".
     */
    #[AsTwigFilter('score')]
    public function format(float $score): string
    {
        return rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
    }
}
