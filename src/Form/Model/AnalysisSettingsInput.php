<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Application\AnalysisSettings;
use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Exam\Exam;
use App\Domain\Exam\QuestionAdjustment;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\InvalidCaesuraException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The settings form on the results page. The exam itself travels with the form and is never stored.
 */
final class AnalysisSettingsInput
{
    public ?Exam $exam = null;

    #[Assert\NotBlank]
    public string $fileName = '';

    public ?Caesura $baselineCaesura = null;

    #[Assert\Valid]
    public CaesuraInput $caesura;

    /** @var array<int, string> */
    public array $adjustments = [];

    #[Assert\Range(min: 0.05, max: 0.95)]
    public float $veryDifficultBelow = 0.30;

    #[Assert\Range(min: 0.05, max: 0.95)]
    public float $veryEasyAbove = 0.90;

    #[Assert\Range(min: 0.05, max: 0.95)]
    public float $weakDiscriminationBelow = 0.20;

    public function __construct()
    {
        $this->caesura = new CaesuraInput();
    }

    public static function from(Exam $exam, string $fileName, AnalysisSettings $settings): self
    {
        $input = new self();
        $input->exam = $exam;
        $input->fileName = $fileName;
        $input->baselineCaesura = $settings->baselineCaesura;
        $input->caesura = CaesuraInput::fromCaesura($settings->caesura);
        $input->adjustments = array_map(static fn (QuestionAdjustment $adjustment): string => $adjustment->value, $settings->adjustments);
        $input->veryDifficultBelow = $settings->guidelines->veryDifficultBelow;
        $input->veryEasyAbove = $settings->guidelines->veryEasyAbove;
        $input->weakDiscriminationBelow = $settings->guidelines->weakDiscriminationBelow;

        return $input;
    }

    public function toSettings(): AnalysisSettings
    {
        if (!$this->exam instanceof Exam || !$this->baselineCaesura instanceof Caesura) {
            throw new \LogicException('The settings form has no exam.');
        }

        return new AnalysisSettings(
            $this->caesura->toCaesura(),
            $this->baselineCaesura,
            $this->resolvedAdjustments(\count($this->exam->questions)),
            new QualityGuidelines($this->veryDifficultBelow, $this->veryEasyAbove, $this->weakDiscriminationBelow),
        );
    }

    #[Assert\Callback]
    public function validateSettings(ExecutionContextInterface $context): void
    {
        if (!$this->exam instanceof Exam) {
            return;
        }

        if (\count($this->adjustments) !== \count($this->exam->questions)) {
            $context->buildViolation('form.adjustments.count')->atPath('adjustments')->addViolation();
        }

        try {
            new QualityGuidelines($this->veryDifficultBelow, $this->veryEasyAbove, $this->weakDiscriminationBelow);
        } catch (\InvalidArgumentException) {
            $context->buildViolation('form.guidelines.order')->atPath('veryDifficultBelow')->addViolation();
        }

        try {
            $this->caesura->toCaesura();
        } catch (InvalidCaesuraException $e) {
            $context->buildViolation($e->messageKey())
                ->setParameters(self::placeholders($e->messageParameters()))
                ->setTranslationDomain('errors')
                ->atPath('caesura')
                ->addViolation();
        }
    }

    /**
     * The scale and question changes to display when the submitted settings are not usable yet.
     */
    public function fallbackSettings(): ?AnalysisSettings
    {
        if (!$this->exam instanceof Exam || !$this->baselineCaesura instanceof Caesura) {
            return null;
        }

        return new AnalysisSettings(
            $this->baselineCaesura,
            $this->baselineCaesura,
            array_fill(0, \count($this->exam->questions), QuestionAdjustment::Keep),
            new QualityGuidelines(),
        );
    }

    /**
     * @return list<QuestionAdjustment>
     */
    private function resolvedAdjustments(int $questionCount): array
    {
        $adjustments = [];
        for ($index = 0; $index < $questionCount; ++$index) {
            $adjustments[] = QuestionAdjustment::tryFrom($this->adjustments[$index] ?? '') ?? QuestionAdjustment::Keep;
        }

        return $adjustments;
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
