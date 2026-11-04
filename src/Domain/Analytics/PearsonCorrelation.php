<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Pearson correlation coefficient, equivalent to Excel's CORREL():
 *
 *   r = Σ(x - x̄)(y - ȳ) / √(Σ(x - x̄)² · Σ(y - ȳ)²)
 */
final class PearsonCorrelation
{
    /**
     * @param list<float> $x
     * @param list<float> $y
     *
     * @return float|null null when the correlation is undefined: fewer than two observations, or
     *                    either variable has no variance
     */
    public function calculate(array $x, array $y): ?float
    {
        if (\count($x) !== \count($y)) {
            throw new \InvalidArgumentException(\sprintf('Both variables need the same number of observations, got %d and %d.', \count($x), \count($y)));
        }

        // Checked exactly rather than via a float tolerance on the variance: identical values must
        // yield "undefined", not a spurious correlation caused by rounding noise in the mean.
        if (\count($x) < 2 || $this->isConstant($x) || $this->isConstant($y)) {
            return null;
        }

        $meanX = array_sum($x) / \count($x);
        $meanY = array_sum($y) / \count($y);

        $sumOfProducts = 0.0;
        $sumOfSquaresX = 0.0;
        $sumOfSquaresY = 0.0;
        foreach ($x as $i => $xValue) {
            $deviationX = $xValue - $meanX;
            $deviationY = $y[$i] - $meanY;

            $sumOfProducts += $deviationX * $deviationY;
            $sumOfSquaresX += $deviationX ** 2;
            $sumOfSquaresY += $deviationY ** 2;
        }

        $denominator = sqrt($sumOfSquaresX * $sumOfSquaresY);
        if (0.0 === $denominator) {
            return null;
        }

        // Rounding can push a perfect correlation marginally outside [-1, 1].
        return max(-1.0, min(1.0, $sumOfProducts / $denominator));
    }

    /**
     * @param list<float> $values
     */
    private function isConstant(array $values): bool
    {
        return \count(array_unique($values, \SORT_REGULAR)) <= 1;
    }
}
