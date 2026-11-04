<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One editable caesura point in the upload form (mutable, because Symfony Form writes into it).
 */
final class CaesuraPointInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Enter a percentage.')]
        #[Assert\Range(notInRangeMessage: 'Use a percentage between {{ min }} and {{ max }}.', min: 0, max: 100)]
        public ?float $percentage = null,
        #[Assert\NotNull(message: 'Enter a grade.')]
        public ?float $grade = null,
    ) {
    }
}
