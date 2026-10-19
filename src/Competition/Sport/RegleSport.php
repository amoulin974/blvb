<?php

namespace App\Competition\Sport;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Règles propres à un sport : forme et validité d'un score, points au classement, statistiques de départage
 * (docs/wizard-saison/decisions.md, D13). Une implémentation par sport ; le volley-ball est la première.
 *
 * Un score est enregistré comme deux entiers (Partie::nbSetGagnantReception / nbSetGagnantDeplacement) : ce que
 * l'équipe a marqué (sets, buts, points selon le sport). Le forfait est encodé par le sport (scoreForfait).
 *
 * $setsGagnants est le format de match de la phase (Phase::getSetsGagnants, D48) : nombre de sets à gagner, 3 par
 * défaut. Les sports qui n'ont pas de sets l'ignorent.
 */
#[AutoconfigureTag(self::TAG)]
interface RegleSport
{
    public const TAG = 'app.regle_sport';

    /** Code enregistré pour la saison, ex. « volley ». */
    public function code(): string;

    public function libelle(): string;

    /** Score acceptable à l'enregistrement, y compris un forfait encodé par scoreForfait. */
    public function estValide(int $reception, int $deplacement, int $setsGagnants = 3): bool;

    /** Message affiché quand estValide refuse un score. */
    public function messageScoreInvalide(int $setsGagnants = 3): string;

    /**
     * Score enregistré quand une équipe est déclarée forfait.
     *
     * @return array{int, int} [équipe qui reçoit, équipe qui se déplace]
     */
    public function scoreForfait(bool $receptionForfait, int $setsGagnants = 3): array;

    /** Points au classement de l'équipe qui a marqué $pour contre $contre ; 0 pour un match non joué. */
    public function points(?int $pour, ?int $contre, Bareme $bareme, int $setsGagnants = 3): int;

    /**
     * Ce qui compte pour l'équipe dans les statistiques de départage (volley : sets gagnés et perdus) ;
     * [0, 0] pour un match non joué ou un match qui ne compte pas.
     *
     * @return array{int, int} [marqués, encaissés]
     */
    public function statistiques(?int $pour, ?int $contre, int $setsGagnants = 3): array;
}
