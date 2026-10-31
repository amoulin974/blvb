<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Joueur présent sur une page du match (D67) : un membre de l'équipe pour la saison du match, jamais un renfort. Le retrait
 * du membre de l'équipe supprime la présence ; les sets qui le citaient affichent « Joueur retiré ».
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'feuille_match_joueur_unique', columns: ['feuille_id', 'membre_id'])]
class FeuilleMatchJoueur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'joueurs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FeuilleMatch $feuille;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MembreEquipe $membre;

    public function __construct(FeuilleMatch $feuille, MembreEquipe $membre)
    {
        $this->feuille = $feuille;
        $this->membre = $membre;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeuille(): FeuilleMatch
    {
        return $this->feuille;
    }

    public function getMembre(): MembreEquipe
    {
        return $this->membre;
    }
}
