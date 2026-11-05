<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use App\Domain\TranslatableError;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders a domain or import error in the language of the page. The CLI keeps the English exception message.
 */
final readonly class ErrorTranslator
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function translate(\Throwable $error): string
    {
        if ($error instanceof TranslatableError) {
            $parameters = [];
            foreach ($error->messageParameters() as $name => $value) {
                $parameters['%'.$name.'%'] = $value;
            }

            return $this->translator->trans($error->messageKey(), $parameters, 'errors');
        }

        return $error->getMessage();
    }
}
