<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Cronbach's alpha for a set of questions, plus alpha with each question left out.
 *
 * Variances use the population divisor. The divisor cancels out in the formula, so the result matches
 * the usual sample-variance definition.
 */
final class CronbachAlpha
{
    /**
     * @param list<list<float>> $itemScores one list of student scores per question; every list has the same length
     *
     * @return array{alpha: ?float, alphaIfRemoved: list<?float>}
     */
    public function calculate(array $itemScores): array
    {
        return [
            'alpha' => $this->alpha($itemScores),
            'alphaIfRemoved' => array_map(function (int $index) use ($itemScores): ?float {
                $remaining = $itemScores;
                unset($remaining[$index]);

                return $this->alpha(array_values($remaining));
            }, array_keys($itemScores)),
        ];
    }

    /**
     * @param list<list<float>> $itemScores
     */
    private function alpha(array $itemScores): ?float
    {
        $questionCount = \count($itemScores);
        $studentCount = \count($itemScores[0] ?? []);
        if ($questionCount < 2 || $studentCount < 2) {
            return null;
        }

        $totals = array_fill(0, $studentCount, 0.0);
        $itemVariance = 0.0;
        foreach ($itemScores as $scores) {
            if (\count($scores) !== $studentCount) {
                throw new \InvalidArgumentException('Every question needs a score for every student.');
            }
            $itemVariance += $this->variance($scores);
            foreach ($scores as $index => $score) {
                $totals[$index] += $score;
            }
        }

        $totalVariance = $this->variance($totals);
        if (0.0 === $totalVariance) {
            return null;
        }

        return ($questionCount / ($questionCount - 1)) * (1.0 - $itemVariance / $totalVariance);
    }

    /**
     * @param non-empty-array<int, float> $values
     */
    private function variance(array $values): float
    {
        $mean = array_sum($values) / \count($values);
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += ($value - $mean) ** 2;
        }

        return $sum / \count($values);
    }
}
