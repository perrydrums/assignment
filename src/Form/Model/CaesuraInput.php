<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\InvalidCaesuraException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * An editable grading scale. Points may be entered in any order and are sorted before validation.
 */
final class CaesuraInput
{
    /** @var array<int, CaesuraPointInput> */
    #[Assert\Valid]
    public array $points = [];

    #[Assert\NotNull(message: 'form.pass_percentage.required')]
    #[Assert\Positive(message: 'form.pass_percentage.positive')]
    #[Assert\LessThanOrEqual(value: 100, message: 'form.pass_percentage.max')]
    public ?float $passPercentage = null;

    public static function fromCaesura(Caesura $caesura): self
    {
        $input = new self();
        foreach ($caesura->points as $point) {
            $input->points[] = new CaesuraPointInput($point->percentage, $point->grade);
        }
        $input->passPercentage = $caesura->passPercentage;

        return $input;
    }

    public function toCaesura(): Caesura
    {
        $points = [];
        foreach ($this->points as $point) {
            if (null === $point->percentage || null === $point->grade) {
                throw InvalidCaesuraException::incompletePoint();
            }
            $points[] = new CaesuraPoint($point->percentage, $point->grade);
        }
        usort($points, static fn (CaesuraPoint $left, CaesuraPoint $right): int => $left->percentage <=> $right->percentage);

        if (null === $this->passPercentage) {
            throw InvalidCaesuraException::missingPassPercentage();
        }

        return new Caesura($points, $this->passPercentage);
    }

    #[Assert\Callback]
    public function validateScale(ExecutionContextInterface $context): void
    {
        $incomplete = null === $this->passPercentage
            || array_any($this->points, static fn (CaesuraPointInput $point): bool => null === $point->percentage || null === $point->grade);
        if ($incomplete) {
            return;
        }

        try {
            $this->toCaesura();
        } catch (InvalidCaesuraException $e) {
            $context->buildViolation($e->messageKey())
                ->setParameters(self::placeholders($e->messageParameters()))
                ->setTranslationDomain('errors')
                ->atPath('points')
                ->addViolation();
        }
    }

    /**
     * @param array<string, int|float|string> $parameters
     *
     * @return array<string, string>
     */
    private static function placeholders(array $parameters): array
    {
        $placeholders = [];
        foreach ($parameters as $name => $value) {
            $placeholders['%'.$name.'%'] = (string) $value;
        }

        return $placeholders;
    }
}
