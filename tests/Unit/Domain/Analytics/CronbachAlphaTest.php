<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\CronbachAlpha;
use PHPUnit\Framework\TestCase;

final class CronbachAlphaTest extends TestCase
{
    public function testMatchesThePopulationFormulaForIdenticalQuestions(): void
    {
        // Three students, three identical questions [1, 2, 3].
        // Item variance is 2/3, total variance is 6, so alpha is 1.5 * (1 - 2/6) = 1.
        $result = (new CronbachAlpha())->calculate([[1, 2, 3], [1, 2, 3], [1, 2, 3]]);

        self::assertEqualsWithDelta(1.0, $result['alpha'], 1e-12);
        self::assertEqualsWithDelta(1.0, $result['alphaIfRemoved'][0], 1e-12);
    }

    public function testCanBeNegativeWhenQuestionsDisagree(): void
    {
        // The third question reverses the first two. Sum of item variances is 2, total variance is 2/3.
        $result = (new CronbachAlpha())->calculate([[1, 2, 3], [1, 2, 3], [3, 2, 1]]);

        self::assertEqualsWithDelta(-3.0, $result['alpha'], 1e-12);
    }

    public function testIsUndefinedWhenThereIsNothingToCompare(): void
    {
        $alpha = new CronbachAlpha();

        self::assertNull($alpha->calculate([[1, 2, 3]])['alpha']);
        self::assertNull($alpha->calculate([[1], [2]])['alpha']);
        self::assertNull($alpha->calculate([[5, 5], [1, 1]])['alpha']);
        self::assertNull($alpha->calculate([[1, 2], [3, 4]])['alphaIfRemoved'][0]);
    }
}
