<?php

namespace App\Competition\Sport;

use App\Entity\Saison;

/**
 * Points attribués au classement selon la catégorie du résultat. Les catégories sont celles du volley (victoire forte
 * ou faible, défaite forte ou faible) ; un sport qui n'a pas de victoire « faible » ne s'en sert pas.
 * Lu aujourd'hui dans la saison ; une phase pourra le surcharger (lot 2, docs/wizard-saison/lots.md).
 */
final class Bareme
{
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
}
