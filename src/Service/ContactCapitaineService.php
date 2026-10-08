<?php

namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Repository\MembreEquipeRepository;

/**
 * Coordonnées du capitaine d'une équipe pour une saison.
 *
 * Ce service ne décide pas QUI a le droit de les voir : l'appelant vérifie l'autorisation
 * (FrontController::equipe_detail, ContactCapitaineExtension). Seul le capitaine est concerné ;
 * les coordonnées des autres joueurs ne passent jamais par ici (réservées aux membres de l'équipe).
 */
class ContactCapitaineService
{
    /** @var array<string, array{prenom: ?string, nom: ?string, telephone: ?string, email: ?string}|null> */
    private array $cache = [];

    public function __construct(private readonly MembreEquipeRepository $membreEquipeRepository)
    {
    }

    /** @return array{prenom: ?string, nom: ?string, telephone: ?string, email: ?string}|null null si pas de capitaine désigné */
    public function pour(Equipe $equipe, Saison $saison): ?array
    {
        $cle = $equipe->getId().'-'.$saison->getId();
        if (!array_key_exists($cle, $this->cache)) {
            $joueur = $this->membreEquipeRepository->findCapitaine($equipe, $saison)?->getJoueur();
            $this->cache[$cle] = $joueur === null ? null : [
                'prenom' => $joueur->getPrenom(),
                'nom' => $joueur->getNom(),
                'telephone' => $joueur->getTelephone() ?: $joueur->getUser()?->getTelephone(),
                'email' => $joueur->getEmail() ?: $joueur->getUser()?->getEmail(),
            ];
        }

        return $this->cache[$cle];
    }
}
