<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

/**
 * One plain-language observation. The message is a translation key.
 */
final readonly class Insight
{
    /**
     * @param array<string, int|float|string> $parameters
     * @param string                          $target     "students", "questions" or "question"
     */
    public function __construct(
        public string $messageKey,
        public array $parameters,
        public string $target,
        public ?int $questionNumber = null,
    ) {
    }
}
