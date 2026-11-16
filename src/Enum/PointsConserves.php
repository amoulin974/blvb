<?php

namespace App\Enum;

/**
 * Points qu'une équipe emporte dans la phase suivante (docs/formats-competition.md, partie D). Seule la remise à zéro
 * est appliquée pour l'instant (docs/wizard-saison/decisions.md, D23) ; les autres valeurs viendront avec les play-offs.
 */
enum PointsConserves: string
{
    case AUCUN = 'aucun';
    case TOUS = 'tous';
    case MOITIE = 'moitie';
    case CONTRE_QUALIFIES = 'contre_qualifies';
}
