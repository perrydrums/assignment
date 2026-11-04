<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\CaesuraNotation;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\InvalidCaesuraException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CaesuraNotationTest extends TestCase
{
    public function testParsesPercentageGradePairs(): void
    {
        $caesura = (new CaesuraNotation())->parse('20:1.0, 70%:5.5,100:10', '70%');

        self::assertSame([20.0, 70.0, 100.0], array_map(static fn ($point): float => $point->percentage, $caesura->points));
        self::assertSame([1.0, 5.5, 10.0], array_map(static fn ($point): float => $point->grade, $caesura->points));
        self::assertSame(70.0, $caesura->passPercentage);
    }

    public function testTheDefaultIsTheCaesuraFromTheAssignment(): void
    {
        $caesura = (new CaesuraNotation())->parseDefault();

        self::assertSame([20.0, 70.0, 100.0], array_map(static fn ($point): float => $point->percentage, $caesura->points));
        self::assertSame([1.0, 5.5, 10.0], array_map(static fn ($point): float => $point->grade, $caesura->points));
        self::assertSame(70.0, $caesura->passPercentage);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supplyInvalidInput(): iterable
    {
        yield 'missing grade' => ['20:1.0,70', '70'];
        yield 'comma decimal separator' => ['20:1,0;70:5,5', '70'];
        yield 'text' => ['twenty:one,100:10', '70'];
        yield 'empty' => ['', '70'];
        yield 'non-numeric pass percentage' => ['20:1,100:10', 'seventy'];
        yield 'invalid caesura order' => ['70:5.5,20:1.0', '70'];
    }

    #[DataProvider('supplyInvalidInput')]
    public function testRejectsInvalidInput(string $points, string $passPercentage): void
    {
        $this->expectException(InvalidCaesuraException::class);

        (new CaesuraNotation())->parse($points, $passPercentage);
    }

    public function testFormatsPointsWithAtLeastOneDecimalForGrades(): void
    {
        $caesura = new Caesura([new CaesuraPoint(0.0, 1.0), new CaesuraPoint(62.5, 5.75), new CaesuraPoint(100.0, 10.0)], 62.5);
        $notation = new CaesuraNotation();

        self::assertSame('0:1.0,62.5:5.75,100:10.0', $notation->formatPoints($caesura));
        self::assertSame('62.5', $notation->formatPassPercentage($caesura));
    }

    public function testFormattingTheDefaultReproducesTheDefaultNotation(): void
    {
        $notation = new CaesuraNotation();
        $caesura = $notation->parseDefault();

        self::assertSame(CaesuraNotation::DEFAULT_POINTS, $notation->formatPoints($caesura));
        self::assertSame(CaesuraNotation::DEFAULT_PASS_PERCENTAGE, $notation->formatPassPercentage($caesura));
    }
}
