<?php

namespace App\Entity;

use App\Repository\JoueurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: JoueurRepository::class)]
#[ORM\Index(columns: ['nom', 'prenom'])]
#[UniqueEntity(fields: ['user'], message: 'Ce compte utilisateur est déjà lié à une autre fiche joueur.')]
class Joueur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, unique: true)]
    private ?User $user = null;

    /**
     * @var Collection<int, MembreEquipe>
     */
    #[ORM\OneToMany(targetEntity: MembreEquipe::class, mappedBy: 'joueur')]
    private Collection $membres;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, MembreEquipe>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(MembreEquipe $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setJoueur($this);
        }

        return $this;
    }

    public function removeMembre(MembreEquipe $membre): static
    {
        if ($this->membres->removeElement($membre)) {
            if ($membre->getJoueur() === $this) {
                $membre->setJoueur(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }
}
