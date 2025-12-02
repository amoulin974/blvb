<?php

namespace App\Entity;

use App\Repository\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipeRepository::class)]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'equipes')]
    private ?Lieu $lieu = null;

    /**
     * @var Collection<int, Partie>
     */
    #[ORM\OneToMany(targetEntity: Partie::class, mappedBy: 'id_equipe_recoit')]
    private Collection $parties_reception;

    /**
     * @var Collection<int, Partie>
     */
    #[ORM\OneToMany(targetEntity: Partie::class, mappedBy: 'id_equipe_deplace')]
    private Collection $parties_deplacement;

    /**
     * @var Collection<int, Poule>
     */
    #[ORM\ManyToMany(targetEntity: Poule::class, mappedBy: 'equipes')]
    private Collection $Poules;

    /**
     * @var Collection<int, Classement>
     */
    #[ORM\OneToMany(targetEntity: Classement::class, mappedBy: 'equipe')]
    private Collection $classements;

    #[ORM\ManyToOne]
    private ?User $capitaine = null;

    public function __construct()
    {
        $this->parties_reception = new ArrayCollection();
        $this->parties_deplacement = new ArrayCollection();
        $this->Poules = new ArrayCollection();
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

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    /**
     * @return Collection<int, Partie>
     */
    public function getPartiesReception(): Collection
    {
        return $this->parties_reception;
    }

    public function addPartiesReception(Partie $partiesReception): static
    {
        if (!$this->parties_reception->contains($partiesReception)) {
            $this->parties_reception->add($partiesReception);
            $partiesReception->setIdEquipeRecoit($this);
        }

        return $this;
    }

    public function removePartiesReception(Partie $partiesReception): static
    {
        if ($this->parties_reception->removeElement($partiesReception)) {
            // set the owning side to null (unless already changed)
            if ($partiesReception->getIdEquipeRecoit() === $this) {
                $partiesReception->setIdEquipeRecoit(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Partie>
     */
    public function getPartiesDeplacement(): Collection
    {
        return $this->parties_deplacement;
    }

    public function addPartiesDeplacement(Partie $partiesDeplacement): static
    {
        if (!$this->parties_deplacement->contains($partiesDeplacement)) {
            $this->parties_deplacement->add($partiesDeplacement);
            $partiesDeplacement->setIdEquipeDeplace($this);
        }

        return $this;
    }

    public function removePartiesDeplacement(Partie $partiesDeplacement): static
    {
        if ($this->parties_deplacement->removeElement($partiesDeplacement)) {
            // set the owning side to null (unless already changed)
            if ($partiesDeplacement->getIdEquipeDeplace() === $this) {
                $partiesDeplacement->setIdEquipeDeplace(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Poule>
     */
    public function getPoules(): Collection
    {
        return $this->Poules;
    }

    public function addPoule(Poule $poule): static
    {
        if (!$this->Poules->contains($poule)) {
            $this->Poules->add($poule);
        }

        return $this;
    }

    public function removePoule(Poule $poule): static
    {
        $this->Poules->removeElement($poule);

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
            $classement->setEquipe($this);
        }

        return $this;
    }

    public function removeClassement(Classement $classement): static
    {
        if ($this->classements->removeElement($classement)) {
            // set the owning side to null (unless already changed)
            if ($classement->getEquipe() === $this) {
                $classement->setEquipe(null);
            }
        }

        return $this;
    }

    public function getCapitaine(): ?User
    {
        return $this->capitaine;
    }

    public function setCapitaine(?User $capitaine): static
    {
        $this->capitaine = $capitaine;

        return $this;
    }
}
