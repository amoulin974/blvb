<?php

namespace App\VolleyStats;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\Partie;
use App\Entity\Saison;

/**
 * Une équipe pour un match du championnat : sujet de FeuilleMatchVoter, avec la page du match si elle existe déjà. Les
 * droits portent sur cette équipe, jamais sur le match seul (relecture sécurité, spécification §4).
 */
final readonly class MatchEquipe
{
    public function __construct(public Equipe $equipe, public Partie $partie, public ?FeuilleMatch $feuille = null)
    {
    }

    /** L'équipe joue-t-elle ce match, contre un adversaire connu ? Sinon la page n'existe pas (404 pour tous). */
    public function estValide(): bool
    {
        $recoit = $this->partie->getIdEquipeRecoit();
        $deplace = $this->partie->getIdEquipeDeplace();

        return $recoit !== null && $deplace !== null && $this->saison() !== null
            && ($recoit === $this->equipe || $deplace === $this->equipe);
    }

    public function adversaire(): ?Equipe
    {
        return $this->partie->getIdEquipeRecoit() === $this->equipe ? $this->partie->getIdEquipeDeplace() : $this->partie->getIdEquipeRecoit();
    }

    /** Saison du match, la seule qui compte (jamais celle choisie dans le menu) */
    public function saison(): ?Saison
    {
        return $this->partie->getPoule()?->getPhase()?->getSaison();
    }
}
