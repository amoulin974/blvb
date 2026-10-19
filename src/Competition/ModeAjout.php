<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Saison;

/**
 * Wizard en mode ajout d'une saison lancée (docs/wizard-saison/decisions.md, D51) : les phases commencées, et toutes
 * celles qui les précèdent, sont figées ; les phases placées après la dernière phase commencée restent libres.
 */
final class ModeAjout
{
    public function __construct(private CompositionPoules $composition)
    {
    }

    /**
     * Phases figées, dans l'ordre : de la première phase jusqu'à la dernière phase commencée (vide si aucune ne l'est).
     *
     * @return list<Phase>
     */
    public function phasesFigees(Saison $saison): array
    {
        $phases = CompositionPoules::phases($saison);
        $derniere = -1;
        foreach ($phases as $i => $phase) {
            if ($this->commencee($phase)) {
                $derniere = $i;
            }
        }

        return \array_slice($phases, 0, $derniere + 1);
    }

    /**
     * Phase commencée : clôturée, avec un match ou un classement non nul (CompositionPoules::verrouillee), avec des
     * équipes dans ses poules (composition faite) ou des lignes de classement, même à zéro (leur poule ne se supprime
     * pas sans elles).
     */
    public function commencee(Phase $phase): bool
    {
        if ($this->composition->verrouillee($phase)) {
            return true;
        }
        foreach ($phase->getPoules() as $poule) {
            if (!$poule->getEquipes()->isEmpty() || !$poule->getClassements()->isEmpty()) {
                return true;
            }
        }

        return false;
    }
}
