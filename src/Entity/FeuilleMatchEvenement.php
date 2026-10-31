<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Événement du journal d'un set (D67) : point, remplacement, correction du score ; plus tard les actions du mode
 * intermédiaire. « Précédent » le marque annulé (il reste dans l'historique, barré), « Suivant » le rétablit. L'ordre est
 * calculé par le serveur ; l'uuid, généré par le téléphone, rend sans effet un envoi rejoué après une coupure.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'feuille_match_evenement_ordre', columns: ['set_id', 'ordre'])]
#[ORM\UniqueConstraint(name: 'feuille_match_evenement_uuid', columns: ['uuid'])]
class FeuilleMatchEvenement
{
    public const POINT = 'point';
    public const REMPLACEMENT = 'remplacement';
    public const CORRECTION = 'correction';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(name: 'set_id', nullable: false, onDelete: 'CASCADE')]
    private FeuilleMatchSet $set;

    #[ORM\Column]
    private int $ordre;

    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 20)]
    private string $type;

    /**
     * point : {pour: nous|eux} ; remplacement : {sort: ?int, entre: int} (identifiants de FeuilleMatchJoueur, sort null
     * pour une place libre) ; correction : {nous: int, eux: int, service: nous|eux}. Pas de motif de remplacement
     * (« blessure » serait une donnée de santé).
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $donnees;

    /** Compte qui a saisi : aide à la correction, pas une preuve (un compte peut être prêté, D66) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $saisiPar;

    #[ORM\Column]
    private \DateTimeImmutable $saisiLe;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $annuleLe = null;

    /** @param array<string, mixed> $donnees */
    public function __construct(FeuilleMatchSet $set, int $ordre, string $uuid, string $type, array $donnees, ?User $saisiPar)
    {
        $this->set = $set;
        $this->ordre = $ordre;
        $this->uuid = $uuid;
        $this->type = $type;
        $this->donnees = $donnees;
        $this->saisiPar = $saisiPar;
        $this->saisiLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSet(): FeuilleMatchSet
    {
        return $this->set;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getDonnees(): array
    {
        return $this->donnees;
    }

    public function getSaisiPar(): ?User
    {
        return $this->saisiPar;
    }

    public function getSaisiLe(): \DateTimeImmutable
    {
        return $this->saisiLe;
    }

    public function getAnnuleLe(): ?\DateTimeImmutable
    {
        return $this->annuleLe;
    }

    public function estAnnule(): bool
    {
        return $this->annuleLe !== null;
    }

    public function annuler(): void
    {
        $this->annuleLe = new \DateTimeImmutable();
    }

    public function retablir(): void
    {
        $this->annuleLe = null;
    }
}
