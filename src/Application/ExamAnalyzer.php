<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Analytics\CronbachAlpha;
use App\Domain\Analytics\PearsonCorrelation;
use App\Domain\Analytics\PValueCalculator;
use App\Domain\Exam\Exam;
use App\Domain\Grade\Caesura;
use App\Domain\Grade\GradeCalculator;

final readonly class ExamAnalyzer
{
    public function __construct(
        private GradeCalculator $gradeCalculator,
        private PValueCalculator $pValueCalculator,
        private PearsonCorrelation $correlation,
        private CronbachAlpha $reliability,
    ) {
    }

    public function analyze(Exam $exam, Caesura $caesura): ExamAnalysis
    {
        $maxScore = $exam->maxScore();

        $students = [];
        foreach ($exam->students as $student) {
            $totalScore = $student->totalScore();
            $students[] = new StudentResult(
                $student->id,
                $totalScore,
                $this->gradeCalculator->calculate($totalScore, $maxScore, $caesura),
            );
        }

        // rit: correlation between the question score and the total exam score, the total
        // including the question itself (as defined in the assignment).
        $totalScores = $exam->totalScores();
        $itemScores = [];
        $questions = [];
        foreach ($exam->questions as $index => $question) {
            $scores = $exam->scoresForQuestion($index);
            $itemScores[] = $scores;
            $questions[] = new QuestionResult(
                $question->label,
                $question->maxScore,
                $this->pValueCalculator->calculate($scores, $question->maxScore),
                $this->correlation->calculate($scores, $totalScores),
            );
        }

        $reliability = $this->reliability->calculate($itemScores);
        foreach ($questions as $index => $question) {
            $questions[$index] = new QuestionResult(
                $question->label,
                $question->maxScore,
                $question->pValue,
                $question->ritValue,
                $reliability['alphaIfRemoved'][$index],
            );
        }

        return new ExamAnalysis($maxScore, $caesura, $students, $questions, $reliability['alpha']);
    }
}
