<?php

namespace App\Entity;

use App\Repository\PartieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartieRepository::class)]
class Partie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne(inversedBy: 'parties')]
    private ?Journee $id_journee = null;

    #[ORM\ManyToOne(inversedBy: 'parties')]
    private ?Poule $poule = null;

    #[ORM\ManyToOne(inversedBy: 'parties')]
    private ?Lieu $lieu = null;

    #[ORM\ManyToOne(inversedBy: 'parties_reception')]
    private ?equipe $id_equipe_recoit = null;

    #[ORM\ManyToOne(inversedBy: 'parties_deplacement')]
    private ?Equipe $id_equipe_deplace = null;

    #[ORM\Column(nullable: true)]
    private ?int $nb_set_gagnant_reception = null;

    #[ORM\Column(nullable: true)]
    private ?int $nb_set_gagnant_deplacement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getIdJournee(): ?Journee
    {
        return $this->id_journee;
    }

    public function setIdJournee(?Journee $id_journee): static
    {
        $this->id_journee = $id_journee;

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

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getIdEquipeRecoit(): ?equipe
    {
        return $this->id_equipe_recoit;
    }

    public function setIdEquipeRecoit(?equipe $id_equipe_recoit): static
    {
        $this->id_equipe_recoit = $id_equipe_recoit;

        return $this;
    }

    public function getIdEquipeDeplace(): ?Equipe
    {
        return $this->id_equipe_deplace;
    }

    public function setIdEquipeDeplace(?Equipe $id_equipe_deplace): static
    {
        $this->id_equipe_deplace = $id_equipe_deplace;

        return $this;
    }

    public function getNbSetGagnantReception(): ?int
    {
        return $this->nb_set_gagnant_reception;
    }

    public function setNbSetGagnantReception(int $nb_set_gagnant_reception): static
    {
        $this->nb_set_gagnant_reception = $nb_set_gagnant_reception;

        return $this;
    }

    public function getNbSetGagnantDeplacement(): ?int
    {
        return $this->nb_set_gagnant_deplacement;
    }

    public function setNbSetGagnantDeplacement(?int $nb_set_gagnant_deplacement): static
    {
        $this->nb_set_gagnant_deplacement = $nb_set_gagnant_deplacement;

        return $this;
    }
}
