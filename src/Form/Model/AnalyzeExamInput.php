<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Domain\Grade\Caesura;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The upload form: a workbook and the grading scale to apply to it.
 */
final class AnalyzeExamInput
{
    #[Assert\NotNull(message: 'form.workbook.required')]
    #[Assert\File(
        maxSize: '10M',
        maxSizeMessage: 'form.workbook.max_size',
        extensions: [
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        ],
        extensionsMessage: 'form.workbook.extension',
        mimeTypesMessage: 'form.workbook.mime',
    )]
    public ?UploadedFile $workbook = null;

    #[Assert\Valid]
    public CaesuraInput $caesura;

    public function __construct()
    {
        $this->caesura = new CaesuraInput();
    }

    public static function withCaesura(Caesura $caesura): self
    {
        $input = new self();
        $input->caesura = CaesuraInput::fromCaesura($caesura);

        return $input;
    }

    public function toCaesura(): Caesura
    {
        return $this->caesura->toCaesura();
    }
}
