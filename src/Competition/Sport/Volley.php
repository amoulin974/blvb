<?php

namespace App\Competition\Sport;

/**
 * Volley-ball, règlement du championnat BLVB (rubrique « Décompte des points », règles 1D et 1R).
 *
 * Score en sets : équipe qui reçoit, équipe qui se déplace ; -1 = forfait de cette équipe.
 * Le format de match donne le nombre de sets à gagner : 3 (par défaut) ou 2 (phase en format court, D48).
 *
 * En 3 sets gagnants :
 *  - Match complet : une équipe à 3 sets, l'autre de 0 à 2 ; points selon le barème : 3-0 / 3-1 → victoire forte,
 *    3-2 → victoire faible, 2-3 → défaite forte, 1-3 / 0-3 → défaite faible.
 *  - Match interrompu (règle 1R : coupure de courant, fermeture du gymnase, blessure obligeant à finir à 4) :
 *    la partie n'est pas rejouée ; scores 2-1, 2-2, 1-1, 1-0 (dans un sens ou dans l'autre) ; points fixés par
 *    le règlement : 2-1 → 2 et 1 point, 2-2 → 1 point chacun, 1-1 et 1-0 → 0 point. Pour 2-2, le set-average
 *    n'est pas modifié (les sets ne sont pas comptés).
 *  - Forfait : 3 sets contre -1 ; le gagnant reçoit les points d'une victoire forte, l'équipe forfait les points
 *    de forfait du barème (« -1 point pour le perdant »).
 *
 * En 2 sets gagnants :
 *  - Match complet : 2-0 → victoire forte, 2-1 → victoire faible, 1-2 → défaite forte, 0-2 → défaite faible.
 *  - Match interrompu : 1-1, 1-0 ou 0-1, sans point pour les deux équipes.
 *  - Forfait : 2 sets contre -1.
 */
final class Volley implements RegleSport
{
    public const CODE = 'volley';

    /** Points du règlement pour un match interrompu, du point de vue de l'équipe qui a gagné le premier nombre de sets. */
    private const INTERROMPUS = ['2-1' => 2, '1-2' => 1, '2-2' => 1, '1-1' => 0, '1-0' => 0, '0-1' => 0];
    private const INTERROMPUS_COURT = ['1-1' => 0, '1-0' => 0, '0-1' => 0];

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Volley-ball';
    }

    public function estValide(int $reception, int $deplacement, int $setsGagnants = 3): bool
    {
        return self::estComplet($reception, $deplacement, $setsGagnants)
            || self::estInterrompu($reception, $deplacement, $setsGagnants)
            || self::estForfait($reception, $deplacement, $setsGagnants);
    }

    public function messageScoreInvalide(int $setsGagnants = 3): string
    {
        if (self::court($setsGagnants)) {
            return 'Score impossible : une équipe doit avoir 2 sets et l\'autre 0 ou 1, ou, pour un match interrompu, 1-1 ou 1-0.';
        }

        return 'Score impossible : une équipe doit avoir 3 sets et l\'autre de 0 à 2, ou, pour un match interrompu (règle 1R), 2-1, 2-2, 1-1 ou 1-0.';
    }

    public function scoreForfait(bool $receptionForfait, int $setsGagnants = 3): array
    {
        $sets = self::court($setsGagnants) ? 2 : 3;

        return $receptionForfait ? [-1, $sets] : [$sets, -1];
    }

    public function points(?int $pour, ?int $contre, Bareme $bareme, int $setsGagnants = 3): int
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
        if (($interrompu = self::interrompus($setsGagnants)[$pour.'-'.$contre] ?? null) !== null) {
            return $interrompu;
        }

        // Match complet : la victoire est forte si l'adversaire a pris au plus (sets gagnants − 2) sets, faible sinon
        $sets = self::court($setsGagnants) ? 2 : 3;
        if ($pour === $sets && $contre >= 0 && $contre < $sets) {
            return $contre <= $sets - 2 ? $bareme->victoireForte : $bareme->victoireFaible;
        }
        if ($contre === $sets && $pour >= 0 && $pour < $sets) {
            return $pour === $sets - 1 ? $bareme->defaiteForte : $bareme->defaiteFaible;
        }

        return 0; // score hors règlement (refusé à la saisie depuis octobre 2026)
    }

    public function statistiques(?int $pour, ?int $contre, int $setsGagnants = 3): array
    {
        if (!self::court($setsGagnants) && $pour === 2 && $contre === 2) {
            return [0, 0]; // match interrompu à 2-2 : set-average inchangé (règle 1R)
        }

        // Non joué : aucun set ; forfait (-1) : aucun set pour l'équipe forfait, ni perdu par son adversaire
        return [max($pour ?? 0, 0), max($contre ?? 0, 0)];
    }

    public static function estComplet(int $reception, int $deplacement, int $setsGagnants = 3): bool
    {
        $sets = self::court($setsGagnants) ? 2 : 3;

        return min($reception, $deplacement) >= 0 && max($reception, $deplacement) === $sets && min($reception, $deplacement) <= $sets - 1;
    }

    public static function estInterrompu(int $reception, int $deplacement, int $setsGagnants = 3): bool
    {
        return isset(self::interrompus($setsGagnants)[$reception.'-'.$deplacement]);
    }

    public static function estForfait(int $reception, int $deplacement, int $setsGagnants = 3): bool
    {
        $sets = self::court($setsGagnants) ? 2 : 3;

        return ($reception === -1 && $deplacement === $sets) || ($reception === $sets && $deplacement === -1);
    }

    private static function court(int $setsGagnants): bool
    {
        return $setsGagnants === 2;
    }

    /**
     * Points d'un match interrompu (règle 1R) selon le score, pour l'équipe qui a le premier nombre de sets ;
     * affichés par le barème du règlement (App\Reglement\Bloc\BlocBareme).
     *
     * @return array<string, int>
     */
    public static function pointsInterrompus(int $setsGagnants): array
    {
        return self::interrompus($setsGagnants);
    }

    /** @return array<string, int> */
    private static function interrompus(int $setsGagnants): array
    {
        return self::court($setsGagnants) ? self::INTERROMPUS_COURT : self::INTERROMPUS;
    }
}
