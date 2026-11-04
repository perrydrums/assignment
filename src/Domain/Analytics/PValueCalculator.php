<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * P'-value of a question: the average score of all students divided by the question's maximum score.
 */
final class PValueCalculator
{
    /**
     * @param list<float> $scores the score of every student on the question
     */
    public function calculate(array $scores, float $maxScore): float
    {
        if (!is_finite($maxScore) || $maxScore <= 0.0) {
            throw new \InvalidArgumentException(\sprintf('The P\'-value is undefined for a maximum score of %s.', $maxScore));
        }

        if ([] === $scores) {
            throw new \InvalidArgumentException('The P\'-value is undefined without scores.');
        }

        return array_sum($scores) / \count($scores) / $maxScore;
    }
}
