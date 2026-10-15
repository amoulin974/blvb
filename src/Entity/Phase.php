<?php

namespace App\Entity;

use App\Repository\PhaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\PhaseType;
#[ORM\Entity(repositoryClass: PhaseRepository::class)]
#[ORM\UniqueConstraint(name: 'phase_code_unique', columns: ['saison_id', 'code'])]
class Phase
{
    /** Valeur calculée pour la page de la saison (SaisonController::show), non enregistrée en base. */
    public ?bool $cloturable = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    /** Code de la phase dans sa saison (P1, P2, F…), utilisé par les règles de passage (App\Competition\CodesCompetition). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $code = null;

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

    #[ORM\Column]
    private ?int $close = null;

    /**
     * Barème propre à la phase, valeur par valeur (ex. un barrage ou un tableau final sans points) ; les valeurs
     * absentes sont celles de la saison. Clés : App\Competition\Sport\Bareme::CLES.
     *
     * @var array<string, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $bareme = null;

    /**
     * Options propres au format de la phase (D8, D42), ex. `reception` d'un barrage : voir OPTION_*.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    /** Réception d'un barrage (D43) : équipe de la poule supérieure (défaut), mieux classée, ou terrain neutre. */
    public const RECEPTION_NIVEAU_SUPERIEUR = 'niveau_superieur';
    public const RECEPTION_MIEUX_CLASSE = 'mieux_classe';
    public const RECEPTION_NEUTRE = 'neutre';

    /**
     * Règles de passage des équipes de cette phase vers la suivante (ou la saison suivante), dans l'ordre.
     *
     * @var Collection<int, RegleDePassage>
     */
    #[ORM\OneToMany(targetEntity: RegleDePassage::class, mappedBy: 'phase', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $reglesDePassage;


    public function __construct()
    {
        $this->poules = new ArrayCollection();
        $this->reglesDePassage = new ArrayCollection();

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

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /** @param array<string, mixed>|null $options */
    public function setOptions(?array $options): static
    {
        $this->options = $options === [] ? null : $options;

        return $this;
    }

    /** Option d'un barrage : où se joue le match ; valeur inconnue = équipe de la poule supérieure. */
    public function getReception(): string
    {
        $valeur = $this->getOptions()['reception'] ?? null;

        return \in_array($valeur, [self::RECEPTION_MIEUX_CLASSE, self::RECEPTION_NEUTRE], true) ? $valeur : self::RECEPTION_NIVEAU_SUPERIEUR;
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

    public function getClose(): ?int
    {
        return $this->close;
    }

    public function setClose(int $close): static
    {
        $this->close = $close;

        return $this;
    }

    /** @return Collection<int, RegleDePassage> */
    public function getReglesDePassage(): Collection
    {
        return $this->reglesDePassage;
    }

    public function addRegleDePassage(RegleDePassage $regle): static
    {
        if (!$this->reglesDePassage->contains($regle)) {
            $this->reglesDePassage->add($regle);
            $regle->setPhase($this);
        }

        return $this;
    }

    public function removeRegleDePassage(RegleDePassage $regle): static
    {
        $this->reglesDePassage->removeElement($regle);

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /** @return array<string, int>|null surcharges du barème de la saison (App\Competition\Sport\Bareme::CLES), null si aucune */
    public function getBareme(): ?array
    {
        return $this->bareme;
    }

    /** @param array<string, int>|null $bareme */
    public function setBareme(?array $bareme): static
    {
        $this->bareme = $bareme ?: null;

        return $this;
    }
}
