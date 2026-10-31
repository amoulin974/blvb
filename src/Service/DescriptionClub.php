<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Description du club (D63) : HTML écrit dans l'éditeur à barre réduite de la fiche, nettoyé par le sanitizer
 * `app.club` (titres h3 et h4, paragraphes, gras, italique, listes, liens http, https et mailto). Les responsables de
 * club ne sont pas admins : rien d'autre ne passe, ni image, ni attribut de style.
 */
final class DescriptionClub
{
    public function __construct(#[Target('app.club')] private HtmlSanitizerInterface $sanitizer)
    {
    }

    /**
     * HTML nettoyé ; null si la description est vide (paragraphe vide laissé par l'éditeur compris). Sans JavaScript, le
     * champ est une zone de texte : un texte brut y garde ses paragraphes et ses retours à la ligne.
     */
    public function nettoyer(?string $html): ?string
    {
        $propre = trim($this->sanitizer->sanitize((string) self::depuisTexte($html)));

        return trim(strip_tags($propre)) === '' ? null : $propre;
    }

    /**
     * Texte brut d'avant l'éditeur, converti une fois en HTML (migration Version20261031120000, commande des clubs) :
     * une ligne vide sépare deux paragraphes, un retour à la ligne devient un saut de ligne. Contient déjà des balises : inchangé.
     */
    public static function depuisTexte(?string $texte): ?string
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", (string) $texte));
        if ($texte === '') {
            return null;
        }
        if (self::estHtml($texte)) {
            return $texte;
        }
        $paragraphes = preg_split('/\n\s*\n/', $texte);

        return implode('', array_map(
            static fn (string $p) => '<p>'.str_replace("\n", '<br>', htmlspecialchars(trim($p), \ENT_NOQUOTES | \ENT_HTML5, 'UTF-8')).'</p>',
            $paragraphes,
        ));
    }

    /** Contient au moins une balise (un « <3 » ou un « a < b » de texte brut n'en est pas une) */
    private static function estHtml(string $texte): bool
    {
        return (bool) preg_match('#</?[a-z][a-z0-9]*(\s[^>]*)?/?>#i', $texte);
    }
}
