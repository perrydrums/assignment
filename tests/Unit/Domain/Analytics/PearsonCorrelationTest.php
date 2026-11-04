<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\PearsonCorrelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PearsonCorrelationTest extends TestCase
{
    private PearsonCorrelation $correlation;

    protected function setUp(): void
    {
        $this->correlation = new PearsonCorrelation();
    }

    public function testPerfectPositiveCorrelation(): void
    {
        self::assertEqualsWithDelta(1.0, $this->correlation->calculate([1.0, 2.0, 3.0, 4.0], [3.0, 5.0, 7.0, 9.0]), 1e-12);
    }

    public function testPerfectNegativeCorrelation(): void
    {
        self::assertEqualsWithDelta(-1.0, $this->correlation->calculate([1.0, 2.0, 3.0, 4.0], [8.0, 6.0, 4.0, 2.0]), 1e-12);
    }

    public function testNoCorrelation(): void
    {
        // Deviations x: -1.5, -0.5, 0.5, 1.5; y: 1, -1, -1, 1 -> Σ products = 0.
        self::assertEqualsWithDelta(0.0, $this->correlation->calculate([1.0, 2.0, 3.0, 4.0], [1.0, -1.0, -1.0, 1.0]), 1e-12);
    }

    public function testKnownValue(): void
    {
        // Σdxdy = 6, Σdx² = 10, Σdy² = 6 -> r = 6 / √60 (Excel CORREL: 0.774596669).
        $r = $this->correlation->calculate([1.0, 2.0, 3.0, 4.0, 5.0], [2.0, 4.0, 5.0, 4.0, 5.0]);

        self::assertEqualsWithDelta(6 / sqrt(60), $r, 1e-12);
    }

    public function testDecimalValues(): void
    {
        // y = 0.3 * x + 0.6 is perfectly linear in decimal scores.
        $r = $this->correlation->calculate([0.3, 1.2, 2.1, 2.4], [0.69, 0.96, 1.23, 1.32]);

        self::assertEqualsWithDelta(1.0, $r, 1e-12);
    }

    public function testDichotomousItemAgainstTotalScore(): void
    {
        // Point-biserial case (a 0/1 question): Σdxdy = 16, Σdx² = 1.2, Σdy² = 288 -> r = 16 / √345.6.
        $r = $this->correlation->calculate([0.0, 1.0, 0.0, 1.0, 1.0], [10.0, 25.0, 12.0, 18.0, 30.0]);

        self::assertEqualsWithDelta(16 / sqrt(345.6), $r, 1e-12);
        self::assertEqualsWithDelta(0.8606629658, $r, 1e-9);
    }

    /**
     * @return iterable<string, array{list<float>, list<float>}>
     */
    public static function supplyUndefinedCases(): iterable
    {
        yield 'no observations' => [[], []];
        yield 'a single observation' => [[1.0], [5.0]];
        yield 'zero variance in x' => [[1.0, 1.0, 1.0], [1.0, 2.0, 3.0]];
        yield 'zero variance in y' => [[1.0, 2.0, 3.0], [4.0, 4.0, 4.0]];
        yield 'constant decimal x whose mean has rounding noise' => [[0.1, 0.1, 0.1], [1.0, 2.0, 3.0]];
    }

    /**
     * @param list<float> $x
     * @param list<float> $y
     */
    #[DataProvider('supplyUndefinedCases')]
    public function testReturnsNullWhenUndefined(array $x, array $y): void
    {
        self::assertNull($this->correlation->calculate($x, $y));
    }

    public function testRejectsVariablesOfDifferentLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->correlation->calculate([1.0, 2.0], [1.0, 2.0, 3.0]);
    }
}
