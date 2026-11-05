<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Application\CaesuraNotation;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\InvalidCaesuraException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Stores a grading scale in the same text notation as the console command.
 *
 * @implements DataTransformerInterface<Caesura|null, string>
 */
final readonly class CaesuraDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private CaesuraNotation $notation,
    ) {
    }

    public function transform(mixed $value): string
    {
        if (!$value instanceof Caesura) {
            return '';
        }

        return $this->notation->formatPoints($value).'|'.$this->notation->formatPassPercentage($value);
    }

    public function reverseTransform(mixed $value): Caesura
    {
        if (!\is_string($value) || !str_contains($value, '|')) {
            throw new TransformationFailedException('The grading scale is missing.');
        }

        [$points, $passPercentage] = explode('|', $value, 2);

        try {
            return $this->notation->parse($points, $passPercentage);
        } catch (InvalidCaesuraException $e) {
            throw new TransformationFailedException($e->getMessage(), 0, $e);
        }
    }
}
