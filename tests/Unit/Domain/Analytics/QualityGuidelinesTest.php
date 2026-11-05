<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Analytics;

use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Analytics\QuestionFlag;
use PHPUnit\Framework\TestCase;

final class QualityGuidelinesTest extends TestCase
{
    public function testFlagsTheDefaultBoundaries(): void
    {
        $guidelines = new QualityGuidelines();

        self::assertSame([], $guidelines->flagsFor(0.30, 0.20));
        self::assertSame([], $guidelines->flagsFor(0.90, 0.20));
        self::assertSame([QuestionFlag::VeryDifficult], $guidelines->flagsFor(0.29, 0.50));
        self::assertSame([QuestionFlag::VeryEasy], $guidelines->flagsFor(0.91, 0.50));
        self::assertSame([QuestionFlag::WeakDiscrimination], $guidelines->flagsFor(0.50, 0.19));
    }

    public function testAlwaysFlagsANegativeOrMissingDiscrimination(): void
    {
        $guidelines = new QualityGuidelines();

        self::assertSame(
            [QuestionFlag::NegativeDiscrimination, QuestionFlag::VeryDifficult],
            $guidelines->flagsFor(0.10, -0.01),
        );
        self::assertSame([QuestionFlag::NoVariation], $guidelines->flagsFor(0.50, null));
    }

    public function testRejectsGuidelinesThatAreNotBetweenZeroAndOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new QualityGuidelines(0.0, 0.90, 0.20);
    }
}
