<?php

namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\EquipeRepository;
use App\Repository\MembreEquipeRepository;

/**
 * Qui peut saisir le score d'un match, hors administrateur : le capitaine de l'équipe qui reçoit
 * (pour la saison du match) et les responsables du club de l'équipe qui reçoit.
 *
 * Source unique de la règle : utilisée par ScoreVoter (API de saisie) et par les pages du calendrier,
 * qui reçoivent la liste des équipes autorisées pour afficher les boutons « Saisir » sans requête par match.
 */
final class SaisieScoreService
{
    /** @var array<string, int[]> mémoïsation par utilisateur et saison, le temps d'une requête */
    private array $cache = [];

    public function __construct(
        private readonly MembreEquipeRepository $membreEquipeRepository,
        private readonly EquipeRepository $equipeRepository,
    ) {
    }

    /**
     * Identifiants des équipes dont l'utilisateur peut saisir les matchs reçus, pour une saison :
     * équipes dont il est capitaine cette saison-là, et équipes des clubs dont il est responsable.
     *
     * @return int[]
     */
    public function equipesAutoriseesIds(?User $user, ?Saison $saison): array
    {
        if ($user === null || $user->getId() === null) {
            return [];
        }

        $cle = $user->getId().'-'.($saison?->getId() ?? 'toutes');
        if (!isset($this->cache[$cle])) {
            $capitaine = array_map(static fn (Equipe $e) => $e->getId(), $this->membreEquipeRepository->findEquipesOuCapitaine($user, $saison));
            $this->cache[$cle] = array_values(array_unique([...$capitaine, ...$this->equipeRepository->findIdsDesClubsDe($user)]));
        }

        return $this->cache[$cle];
    }
}
