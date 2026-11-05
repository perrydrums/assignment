<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Domain\Exam\Exam;
use App\Infrastructure\Web\ExamDataCodec;
use App\Infrastructure\Web\InvalidExamDataException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<Exam|null, string>
 */
final readonly class ExamDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private ExamDataCodec $codec,
    ) {
    }

    public function transform(mixed $value): string
    {
        return $value instanceof Exam ? $this->codec->encode($value) : '';
    }

    public function reverseTransform(mixed $value): Exam
    {
        if (!\is_string($value) || '' === $value) {
            throw new TransformationFailedException('The exam data is missing.');
        }

        try {
            return $this->codec->decode($value);
        } catch (InvalidExamDataException $e) {
            throw new TransformationFailedException($e->getMessage(), 0, $e);
        }
    }
}
