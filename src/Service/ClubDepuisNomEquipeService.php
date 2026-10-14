<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Historiquement, le club d'une équipe était écrit à la fin de son nom : « EROAK (AOVB) ».
 * Ce service sépare le nom de l'équipe et ce sigle, rattache l'équipe au club correspondant
 * (créé au besoin) et enregistre le nom sans le sigle ; l'affichage ajoute le nom court du club (Equipe::getNom).
 *
 * Le sigle désigne le club par son nom court (« (VBLC) ») ou par un ancien sigle traduit par la table
 * d'alias (« (SJDL) » → VBLC). Utilisé par la commande app:clubs:depuis-noms-equipes et par l'import Excel.
 */
final class ClubDepuisNomEquipeService
{
    /** Ce qui est entre parenthèses en fin de nom. */
    private const MOTIF = '/^(?<nom>.*\S)\s*\((?<court>[^()]{1,20})\)$/u';

    /** Sigle inconnu accepté pour créer un club : majuscules et chiffres, 2 à 10 caractères. */
    private const SIGLE_NOUVEAU_CLUB = '/^[A-Z0-9]{2,10}$/';

    /** @var array<string, Club> clubs créés pendant ce traitement, pas encore en base (clé : nom court en majuscules, la base ne distingue pas la casse) */
    private array $nouveaux = [];

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Sépare « EROAK (AOVB) » en ['nom' => 'EROAK', 'court' => 'AOVB'].
     * Sans parenthèses en fin de nom : ['nom' => nom nettoyé des espaces, 'court' => null].
     *
     * @return array{nom: string, court: ?string}
     */
    public function separer(string $nom): array
    {
        $nom = trim($nom);
        if (preg_match(self::MOTIF, $nom, $m)) {
            return ['nom' => $m['nom'], 'court' => trim($m['court'])];
        }

        return ['nom' => $nom, 'court' => null];
    }

    /**
     * Club de ce nom court : en base, créé pendant ce traitement, ou créé maintenant (nom long fourni,
     * sinon le nom court, à compléter dans l'admin). Un nouveau club est enregistré au prochain flush.
     *
     * @return array{club: Club, cree: bool}
     */
    public function club(string $nomCourt, ?string $nomLong = null): array
    {
        $club = $this->nouveaux[mb_strtoupper($nomCourt)] ?? $this->clubRepository->findOneBy(['nomCourt' => $nomCourt]);
        if ($club !== null) {
            return ['club' => $club, 'cree' => false];
        }

        $club = (new Club())->setNomCourt($nomCourt)->setNom($nomLong ?? $nomCourt);
        $this->em->persist($club);

        return ['club' => $this->nouveaux[mb_strtoupper($nomCourt)] = $club, 'cree' => true];
    }

    /**
     * Rattache l'équipe au club désigné par le sigle qui termine son nom et retire ce sigle du nom enregistré.
     *
     * @param array<string, string> $alias anciens sigles => nom court du club, ex. ['SJDL' => 'VBLC']
     *
     * @return array{statut: 'rattachee'|'sans_club'|'conflit', club: ?Club, cree: bool, nom: string}
     *   sans_club : pas de sigle reconnu (nom seulement nettoyé des espaces) ;
     *   conflit : l'équipe a déjà un autre club, rien n'est modifié
     */
    public function rattacher(Equipe $equipe, array $alias = []): array
    {
        ['nom' => $nom, 'court' => $sigle] = $this->separer((string) $equipe->getNomSansClub());
        $nomCourt = $sigle === null ? null : ($alias[$sigle] ?? $sigle);

        // Sigle qui ne désigne aucun club connu et n'a pas la forme d'un sigle : parenthèses ordinaires du nom
        if ($nomCourt !== null && !isset($alias[$sigle]) && !isset($this->nouveaux[mb_strtoupper($nomCourt)])
            && $this->clubRepository->findOneBy(['nomCourt' => $nomCourt]) === null
            && !preg_match(self::SIGLE_NOUVEAU_CLUB, $nomCourt)) {
            $nomCourt = null;
            $nom = trim((string) $equipe->getNomSansClub());
        }

        if ($nomCourt === null) {
            $equipe->setNom($nom);

            return ['statut' => 'sans_club', 'club' => null, 'cree' => false, 'nom' => $nom];
        }

        if ($equipe->getClub() !== null && $equipe->getClub()->getNomCourt() !== $nomCourt) {
            return ['statut' => 'conflit', 'club' => $equipe->getClub(), 'cree' => false, 'nom' => (string) $equipe->getNomSansClub()];
        }

        ['club' => $club, 'cree' => $cree] = $this->club($nomCourt);
        $club->addEquipe($equipe);
        $equipe->setNom($nom);

        return ['statut' => 'rattachee', 'club' => $club, 'cree' => $cree, 'nom' => $nom];
    }
}
