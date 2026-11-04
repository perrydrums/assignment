<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Grade;

use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\InvalidCaesuraException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CaesuraTest extends TestCase
{
    public function testAcceptsAValidCaesura(): void
    {
        $caesura = new Caesura([new CaesuraPoint(20.0, 1.0), new CaesuraPoint(100.0, 10.0)], 70.0);

        self::assertCount(2, $caesura->points);
        self::assertSame(70.0, $caesura->passPercentage);
    }

    /**
     * @return iterable<string, array{list<array{float, float}>, float}>
     */
    public static function supplyInvalidCaesuras(): iterable
    {
        yield 'no points' => [[], 70.0];
        yield 'a single point' => [[[50.0, 5.0]], 70.0];
        yield 'duplicate percentage' => [[[20.0, 1.0], [20.0, 5.5], [100.0, 10.0]], 70.0];
        yield 'decreasing percentage' => [[[70.0, 5.5], [20.0, 1.0], [100.0, 10.0]], 70.0];
        yield 'decreasing grade' => [[[20.0, 5.0], [70.0, 4.0], [100.0, 10.0]], 70.0];
        yield 'percentage above 100' => [[[20.0, 1.0], [120.0, 10.0]], 70.0];
        yield 'negative percentage' => [[[-10.0, 1.0], [100.0, 10.0]], 70.0];
        yield 'zero pass percentage' => [[[20.0, 1.0], [100.0, 10.0]], 0.0];
        yield 'pass percentage above 100' => [[[20.0, 1.0], [100.0, 10.0]], 101.0];
    }

    /**
     * @param list<array{float, float}> $points
     */
    #[DataProvider('supplyInvalidCaesuras')]
    public function testRejectsInvalidConfigurations(array $points, float $passPercentage): void
    {
        $this->expectException(InvalidCaesuraException::class);

        new Caesura(
            array_map(static fn (array $point): CaesuraPoint => new CaesuraPoint($point[0], $point[1]), $points),
            $passPercentage,
        );
    }
}
