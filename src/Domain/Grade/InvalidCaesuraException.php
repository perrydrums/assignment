<?php

declare(strict_types=1);

namespace App\Domain\Grade;

use App\Domain\TranslatableError;

final class InvalidCaesuraException extends \InvalidArgumentException implements TranslatableError
{
    /**
     * @param array<string, int|float|string> $messageParameters
     */
    public function __construct(
        string $message,
        private readonly string $messageKey = 'caesura.invalid',
        private readonly array $messageParameters = [],
    ) {
        parent::__construct($message);
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    public function messageParameters(): array
    {
        return $this->messageParameters;
    }

    public static function tooFewPoints(): self
    {
        return new self('A caesura needs at least two reference points.', 'caesura.too_few_points');
    }

    public static function percentageOutOfRange(float $percentage): self
    {
        return new self(\sprintf('A caesura percentage must be between 0 and 100, got %s.', self::number($percentage)), 'caesura.percentage_range', ['value' => $percentage]);
    }

    public static function gradeNotFinite(): self
    {
        return new self('A caesura grade must be a finite number.', 'caesura.grade_not_finite');
    }

    public static function percentagesNotIncreasing(float $current, float $previous): self
    {
        return new self(\sprintf('Caesura percentages must be strictly increasing, got %s%% after %s%%.', self::number($current), self::number($previous)), 'caesura.percentages_not_increasing', ['current' => $current, 'previous' => $previous]);
    }

    public static function gradesDecrease(float $current, float $previous): self
    {
        return new self(\sprintf('Caesura grades must not decrease, got %s after %s.', self::number($current), self::number($previous)), 'caesura.grades_decrease', ['current' => $current, 'previous' => $previous]);
    }

    public static function passPercentageOutOfRange(float $percentage): self
    {
        return new self(\sprintf('The pass percentage must be greater than 0 and at most 100, got %s.', self::number($percentage)), 'caesura.pass_percentage_range', ['value' => $percentage]);
    }

    public static function incompletePoint(): self
    {
        return new self('Every caesura point needs a percentage and a grade.', 'caesura.incomplete_point');
    }

    public static function missingPassPercentage(): self
    {
        return new self('The pass percentage is missing.', 'caesura.pass_percentage_missing');
    }

    public static function invalidPoint(string $point): self
    {
        return new self(\sprintf('Invalid caesura point "%s"; expected "percentage:grade", e.g. "70:5.5".', $point), 'caesura.invalid_point', ['point' => $point]);
    }

    public static function invalidNumber(string $description, string $value): self
    {
        return new self(\sprintf('Invalid %s "%s"; expected a number (use a dot as decimal separator).', $description, $value), 'caesura.invalid_number', ['description' => $description, 'value' => $value]);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.6F', $value), '0'), '.');
    }
}
