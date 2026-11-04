<?php

declare(strict_types=1);

namespace App\Domain\Exam;

final readonly class Question
{
    public function __construct(
        public string $label,
        public float $maxScore,
    ) {
        if ('' === trim($label)) {
            throw new InvalidExamException('A question label must not be empty.');
        }

        if (!is_finite($maxScore) || $maxScore <= 0.0) {
            throw new InvalidExamException(\sprintf('Question "%s" must have a maximum score greater than 0, got %s.', $label, $maxScore));
        }
    }

    public function isValidScore(float $score): bool
    {
        return is_finite($score) && $score >= 0.0 && $score <= $this->maxScore;
    }
}
