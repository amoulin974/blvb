<?php

namespace App\Entity;

use App\Repository\PhaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\PhaseType;
#[ORM\Entity(repositoryClass: PhaseRepository::class)]
class Phase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable  $datedebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable  $datefin = null;

    #[ORM\ManyToOne(inversedBy: 'phases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Saison $saison = null;

    /**
     * @var Collection<int, Poule>
     */
    #[ORM\OneToMany(targetEntity: Poule::class, mappedBy: 'phase', orphanRemoval: true)]
    private Collection $poules;

    #[ORM\Column(enumType: PhaseType::class)]
    private PhaseType $type = PhaseType::CHAMPIONNAT;

    #[ORM\Column]
    private ?int $ordre = null;


    public function __construct()
    {
        $this->poules = new ArrayCollection();

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

    public function getDatedebut(): ?\DateTimeImmutable
    {
        return $this->datedebut;
    }

    public function setDatedebut(\DateTimeImmutable $datedebut): static
    {
        $this->datedebut = $datedebut;

        return $this;
    }

    public function getDatefin(): ?\DateTimeImmutable
    {
        return $this->datefin;
    }

    public function setDatefin(\DateTimeImmutable $datefin): static
    {
        $this->datefin = $datefin;

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

    /**
     * @return Collection<int, Poule>
     */
    public function getPoules(): Collection
    {
        return $this->poules;
    }

    public function addPoule(Poule $poule): static
    {
        if (!$this->poules->contains($poule)) {
            $this->poules->add($poule);
            $poule->setPhase($this);
        }

        return $this;
    }

    public function removePoule(Poule $poule): static
    {
        if ($this->poules->removeElement($poule)) {
            // set the owning side to null (unless already changed)
            if ($poule->getPhase() === $this) {
                $poule->setPhase(null);
            }
        }

        return $this;
    }

    public function getType(): ?PhaseType
    {
        return $this->type;
    }

    public function setType(?PhaseType  $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }


}
