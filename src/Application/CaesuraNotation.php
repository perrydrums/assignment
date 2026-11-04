<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\InvalidCaesuraException;

/**
 * Text notation of a caesura, shared by the CLI options and the web UI: comma-separated
 * "percentage:grade" points such as "20:1.0,70:5.5,100:10.0", plus a separate pass percentage.
 */
final class CaesuraNotation
{
    /** The caesura from the assignment: 20% → 1.0, 70% → 5.5 and 100% → 10.0, passing from 70%. */
    public const string DEFAULT_POINTS = '20:1.0,70:5.5,100:10.0';
    public const string DEFAULT_PASS_PERCENTAGE = '70';

    public function parse(string $points, string $passPercentage): Caesura
    {
        $caesuraPoints = [];
        foreach (explode(',', $points) as $pair) {
            $parts = explode(':', trim($pair));
            if (2 !== \count($parts)) {
                throw new InvalidCaesuraException(\sprintf('Invalid caesura point "%s"; expected "percentage:grade", e.g. "70:5.5".', trim($pair)));
            }

            $caesuraPoints[] = new CaesuraPoint(
                $this->parseNumber(rtrim($parts[0], '% '), 'caesura percentage'),
                $this->parseNumber($parts[1], 'caesura grade'),
            );
        }

        return new Caesura($caesuraPoints, $this->parseNumber(rtrim($passPercentage, '% '), 'pass percentage'));
    }

    public function parseDefault(): Caesura
    {
        return $this->parse(self::DEFAULT_POINTS, self::DEFAULT_PASS_PERCENTAGE);
    }

    public function formatPoints(Caesura $caesura): string
    {
        return implode(',', array_map(
            fn (CaesuraPoint $point): string => $this->formatNumber($point->percentage).':'.$this->formatNumber($point->grade, minimumDecimals: 1),
            $caesura->points,
        ));
    }

    public function formatPassPercentage(Caesura $caesura): string
    {
        return $this->formatNumber($caesura->passPercentage);
    }

    private function parseNumber(string $value, string $description): float
    {
        $value = trim($value);
        if (!is_numeric($value)) {
            throw new InvalidCaesuraException(\sprintf('Invalid %s "%s"; expected a number (use a dot as decimal separator).', $description, $value));
        }

        return (float) $value;
    }

    private function formatNumber(float $value, int $minimumDecimals = 0): string
    {
        $formatted = rtrim(rtrim(\sprintf('%.6F', $value), '0'), '.');
        if ($minimumDecimals > 0 && !str_contains($formatted, '.')) {
            $formatted .= '.'.str_repeat('0', $minimumDecimals);
        }

        return $formatted;
    }
}
