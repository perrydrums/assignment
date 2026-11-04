<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Application\CaesuraNotation;
use App\Application\ExamAnalysis;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\GradeCalculator;
use App\Infrastructure\Console\ExamResultPresenter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ExamAnalysisViewFactory
{
    public function __construct(
        private GradeCalculator $gradeCalculator,
        private CaesuraNotation $caesuraNotation,
        private ExamResultPresenter $consolePresenter,
    ) {
    }

    public function create(ExamAnalysis $analysis, string $fileName): ExamAnalysisView
    {
        $caesura = $analysis->caesura;

        return new ExamAnalysisView(
            $analysis,
            $fileName,
            $this->gradeCalculator->calculate($caesura->passPercentage, 100.0, $caesura)->roundedGrade(),
            $this->cliCommand($caesura, $fileName),
            $this->consoleReport($analysis),
        );
    }

    /**
     * The `app:analyze-exam` invocation that produces the same analysis.
     */
    private function cliCommand(Caesura $caesura, string $fileName): string
    {
        $command = 'php bin/console app:analyze-exam '.$this->shellArgument($fileName);

        $points = $this->caesuraNotation->formatPoints($caesura);
        if (CaesuraNotation::DEFAULT_POINTS !== $points) {
            $command .= ' --caesura='.$this->shellArgument($points);
        }

        $passPercentage = $this->caesuraNotation->formatPassPercentage($caesura);
        if (CaesuraNotation::DEFAULT_PASS_PERCENTAGE !== $passPercentage) {
            $command .= ' --pass-percentage='.$passPercentage;
        }

        return $command;
    }

    /**
     * The report exactly as the console command prints it.
     */
    private function consoleReport(ExamAnalysis $analysis): string
    {
        $output = new BufferedOutput();
        $this->consolePresenter->present($analysis, new SymfonyStyle(new ArrayInput([]), $output));

        return $output->fetch();
    }

    private function shellArgument(string $value): string
    {
        return 1 === preg_match('#^[\w.,:/-]+$#', $value) ? $value : escapeshellarg($value);
    }
}
