<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * An error that can be shown in the user's language. The exception message stays English for the CLI and logs.
 */
interface TranslatableError extends \Throwable
{
    public function messageKey(): string;

    /**
     * @return array<string, int|float|string>
     */
    public function messageParameters(): array;
}
