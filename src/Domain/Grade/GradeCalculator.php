<?php

declare(strict_types=1);

namespace App\Domain\Grade;

final class GradeCalculator
{
    /**
     * Tolerance (in percentage points) for the pass comparison. Scores are summed as floats, so a
     * score of exactly 70% can come out as 69.99999999999999%; that must still count as a pass.
     */
    private const float PASS_TOLERANCE = 1e-9;

    public function calculate(float $score, float $maxScore, Caesura $caesura): GradeResult
    {
        if (!is_finite($maxScore) || $maxScore <= 0.0) {
            throw new \InvalidArgumentException(\sprintf('The maximum score must be greater than 0, got %s.', $maxScore));
        }

        if (!is_finite($score) || $score < 0.0 || $score > $maxScore) {
            throw new \InvalidArgumentException(\sprintf('The score must be between 0 and %s, got %s.', $maxScore, $score));
        }

        $percentage = $score / $maxScore * 100.0;

        return new GradeResult(
            $percentage,
            $this->interpolateGrade($percentage, $caesura->points),
            $percentage >= $caesura->passPercentage - self::PASS_TOLERANCE,
        );
    }

    /**
     * @param non-empty-list<CaesuraPoint> $points
     */
    private function interpolateGrade(float $percentage, array $points): float
    {
        $first = $points[0];
        if ($percentage <= $first->percentage) {
            return $first->grade;
        }

        for ($i = 1, $count = \count($points); $i < $count; ++$i) {
            $lower = $points[$i - 1];
            $upper = $points[$i];

            if ($percentage <= $upper->percentage) {
                $fraction = ($percentage - $lower->percentage) / ($upper->percentage - $lower->percentage);

                return $lower->grade + $fraction * ($upper->grade - $lower->grade);
            }
        }

        return $points[\count($points) - 1]->grade;
    }
}
