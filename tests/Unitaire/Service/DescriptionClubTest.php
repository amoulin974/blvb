<?php

namespace App\Tests\Unitaire\Service;

use App\Service\DescriptionClub;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** CLU-08 : description d'avant l'éditeur convertie en HTML (migration Version20261031120000, D63). */
final class DescriptionClubTest extends TestCase
{
    #[TestDox('CLU-08 : une ligne vide sépare deux paragraphes, un retour à la ligne devient un saut de ligne, le texte est échappé')]
    public function test_depuis_texte(): void
    {
        self::assertSame(
            '<p>Section volley &amp; loisir.<br>Siège à Anglet.</p><p>Plus de 18 ans, niveau &gt; 2 &lt;3</p>',
            DescriptionClub::depuisTexte("Section volley & loisir.\r\nSiège à Anglet.\n\n  \nPlus de 18 ans, niveau > 2 <3\n"),
        );
    }

    #[TestDox('CLU-08 : texte vide → null ; texte qui contient déjà des balises → inchangé (migration rejouable)')]
    public function test_vide_et_deja_converti(): void
    {
        self::assertNull(DescriptionClub::depuisTexte("  \n "));
        self::assertNull(DescriptionClub::depuisTexte(null));
        self::assertSame('<p>Déjà</p>', DescriptionClub::depuisTexte('<p>Déjà</p>'));
    }
}
