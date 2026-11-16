<?php

namespace App\Enum;

/**
 * Format d'une phase (docs/wizard-saison/decisions.md, D42) : le type de la phase est son format.
 */
enum PhaseType: int
{
    case CHAMPIONNAT = 0;
    case FINALE = 1;
    /** Phase de barrages : une poule de deux équipes par match (montée / descente, D42). */
    case BARRAGE = 2;

    public function label(): string
    {
        return match ($this) {
            self::CHAMPIONNAT => 'Phase de championnat',
            self::FINALE      => 'Phase finale',
            self::BARRAGE     => 'Phase de barrages',
        };
    }
}
