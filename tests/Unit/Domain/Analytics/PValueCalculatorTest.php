<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\PValueCalculator;
use PHPUnit\Framework\TestCase;

final class PValueCalculatorTest extends TestCase
{
    private PValueCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PValueCalculator();
    }

    public function testCalculatesTheAverageScoreRelativeToTheMaximum(): void
    {
        // 3 of 4 students answered a 1-point question correctly.
        self::assertEqualsWithDelta(0.75, $this->calculator->calculate([1.0, 1.0, 0.0, 1.0], 1.0), 1e-12);
    }

    public function testUsesTheMaximumScoreOfTheQuestion(): void
    {
        // Average 2.5 on a 5-point question.
        self::assertEqualsWithDelta(0.5, $this->calculator->calculate([5.0, 0.0, 3.0, 2.0], 5.0), 1e-12);
    }

    public function testPreservesPartialScores(): void
    {
        // Average (1.2 + 2.1 + 0.6) / 3 = 1.3 on a 3-point question.
        self::assertEqualsWithDelta(1.3 / 3, $this->calculator->calculate([1.2, 2.1, 0.6], 3.0), 1e-12);
    }

    public function testReturnsTheBoundsForAllWrongAndAllCorrect(): void
    {
        self::assertSame(0.0, $this->calculator->calculate([0.0, 0.0], 2.0));
        self::assertSame(1.0, $this->calculator->calculate([2.0, 2.0], 2.0));
    }

    public function testRejectsAZeroMaximumScore(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate([0.0, 0.0], 0.0);
    }

    public function testRejectsAnEmptyListOfScores(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate([], 1.0);
    }
}
