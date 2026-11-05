<?php

declare(strict_types=1);

namespace App\Domain\Exam;

/**
 * The results of one exam: its questions and every student's score per question.
 */
final readonly class Exam
{
    /**
     * @param list<Question> $questions
     * @param list<Student>  $students
     */
    public function __construct(
        public array $questions,
        public array $students,
    ) {
        if ([] === $questions) {
            throw new InvalidExamException('An exam must contain at least one question.');
        }

        if ([] === $students) {
            throw new InvalidExamException('An exam must contain at least one student.');
        }

        $seenIds = [];
        foreach ($students as $student) {
            if (isset($seenIds[$student->id])) {
                throw new InvalidExamException(\sprintf('Student ID "%s" occurs more than once.', $student->id));
            }
            $seenIds[$student->id] = true;

            $this->assertValidScores($student);
        }
    }

    /**
     * A copy of this exam with questions removed or awarded in full.
     *
     * @param list<QuestionAdjustment> $adjustments one per question, in question order
     */
    public function adjusted(array $adjustments): self
    {
        if (\count($adjustments) !== \count($this->questions)) {
            throw new InvalidExamException(\sprintf('Expected an adjustment for each of the %d questions, got %d.', \count($this->questions), \count($adjustments)));
        }

        $kept = [];
        foreach ($this->questions as $index => $question) {
            if (QuestionAdjustment::Remove !== $adjustments[$index]) {
                $kept[] = ['question' => $question, 'index' => $index, 'fullMarks' => QuestionAdjustment::FullMarks === $adjustments[$index]];
            }
        }

        if ([] === $kept) {
            throw new InvalidExamException('At least one question must remain in the analysis.');
        }

        $students = [];
        foreach ($this->students as $student) {
            $scores = [];
            foreach ($kept as $item) {
                $scores[] = $item['fullMarks'] ? $item['question']->maxScore : $student->scores[$item['index']];
            }
            $students[] = new Student($student->id, $scores);
        }

        return new self(array_map(static fn (array $item): Question => $item['question'], $kept), $students);
    }

    public function maxScore(): float
    {
        return array_sum(array_map(static fn (Question $question): float => $question->maxScore, $this->questions));
    }

    /**
     * @return list<float> the score of every student on the given question, in student order
     */
    public function scoresForQuestion(int $questionIndex): array
    {
        if (!isset($this->questions[$questionIndex])) {
            throw new \OutOfRangeException(\sprintf('Question index %d does not exist.', $questionIndex));
        }

        return array_map(static fn (Student $student): float => $student->scores[$questionIndex], $this->students);
    }

    /**
     * @return list<float> the total score of every student, in student order
     */
    public function totalScores(): array
    {
        return array_map(static fn (Student $student): float => $student->totalScore(), $this->students);
    }

    private function assertValidScores(Student $student): void
    {
        if (\count($student->scores) !== \count($this->questions)) {
            throw new InvalidExamException(\sprintf('Student "%s" has %d scores, expected one for each of the %d questions.', $student->id, \count($student->scores), \count($this->questions)));
        }

        foreach ($this->questions as $index => $question) {
            $score = $student->scores[$index];
            if (!$question->isValidScore($score)) {
                throw new InvalidExamException(\sprintf('Student "%s" has score %s on question "%s"; expected a value between 0 and %s.', $student->id, $score, $question->label, $question->maxScore));
            }
        }
    }
}
