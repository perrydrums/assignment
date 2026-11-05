<?php

declare(strict_types=1);

namespace App\Application;

final readonly class QuestionResult
{
    public function __construct(
        public string $label,
        public float $maxScore,
        public float $pValue,
        /** null when undefined, e.g. when every student has the same score on the question */
        public ?float $ritValue,
        /** null when reliability cannot be computed without this question */
        public ?float $alphaIfRemoved = null,
    ) {
    }
}
