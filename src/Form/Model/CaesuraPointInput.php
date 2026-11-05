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
        #[Assert\NotNull(message: 'form.percentage.required')]
        #[Assert\Range(notInRangeMessage: 'form.percentage.range', min: 0, max: 100)]
        public ?float $percentage = null,
        #[Assert\NotNull(message: 'form.grade.required')]
        public ?float $grade = null,
    ) {
    }
}
