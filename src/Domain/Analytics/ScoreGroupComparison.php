<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * How the highest-scoring and lowest-scoring students (the top and bottom 27%) did on one question.
 */
final readonly class ScoreGroupComparison
{
    public function __construct(
        public int $groupSize,
        public float $highGroupAverage,
        public float $lowGroupAverage,
    ) {
    }

    /**
     * @param list<float> $questionScores
     * @param list<float> $totalScores    the total of the same students, in the same order
     */
    public static function fromScores(array $questionScores, array $totalScores): ?self
    {
        $count = \count($questionScores);
        $groupSize = (int) floor($count * 0.27);
        if ($groupSize < 1 || \count($totalScores) !== $count) {
            return null;
        }

        $order = range(0, $count - 1);
        usort($order, static fn (int $left, int $right): int => $totalScores[$right] <=> $totalScores[$left]);

        return new self(
            $groupSize,
            self::average($questionScores, \array_slice($order, 0, $groupSize)),
            self::average($questionScores, \array_slice($order, -$groupSize)),
        );
    }

    /**
     * @param list<float> $scores
     * @param list<int>   $indexes
     */
    private static function average(array $scores, array $indexes): float
    {
        $total = 0.0;
        foreach ($indexes as $index) {
            $total += $scores[$index];
        }

        return $total / \count($indexes);
    }
}
