<?php

namespace App\Entity;

use App\Repository\FermetureGymnaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Période pendant laquelle un gymnase est fermé (travaux, réquisition…), saisie par un responsable de club ou un
 * administrateur (D60). Aucun match n'y est placé. À ne pas confondre avec Indisponibilite : une période sans match
 * de toute la saison, qui fait sauter la journée à tous les clubs.
 */
#[ORM\Entity(repositoryClass: FermetureGymnaseRepository::class)]
class FermetureGymnase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fermetures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lieu $lieu = null;

    #[ORM\Column(length: 255)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateFin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }
}
