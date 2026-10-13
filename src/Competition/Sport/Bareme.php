<?php

namespace App\Competition\Sport;

use App\Entity\Phase;
use App\Entity\Saison;

/**
 * Points attribués au classement selon la catégorie du résultat. Les catégories sont celles du volley (victoire forte
 * ou faible, défaite forte ou faible) ; un sport qui n'a pas de victoire « faible » ne s'en sert pas.
 * Barème de la saison, que la phase peut surcharger valeur par valeur (Phase::getBareme).
 */
final class Bareme
{
    /** Clés d'un barème enregistré (surcharge de phase). */
    public const CLES = ['victoire_forte', 'victoire_faible', 'nul', 'defaite_forte', 'defaite_faible', 'forfait'];

    public function __construct(
        public readonly int $victoireForte,
        public readonly int $victoireFaible,
        public readonly int $nul,
        public readonly int $defaiteForte,
        public readonly int $defaiteFaible,
        public readonly int $forfait,
    ) {
    }

    public static function deSaison(Saison $saison): self
    {
        return new self(
            $saison->getPointsVictoireForte(),
            $saison->getPointsVictoireFaible(),
            $saison->getPointsNul(),
            $saison->getPointsDefaiteForte(),
            $saison->getPointsDefaiteFaible(),
            $saison->getPointsForfait(),
        );
    }

    /** Barème de la saison de la phase, avec les valeurs surchargées par la phase ; clés inconnues ignorées. */
    public static function dePhase(Phase $phase): self
    {
        $saison = self::deSaison($phase->getSaison());
        $surcharges = $phase->getBareme() ?? [];
        $valeur = fn (string $cle, int $defaut) => isset($surcharges[$cle]) && \is_int($surcharges[$cle]) ? $surcharges[$cle] : $defaut;

        return new self(
            $valeur('victoire_forte', $saison->victoireForte),
            $valeur('victoire_faible', $saison->victoireFaible),
            $valeur('nul', $saison->nul),
            $valeur('defaite_forte', $saison->defaiteForte),
            $valeur('defaite_faible', $saison->defaiteFaible),
            $valeur('forfait', $saison->forfait),
        );
    }
}
