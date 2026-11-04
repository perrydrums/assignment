<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Grade\Caesura;

final readonly class ExamAnalysis
{
    /**
     * @param list<StudentResult>  $students
     * @param list<QuestionResult> $questions
     */
    public function __construct(
        public float $maxScore,
        public Caesura $caesura,
        public array $students,
        public array $questions,
    ) {
        if ([] === $students || [] === $questions) {
            throw new \InvalidArgumentException('An exam analysis needs at least one student and one question.');
        }
    }

    public function passedCount(): int
    {
        return \count(array_filter($this->students, static fn (StudentResult $student): bool => $student->grade->passed));
    }

    public function failedCount(): int
    {
        return \count($this->students) - $this->passedCount();
    }

    /**
     * Average of the unrounded grades.
     */
    public function averageGrade(): float
    {
        $grades = array_map(static fn (StudentResult $student): float => $student->grade->grade, $this->students);

        return array_sum($grades) / \count($grades);
    }
}
