<?php

namespace App\Entity;

use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Club : regroupe des équipes. Nom long (ex. « Anglet Olympique Volley-Ball ») et nom court, le sigle
 * affiché entre parenthèses après le nom de ses équipes (ex. « EROAK (AOVB) », voir Equipe::getNom).
 * Le gymnase ne suffit pas à le déduire : plusieurs clubs partagent
 * parfois un gymnase, et un club peut jouer dans plusieurs gymnases.
 *
 * Les responsables du club peuvent saisir le score des matchs reçus par ses équipes, comme le
 * capitaine de l'équipe qui reçoit (règle unique : App\Security\ScoreVoter). Un utilisateur responsable
 * d'au moins un club reçoit le rôle ROLE_RESPONSABLE_CLUB (User::getRoles).
 */
#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[UniqueEntity(fields: ['nom'], message: 'Un club porte déjà ce nom.')]
#[UniqueEntity(fields: ['nomCourt'], message: 'Un club porte déjà ce nom court.')]
class Club
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez le nom du club.')]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    /** Sigle affiché après le nom des équipes du club, ex. AOVB */
    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez le nom court du club.')]
    #[Assert\Length(max: 20)]
    private ?string $nomCourt = null;

    /** @var Collection<int, Equipe> */
    #[ORM\OneToMany(targetEntity: Equipe::class, mappedBy: 'club')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $equipes;

    /** @var Collection<int, Lieu> gymnases utilisés par le club (un gymnase peut être partagé entre clubs) */
    #[ORM\ManyToMany(targetEntity: Lieu::class)]
    #[ORM\JoinTable(name: 'club_lieu')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $lieux;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'clubsResponsable')]
    #[ORM\JoinTable(name: 'club_responsable')]
    private Collection $responsables;

    public function __construct()
    {
        $this->equipes = new ArrayCollection();
        $this->lieux = new ArrayCollection();
        $this->responsables = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->nom;
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

    public function getNomCourt(): ?string
    {
        return $this->nomCourt;
    }

    public function setNomCourt(string $nomCourt): static
    {
        $this->nomCourt = trim($nomCourt);

        return $this;
    }

    /** @return Collection<int, Equipe> */
    public function getEquipes(): Collection
    {
        return $this->equipes;
    }

    public function addEquipe(Equipe $equipe): static
    {
        if (!$this->equipes->contains($equipe)) {
            $this->equipes->add($equipe);
            $equipe->setClub($this);
        }

        return $this;
    }

    public function removeEquipe(Equipe $equipe): static
    {
        if ($this->equipes->removeElement($equipe) && $equipe->getClub() === $this) {
            $equipe->setClub(null);
        }

        return $this;
    }

    /** @return Collection<int, Lieu> */
    public function getLieux(): Collection
    {
        return $this->lieux;
    }

    /**
     * Remplace les gymnases du club. Nécessaire au formulaire et aux factories : le composant PropertyAccess
     * ne sait pas mettre « lieux » au singulier et ne trouve donc pas addLieu / removeLieu.
     *
     * @param iterable<Lieu> $lieux
     */
    public function setLieux(iterable $lieux): static
    {
        $this->lieux->clear();
        foreach ($lieux as $lieu) {
            $this->addLieu($lieu);
        }

        return $this;
    }

    public function addLieu(Lieu $lieu): static
    {
        if (!$this->lieux->contains($lieu)) {
            $this->lieux->add($lieu);
        }

        return $this;
    }

    public function removeLieu(Lieu $lieu): static
    {
        $this->lieux->removeElement($lieu);

        return $this;
    }

    /** @return Collection<int, User> */
    public function getResponsables(): Collection
    {
        return $this->responsables;
    }

    public function addResponsable(User $user): static
    {
        if (!$this->responsables->contains($user)) {
            $this->responsables->add($user);
            $user->addClubResponsable($this);
        }

        return $this;
    }

    public function removeResponsable(User $user): static
    {
        if ($this->responsables->removeElement($user)) {
            $user->removeClubResponsable($this);
        }

        return $this;
    }
}
