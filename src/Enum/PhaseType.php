<?php

namespace App\Enum;

enum PhaseType: int
{
    case CHAMPIONNAT = 0;
    case FINALE = 1;

    public function label(): string
    {
        return match ($this) {
            self::CHAMPIONNAT => 'Phase de championnat',
            self::FINALE      => 'Phase finale',
        };
    }
}
