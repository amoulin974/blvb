<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\MembreEquipeRepository;

/**
 * Rôles d'un compte pour « Ce que vous pouvez faire » (D71), calculés d'après ses droits réels sur la saison affichée,
 * de la plus large à la plus précise : joueur (dans une composition), capitaine, responsable de club (désigné pour la
 * saison favorite, comme le menu « Mon club »), administrateur.
 */
final readonly class PresentationRoles
{
    public const JOUEUR = 'joueur';
    public const CAPITAINE = 'capitaine';
    public const RESPONSABLE = 'responsable';
    public const ADMIN = 'admin';

    public function __construct(private MembreEquipeRepository $membreEquipeRepository)
    {
    }

    /**
     * @return array{roles: list<string>, equipes: list<Equipe>, capitaine: list<Equipe>, clubs: list<Club>}
     */
    public function pour(?User $user, ?Saison $saison, bool $admin): array
    {
        $resultat = ['roles' => [], 'equipes' => [], 'capitaine' => [], 'clubs' => []];
        if ($user === null) {
            return $resultat;
        }
        if ($saison !== null) {
            $resultat['equipes'] = $this->membreEquipeRepository->findEquipesDuMembre($user, $saison);
            $resultat['capitaine'] = $this->membreEquipeRepository->findEquipesOuCapitaine($user, $saison);
        }
        $resultat['clubs'] = $user->getClubsResponsable();

        $roles = [
            self::JOUEUR => $resultat['equipes'] !== [],
            self::CAPITAINE => $resultat['capitaine'] !== [],
            self::RESPONSABLE => $resultat['clubs'] !== [],
            self::ADMIN => $admin,
        ];
        $resultat['roles'] = array_keys(array_filter($roles));

        return $resultat;
    }
}
