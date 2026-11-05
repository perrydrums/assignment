<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * The theme chosen in the switcher, remembered in a cookie so every page renders in it straight away.
 */
final readonly class ThemeResolver
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction('current_theme')]
    public function current(): Theme
    {
        $value = $this->requestStack->getCurrentRequest()?->cookies->getString(Theme::COOKIE) ?? '';

        return Theme::tryFrom($value) ?? Theme::Default;
    }

    /**
     * @return list<Theme>
     */
    #[AsTwigFunction('themes')]
    public function all(): array
    {
        return Theme::cases();
    }
}
