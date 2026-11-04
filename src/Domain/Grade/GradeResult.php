<?php

declare(strict_types=1);

namespace App\Domain\Grade;

final readonly class GradeResult
{
    public function __construct(
        public float $percentage,
        public float $grade,
        public bool $passed,
    ) {
    }

    /**
     * The official grade, with one decimal (rounded half up).
     *
     * Interpolation can produce e.g. 5.4499999999999993 for a grade that is exactly 5.45, so
     * floating-point noise is removed before rounding to one decimal.
     */
    public function roundedGrade(): float
    {
        return round(round($this->grade, 9), 1);
    }
}
