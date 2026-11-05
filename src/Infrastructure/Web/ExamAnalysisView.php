<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Application\AnalysisOutcome;
use App\Application\ExamAnalysis;
use App\Application\QuestionResult;
use App\Application\StudentResult;
use App\Domain\Analytics\QualityGuidelines;
use App\Domain\Analytics\QuestionFlag;
use App\Domain\Analytics\ScoreGroupComparison;

/**
 * Presentation model of an analysis for the web UI: summary figures, chart geometry and observations.
 *
 * Chart coordinates are normalised to 0–1; the templates scale them to the size of their SVG.
 */
final readonly class ExamAnalysisView
{
    private const int MAX_HISTOGRAM_BINS = 20;
    private const int MAX_INSIGHTS = 6;
    private const array NICE_FACTORS = [1.0, 2.0, 2.5, 5.0];
    private const float EPSILON = 1e-9;

    /**
     * @param list<int>         $originalIndexes original question index for each question in the current analysis
     * @param list<list<float>> $questionScores  student scores per displayed question
     */
    public function __construct(
        public AnalysisOutcome $outcome,
        public string $fileName,
        public float $passGrade,
        public QualityGuidelines $guidelines,
        public array $originalIndexes,
        public array $questionScores,
    ) {
    }

    public function analysis(): ExamAnalysis
    {
        return $this->outcome->analysis;
    }

    public function passRate(): float
    {
        return $this->analysis()->passedCount() / \count($this->analysis()->students);
    }

    public function averageScore(): float
    {
        $scores = array_map(static fn (StudentResult $student): float => $student->totalScore, $this->analysis()->students);

        return array_sum($scores) / \count($scores);
    }

    public function averagePValue(): float
    {
        $pValues = array_map(static fn (QuestionResult $question): float => $question->pValue, $this->analysis()->questions);

        return array_sum($pValues) / \count($pValues);
    }

    /**
     * good, acceptable, low, or unknown.
     */
    public function reliabilityBand(): string
    {
        $alpha = $this->analysis()->reliability;

        return match (true) {
            null === $alpha => 'unknown',
            $alpha >= 0.80 => 'good',
            $alpha >= 0.70 => 'acceptable',
            default => 'low',
        };
    }

    /**
     * @return list<QuestionFlag>
     */
    public function flags(QuestionResult $question): array
    {
        return $this->guidelines->flagsFor($question->pValue, $question->ritValue);
    }

    public function reviewCount(): int
    {
        return \count(array_filter($this->analysis()->questions, fn (QuestionResult $question): bool => [] !== $this->flags($question)));
    }

    /**
     * @return list<Insight>
     */
    public function insights(): array
    {
        $insights = [];
        $delta = $this->outcome->passCountDelta();
        if (0 !== $delta) {
            $insights[] = new Insight($delta > 0 ? 'insight.passes_gained' : 'insight.passes_lost', ['count' => abs($delta)], 'students');
        }

        $band = $this->reliabilityBand();
        if ('low' === $band || 'good' === $band) {
            $insights[] = new Insight('insight.reliability_'.$band, ['alpha' => (string) round((float) $this->analysis()->reliability, 2)], 'questions');
        }

        $negative = $this->questionNumbersWith(QuestionFlag::NegativeDiscrimination);
        if ([] !== $negative) {
            $insights[] = new Insight('insight.negative_rit', ['questions' => implode(', ', $negative), 'count' => \count($negative)], 'question', $negative[0]);
        }

        $difficult = $this->questionNumbersWith(QuestionFlag::VeryDifficult);
        if (\count($difficult) >= 2) {
            $insights[] = new Insight('insight.many_difficult', ['count' => \count($difficult)], 'questions');
        }

        $easy = $this->questionNumbersWith(QuestionFlag::VeryEasy);
        if (\count($easy) >= 2) {
            $insights[] = new Insight('insight.many_easy', ['count' => \count($easy)], 'questions');
        }

        if ($this->passRate() < 0.5) {
            $insights[] = new Insight('insight.low_pass_rate', ['rate' => (string) round($this->passRate() * 100, 1)], 'students');
        }

        return \array_slice($insights, 0, self::MAX_INSIGHTS);
    }

    /**
     * Key figures, score distribution and the high/low group comparison, per displayed question.
     *
     * @return list<array{pValue: float, ritValue: ?float, alphaIfRemoved: ?float, distribution: list<array{score: float, count: int}>, group: ?array{size: int, high: float, low: float}}>
     */
    public function questionDetails(): array
    {
        $details = [];
        foreach ($this->analysis()->questions as $question) {
            $comparison = ScoreGroupComparison::fromScores(
                $this->scoresMatchingQuestion($question),
                array_map(static fn (StudentResult $student): float => $student->totalScore, $this->analysis()->students),
            );
            $details[] = [
                'pValue' => $question->pValue,
                'ritValue' => $question->ritValue,
                'alphaIfRemoved' => $question->alphaIfRemoved,
                'distribution' => $this->distribution($question),
                'group' => null === $comparison ? null : [
                    'size' => $comparison->groupSize,
                    'high' => $comparison->highGroupAverage,
                    'low' => $comparison->lowGroupAverage,
                ],
            ];
        }

        return $details;
    }

    /**
     * @return non-empty-list<GradeHistogramBin>
     */
    public function gradeHistogram(): array
    {
        $points = $this->analysis()->caesura->points;
        $lowestGrade = $points[0]->grade;
        $highestGrade = $points[\count($points) - 1]->grade;

        $width = $this->niceStep(($highestGrade - $lowestGrade) / self::MAX_HISTOGRAM_BINS, 0.1);
        $start = floor($lowestGrade / $width + self::EPSILON) * $width;
        $binCount = max(1, (int) ceil(($highestGrade - $start) / $width - self::EPSILON));

        $passed = array_fill(0, $binCount, 0);
        $failed = array_fill(0, $binCount, 0);
        foreach ($this->analysis()->students as $student) {
            $index = (int) floor(($student->grade->roundedGrade() - $start) / $width + self::EPSILON);
            $index = max(0, min($binCount - 1, $index));
            if ($student->grade->passed) {
                ++$passed[$index];
            } else {
                ++$failed[$index];
            }
        }

        $bins = [];
        for ($i = 0; $i < $binCount; ++$i) {
            $bins[] = new GradeHistogramBin(round($start + $i * $width, 6), round($start + ($i + 1) * $width, 6), $passed[$i], $failed[$i], $i === $binCount - 1);
        }

        return $bins;
    }

    /**
     * @return non-empty-list<int>
     */
    public function histogramTicks(): array
    {
        $largestBin = max(array_map(static fn (GradeHistogramBin $bin): int => $bin->total(), $this->gradeHistogram()));
        $step = (int) $this->niceStep(max(1, $largestBin) / 4, 1.0, integersOnly: true);

        return range(0, (int) (ceil($largestBin / $step) * $step), $step);
    }

    /**
     * @return list<ItemMapPoint>
     */
    public function itemMap(): array
    {
        $ticks = $this->ritTicks();
        $lowest = $ticks[0];
        $highest = $ticks[\count($ticks) - 1];

        $points = [];
        foreach ($this->analysis()->questions as $index => $question) {
            if (null !== $question->ritValue) {
                $points[] = new ItemMapPoint($index + 1, $question, $question->pValue, ($question->ritValue - $lowest) / ($highest - $lowest));
            }
        }

        return $points;
    }

    /**
     * @return non-empty-list<float>
     */
    public function ritTicks(): array
    {
        $ritValues = array_values(array_filter(
            array_map(static fn (QuestionResult $question): ?float => $question->ritValue, $this->analysis()->questions),
            static fn (?float $rit): bool => null !== $rit,
        ));
        $lowest = min([0.0, ...$ritValues]);
        $highest = max([0.0, ...$ritValues]);
        $step = $highest - $lowest > 1.0 ? 0.25 : 0.1;
        $first = floor($lowest / $step + self::EPSILON);
        $last = max($first + 1, ceil($highest / $step - self::EPSILON));

        return array_map(static fn (float $tick): float => round($tick * $step, 2), range($first, $last));
    }

    /**
     * @return array<int, QuestionResult>
     */
    public function questionsWithoutRitValue(): array
    {
        $questions = [];
        foreach ($this->analysis()->questions as $index => $question) {
            if (null === $question->ritValue) {
                $questions[$index + 1] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return list<float>
     */
    private function scoresMatchingQuestion(QuestionResult $question): array
    {
        foreach ($this->analysis()->questions as $index => $candidate) {
            if ($candidate === $question) {
                return $this->questionScores[$index] ?? [];
            }
        }

        return [];
    }

    /**
     * @return list<array{score: float, count: int}>
     */
    private function distribution(QuestionResult $question): array
    {
        $counts = [];
        foreach ($this->scoresMatchingQuestion($question) as $score) {
            $key = number_format($score, 2, '.', '');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts, \SORT_NUMERIC);

        $distribution = [];
        foreach ($counts as $score => $count) {
            $distribution[] = ['score' => (float) $score, 'count' => $count];
        }

        return $distribution;
    }

    /**
     * @return list<int> 1-based question numbers
     */
    private function questionNumbersWith(QuestionFlag $flag): array
    {
        $numbers = [];
        foreach ($this->analysis()->questions as $index => $question) {
            if (\in_array($flag, $this->flags($question), true)) {
                $numbers[] = $index + 1;
            }
        }

        return $numbers;
    }

    private function niceStep(float $minimum, float $smallest, bool $integersOnly = false): float
    {
        for ($magnitude = $smallest;; $magnitude *= 10) {
            foreach (self::NICE_FACTORS as $factor) {
                $step = $factor * $magnitude;
                if ($step >= $minimum - self::EPSILON && (!$integersOnly || floor($step) === $step)) {
                    return $step;
                }
            }
        }
    }
}
