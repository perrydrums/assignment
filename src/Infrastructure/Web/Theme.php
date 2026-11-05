<?php

declare(strict_types=1);

namespace App\Infrastructure\Web;

/**
 * Colour themes of the web UI: the certify360.com style and the brands of the Certify360 group.
 *
 * The colours live in assets/styles/themes.css under the same identifiers.
 */
enum Theme: string
{
    case Default = 'default';
    case Lindenhaeghe = 'lindenhaeghe';
    case CsOpleidingen = 'cs-opleidingen';
    case Nci = 'nci';
    case WwzAcademie = 'wwz-academie';
    case Foi = 'foi';
    case Learncare = 'learncare';
    case Examenadviesburo = 'examenadviesburo';
    case Coel = 'coel';

    public const string COOKIE = 'theme';

    public function brand(): string
    {
        return match ($this) {
            self::Default => 'Certify360',
            self::Lindenhaeghe => 'Lindenhaeghe',
            self::CsOpleidingen => 'CS Opleidingen',
            self::Nci => 'Nederlands Compliance Instituut',
            self::WwzAcademie => 'WWZ Academie',
            self::Foi => 'FOI',
            self::Learncare => 'Learncare',
            self::Examenadviesburo => 'Examenadviesburo',
            self::Coel => 'COEL toetscentra',
        };
    }

    /**
     * The brand's logo for dark backgrounds, as an AssetMapper path.
     */
    public function logo(): string
    {
        return match ($this) {
            self::Default => 'images/brands/certify360.png',
            self::Coel => 'images/brands/coel.png',
            default => 'images/brands/'.$this->value.'.svg',
        };
    }
}
