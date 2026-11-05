<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;

/**
 * Formats scores and percentages for people, in the console as well as in Twig (`|score`).
 *
 * The console has no request, so it keeps a dot as the decimal separator.
 */
final class ScoreFormatter
{
    public function __construct(
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * Shows up to two decimals without trailing zeros, so 63.0 shows as "63" and 62.1 as "62.1".
     */
    #[AsTwigFilter('score')]
    public function format(float $score): string
    {
        $locale = $this->requestStack?->getCurrentRequest()?->getLocale() ?? 'en';
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        $formatted = $formatter->format($score);

        return false === $formatted ? (string) $score : $formatted;
    }
}
