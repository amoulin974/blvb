<?php

namespace App\Entity;

use App\Repository\FeuilleMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Page du match d'une équipe pour un match du championnat (Volley Stats, D66, D67) : joueurs présents, composition de
 * chaque set, journal des points et des remplacements. Visible de l'équipe et du responsable de son club seulement
 * (FeuilleMatchVoter) ; n'écrit jamais dans Partie ni Classement. Les deux équipes d'un match ont chacune la leur.
 */
#[ORM\Entity(repositoryClass: FeuilleMatchRepository::class)]
#[ORM\UniqueConstraint(name: 'feuille_match_unique', columns: ['partie_id', 'equipe_id'])]
class FeuilleMatch
{
    public const MODE_INTERMEDIAIRE = 'intermediaire';

    public const PREPARATION = 'preparation';
    public const EN_COURS = 'en_cours';
    public const TERMINEE = 'terminee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Partie $partie;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Equipe $equipe;

    /** Mode de saisie (D66) : seul le mode intermédiaire existe dans la première version */
    #[ORM\Column(length: 20)]
    private string $mode = self::MODE_INTERMEDIAIRE;

    /** Préparation, en cours, terminée ; le gel (7 jours après la fin) est calculé par FeuilleMatchVoter, pas stocké */
    #[ORM\Column(length: 20)]
    private string $etat = self::PREPARATION;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column]
    private \DateTimeImmutable $modifieLe;

    /** Point de départ du délai de correction de 7 jours (D66) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $termineeLe = null;

    /** @var Collection<int, FeuilleMatchJoueur> */
    #[ORM\OneToMany(targetEntity: FeuilleMatchJoueur::class, mappedBy: 'feuille', cascade: ['persist'], orphanRemoval: true)]
    private Collection $joueurs;

    /** @var Collection<int, FeuilleMatchSet> */
    #[ORM\OneToMany(targetEntity: FeuilleMatchSet::class, mappedBy: 'feuille', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['numero' => 'ASC'])]
    private Collection $sets;

    public function __construct(Partie $partie, Equipe $equipe)
    {
        $this->partie = $partie;
        $this->equipe = $equipe;
        $this->creeLe = $this->modifieLe = new \DateTimeImmutable();
        $this->joueurs = new ArrayCollection();
        $this->sets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartie(): Partie
    {
        return $this->partie;
    }

    public function getEquipe(): Equipe
    {
        return $this->equipe;
    }

    /** Saison du match, la seule qui compte pour les droits (jamais celle choisie dans le menu) */
    public function getSaison(): ?Saison
    {
        return $this->partie->getPoule()?->getPhase()?->getSaison();
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;
        $this->termineeLe = $etat === self::TERMINEE ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function getModifieLe(): \DateTimeImmutable
    {
        return $this->modifieLe;
    }

    public function marquerModifiee(): static
    {
        $this->modifieLe = new \DateTimeImmutable();

        return $this;
    }

    public function getTermineeLe(): ?\DateTimeImmutable
    {
        return $this->termineeLe;
    }

    /** @return Collection<int, FeuilleMatchJoueur> */
    public function getJoueurs(): Collection
    {
        return $this->joueurs;
    }

    public function joueur(int $id): ?FeuilleMatchJoueur
    {
        foreach ($this->joueurs as $joueur) {
            if ($joueur->getId() === $id) {
                return $joueur;
            }
        }

        return null;
    }

    public function joueurDuMembre(MembreEquipe $membre): ?FeuilleMatchJoueur
    {
        foreach ($this->joueurs as $joueur) {
            if ($joueur->getMembre() === $membre) {
                return $joueur;
            }
        }

        return null;
    }

    public function ajouterJoueur(MembreEquipe $membre): FeuilleMatchJoueur
    {
        $joueur = $this->joueurDuMembre($membre) ?? new FeuilleMatchJoueur($this, $membre);
        if (!$this->joueurs->contains($joueur)) {
            $this->joueurs->add($joueur);
        }

        return $joueur;
    }

    public function retirerJoueur(FeuilleMatchJoueur $joueur): void
    {
        $this->joueurs->removeElement($joueur);
    }

    /** @return Collection<int, FeuilleMatchSet> */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function set(int $numero): ?FeuilleMatchSet
    {
        foreach ($this->sets as $set) {
            if ($set->getNumero() === $numero) {
                return $set;
            }
        }

        return null;
    }

    public function ajouterSet(FeuilleMatchSet $set): void
    {
        $this->sets->add($set);
    }
}
