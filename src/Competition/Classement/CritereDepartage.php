<?php

namespace App\Competition\Classement;

/**
 * Critères de départage du classement d'une poule, appliqués dans l'ordre choisi pour la saison
 * (docs/wizard-saison/decisions.md, D16 et D17). L'ordre alphabétique est toujours appliqué en dernier, pour un
 * classement identique d'un calcul à l'autre.
 */
enum CritereDepartage: string
{
    case POINTS = 'points';
    case DIFFERENCE_SETS = 'difference_sets';
    case QUOTIENT_SETS = 'quotient_sets';
    /** Dernier match joué entre les deux équipes, comparaison deux à deux. */
    case CONFRONTATION_DIRECTE = 'confrontation_directe';
    /** Points, puis différence de sets, sur les seuls matchs joués entre les équipes à égalité. */
    case MINI_CLASSEMENT = 'mini_classement';
    case ALPHABETIQUE = 'alphabetique';

    public function libelle(): string
    {
        return match ($this) {
            self::POINTS => 'Points',
            self::DIFFERENCE_SETS => 'Différence de sets',
            self::QUOTIENT_SETS => 'Quotient de sets',
            self::CONFRONTATION_DIRECTE => 'Confrontation directe',
            self::MINI_CLASSEMENT => 'Mini-classement entre les équipes à égalité',
            self::ALPHABETIQUE => 'Ordre alphabétique',
        };
    }

    /** Les critères qui se calculent par équipe (une valeur à comparer), et non par paire d'équipes. */
    public function estUneValeur(): bool
    {
        return \in_array($this, [self::POINTS, self::DIFFERENCE_SETS, self::QUOTIENT_SETS], true);
    }

    /**
     * Départage du championnat avant le paramétrage (octobre 2026), valeur par défaut d'une saison.
     *
     * @return list<self>
     */
    public static function parDefaut(): array
    {
        return [self::POINTS, self::DIFFERENCE_SETS, self::CONFRONTATION_DIRECTE, self::ALPHABETIQUE];
    }

    /**
     * Liste enregistrée (codes) → critères ; codes inconnus ignorés, doublons retirés, ordre alphabétique ajouté
     * en dernier s'il manque. Liste vide ou absente : départage par défaut.
     *
     * @param list<string>|null $codes
     * @return list<self>
     */
    public static function depuisCodes(?array $codes): array
    {
        return self::normaliser(array_filter(array_map(
            fn ($code) => \is_string($code) ? self::tryFrom($code) : null,
            $codes ?? [],
        )));
    }

    /**
     * Doublons retirés, ordre alphabétique placé en dernier ; liste vide : départage par défaut.
     *
     * @param iterable<self> $criteres
     * @return list<self>
     */
    public static function normaliser(iterable $criteres): array
    {
        $liste = [];
        foreach ($criteres as $critere) {
            if ($critere !== self::ALPHABETIQUE && !\in_array($critere, $liste, true)) {
                $liste[] = $critere;
            }
        }
        if ($liste === []) {
            return self::parDefaut();
        }
        $liste[] = self::ALPHABETIQUE;

        return $liste;
    }
}
