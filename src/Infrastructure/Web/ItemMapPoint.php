<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Application\QuestionResult;

/**
 * A question in the item map: x is its P'-value, y its rit-value scaled to the axis (both 0–1).
 */
final readonly class ItemMapPoint
{
    public function __construct(
        public int $number,
        public QuestionResult $question,
        public float $x,
        public float $y,
    ) {
    }
}
