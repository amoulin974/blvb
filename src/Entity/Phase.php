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

    /** Phase jouée (D54), calculée pour la page de la saison : suppressions en masse masquées. Non enregistrée en base. */
    public ?bool $jouee = null;

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

    /** Format de match (D48) : nombre de sets à gagner, 3 par défaut. */
    public const SETS_GAGNANTS_DEFAUT = 3;
    public const SETS_GAGNANTS_COURT = 2;

    /**
     * Option d'un championnat (A2, D47) : chaque équipe rencontre deux fois chaque autre, le retour reprenant les
     * journées de l'aller dans le même ordre, domicile et extérieur inversés.
     */
    public function estAllerRetour(): bool
    {
        return $this->getType() === PhaseType::CHAMPIONNAT && ($this->getOptions()['aller_retour'] ?? false) === true;
    }

    public function setAllerRetour(bool $allerRetour): static
    {
        $options = $this->getOptions();
        unset($options['aller_retour']);
        if ($allerRetour) {
            $options['aller_retour'] = true;
        }

        return $this->setOptions($options);
    }

    /** Durée d'un match proposée (minutes) selon le format (D49), modifiable pour la journée des finales. */
    public const DUREE_MATCH_DEFAUT = 120;
    public const DUREE_MATCH_COURT = 75;

    /** Journée des finales (D45) : tours regroupés le même jour. */
    public const TOURS_FINALES = 'finales';
    public const TOURS_DEMI_FINALES = 'demi_finales';

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

    /**
     * Option d'un tableau à élimination : journée des finales (C3, D45, D46). Les derniers tours (finales seules, ou
     * demi-finales et finales) de tous les tableaux de la phase se jouent le même jour dans un gymnase, à des heures
     * calculées (PlanningFinales). Null quand l'option n'est pas choisie ; valeurs corrigées si elles sont incomplètes.
     *
     * @return array{tours: string, lieu: ?int, date: ?\DateTimeImmutable, heure: string, terrains: int, duree: int}|null
     */
    public function getJourneeFinales(): ?array
    {
        $option = $this->getOptions()['journee_finales'] ?? null;
        if (!\is_array($option)) {
            return null;
        }
        $date = \is_string($option['date'] ?? null) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $option['date']) : false;
        $heure = $option['heure'] ?? null;

        return [
            'tours' => ($option['tours'] ?? null) === self::TOURS_DEMI_FINALES ? self::TOURS_DEMI_FINALES : self::TOURS_FINALES,
            'lieu' => is_numeric($option['lieu'] ?? null) ? (int) $option['lieu'] : null,
            'date' => $date ?: null,
            'heure' => \is_string($heure) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure) ? $heure : '19:00',
            'terrains' => max(1, (int) ($option['terrains'] ?? 1)),
            'duree' => max(15, (int) ($option['duree'] ?? $this->dureeMatchParDefaut())),
        ];
    }

    /** Nombre de tours regroupés le jour des finales : 0 sans l'option, 1 (finales) ou 2 (demi-finales et finales). */
    public function nbToursJourneeFinales(): int
    {
        if ($this->getType() !== PhaseType::FINALE || ($option = $this->getJourneeFinales()) === null) {
            return 0;
        }

        return $option['tours'] === self::TOURS_DEMI_FINALES ? 2 : 1;
    }

    /**
     * Option de toute phase (D48) : nombre de sets à gagner pour remporter un match, 3 par défaut, 2 en format court.
     * Toute autre valeur enregistrée est lue comme 3.
     */
    public function getSetsGagnants(): int
    {
        return ($this->getOptions()['sets_gagnants'] ?? null) === self::SETS_GAGNANTS_COURT ? self::SETS_GAGNANTS_COURT : self::SETS_GAGNANTS_DEFAUT;
    }

    /** Durée d'un match proposée pour cette phase (D49) : 120 minutes en 3 sets gagnants, 75 en 2 sets gagnants. */
    public function dureeMatchParDefaut(): int
    {
        return $this->getSetsGagnants() === self::SETS_GAGNANTS_COURT ? self::DUREE_MATCH_COURT : self::DUREE_MATCH_DEFAUT;
    }

    /** Le format par défaut (3 sets gagnants) n'est pas écrit dans les options. */
    public function setSetsGagnants(int $sets): static
    {
        $options = $this->getOptions();
        unset($options['sets_gagnants']);
        if ($sets === self::SETS_GAGNANTS_COURT) {
            $options['sets_gagnants'] = $sets;
        }

        return $this->setOptions($options);
    }

    /** Option d'un tableau à élimination : un match pour la 3e place entre les perdants des demi-finales (B2). */
    public function aPetiteFinale(): bool
    {
        return ($this->getOptions()['petite_finale'] ?? false) === true;
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
