<?php

namespace App\Entity;

use App\Repository\MembreEquipeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// Appartenance d'un Joueur à une Equipe pour une Saison donnée (la composition d'une
// équipe peut changer d'une saison à l'autre). Le capitaine est désigné parmi les
// membres : un seul membre par (equipe, saison) doit avoir capitaine = true, règle
// appliquée par MembreEquipeRepository::definirCapitaine plutôt que par une contrainte DB.
#[ORM\Entity(repositoryClass: MembreEquipeRepository::class)]
#[ORM\UniqueConstraint(name: 'membre_equipe_unique', fields: ['equipe', 'saison', 'joueur'])]
#[UniqueEntity(fields: ['equipe', 'saison', 'joueur'], message: 'Ce joueur fait déjà partie de cette équipe pour cette saison.')]
class MembreEquipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipe $equipe = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Saison $saison = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Joueur $joueur = null;

    #[ORM\Column]
    private bool $capitaine = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipe(): ?Equipe
    {
        return $this->equipe;
    }

    public function setEquipe(?Equipe $equipe): static
    {
        $this->equipe = $equipe;

        return $this;
    }

    public function getSaison(): ?Saison
    {
        return $this->saison;
    }

    public function setSaison(?Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function getJoueur(): ?Joueur
    {
        return $this->joueur;
    }

    public function setJoueur(?Joueur $joueur): static
    {
        $this->joueur = $joueur;

        return $this;
    }

    public function isCapitaine(): bool
    {
        return $this->capitaine;
    }

    public function setCapitaine(bool $capitaine): static
    {
        $this->capitaine = $capitaine;

        return $this;
    }
}
