<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un set d'une page du match (D67) : composition de départ dans l'ordre officiel des postes 1 à 6 (celui du service, jamais
 * les places prises pendant l'échange), premier service, score de départ, puis le journal des événements. Score, rotation
 * et serveur se recalculent depuis le journal (App\VolleyStats\EtatSet), rien n'est stocké en double.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'feuille_match_set_unique', columns: ['feuille_id', 'numero'])]
class FeuilleMatchSet
{
    public const COMPOSITION = 'composition';
    public const EN_COURS = 'en_cours';
    public const TERMINE = 'termine';

    /** Ce que l'équipe note pendant le set (D68) : le score seulement, ou le score et les touches des joueurs */
    public const SAISIE_SCORE = 'score';
    public const SAISIE_TOUCHES = 'touches';

    public const NOUS = 'nous';
    public const EUX = 'eux';

    /** Scores de départ possibles (règle 1J, non contrôlée) : nous – eux */
    public const SCORES_DEPART = [[0, 0], [0, 8], [8, 0]];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FeuilleMatch $feuille;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $numero;

    /** @var list<?int> identifiants de FeuilleMatchJoueur aux postes 1 à 6 ; null : place libre (set commencé à 5) */
    #[ORM\Column(type: Types::JSON)]
    private array $postes = [null, null, null, null, null, null];

    /** nous / eux, saisi à chaque set (tiré par les joueurs, D67) */
    #[ORM\Column(length: 4, nullable: true)]
    private ?string $premierService = null;

    /** @var array{0: int, 1: int} nous, eux */
    #[ORM\Column(type: Types::JSON)]
    private array $scoreDepart = [0, 0];

    /** 25, ou 15 pour le set décisif (5ᵉ en 3 sets gagnants, 3ᵉ en 2) ; sets secs */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $pointsFin;

    #[ORM\Column(length: 12)]
    private string $etat = self::COMPOSITION;

    /** Choisi au moment de commencer le set, figé ensuite (D68) */
    #[ORM\Column(length: 8, options: ['default' => self::SAISIE_SCORE])]
    private string $saisie = self::SAISIE_SCORE;

    /** @var Collection<int, FeuilleMatchEvenement> */
    #[ORM\OneToMany(targetEntity: FeuilleMatchEvenement::class, mappedBy: 'set', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $evenements;

    public function __construct(FeuilleMatch $feuille, int $numero, int $pointsFin)
    {
        $this->feuille = $feuille;
        $this->numero = $numero;
        $this->pointsFin = $pointsFin;
        $this->evenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeuille(): FeuilleMatch
    {
        return $this->feuille;
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    /** @return list<?int> */
    public function getPostes(): array
    {
        return $this->postes;
    }

    /** @param list<?int> $postes */
    public function setPostes(array $postes): static
    {
        $this->postes = array_values($postes);

        return $this;
    }

    public function getPremierService(): ?string
    {
        return $this->premierService;
    }

    public function setPremierService(?string $premierService): static
    {
        $this->premierService = $premierService;

        return $this;
    }

    /** @return array{0: int, 1: int} */
    public function getScoreDepart(): array
    {
        return $this->scoreDepart;
    }

    /** @param array{0: int, 1: int} $scoreDepart */
    public function setScoreDepart(array $scoreDepart): static
    {
        $this->scoreDepart = $scoreDepart;

        return $this;
    }

    public function getPointsFin(): int
    {
        return $this->pointsFin;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getSaisie(): string
    {
        return $this->saisie;
    }

    public function setSaisie(string $saisie): static
    {
        $this->saisie = $saisie;

        return $this;
    }

    public function noteLesTouches(): bool
    {
        return $this->saisie === self::SAISIE_TOUCHES;
    }

    /** @return Collection<int, FeuilleMatchEvenement> */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function ajouterEvenement(FeuilleMatchEvenement $evenement): void
    {
        $this->evenements->add($evenement);
    }
}
