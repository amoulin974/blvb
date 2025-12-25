<?php

namespace App\Entity;

use App\Repository\PouleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PouleRepository::class)]
class Poule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'poules')]
    private ?Phase $phase = null;

    /**
     * @var Collection<int, Partie>
     */
    #[ORM\OneToMany(targetEntity: Partie::class, mappedBy: 'poule')]
    private Collection $parties;

    #[ORM\OneToMany(targetEntity: Journee::class, mappedBy: 'poule')]
    private Collection $journees;

    /**
     * @var Collection<int, Equipe>
     */
    #[ORM\ManyToMany(targetEntity: Equipe::class, inversedBy: 'Poules')]
    #[ORM\JoinTable(name: 'equipe_poule')]
    private Collection $equipes;

    /**
     * @var Collection<int, Classement>
     */
    #[ORM\OneToMany(targetEntity: Classement::class, mappedBy: 'poule')]
    private Collection $classements;

    #[ORM\Column]
    private ?int $nb_montee_defaut = null;

    #[ORM\Column]
    private ?int $nb_descente_defaut = null;

    public function __construct()
    {
        $this->parties = new ArrayCollection();
        $this->equipes = new ArrayCollection();
        $this->journees = new ArrayCollection();
        $this->classements = new ArrayCollection();
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

    public function getPhase(): ?Phase
    {
        return $this->phase;
    }

    public function setPhase(?Phase $phase): static
    {
        $this->phase = $phase;

        return $this;
    }



    /**
     * @return Collection<int, Partie>
     */
    public function getParties(): Collection
    {
        return $this->parties;
    }

    public function addParty(Partie $party): static
    {
        if (!$this->parties->contains($party)) {
            $this->parties->add($party);
            $party->setIdPoule($this);
        }

        return $this;
    }

 
    public function removeParty(Partie $party): static
    {
        if ($this->parties->removeElement($party)) {
            // set the owning side to null (unless already changed)
            if ($party->getPoule() === $this) {
                $party->setPoule(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Equipe>
     */
    public function getEquipes(): Collection
    {
        return $this->equipes;
    }

    public function addEquipe(Equipe $equipe): static
    {
        if (!$this->equipes->contains($equipe)) {
            $this->equipes->add($equipe);
            $equipe->addPoule($this);
        }

        return $this;
    }

    public function removeEquipe(Equipe $equipe): static
    {
        if ($this->equipes->removeElement($equipe)) {
            $equipe->removePoule($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Journee>
     */
    public function getJournees(): Collection
    {
        return $this->journees;
    }

    public function addJournee(Journee $journee): static
    {
        if (!$this->journees->contains($journee)) {
            $this->journees->add($journee);
            $journee->setPoule($this);
        }
        return $this;
    }

    public function removeJournee(Journee $journee): static
    {
        if ($this->journees->removeElement($journee)) {
            if ($journee->getPoule() === $this) {
                $journee->setPoule(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Classement>
     */
    public function getClassements(): Collection
    {
        return $this->classements;
    }

    public function addClassement(Classement $classement): static
    {
        if (!$this->classements->contains($classement)) {
            $this->classements->add($classement);
            $classement->setPoule($this);
        }

        return $this;
    }

    public function removeClassement(Classement $classement): static
    {
        if ($this->classements->removeElement($classement)) {
            // set the owning side to null (unless already changed)
            if ($classement->getPoule() === $this) {
                $classement->setPoule(null);
            }
        }

        return $this;
    }

    public function setClassements(ArrayCollection $classements): self
    {
        $this->classements = $classements;
        return $this;
    }

    public function getNbMonteeDefaut(): ?int
    {
        return $this->nb_montee_defaut;
    }

    public function setNbMonteeDefaut(int $nb_montee_defaut): static
    {
        $this->nb_montee_defaut = $nb_montee_defaut;

        return $this;
    }

    public function getNbDescenteDefaut(): ?int
    {
        return $this->nb_descente_defaut;
    }

    public function setNbDescenteDefaut(int $nb_descente_defaut): static
    {
        $this->nb_descente_defaut = $nb_descente_defaut;

        return $this;
    }
}
