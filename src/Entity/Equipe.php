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
    private ?Lieu $id_lieu = null;

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

    public function __construct()
    {
        $this->parties_reception = new ArrayCollection();
        $this->parties_deplacement = new ArrayCollection();
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

    public function getIdLieu(): ?Lieu
    {
        return $this->id_lieu;
    }

    public function setIdLieu(?Lieu $id_lieu): static
    {
        $this->id_lieu = $id_lieu;

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
}
