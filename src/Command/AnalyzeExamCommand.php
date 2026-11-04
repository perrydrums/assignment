<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\CaesuraNotation;
use App\Application\ExamAnalyzer;
use App\Domain\Grade\InvalidCaesuraException;
use App\Infrastructure\Console\ExamResultPresenter;
use App\Infrastructure\Excel\ExcelResultImporter;
use App\Infrastructure\Excel\InvalidWorkbookException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:analyze-exam',
    description: 'Calculates grades, pass/fail, P\'-values and rit-values for exam results in an Excel workbook',
)]
final readonly class AnalyzeExamCommand
{
    public function __construct(
        private ExcelResultImporter $importer,
        private CaesuraNotation $caesuraNotation,
        private ExamAnalyzer $analyzer,
        private ExamResultPresenter $presenter,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to the Excel workbook (.xlsx or .xls)')]
        string $file,
        #[Option(description: 'Caesura as comma-separated "percentage:grade" points, interpolated linearly')]
        string $caesura = CaesuraNotation::DEFAULT_POINTS,
        #[Option(description: 'Minimum percentage of the maximum score needed to pass')]
        string $passPercentage = CaesuraNotation::DEFAULT_PASS_PERCENTAGE,
    ): int {
        try {
            $parsedCaesura = $this->caesuraNotation->parse($caesura, $passPercentage);
        } catch (InvalidCaesuraException $e) {
            $io->error('Invalid caesura: '.$e->getMessage());

            return Command::INVALID;
        }

        try {
            $exam = $this->importer->import($file);
        } catch (InvalidWorkbookException $e) {
            $io->error([\sprintf('The exam results in "%s" could not be imported.', $file), $e->getMessage()]);

            return Command::FAILURE;
        }

        $this->presenter->present($this->analyzer->analyze($exam, $parsedCaesura), $io);

        return Command::SUCCESS;
    }
}
