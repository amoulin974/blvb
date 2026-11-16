<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Version précédente du règlement d'une saison, consultable et restaurable par un admin (D56). */
#[ORM\Entity]
class ReglementVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reglement $reglement = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $contenu = '';

    /** Date à laquelle ce contenu avait été enregistré */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $enregistreLe = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $auteur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReglement(): ?Reglement
    {
        return $this->reglement;
    }

    public function setReglement(Reglement $reglement): static
    {
        $this->reglement = $reglement;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getEnregistreLe(): ?\DateTimeImmutable
    {
        return $this->enregistreLe;
    }

    public function setEnregistreLe(\DateTimeImmutable $enregistreLe): static
    {
        $this->enregistreLe = $enregistreLe;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }
}
