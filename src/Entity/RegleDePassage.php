<?php

namespace App\Entity;

use App\Enum\PointsConserves;
use App\Repository\RegleDePassageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Règle de passage d'une phase vers la suivante : « source → destination » (docs/formats-competition.md, partie D).
 * La source est écrite avec la notation des places (P1.A#1..-3, BAR1.AB.vainqueur : App\Competition\NotationPlace) ;
 * la destination est une poule d'une phase suivante, ou un niveau de la saison suivante (D22).
 * Les règles d'une phase s'appliquent dans l'ordre ; une équipe déjà placée ne l'est pas une seconde fois (D24).
 */
#[ORM\Entity(repositoryClass: RegleDePassageRepository::class)]
class RegleDePassage
{
    public const PLACEMENT_RANG = 'rang';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Phase dont les équipes partent. */
    #[ORM\ManyToOne(inversedBy: 'reglesDePassage')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Phase $phase = null;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(length: 100)]
    private string $source = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Poule $pouleDestination = null;

    /** Niveau de la poule de la saison suivante (montées et descentes différées) ; null si la destination est une poule. */
    #[ORM\Column(nullable: true)]
    private ?int $niveauSaisonSuivante = null;

    /** Placement dans la destination : selon le rang (seul appliqué pour l'instant). */
    #[ORM\Column(length: 20)]
    private string $placement = self::PLACEMENT_RANG;

    #[ORM\Column(length: 20, enumType: PointsConserves::class)]
    private PointsConserves $pointsConserves = PointsConserves::AUCUN;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhase(): ?Phase
    {
        return $this->phase;
    }

    public function setPhase(?Phase $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getPouleDestination(): ?Poule
    {
        return $this->pouleDestination;
    }

    public function setPouleDestination(?Poule $pouleDestination): static
    {
        $this->pouleDestination = $pouleDestination;

        return $this;
    }

    public function getNiveauSaisonSuivante(): ?int
    {
        return $this->niveauSaisonSuivante;
    }

    public function setNiveauSaisonSuivante(?int $niveauSaisonSuivante): static
    {
        $this->niveauSaisonSuivante = $niveauSaisonSuivante;

        return $this;
    }

    public function versSaisonSuivante(): bool
    {
        return $this->pouleDestination === null && $this->niveauSaisonSuivante !== null;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function setPlacement(string $placement): static
    {
        $this->placement = $placement;

        return $this;
    }

    public function getPointsConserves(): PointsConserves
    {
        return $this->pointsConserves;
    }

    public function setPointsConserves(PointsConserves $pointsConserves): static
    {
        $this->pointsConserves = $pointsConserves;

        return $this;
    }

    /** Texte lisible pour les messages : « P1.A#1..2 → Poule A (Phase 2) » ou « … → niveau 1 de la saison suivante ». */
    public function __toString(): string
    {
        $destination = $this->pouleDestination !== null
            ? sprintf('%s (%s)', $this->pouleDestination->getNom(), $this->pouleDestination->getPhase()?->getNom())
            : sprintf('niveau %d de la saison suivante', $this->niveauSaisonSuivante);

        return $this->source.' → '.$destination;
    }
}
