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
            throw new InvalidCaesuraException(\sprintf('A caesura percentage must be between 0 and 100, got %s.', $percentage));
        }

        if (!is_finite($grade)) {
            throw new InvalidCaesuraException('A caesura grade must be a finite number.');
        }
    }
}
