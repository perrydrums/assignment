<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Domain\Grade\InvalidCaesuraException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Data of the upload form: the workbook plus an editable caesura (mutable, because Symfony Form writes into it).
 */
final class AnalyzeExamInput
{
    #[Assert\NotNull(message: 'Choose an Excel workbook (.xlsx or .xls) to analyse.')]
    #[Assert\File(
        maxSize: '10M',
        extensions: [
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        ],
        extensionsMessage: 'Only Excel workbooks ({{ extensions }}) can be analysed, not "{{ extension }}" files.',
        mimeTypesMessage: 'This file does not look like an Excel workbook.',
    )]
    public ?UploadedFile $workbook = null;

    /** @var array<int, CaesuraPointInput> */
    #[Assert\Valid]
    public array $caesuraPoints = [];

    #[Assert\NotNull(message: 'Enter the pass percentage.')]
    #[Assert\Positive(message: 'The pass percentage must be greater than 0.')]
    #[Assert\LessThanOrEqual(100, message: 'The pass percentage can be at most 100.')]
    public ?float $passPercentage = null;

    public static function withCaesura(Caesura $caesura): self
    {
        $input = new self();
        foreach ($caesura->points as $point) {
            $input->caesuraPoints[] = new CaesuraPointInput($point->percentage, $point->grade);
        }
        $input->passPercentage = $caesura->passPercentage;

        return $input;
    }

    /**
     * Points may be entered in any order; they are sorted by percentage.
     *
     * @throws InvalidCaesuraException
     */
    public function toCaesura(): Caesura
    {
        $points = [];
        foreach ($this->caesuraPoints as $point) {
            if (null === $point->percentage || null === $point->grade) {
                throw new InvalidCaesuraException('Every caesura point needs a percentage and a grade.');
            }
            $points[] = new CaesuraPoint($point->percentage, $point->grade);
        }
        usort($points, static fn (CaesuraPoint $a, CaesuraPoint $b): int => $a->percentage <=> $b->percentage);

        if (null === $this->passPercentage) {
            throw new InvalidCaesuraException('The pass percentage is missing.');
        }

        return new Caesura($points, $this->passPercentage);
    }

    /**
     * Applies the domain rules of a caesura (e.g. increasing percentages) once the individual fields are filled in.
     */
    #[Assert\Callback]
    public function validateCaesura(ExecutionContextInterface $context): void
    {
        $isIncomplete = null === $this->passPercentage
            || array_any($this->caesuraPoints, static fn (CaesuraPointInput $point): bool => null === $point->percentage || null === $point->grade);
        if ($isIncomplete) {
            return;
        }

        try {
            $this->toCaesura();
        } catch (InvalidCaesuraException $e) {
            $context->buildViolation($e->getMessage())->atPath('caesuraPoints')->addViolation();
        }
    }
}
