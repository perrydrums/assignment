<?php

declare(strict_types=1);

namespace App\Domain\Grade;

/**
 * A reference point of a caesura: scoring this percentage of the maximum score yields this grade.
 */
final readonly class CaesuraPoint
{
    public function __construct(
        public float $percentage,
        public float $grade,
    ) {
        if (!is_finite($percentage) || $percentage < 0.0 || $percentage > 100.0) {
            throw InvalidCaesuraException::percentageOutOfRange($percentage);
        }

        if (!is_finite($grade)) {
            throw InvalidCaesuraException::gradeNotFinite();
        }
    }
}
