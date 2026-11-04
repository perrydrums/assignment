<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Grade;

use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\GradeCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GradeCalculatorTest extends TestCase
{
    private GradeCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new GradeCalculator();
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function supplyCaesuraCases(): iterable
    {
        // score, max score, expected unrounded grade
        yield '0% clamps to 1.0' => [0.0, 90.0, 1.0];
        yield 'below 20% clamps to 1.0' => [9.0, 90.0, 1.0];
        yield 'exactly 20% is 1.0' => [18.0, 90.0, 1.0];
        yield '45% is halfway between 1.0 and 5.5' => [40.5, 90.0, 3.25];
        yield 'exactly 70% is 5.5' => [63.0, 90.0, 5.5];
        yield '85% is halfway between 5.5 and 10.0' => [76.5, 90.0, 7.75];
        yield 'exactly 100% is 10.0' => [90.0, 90.0, 10.0];
        yield 'decimal score' => [63.2, 90.0, 5.5 + (63.2 / 90 * 100 - 70) / 30 * 4.5];
    }

    #[DataProvider('supplyCaesuraCases')]
    public function testInterpolatesTheSuppliedCaesura(float $score, float $maxScore, float $expectedGrade): void
    {
        $result = $this->calculator->calculate($score, $maxScore, self::suppliedCaesura());

        self::assertEqualsWithDelta($expectedGrade, $result->grade, 1e-9);
    }

    public function testReturnsThePercentageOfTheMaximumScore(): void
    {
        $result = $this->calculator->calculate(45.0, 90.0, self::suppliedCaesura());

        self::assertEqualsWithDelta(50.0, $result->percentage, 1e-9);
    }

    public function testRoundsTheGradeToOneDecimal(): void
    {
        // 50 / 90 = 55.56% -> 1.0 + 35.56 / 50 * 4.5 = 4.2
        $result = $this->calculator->calculate(50.0, 90.0, self::suppliedCaesura());

        self::assertSame(4.2, $result->roundedGrade());
    }

    public function testExactlySeventyPercentPasses(): void
    {
        $result = $this->calculator->calculate(63.0, 90.0, self::suppliedCaesura());

        self::assertTrue($result->passed);
        self::assertSame(5.5, $result->roundedGrade());
    }

    public function testSeventyPercentPassesDespiteFloatingPointSummation(): void
    {
        // 0.1 + 0.2 + 0.4 = 0.7000000000000001 and 0.7 * 3 = 2.0999999999999996: both are 70%.
        $score = 0.1 + 0.2 + 0.4;
        $maxScore = 1.0;
        $justBelow = 0.7 * 3;

        self::assertTrue($this->calculator->calculate($score, $maxScore, self::suppliedCaesura())->passed);
        self::assertTrue($this->calculator->calculate($justBelow, 3.0, self::suppliedCaesura())->passed);
    }

    public function testJustBelowSeventyPercentFailsEvenThoughTheRoundedGradeIsFivePointFive(): void
    {
        // 62.5 / 90 = 69.44% -> grade 5.45, which displays as 5.5, but the student did not reach 70%.
        $result = $this->calculator->calculate(62.5, 90.0, self::suppliedCaesura());

        self::assertEqualsWithDelta(5.45, $result->grade, 1e-9);
        self::assertSame(5.5, $result->roundedGrade());
        self::assertFalse($result->passed);
    }

    public function testUsesACustomCaesura(): void
    {
        // 0% -> 1.0, 55% -> 6.0 (pass), 100% -> 10.0
        $caesura = new Caesura([
            new CaesuraPoint(0.0, 1.0),
            new CaesuraPoint(55.0, 6.0),
            new CaesuraPoint(100.0, 10.0),
        ], 55.0);

        $atPass = $this->calculator->calculate(55.0, 100.0, $caesura);
        $quarter = $this->calculator->calculate(5.0, 20.0, $caesura);

        self::assertEqualsWithDelta(6.0, $atPass->grade, 1e-9);
        self::assertTrue($atPass->passed);
        self::assertEqualsWithDelta(1.0 + 25.0 / 55.0 * 5.0, $quarter->grade, 1e-9);
        self::assertFalse($quarter->passed);
    }

    public function testClampsToTheLastPointWhenTheCaesuraEndsBeforeOneHundredPercent(): void
    {
        $caesura = new Caesura([new CaesuraPoint(10.0, 1.0), new CaesuraPoint(80.0, 10.0)], 50.0);

        self::assertSame(10.0, $this->calculator->calculate(9.0, 10.0, $caesura)->grade);
    }

    public function testRejectsAZeroMaximumScore(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate(0.0, 0.0, self::suppliedCaesura());
    }

    public function testRejectsAScoreAboveTheMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate(91.0, 90.0, self::suppliedCaesura());
    }

    public function testRejectsANegativeScore(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->calculate(-1.0, 90.0, self::suppliedCaesura());
    }

    private static function suppliedCaesura(): Caesura
    {
        return new Caesura([
            new CaesuraPoint(20.0, 1.0),
            new CaesuraPoint(70.0, 5.5),
            new CaesuraPoint(100.0, 10.0),
        ], 70.0);
    }
}
