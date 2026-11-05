<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Domain\Exam\Exam;
use App\Domain\Exam\InvalidExamException;
use App\Domain\Exam\Question;
use App\Domain\Exam\Student;

/**
 * The exam embedded in the results page, so a teacher can adjust the analysis without uploading again.
 *
 * The payload is not trusted: it is validated by the same rules as an imported workbook.
 */
final class ExamDataCodec
{
    public const int MAX_STUDENTS = 5000;
    public const int MAX_QUESTIONS = 500;

    public function encode(Exam $exam): string
    {
        $payload = [
            'questions' => array_map(static fn (Question $question): array => [
                'label' => $question->label,
                'maxScore' => $question->maxScore,
            ], $exam->questions),
            'students' => array_map(static fn (Student $student): array => [
                'id' => $student->id,
                'scores' => $student->scores,
            ], $exam->students),
        ];

        return (string) json_encode($payload, \JSON_THROW_ON_ERROR);
    }

    public function decode(string $json): Exam
    {
        try {
            $payload = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidExamDataException('The embedded exam data is not valid JSON.', 'exam_data.invalid_json');
        }

        if (!\is_array($payload) || !isset($payload['questions'], $payload['students']) || !\is_array($payload['questions']) || !\is_array($payload['students'])) {
            throw new InvalidExamDataException('The embedded exam data has an unexpected structure.', 'exam_data.structure');
        }

        if (\count($payload['questions']) > self::MAX_QUESTIONS || \count($payload['students']) > self::MAX_STUDENTS) {
            throw new InvalidExamDataException(\sprintf('An analysis is limited to %d questions and %d students.', self::MAX_QUESTIONS, self::MAX_STUDENTS), 'exam_data.too_large', ['questions' => self::MAX_QUESTIONS, 'students' => self::MAX_STUDENTS]);
        }

        try {
            $questions = [];
            foreach ($payload['questions'] as $question) {
                if (!\is_array($question) || !isset($question['label'], $question['maxScore']) || !\is_string($question['label']) || !is_numeric($question['maxScore'])) {
                    throw new InvalidExamDataException('A question in the embedded exam data is incomplete.', 'exam_data.question');
                }
                $questions[] = new Question($question['label'], (float) $question['maxScore']);
            }

            $students = [];
            foreach ($payload['students'] as $student) {
                if (!\is_array($student) || !isset($student['id'], $student['scores']) || !\is_string($student['id']) || !\is_array($student['scores'])) {
                    throw new InvalidExamDataException('A student in the embedded exam data is incomplete.', 'exam_data.student');
                }
                $scores = [];
                foreach ($student['scores'] as $score) {
                    if (!is_numeric($score)) {
                        throw new InvalidExamDataException('A score in the embedded exam data is not a number.', 'exam_data.score');
                    }
                    $scores[] = (float) $score;
                }
                $students[] = new Student($student['id'], $scores);
            }

            return new Exam($questions, $students);
        } catch (InvalidExamException $e) {
            throw new InvalidExamDataException($e->getMessage(), 'exam_data.rejected', [], $e);
        }
    }
}
