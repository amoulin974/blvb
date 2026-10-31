<?php

namespace App\Entity;

use App\Repository\ParametresSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Paramètres du site, modifiables par les admins (D62) : une seule ligne, d'identifiant 1 (ParametresSiteRepository::courants). */
#[ORM\Entity(repositoryClass: ParametresSiteRepository::class)]
class ParametresSite
{
    public const ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    /** Limitation des essais de connexion (D61) ; désactivée tant qu'un admin ne l'a pas activée après vérification */
    #[ORM\Column(options: ['default' => false])]
    private bool $connexionLimitee = false;

    /** Dernière vérification réussie (VerificationConnexion), valable une heure pour activer */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $connexionVerifieeLe = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Indiquez une adresse e-mail valide, par exemple contact@blvb.fr.')]
    // L'adresse entre dans des liens mailto : pas de ? ni de & qui y ajouteraient un destinataire caché
    #[Assert\Regex(pattern: '/^[A-Za-z0-9._+-]+@/', message: 'Indiquez une adresse e-mail valide, par exemple contact@blvb.fr.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse est trop longue : {{ limit }} caractères au plus.')]
    private ?string $adresseContact = null;

    /** Fichier du logo, dans le dossier app.dossier_images_site ; null : logo d'origine (front/fragments/_logo.html.twig) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $logo = null;

    /** Volley Stats (D68) : part d'échanges détaillés, en %, pour qu'un set compte dans les classements des joueurs */
    #[ORM\Column(options: ['default' => 80])]
    #[\Symfony\Component\Validator\Constraints\NotNull]
    #[\Symfony\Component\Validator\Constraints\Range(min: 0, max: 100, notInRangeMessage: 'Indiquez un pourcentage entre {{ min }} et {{ max }}.')]
    private ?int $seuilEchangesDetailles = 80;

    public function getId(): int
    {
        return $this->id;
    }

    public function isConnexionLimitee(): bool
    {
        return $this->connexionLimitee;
    }

    public function setConnexionLimitee(bool $connexionLimitee): static
    {
        $this->connexionLimitee = $connexionLimitee;

        return $this;
    }

    public function getConnexionVerifieeLe(): ?\DateTimeImmutable
    {
        return $this->connexionVerifieeLe;
    }

    public function setConnexionVerifieeLe(?\DateTimeImmutable $connexionVerifieeLe): static
    {
        $this->connexionVerifieeLe = $connexionVerifieeLe;

        return $this;
    }

    public function getAdresseContact(): ?string
    {
        return $this->adresseContact;
    }

    public function setAdresseContact(?string $adresseContact): static
    {
        $adresseContact = $adresseContact === null ? null : trim($adresseContact);
        $this->adresseContact = $adresseContact === '' ? null : $adresseContact;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getSeuilEchangesDetailles(): ?int
    {
        return $this->seuilEchangesDetailles;
    }

    public function setSeuilEchangesDetailles(?int $seuil): static
    {
        $this->seuilEchangesDetailles = $seuil;

        return $this;
    }
}
