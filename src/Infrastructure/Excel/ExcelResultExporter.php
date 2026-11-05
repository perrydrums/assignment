<?php

declare(strict_types=1);

namespace App\Infrastructure\Excel;

use App\Domain\Analytics\QuestionFlag;
use App\Domain\Grade\CaesuraPoint;
use App\Infrastructure\Web\ExamAnalysisView;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A workbook a teacher can hand to the student administration or the exam committee.
 */
final readonly class ExcelResultExporter
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function toXlsx(ExamAnalysisView $view): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $this->addSummary($spreadsheet, $view);
        $this->addGrades($spreadsheet, $view);
        $this->addQuestions($spreadsheet, $view);
        $spreadsheet->setActiveSheetIndex(0);

        $handle = fopen('php://temp', 'w+b');
        if (false === $handle) {
            throw new \RuntimeException('The export could not be created.');
        }
        (new Xlsx($spreadsheet))->save($handle);
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return false === $contents ? '' : $contents;
    }

    private function addSummary(Spreadsheet $spreadsheet, ExamAnalysisView $view): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sheetTitle('export.sheet.summary'));
        $analysis = $view->analysis();
        $rows = [
            [$this->t('export.file'), $view->fileName],
            [$this->t('export.students'), \count($analysis->students)],
            [$this->t('export.questions'), \count($analysis->questions)],
            [$this->t('export.max_score'), $analysis->maxScore],
            [$this->t('export.pass_percentage'), $analysis->caesura->passPercentage / 100],
            [$this->t('export.passed'), $analysis->passedCount()],
            [$this->t('export.failed'), $analysis->failedCount()],
            [$this->t('export.average_grade'), round($analysis->averageGrade(), 2)],
            [$this->t('export.reliability'), $analysis->reliability],
            [$this->t('export.grading_scale'), implode('; ', array_map(
                static fn (CaesuraPoint $point): string => $point->percentage.'% → '.$point->grade,
                $analysis->caesura->points,
            ))],
        ];
        $sheet->fromArray($rows);
        $sheet->getStyle('B5')->getNumberFormat()->setFormatCode('0%');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function addGrades(Spreadsheet $spreadsheet, ExamAnalysisView $view): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sheetTitle('export.sheet.grades'));
        $sheet->fromArray([[
            $this->t('export.student'),
            $this->t('export.score'),
            $this->t('export.max_score'),
            $this->t('export.percentage'),
            $this->t('export.grade'),
            $this->t('export.result'),
        ]]);

        $rows = [];
        foreach ($view->analysis()->students as $index => $student) {
            $rows[] = [
                $student->studentId,
                $student->totalScore,
                $view->analysis()->maxScore,
                $student->grade->percentage / 100,
                $student->grade->roundedGrade(),
                $this->t($student->grade->passed ? 'export.passed_value' : 'export.failed_value'),
            ];
            unset($index);
        }
        $sheet->fromArray($rows, null, 'A2');
        $last = \count($rows) + 1;
        $sheet->getStyle('D2:D'.$last)->getNumberFormat()->setFormatCode('0.0%');
        $sheet->getStyle('E2:E'.$last)->getNumberFormat()->setFormatCode('0.0');
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function addQuestions(Spreadsheet $spreadsheet, ExamAnalysisView $view): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->sheetTitle('export.sheet.questions'));
        $sheet->fromArray([[
            $this->t('export.number'),
            $this->t('export.question'),
            $this->t('export.max_score'),
            $this->t('export.difficulty'),
            $this->t('export.discrimination'),
            $this->t('export.alpha_if_removed'),
            $this->t('export.review'),
        ]]);

        $rows = [];
        foreach ($view->analysis()->questions as $index => $question) {
            $rows[] = [
                $index + 1,
                $question->label,
                $question->maxScore,
                $question->pValue,
                $question->ritValue,
                $question->alphaIfRemoved,
                implode(', ', array_map(fn (QuestionFlag $flag): string => $this->t('flag.'.$flag->value), $view->flags($question))),
            ];
        }
        $sheet->fromArray($rows, null, 'A2');
        $last = max(2, \count($rows) + 1);
        $sheet->getStyle('D2:F'.$last)->getNumberFormat()->setFormatCode('0.000');
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function sheetTitle(string $key): string
    {
        return mb_substr(str_replace(['\\', '/', '*', '?', ':', '[', ']'], '', $this->t($key)), 0, 31);
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key);
    }
}
