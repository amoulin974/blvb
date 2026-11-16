<?php

namespace App\Reglement\Bloc;

use App\Entity\Phase;
use App\Entity\Saison;
use App\Reglement\BlocReglement;
use App\Reglement\ParametreBloc;

/** Élément calculé qui accepte le paramètre « phase » (code de la phase, D20) : toute la saison par défaut. */
abstract class BlocParPhase implements BlocReglement
{
    public function parametres(Saison $saison): array
    {
        $valeurs = [];
        foreach (self::phasesDansLOrdre($saison) as $phase) {
            if ($phase->getCode() !== null) {
                $valeurs[$phase->getCode()] = (string) $phase->getNom();
            }
        }

        return [new ParametreBloc('phase', 'Limite l\'élément à une phase, désignée par son code.', $valeurs, 'toute la saison')];
    }

    /**
     * Phases concernées, dans l'ordre : celle du paramètre, sinon toutes
     *
     * @param array<string, string> $parametres
     * @return list<Phase>
     */
    protected static function phases(Saison $saison, array $parametres): array
    {
        $phases = self::phasesDansLOrdre($saison);
        if (isset($parametres['phase'])) {
            $phases = array_values(array_filter($phases, fn (Phase $p) => $p->getCode() === $parametres['phase']));
        }

        return $phases;
    }

    /** @return list<Phase> */
    protected static function phasesDansLOrdre(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return array_values($phases);
    }
}
