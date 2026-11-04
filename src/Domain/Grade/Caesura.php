<?php

declare(strict_types=1);

namespace App\Domain\Grade;

/**
 * Defines how a score percentage translates into a grade, and from which percentage a student passes.
 *
 * Grades between reference points are linearly interpolated; below the first point the first grade
 * applies and above the last point the last grade applies.
 *
 * The pass percentage is deliberately separate from the points: passing is decided on the score,
 * not on the (rounded) grade.
 */
final readonly class Caesura
{
    /** @var non-empty-list<CaesuraPoint> */
    public array $points;

    /**
     * @param list<CaesuraPoint> $points ordered by strictly increasing percentage
     */
    public function __construct(
        array $points,
        public float $passPercentage,
    ) {
        if (\count($points) < 2) {
            throw new InvalidCaesuraException('A caesura needs at least two reference points.');
        }

        for ($i = 1, $count = \count($points); $i < $count; ++$i) {
            $previous = $points[$i - 1];
            $current = $points[$i];

            if ($current->percentage <= $previous->percentage) {
                throw new InvalidCaesuraException(\sprintf('Caesura percentages must be strictly increasing, got %s%% after %s%%.', $current->percentage, $previous->percentage));
            }

            if ($current->grade < $previous->grade) {
                throw new InvalidCaesuraException(\sprintf('Caesura grades must not decrease, got %s after %s.', $current->grade, $previous->grade));
            }
        }

        if (!is_finite($passPercentage) || $passPercentage <= 0.0 || $passPercentage > 100.0) {
            throw new InvalidCaesuraException(\sprintf('The pass percentage must be greater than 0 and at most 100, got %s.', $passPercentage));
        }

        $this->points = $points;
    }
}
