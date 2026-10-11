<?php

namespace App\Entity;

use App\Repository\ResponsableClubRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Désignation d'un compte comme responsable d'un club pour une saison (comme le capitaine d'une équipe).
 * Pendant cette saison, le responsable saisit le score de tous les matchs des équipes du club (SaisieScoreService) ;
 * pendant la saison en cours, il modifie aussi la fiche du club (ClubVoter) et porte le rôle ROLE_RESPONSABLE_CLUB.
 */
#[ORM\Entity(repositoryClass: ResponsableClubRepository::class)]
#[ORM\UniqueConstraint(name: 'responsable_club_unique', columns: ['club_id', 'user_id', 'saison_id'])]
class ResponsableClub
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'responsabilites')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Club $club;

    #[ORM\ManyToOne(inversedBy: 'responsabilitesClub')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Saison $saison;

    public function __construct(Club $club, User $user, Saison $saison)
    {
        $this->club = $club;
        $this->user = $user;
        $this->saison = $saison;
        $user->ajouterResponsabilite($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): Club
    {
        return $this->club;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSaison(): Saison
    {
        return $this->saison;
    }
}
