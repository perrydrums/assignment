<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Grade\GradeResult;

final readonly class StudentResult
{
    public function __construct(
        public string $studentId,
        public float $totalScore,
        public GradeResult $grade,
    ) {
    }
}
