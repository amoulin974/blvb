<?php

namespace App\Service;

/**
 * Règles de score du championnat (règlement, rubrique « Décompte des points » et règle 1R), source unique pour
 * l'API de saisie (FrontController::api_score_update) et le classement (ClassementService).
 *
 * Un score est enregistré en sets : équipe qui reçoit, équipe qui se déplace ; -1 = forfait de cette équipe.
 *  - Match complet : une équipe à 3 sets, l'autre de 0 à 2 ; points selon le barème de la saison.
 *  - Match interrompu (règle 1R : coupure de courant, fermeture du gymnase, blessure obligeant à finir à 4) :
 *    la partie n'est pas rejouée ; scores 2-1, 2-2, 1-1, 1-0 (dans un sens ou dans l'autre) ; points fixés par
 *    le règlement : 2-1 → 2 et 1 point, 2-2 → 1 point chacun, 1-1 et 1-0 → 0 point. Pour 2-2, le set-average
 *    n'est pas modifié (les sets ne sont pas comptés).
 *  - Forfait : 3 sets contre -1.
 */
final class RegleScore
{
    /** Points du règlement pour un match interrompu, du point de vue de l'équipe qui a gagné le premier nombre de sets. */
    private const INTERROMPUS = ['2-1' => 2, '1-2' => 1, '2-2' => 1, '1-1' => 0, '1-0' => 0, '0-1' => 0];

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

    /** Score acceptable à l'enregistrement : match complet, match interrompu (règle 1R) ou forfait. */
    public static function estValide(int $reception, int $deplacement): bool
    {
        return self::estComplet($reception, $deplacement) || self::estInterrompu($reception, $deplacement) || self::estForfait($reception, $deplacement);
    }

    /** Points d'un match interrompu pour l'équipe qui a gagné $setsGagnes sets ; null si ce n'est pas un match interrompu. */
    public static function pointsInterrompu(int $setsGagnes, int $setsPerdus): ?int
    {
        return self::INTERROMPUS[$setsGagnes.'-'.$setsPerdus] ?? null;
    }

    /** Les sets du match comptent-ils dans le set-average ? Non pour un match interrompu à 2-2 (règle 1R). */
    public static function setsComptes(?int $reception, ?int $deplacement): bool
    {
        return !($reception === 2 && $deplacement === 2);
    }
}
