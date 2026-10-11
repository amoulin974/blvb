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
 * Les responsables du club sont désignés pour une saison (ResponsableClub). Pendant cette saison, ils saisissent
 * le score de tous les matchs des équipes du club (SaisieScoreService) ; pendant la saison en cours, ils modifient
 * la fiche du club (ClubVoter) et portent le rôle ROLE_RESPONSABLE_CLUB (User::getRoles).
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
    // Pas de caractère de contrôle (retour à la ligne, tabulation…) : le nom est repris dans d'autres formats
    #[Assert\Regex(pattern: '/^[^\p{C}]*$/u', message: 'Le nom ne doit pas contenir de retour à la ligne ni de caractère spécial invisible.')]
    private ?string $nom = null;

    /** Sigle affiché après le nom des équipes du club, ex. AOVB */
    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Indiquez le nom court du club.')]
    #[Assert\Length(max: 20)]
    // Repris dans le nom des équipes, donc dans les agendas (.ics) : lettres, chiffres, espace, point, &, apostrophe, tiret
    #[Assert\Regex(pattern: "/^[\\p{L}\\p{N}][\\p{L}\\p{N} .&'-]*$/u", message: 'L\'abréviation ne peut contenir que des lettres, des chiffres, des espaces et les signes . & \' -')]
    private ?string $nomCourt = null;

    /** Présentation du club (fiche publique), modifiable par ses responsables */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    /** Lien vers le site du club ou sa page de réseau social */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Indiquez une adresse web complète, ex. https://www.monclub.fr', requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $url = null;

    /** Nom du fichier de l'image du club (logo), dans public/uploads/clubs (ClubImageService) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** @var Collection<int, Equipe> */
    #[ORM\OneToMany(targetEntity: Equipe::class, mappedBy: 'club')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $equipes;

    /** @var Collection<int, Lieu> gymnases utilisés par le club (un gymnase peut être partagé entre clubs) */
    #[ORM\ManyToMany(targetEntity: Lieu::class)]
    #[ORM\JoinTable(name: 'club_lieu')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $lieux;

    /** @var Collection<int, ResponsableClub> désignations des responsables, par saison */
    #[ORM\OneToMany(targetEntity: ResponsableClub::class, mappedBy: 'club', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $responsabilites;

    public function __construct()
    {
        $this->equipes = new ArrayCollection();
        $this->lieux = new ArrayCollection();
        $this->responsabilites = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = $description === null ? null : trim($description);
        $this->description = $description === '' ? null : $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $url = $url === null ? null : trim($url);
        $this->url = $url === '' ? null : $url;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /** Le compte est-il responsable du club pour cette saison ? (droits : voir ClubVoter et SaisieScoreService) */
    public function estResponsable(?User $user, ?Saison $saison): bool
    {
        if ($user === null || $saison === null) {
            return false;
        }
        foreach ($this->responsabilites as $responsabilite) {
            if ($responsabilite->getUser() === $user && $responsabilite->getSaison() === $saison) {
                return true;
            }
        }

        return false;
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

    /** @return Collection<int, ResponsableClub> */
    public function getResponsabilites(): Collection
    {
        return $this->responsabilites;
    }

    /**
     * Responsables du club pour une saison.
     *
     * @return list<User>
     */
    public function getResponsables(?Saison $saison): array
    {
        $users = [];
        foreach ($this->responsabilites as $responsabilite) {
            if ($saison !== null && $responsabilite->getSaison() === $saison) {
                $users[] = $responsabilite->getUser();
            }
        }

        return $users;
    }

    /**
     * Fixe les responsables du club pour une saison : désigne les nouveaux, retire ceux qui ne sont plus dans la liste.
     * Les désignations des autres saisons ne changent pas.
     *
     * @param iterable<User> $users
     */
    public function setResponsables(Saison $saison, iterable $users): static
    {
        $voulus = [];
        foreach ($users as $user) {
            $voulus[spl_object_id($user)] = $user;
        }
        foreach ($this->responsabilites->toArray() as $responsabilite) {
            if ($responsabilite->getSaison() === $saison) {
                if (isset($voulus[spl_object_id($responsabilite->getUser())])) {
                    unset($voulus[spl_object_id($responsabilite->getUser())]);
                } else {
                    $this->responsabilites->removeElement($responsabilite);
                    $responsabilite->getUser()->retirerResponsabilite($responsabilite);
                }
            }
        }
        foreach ($voulus as $user) {
            $this->responsabilites->add(new ResponsableClub($this, $user, $saison));
        }

        return $this;
    }
}
