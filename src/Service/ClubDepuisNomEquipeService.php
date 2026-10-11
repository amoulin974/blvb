<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Historiquement, le club d'une équipe était écrit à la fin de son nom : « EROAK (AOVB) ».
 * Ce service sépare le nom de l'équipe et ce nom court, rattache l'équipe au club correspondant
 * (créé au besoin) et enregistre le nom sans le club ; l'affichage le rajoute (Equipe::getNom).
 *
 * Utilisé par la commande app:clubs:depuis-noms-equipes (reprise des équipes existantes) et par
 * l'import Excel des équipes.
 */
final class ClubDepuisNomEquipeService
{
    /** Nom court reconnu : lettres majuscules et chiffres, 2 à 10 caractères, entre parenthèses en fin de nom. */
    private const MOTIF = '/^(?<nom>.*\S)\s*\((?<court>[A-Z0-9]{2,10})\)$/u';

    /** @var array<string, Club> clubs créés pendant ce traitement, pas encore en base */
    private array $nouveaux = [];

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Sépare « EROAK (AOVB) » en ['nom' => 'EROAK', 'court' => 'AOVB'].
     * Sans nom court reconnu : ['nom' => nom nettoyé des espaces, 'court' => null].
     *
     * @return array{nom: string, court: ?string}
     */
    public function separer(string $nom): array
    {
        $nom = trim($nom);
        if (preg_match(self::MOTIF, $nom, $m)) {
            return ['nom' => $m['nom'], 'court' => $m['court']];
        }

        return ['nom' => $nom, 'court' => null];
    }

    /**
     * Rattache l'équipe au club dont le nom court termine son nom et retire ce nom court du nom enregistré.
     * Le club est créé s'il n'existe pas (nom long = nom court, à compléter dans l'admin) ; à enregistrer par flush.
     *
     * @return array{statut: 'rattachee'|'sans_club'|'conflit', club: ?Club, cree: bool, nom: string}
     *   conflit : l'équipe a déjà un autre club ; rien n'est modifié
     */
    public function rattacher(Equipe $equipe): array
    {
        ['nom' => $nom, 'court' => $court] = $this->separer((string) $equipe->getNomSansClub());

        if ($court === null) {
            $equipe->setNom($nom);

            return ['statut' => 'sans_club', 'club' => null, 'cree' => false, 'nom' => $nom];
        }

        if ($equipe->getClub() !== null && $equipe->getClub()->getNomCourt() !== $court) {
            return ['statut' => 'conflit', 'club' => $equipe->getClub(), 'cree' => false, 'nom' => (string) $equipe->getNomSansClub()];
        }

        $cree = false;
        $club = $this->nouveaux[$court] ?? $this->clubRepository->findOneBy(['nomCourt' => $court]);
        if ($club === null) {
            $club = (new Club())->setNom($court)->setNomCourt($court);
            $this->em->persist($club);
            $this->nouveaux[$court] = $club;
            $cree = true;
        }

        $club->addEquipe($equipe);
        $equipe->setNom($nom);

        return ['statut' => 'rattachee', 'club' => $club, 'cree' => $cree, 'nom' => $nom];
    }
}
