<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;

/**
 * Règles de passage par défaut, celles du schéma 2025 (docs/wizard-saison/decisions.md, D21) :
 *  - vers une phase de championnat : les nbMontee premiers de chaque poule montent d'un niveau, les nbDescente derniers
 *    descendent d'un niveau, les autres restent ; niveau voisin absent : l'équipe reste à son niveau ; son propre
 *    niveau absent : première poule de la phase suivante ;
 *  - vers une phase finale : chaque poule vers le tableau de son niveau, dans l'ordre du classement ; les montées et
 *    descentes sont enregistrées pour la saison suivante (D22).
 * Utilisées par la migration des saisons existantes, la création d'une saison et la clôture d'une phase sans règles
 * (D26). Le calcul (calculer) travaille sur des tableaux, pour servir aussi à la migration.
 */
final class ReglesParDefaut
{
    /**
     * Règles (non enregistrées) de la phase vers la phase suivante ; aucune s'il n'y a pas de phase suivante.
     * Les codes des phases et poules doivent être attribués (CodesCompetition::attribuer).
     *
     * @return list<RegleDePassage>
     */
    public static function pourPhase(Phase $phase, ?Phase $suivante): array
    {
        $poulesDestination = [];
        foreach ($suivante?->getPoules() ?? [] as $i => $poule) {
            $poulesDestination[$i] = $poule;
        }

        $regles = [];
        foreach (self::calculer(self::donnees($phase), $suivante === null ? null : self::donnees($suivante)) as $r) {
            $regles[] = (new RegleDePassage())
                ->setOrdre($r['ordre'])
                ->setSource($r['source'])
                ->setPouleDestination($r['poule'] === null ? null : $poulesDestination[$r['poule']])
                ->setNiveauSaisonSuivante($r['niveauSaisonSuivante']);
        }

        return $regles;
    }

    /**
     * @param array{code: string, finale: bool, poules: list<array{cle: int|string, code: string, niveau: ?int, montee: ?int, descente: ?int, nbEquipes?: ?int}>} $source
     * @param array{code: string, finale: bool, poules: list<array{cle: int|string, code: string, niveau: ?int, montee: ?int, descente: ?int}>}|null $suivante
     * @return list<array{ordre: int, source: string, poule: int|string|null, niveauSaisonSuivante: ?int}>
     */
    public static function calculer(array $source, ?array $suivante): array
    {
        if ($suivante === null || $suivante['poules'] === []) {
            return [];
        }

        $poules = $source['poules'];
        usort($poules, fn (array $a, array $b) => [$a['niveau'] ?? PHP_INT_MAX, $a['code']] <=> [$b['niveau'] ?? PHP_INT_MAX, $b['code']]);

        $destinations = [];
        foreach ($suivante['poules'] as $p) {
            $destinations[$p['niveau']] ??= $p['cle'];
        }
        $premiere = $suivante['poules'][0]['cle'];
        $niveauxSource = array_column($poules, 'niveau');

        $regles = [];
        $ajouter = function (string $source, int|string|null $poule, ?int $niveauSuivant) use (&$regles): void {
            $regles[] = ['ordre' => \count($regles) + 1, 'source' => $source, 'poule' => $poule, 'niveauSaisonSuivante' => $niveauSuivant];
        };

        foreach ($poules as $poule) {
            $prefixe = $source['code'].'.'.$poule['code'].'#';
            $niveau = $poule['niveau'];
            $montee = max(0, (int) $poule['montee']);
            $descente = max(0, (int) $poule['descente']);
            // Nombre d'équipes connu (clôture, migration) : une petite poule ne descend pas une équipe qui monte (la
            // montée l'emporte, comme le calcul d'avant les règles), pour des règles sans chevauchement
            if (($poule['nbEquipes'] ?? null) !== null) {
                $montee = min($montee, $poule['nbEquipes']);
                $descente = min($descente, $poule['nbEquipes'] - $montee);
            }
            $milieu = $prefixe.($montee + 1).'..'.(-$descente - 1);

            if ($suivante['finale']) {
                // Tableau de son niveau, dans l'ordre du classement…
                $ajouter($prefixe.'1..-1', $destinations[$niveau] ?? $premiere, null);
                // … et montées / descentes pour la saison suivante (niveau voisin s'il existe dans cette phase)
                if ($niveau !== null) {
                    if ($montee > 0) {
                        $ajouter($prefixe.'1..'.$montee, null, \in_array($niveau - 1, $niveauxSource, true) ? $niveau - 1 : $niveau);
                    }
                    $ajouter($milieu, null, $niveau);
                    if ($descente > 0) {
                        $ajouter($prefixe.(-$descente).'..-1', null, \in_array($niveau + 1, $niveauxSource, true) ? $niveau + 1 : $niveau);
                    }
                }
                continue;
            }

            $memeNiveau = $destinations[$niveau] ?? $premiere;
            if ($montee > 0) {
                $ajouter($prefixe.'1..'.$montee, $niveau !== null ? ($destinations[$niveau - 1] ?? $memeNiveau) : $memeNiveau, null);
            }
            $ajouter($milieu, $memeNiveau, null);
            if ($descente > 0) {
                $ajouter($prefixe.(-$descente).'..-1', $niveau !== null ? ($destinations[$niveau + 1] ?? $memeNiveau) : $memeNiveau, null);
            }
        }

        return $regles;
    }

    /** @return array{code: string, finale: bool, poules: list<array{cle: int, code: string, niveau: ?int, montee: ?int, descente: ?int, nbEquipes: ?int}>} */
    private static function donnees(Phase $phase): array
    {
        $poules = [];
        foreach ($phase->getPoules() as $i => $poule) {
            $nb = $poule->getEquipes()->count();
            $poules[] = ['cle' => $i, 'code' => (string) $poule->getCode(), 'niveau' => $poule->getNiveau(),
                'montee' => $poule->getNbMonteeDefaut(), 'descente' => $poule->getNbDescenteDefaut(), 'nbEquipes' => $nb > 0 ? $nb : null];
        }

        return ['code' => (string) $phase->getCode(), 'finale' => $phase->getType() === PhaseType::FINALE, 'poules' => $poules];
    }
}
