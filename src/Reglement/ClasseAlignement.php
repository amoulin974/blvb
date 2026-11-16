<?php

namespace App\Reglement;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Attribut `class` du règlement (sanitizer `app.reglement`) : seules les classes de mise en forme de l'éditeur sont
 * gardées (alignement d'un paragraphe, d'un titre ou d'une image) ; toute autre classe est retirée, pour qu'un texte
 * collé ou écrit dans l'onglet HTML ne puisse pas reprendre les styles du site.
 */
final class ClasseAlignement implements AttributeSanitizerInterface
{
    public const CLASSES = ['aligne-centre', 'aligne-droite', 'aligne-justifie'];

    public function getSupportedElements(): ?array
    {
        return ['p', 'h2', 'h3', 'h4', 'img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $gardees = array_intersect(preg_split('/\s+/', trim($value)) ?: [], self::CLASSES);

        return $gardees === [] ? null : implode(' ', array_unique($gardees));
    }
}
