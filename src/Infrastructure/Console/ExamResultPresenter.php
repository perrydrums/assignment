<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\ExamAnalysis;
use App\Application\QuestionResult;
use App\Application\StudentResult;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\CaesuraPoint;
use App\Infrastructure\ScoreFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ExamResultPresenter
{
    private const string UNDEFINED = 'n/a';

    public function __construct(
        private ScoreFormatter $scoreFormatter,
    ) {
    }

    public function present(ExamAnalysis $analysis, SymfonyStyle $io): void
    {
        $io->title('Exam analysis');
        $io->definitionList(
            ['Students' => (string) \count($analysis->students)],
            ['Questions' => (string) \count($analysis->questions)],
            ['Maximum score' => $this->formatScore($analysis->maxScore)],
            ['Caesura' => $this->formatCaesura($analysis->caesura)],
            ['Passed' => (string) $analysis->passedCount()],
            ['Failed' => (string) $analysis->failedCount()],
            ['Average grade' => number_format($analysis->averageGrade(), 1)],
        );

        $io->section('Students');
        $io->table(
            ['Student', 'Score', 'Percentage', 'Grade', 'Result'],
            array_map(fn (StudentResult $student): array => [
                $student->studentId,
                $this->formatScore($student->totalScore),
                number_format($student->grade->percentage, 1).'%',
                number_format($student->grade->roundedGrade(), 1),
                $student->grade->passed ? 'passed' : 'FAILED',
            ], $analysis->students),
        );

        $io->section('Questions');
        $io->table(
            ['Question', 'Max score', "P'-value", 'rit-value'],
            array_map(fn (QuestionResult $question): array => [
                $question->label,
                $this->formatScore($question->maxScore),
                number_format($question->pValue, 3),
                null === $question->ritValue ? self::UNDEFINED : number_format($question->ritValue, 3),
            ], $analysis->questions),
        );

        $io->note([
            "P'-value: average score on the question divided by its maximum score (0 = nobody scored, 1 = everybody had full marks).",
            'rit-value: Pearson correlation between the question score and the total exam score (including that question).',
            \sprintf('"%s": the rit-value is undefined because all students had the same score on the question.', self::UNDEFINED),
        ]);
    }

    private function formatCaesura(Caesura $caesura): string
    {
        $points = array_map(
            fn (CaesuraPoint $point): string => \sprintf('%s%% → %s', $this->formatScore($point->percentage), number_format($point->grade, 1)),
            $caesura->points,
        );

        return \sprintf('%s (pass from %s%%)', implode(', ', $points), $this->formatScore($caesura->passPercentage));
    }

    private function formatScore(float $score): string
    {
        return $this->scoreFormatter->format($score);
    }
}
