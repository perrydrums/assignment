<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Application\ExamAnalysis;
use App\Application\QuestionResult;
use App\Application\StudentResult;

/**
 * Presentation model of an analysis for the web UI: summary figures and chart geometry.
 *
 * Chart coordinates are normalised to 0–1; the templates scale them to the size of their SVG.
 */
final readonly class ExamAnalysisView
{
    private const int MAX_HISTOGRAM_BINS = 20;
    private const array NICE_FACTORS = [1.0, 2.0, 2.5, 5.0];
    private const float EPSILON = 1e-9;

    public function __construct(
        public ExamAnalysis $analysis,
        public string $fileName,
        public float $passGrade,
        public string $cliCommand,
        public string $consoleOutput,
    ) {
    }

    public function passRate(): float
    {
        return $this->analysis->passedCount() / \count($this->analysis->students);
    }

    public function averageScore(): float
    {
        $scores = array_map(static fn (StudentResult $student): float => $student->totalScore, $this->analysis->students);

        return array_sum($scores) / \count($scores);
    }

    public function averagePValue(): float
    {
        $pValues = array_map(static fn (QuestionResult $question): float => $question->pValue, $this->analysis->questions);

        return array_sum($pValues) / \count($pValues);
    }

    /**
     * Distribution of the rounded grades over the grade range of the caesura, split into passed and failed.
     *
     * @return non-empty-list<GradeHistogramBin>
     */
    public function gradeHistogram(): array
    {
        $points = $this->analysis->caesura->points;
        $lowestGrade = $points[0]->grade;
        $highestGrade = $points[\count($points) - 1]->grade;

        $width = $this->niceStep(($highestGrade - $lowestGrade) / self::MAX_HISTOGRAM_BINS, 0.1);
        $start = floor($lowestGrade / $width + self::EPSILON) * $width;
        $binCount = max(1, (int) ceil(($highestGrade - $start) / $width - self::EPSILON));

        $passed = array_fill(0, $binCount, 0);
        $failed = array_fill(0, $binCount, 0);
        foreach ($this->analysis->students as $student) {
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
            $bins[] = new GradeHistogramBin(
                round($start + $i * $width, 6),
                round($start + ($i + 1) * $width, 6),
                $passed[$i],
                $failed[$i],
                $i === $binCount - 1,
            );
        }

        return $bins;
    }

    /**
     * Gridline values for the histogram's student-count axis; the last one is the top of the axis.
     *
     * @return non-empty-list<int>
     */
    public function histogramTicks(): array
    {
        $largestBin = max(array_map(static fn (GradeHistogramBin $bin): int => $bin->total(), $this->gradeHistogram()));
        $step = (int) $this->niceStep(max(1, $largestBin) / 4, 1.0, integersOnly: true);

        return range(0, (int) (ceil($largestBin / $step) * $step), $step);
    }

    /**
     * Questions with a defined rit-value, positioned by P'-value (x) and rit-value (y).
     *
     * @return list<ItemMapPoint>
     */
    public function itemMap(): array
    {
        $ticks = $this->ritTicks();
        $lowest = $ticks[0];
        $highest = $ticks[\count($ticks) - 1];

        $points = [];
        foreach ($this->analysis->questions as $index => $question) {
            if (null !== $question->ritValue) {
                $points[] = new ItemMapPoint($index + 1, $question, $question->pValue, ($question->ritValue - $lowest) / ($highest - $lowest));
            }
        }

        return $points;
    }

    /**
     * Gridline values for the rit axis of the item map. Always includes 0, which separates
     * questions that discriminate positively from those that do not.
     *
     * @return non-empty-list<float>
     */
    public function ritTicks(): array
    {
        $ritValues = array_values(array_filter(array_map(static fn (QuestionResult $question): ?float => $question->ritValue, $this->analysis->questions), static fn (?float $rit): bool => null !== $rit));
        $lowest = min([0.0, ...$ritValues]);
        $highest = max([0.0, ...$ritValues]);

        $step = $highest - $lowest > 1.0 ? 0.25 : 0.1;
        $first = floor($lowest / $step + self::EPSILON);
        $last = max($first + 1, ceil($highest / $step - self::EPSILON));

        return array_map(static fn (float $tick): float => round($tick * $step, 2), range($first, $last));
    }

    /**
     * @return array<int, QuestionResult> questions without a rit-value, keyed by question number
     */
    public function questionsWithoutRitValue(): array
    {
        $questions = [];
        foreach ($this->analysis->questions as $index => $question) {
            if (null === $question->ritValue) {
                $questions[$index + 1] = $question;
            }
        }

        return $questions;
    }

    /**
     * The smallest "nice" step (1, 2, 2.5 or 5 times a power of ten, starting at $smallest) of at least $minimum.
     */
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
