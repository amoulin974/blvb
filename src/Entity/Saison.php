<?php

namespace App\Entity;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\SaisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SaisonRepository::class)]
class Saison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nom = "Saison 2025";

    /**
     * @var Collection<int, Phase>
     */
    #[ORM\OneToMany(targetEntity: Phase::class, mappedBy: 'saison', orphanRemoval: true)]
    private Collection $phases;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private $favori = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $date_debut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $date_fin = null;

    //Victoire bonifiée
    #[ORM\Column(options: ['default' => 3])]
    private ?int $points_victoire_forte = 3;


    //Défaite bonifiée
    #[ORM\Column(options: ['default' => 1])]
    private ?int $points_defaite_forte = 1;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $points_nul = 0;

    #[ORM\Column(options: ['default' => -3])]
    private ?int $points_forfait = -3;

    //Victoire non bonifiée
    #[ORM\Column(options: ['default' => 2])]
    private ?int $points_victoire_faible = 2;

    //Défaite non bonifiée
    #[ORM\Column(options: ['default' => 0])]
    private ?int $points_defaite_faible = 0;

    /**
     * @var Collection<int, Indisponibilite>
     */
    #[ORM\OneToMany(targetEntity: Indisponibilite::class, mappedBy: 'saison', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $indisponibilites;

    public function __construct()
    {

        $this->phases = new ArrayCollection();
        $this->date_debut = new \DateTimeImmutable('first day of september this year'); // date actuelle par défaut
        $nextYear=(int)$this->date_debut->format('Y')+1;
        $this->date_fin = new \DateTimeImmutable("last day of july $nextYear"); // date actuelle par défaut
        $this->indisponibilites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection<int, Phase>
     */
    public function getPhases(): Collection
    {
        return $this->phases;
    }

    public function addPhase(Phase $phase): static
    {
        if (!$this->phases->contains($phase)) {
            $this->phases->add($phase);
            $phase->setSaison($this);
        }

        return $this;
    }

    public function removePhase(Phase $phase): static
    {
        if ($this->phases->removeElement($phase)) {
            // set the owning side to null (unless already changed)
            if ($phase->getSaison() === $this) {
                $phase->setSaison(null);
            }
        }

        return $this;
    }

    public function getFavori()
    {
        return $this->favori;
    }

    public function setFavori($favori): static
    {
        $this->favori = $favori;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTimeImmutable $date_debut): static
    {
        $this->date_debut = $date_debut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->date_fin;
    }

    public function setDateFin(\DateTimeImmutable $date_fin): static
    {
        $this->date_fin = $date_fin;

        return $this;
    }

    public function getPointsVictoireForte(): ?int
    {
        return $this->points_victoire_forte;
    }

    public function setPointsVictoireForte(int $points_victoire_forte): static
    {
        $this->points_victoire_forte = $points_victoire_forte;

        return $this;
    }

    public function getPointsDefaiteForte(): ?int
    {
        return $this->points_defaite_forte;
    }

    public function setPointsDefaiteForte(int $points_defaite_forte): static
    {
        $this->points_defaite_forte = $points_defaite_forte;

        return $this;
    }

    public function getPointsNul(): ?int
    {
        return $this->points_nul;
    }

    public function setPointsNul(int $points_nul): static
    {
        $this->points_nul = $points_nul;

        return $this;
    }

    public function getPointsForfait(): ?int
    {
        return $this->points_forfait;
    }

    public function setPointsForfait(int $points_forfait): static
    {
        $this->points_forfait = $points_forfait;

        return $this;
    }

    public function getPointsVictoireFaible(): ?int
    {
        return $this->points_victoire_faible;
    }

    public function setPointsVictoireFaible(int $points_victoire_faible): static
    {
        $this->points_victoire_faible = $points_victoire_faible;

        return $this;
    }

    public function getPointsDefaiteFaible(): ?int
    {
        return $this->points_defaite_faible;
    }

    public function setPointsDefaiteFaible(int $points_defaite_faible): static
    {
        $this->points_defaite_faible = $points_defaite_faible;

        return $this;
    }

    /**
     * @return Collection<int, Indisponibilite>
     */
    public function getIndisponibilites(): Collection
    {
        return $this->indisponibilites;
    }

    public function addIndisponibilite(Indisponibilite $indisponibilite): static
    {
        if (!$this->indisponibilites->contains($indisponibilite)) {
            $this->indisponibilites->add($indisponibilite);
            $indisponibilite->setSaison($this);
        }

        return $this;
    }

    public function removeIndisponibilite(Indisponibilite $indisponibilite): static
    {
        if ($this->indisponibilites->removeElement($indisponibilite)) {
            // set the owning side to null (unless already changed)
            if ($indisponibilite->getSaison() === $this) {
                $indisponibilite->setSaison(null);
            }
        }

        return $this;
    }
}
