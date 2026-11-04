<?php

declare(strict_types=1);

namespace App\Domain\Exam;

final readonly class Student
{
    /**
     * @param list<float> $scores one score per question, in question order
     */
    public function __construct(
        public string $id,
        public array $scores,
    ) {
        if ('' === trim($id)) {
            throw new InvalidExamException('A student ID must not be empty.');
        }
    }

    public function totalScore(): float
    {
        return array_sum($this->scores);
    }
}
