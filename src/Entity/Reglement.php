<?php

namespace App\Entity;

use App\Repository\ReglementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Règlement d'une saison (docs/wizard-saison/decisions.md, D55 à D57) : texte HTML rédigé par les admins, déjà
 * nettoyé (App\Reglement\EditionReglement), où des codes courts (« [bareme phase=P2] ») marquent les éléments
 * calculés à partir des paramètres de la saison. Chaque enregistrement garde la version précédente.
 */
#[ORM\Entity(repositoryClass: ReglementRepository::class)]
class Reglement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Saison $saison = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $contenu = '';

    /** Dernier enregistrement (« Mis à jour le … » sur la page publique) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $modifieLe = null;

    /** Auteur du dernier enregistrement ; null si son compte a été supprimé */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $modifiePar = null;

    /**
     * Versions précédentes, de la plus récente à la plus ancienne
     *
     * @var Collection<int, ReglementVersion>
     */
    #[ORM\OneToMany(targetEntity: ReglementVersion::class, mappedBy: 'reglement', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['enregistreLe' => 'DESC', 'id' => 'DESC'])]
    private Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaison(): ?Saison
    {
        return $this->saison;
    }

    public function setSaison(Saison $saison): static
    {
        $this->saison = $saison;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getModifieLe(): ?\DateTimeImmutable
    {
        return $this->modifieLe;
    }

    public function setModifieLe(?\DateTimeImmutable $modifieLe): static
    {
        $this->modifieLe = $modifieLe;

        return $this;
    }

    public function getModifiePar(): ?User
    {
        return $this->modifiePar;
    }

    public function setModifiePar(?User $modifiePar): static
    {
        $this->modifiePar = $modifiePar;

        return $this;
    }

    /**
     * @return Collection<int, ReglementVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(ReglementVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setReglement($this);
        }

        return $this;
    }
}
