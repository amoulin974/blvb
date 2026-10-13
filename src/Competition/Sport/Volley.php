<?php

namespace App\Competition\Sport;

/**
 * Volley-ball, règlement du championnat BLVB (rubrique « Décompte des points », règles 1D et 1R).
 *
 * Score en sets : équipe qui reçoit, équipe qui se déplace ; -1 = forfait de cette équipe.
 *  - Match complet : une équipe à 3 sets, l'autre de 0 à 2 ; points selon le barème : 3-0 / 3-1 → victoire forte,
 *    3-2 → victoire faible, 2-3 → défaite forte, 1-3 / 0-3 → défaite faible.
 *  - Match interrompu (règle 1R : coupure de courant, fermeture du gymnase, blessure obligeant à finir à 4) :
 *    la partie n'est pas rejouée ; scores 2-1, 2-2, 1-1, 1-0 (dans un sens ou dans l'autre) ; points fixés par
 *    le règlement : 2-1 → 2 et 1 point, 2-2 → 1 point chacun, 1-1 et 1-0 → 0 point. Pour 2-2, le set-average
 *    n'est pas modifié (les sets ne sont pas comptés).
 *  - Forfait : 3 sets contre -1 ; le gagnant reçoit les points d'une victoire forte, l'équipe forfait les points
 *    de forfait du barème (« -1 point pour le perdant »).
 */
final class Volley implements RegleSport
{
    public const CODE = 'volley';

    /** Points du règlement pour un match interrompu, du point de vue de l'équipe qui a gagné le premier nombre de sets. */
    private const INTERROMPUS = ['2-1' => 2, '1-2' => 1, '2-2' => 1, '1-1' => 0, '1-0' => 0, '0-1' => 0];

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Volley-ball';
    }

    public function estValide(int $reception, int $deplacement): bool
    {
        return self::estComplet($reception, $deplacement) || self::estInterrompu($reception, $deplacement) || self::estForfait($reception, $deplacement);
    }

    public function messageScoreInvalide(): string
    {
        return 'Score impossible : une équipe doit avoir 3 sets et l\'autre de 0 à 2, ou, pour un match interrompu (règle 1R), 2-1, 2-2, 1-1 ou 1-0.';
    }

    public function scoreForfait(bool $receptionForfait): array
    {
        return $receptionForfait ? [-1, 3] : [3, -1];
    }

    public function points(?int $pour, ?int $contre, Bareme $bareme): int
    {
        if ($pour === null || $contre === null) {
            return 0; // match non joué
        }

        if ($pour === -1) {
            return $bareme->forfait; // équipe déclarée forfait
        }
        if ($contre === -1) {
            return $bareme->victoireForte; // victoire par forfait de l'adversaire
        }

        // Match interrompu (règle 1R) : points fixés par le règlement
        if (($interrompu = self::INTERROMPUS[$pour.'-'.$contre] ?? null) !== null) {
            return $interrompu;
        }

        return match ([$pour, $contre]) {
            [3, 0], [3, 1] => $bareme->victoireForte,
            [3, 2] => $bareme->victoireFaible,
            [2, 3] => $bareme->defaiteForte,
            [1, 3], [0, 3] => $bareme->defaiteFaible,
            default => 0, // score hors règlement (refusé à la saisie depuis octobre 2026)
        };
    }

    public function statistiques(?int $pour, ?int $contre): array
    {
        if ($pour === 2 && $contre === 2) {
            return [0, 0]; // match interrompu à 2-2 : set-average inchangé (règle 1R)
        }

        // Non joué : aucun set ; forfait (-1) : aucun set pour l'équipe forfait, ni perdu par son adversaire
        return [max($pour ?? 0, 0), max($contre ?? 0, 0)];
    }

    public static function estComplet(int $reception, int $deplacement): bool
    {
        return min($reception, $deplacement) >= 0 && max($reception, $deplacement) === 3 && min($reception, $deplacement) <= 2;
    }

    public static function estInterrompu(int $reception, int $deplacement): bool
    {
        return isset(self::INTERROMPUS[$reception.'-'.$deplacement]);
    }

    public static function estForfait(int $reception, int $deplacement): bool
    {
        return ($reception === -1 && $deplacement === 3) || ($reception === 3 && $deplacement === -1);
    }
}
