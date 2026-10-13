<?php

namespace App\Competition\Sport;

/**
 * Règles propres à un sport : forme et validité d'un score, points au classement, statistiques de départage
 * (docs/wizard-saison/decisions.md, D13). Une implémentation par sport ; le volley-ball est la première.
 *
 * Un score est enregistré comme deux entiers (Partie::nbSetGagnantReception / nbSetGagnantDeplacement) : ce que
 * l'équipe a marqué (sets, buts, points selon le sport). Le forfait est encodé par le sport (scoreForfait).
 */
interface RegleSport
{
    /** Code enregistré pour la saison, ex. « volley ». */
    public function code(): string;

    public function libelle(): string;

    /** Score acceptable à l'enregistrement, y compris un forfait encodé par scoreForfait. */
    public function estValide(int $reception, int $deplacement): bool;

    /** Message affiché quand estValide refuse un score. */
    public function messageScoreInvalide(): string;

    /**
     * Score enregistré quand une équipe est déclarée forfait.
     *
     * @return array{int, int} [équipe qui reçoit, équipe qui se déplace]
     */
    public function scoreForfait(bool $receptionForfait): array;

    /** Points au classement de l'équipe qui a marqué $pour contre $contre ; 0 pour un match non joué. */
    public function points(?int $pour, ?int $contre, Bareme $bareme): int;

    /**
     * Ce qui compte pour l'équipe dans les statistiques de départage (volley : sets gagnés et perdus) ;
     * [0, 0] pour un match non joué ou un match qui ne compte pas.
     *
     * @return array{int, int} [marqués, encaissés]
     */
    public function statistiques(?int $pour, ?int $contre): array;
}
