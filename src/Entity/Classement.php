<?php

namespace App\Entity;

use App\Repository\ClassementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClassementRepository::class)]
class Classement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'classements')]
    private ?Poule $poule = null;

    #[ORM\ManyToOne(inversedBy: 'classements')]
    private ?Equipe $equipe = null;

    #[ORM\Column(nullable: true)]
    private ?int $points = null;

    #[ORM\Column(nullable: true)]
    private ?int $setGagnes = null;

    #[ORM\Column(nullable: true)]
    private ?int $setPerdus = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getPoule(): ?Poule
    {
        return $this->poule;
    }

    public function setPoule(?Poule $poule): static
    {
        $this->poule = $poule;

        return $this;
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

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(?int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getSetGagnes(): ?int
    {
        return $this->setGagnes;
    }

    public function setSetGagnes(?int $setGagnes): static
    {
        $this->setGagnes = $setGagnes;

        return $this;
    }

    public function getSetPerdus(): ?int
    {
        return $this->setPerdus;
    }

    public function setSetPerdus(?int $setPerdus): static
    {
        $this->setPerdus = $setPerdus;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
