<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Indicative boundaries for reviewing questions. They are rules of thumb, not a verdict on a question.
 */
final readonly class QualityGuidelines
{
    public function __construct(
        public float $veryDifficultBelow = 0.30,
        public float $veryEasyAbove = 0.90,
        public float $weakDiscriminationBelow = 0.20,
    ) {
        foreach ([
            'very difficult' => $veryDifficultBelow,
            'very easy' => $veryEasyAbove,
            'weak discrimination' => $weakDiscriminationBelow,
        ] as $name => $value) {
            if (!is_finite($value) || $value <= 0.0 || $value >= 1.0) {
                throw new \InvalidArgumentException(\sprintf('The "%s" guideline must be between 0 and 1, got %s.', $name, $value));
            }
        }

        if ($veryDifficultBelow >= $veryEasyAbove) {
            throw new \InvalidArgumentException('The "very difficult" guideline must be below the "very easy" guideline.');
        }
    }

    /**
     * @return list<QuestionFlag>
     */
    public function flagsFor(float $pValue, ?float $ritValue): array
    {
        $flags = [];
        if (null === $ritValue) {
            $flags[] = QuestionFlag::NoVariation;
        } elseif ($ritValue < 0.0) {
            $flags[] = QuestionFlag::NegativeDiscrimination;
        } elseif ($ritValue < $this->weakDiscriminationBelow) {
            $flags[] = QuestionFlag::WeakDiscrimination;
        }

        if ($pValue < $this->veryDifficultBelow) {
            $flags[] = QuestionFlag::VeryDifficult;
        } elseif ($pValue > $this->veryEasyAbove) {
            $flags[] = QuestionFlag::VeryEasy;
        }

        return $flags;
    }
}
