<?php

declare(strict_types=1);

namespace App\Infrastructure\Excel;

use App\Domain\TranslatableError;

final class InvalidWorkbookException extends \RuntimeException implements TranslatableError
{
    /**
     * @param array<string, int|float|string> $messageParameters
     */
    public function __construct(
        string $message,
        private readonly string $messageKey = 'workbook.generic',
        private readonly array $messageParameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    public function messageParameters(): array
    {
        return $this->messageParameters;
    }
}
