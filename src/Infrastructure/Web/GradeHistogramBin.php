<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

/**
 * Number of students whose rounded grade lies in [lowerGrade, upperGrade); the last bin includes its upper grade.
 */
final readonly class GradeHistogramBin
{
    public function __construct(
        public float $lowerGrade,
        public float $upperGrade,
        public int $passed,
        public int $failed,
        public bool $includesUpperGrade,
    ) {
    }

    public function total(): int
    {
        return $this->passed + $this->failed;
    }

    /**
     * The range of one-decimal grades in this bin, e.g. "5.5–5.9", or "9.5–10.0" for the last bin.
     */
    public function label(): string
    {
        $highestGrade = $this->includesUpperGrade ? $this->upperGrade : $this->upperGrade - 0.1;
        if ($highestGrade - $this->lowerGrade < 0.05) {
            return number_format($this->lowerGrade, 1);
        }

        return number_format($this->lowerGrade, 1).'–'.number_format($highestGrade, 1);
    }
}
