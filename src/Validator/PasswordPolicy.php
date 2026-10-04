<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Règle de mot de passe commune à tous les formulaires du site
 * (inscription, changement de mot de passe, admin, réinitialisation par email).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const HELP_TEXT = 'Au moins 12 caractères, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial. Évitez les mots de passe déjà connus dans des fuites de données.';

    private const COMPLEXITY_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/u';

    /**
     * @param string[] $groups Groupes de validation à attacher aux contraintes (vide = groupe "Default")
     *
     * @return Constraint[]
     */
    public static function constraints(array $groups = []): array
    {
        $groups = $groups ?: null;

        return [
            new Length(
                min: self::MIN_LENGTH,
                minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                // max length allowed by Symfony for security reasons
                max: 4096,
                groups: $groups,
            ),
            new Regex(
                pattern: self::COMPLEXITY_PATTERN,
                message: 'Votre mot de passe doit contenir au moins une lettre minuscule, une lettre majuscule, un chiffre et un caractère spécial.',
                groups: $groups,
            ),
            new PasswordStrength(
                minScore: PasswordStrength::STRENGTH_WEAK,
                message: 'Votre mot de passe est trop simple. Essayez d\'utiliser des chiffres, des lettres majuscules et minuscules, et des caractères spéciaux.',
                groups: $groups,
            ),
            new NotCompromisedPassword(
                // ne bloque que les mots de passe retrouvés très fréquemment dans les fuites de données connues
                threshold: 100,
                // si l'API haveibeenpwned est indisponible, on n'empêche pas la création/modification du mot de passe
                skipOnError: true,
                message: 'Ce mot de passe a été compromis dans une fuite de données. Choisissez-en un autre.',
                groups: $groups,
            ),
        ];
    }
}
