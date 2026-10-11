<?php

namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\EquipeRepository;
use App\Repository\MembreEquipeRepository;

/**
 * Qui peut saisir le score d'un match, hors administrateur :
 *  - le capitaine de l'équipe qui reçoit, pour la saison du match ;
 *  - les responsables du club de l'une ou l'autre équipe (tous les résultats de leur club).
 *
 * Source unique de la règle : utilisée par ScoreVoter (API de saisie) et par les pages du calendrier, qui
 * reçoivent les droits de l'utilisateur (droits()) pour afficher les boutons « Saisir » sans requête par match.
 */
final class SaisieScoreService
{
    /** @var array<string, array{recoit: int[], club: int[]}> mémoïsation par utilisateur et saison, le temps d'une requête */
    private array $cache = [];

    public function __construct(
        private readonly MembreEquipeRepository $membreEquipeRepository,
        private readonly EquipeRepository $equipeRepository,
    ) {
    }

    /**
     * Droits de saisie d'un utilisateur pour une saison :
     *  recoit : équipes dont il est capitaine (matchs qu'elles reçoivent) ;
     *  club   : équipes des clubs dont il est responsable (tous leurs matchs).
     *
     * @return array{recoit: int[], club: int[]}
     */
    public function droits(?User $user, ?Saison $saison): array
    {
        if ($user === null || $user->getId() === null) {
            return ['recoit' => [], 'club' => []];
        }

        $cle = $user->getId().'-'.($saison?->getId() ?? 'toutes');

        return $this->cache[$cle] ??= [
            'recoit' => array_map(static fn (Equipe $e) => $e->getId(), $this->membreEquipeRepository->findEquipesOuCapitaine($user, $saison)),
            'club' => $this->equipeRepository->findIdsDesClubsDe($user),
        ];
    }

    /** Même règle que _score_partie.html.twig, hors administrateur (vérifié par ScoreVoter). */
    public function peutSaisir(User $user, Partie $partie): bool
    {
        $saison = $partie->getPoule()?->getPhase()?->getSaison();
        $recoit = $partie->getIdEquipeRecoit()?->getId();
        $deplace = $partie->getIdEquipeDeplace()?->getId();
        if ($saison === null || $recoit === null) {
            return false;
        }

        $droits = $this->droits($user, $saison);

        return \in_array($recoit, $droits['recoit'], true)
            || \in_array($recoit, $droits['club'], true)
            || ($deplace !== null && \in_array($deplace, $droits['club'], true));
    }
}
