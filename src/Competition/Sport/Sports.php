<?php

namespace App\Competition\Sport;

use App\Entity\Saison;

/**
 * Sport d'une saison. Tant que la saison n'enregistre pas son sport (colonne ajoutée avec le wizard, lot 4,
 * docs/wizard-saison/lots.md), toutes les saisons sont en volley-ball.
 */
class Sports
{
    public function pourSaison(?Saison $saison): RegleSport
    {
        return new Volley();
    }
}
