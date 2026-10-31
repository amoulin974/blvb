<?php

namespace App\VolleyStats;

use App\Entity\FeuilleMatch;
use App\Entity\MembreEquipe;
use App\Repository\FeuilleMatchRepository;
use App\Repository\MembreEquipeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Écritures de la page du match (D67) : création, joueurs présents. Chaque identifiant reçu est vérifié côté serveur
 * (membre de l'équipe de la page pour la saison du match) ; les droits sont vérifiés avant, par FeuilleMatchVoter.
 */
final class FeuilleMatchService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ManagerRegistry $doctrine,
        private FeuilleMatchRepository $feuilles,
        private MembreEquipeRepository $membres,
    ) {
    }

    /** Membres de l'équipe pour la saison du match, triés par nom @return list<MembreEquipe> */
    public function membres(MatchEquipe $match): array
    {
        $membres = $this->membres->findRosterBySaison($match->equipe, $match->saison());
        usort($membres, fn (MembreEquipe $a, MembreEquipe $b) => [$a->getJoueur()?->getPrenom(), $a->getJoueur()?->getNom()] <=> [$b->getJoueur()?->getPrenom(), $b->getJoueur()?->getNom()]);

        return $membres;
    }

    /**
     * Joueurs présents proposés tant que la page n'existe pas : ceux du match précédent de l'équipe (encore membres), sinon
     * toute l'équipe.
     *
     * @return list<int> identifiants de MembreEquipe
     */
    public function presentsProposes(MatchEquipe $match): array
    {
        $membres = array_map(fn (MembreEquipe $m) => $m->getId(), $this->membres($match));
        $precedente = $this->feuilles->precedente($match->equipe, $match->partie);
        if ($precedente === null) {
            return $membres;
        }

        return array_values(array_intersect($membres, array_map(fn ($j) => $j->getMembre()->getId(), $precedente->getJoueurs()->toArray())));
    }

    /**
     * Crée la page du match si elle n'existe pas (idempotent : deux envois simultanés donnent la même page). Joueurs présents
     * d'office : ceux du match précédent de l'équipe, sinon toute l'équipe.
     */
    public function preparer(MatchEquipe $match): FeuilleMatch
    {
        if ($match->feuille !== null) {
            return $match->feuille;
        }
        $feuille = new FeuilleMatch($match->partie, $match->equipe);
        $proposes = $this->presentsProposes($match);
        foreach ($this->membres($match) as $membre) {
            if (\in_array($membre->getId(), $proposes, true)) {
                $feuille->ajouterJoueur($membre);
            }
        }
        try {
            $this->em->persist($feuille);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Créée entre-temps par un autre téléphone : on relit celle-là
            $this->doctrine->resetManager();

            return $this->feuilles->pour($match->partie, $match->equipe) ?? throw new SaisieInvalide('La page du match n\'a pas pu être créée : rechargez la page.');
        }

        return $feuille;
    }

    /**
     * Joueurs présents : coche exactement ces membres. Refuse un identifiant qui n'est pas un membre de l'équipe de la page
     * pour la saison du match, et le retrait d'un joueur placé dans un set.
     *
     * @param list<mixed> $identifiants
     */
    public function presents(FeuilleMatch $feuille, MatchEquipe $match, array $identifiants): void
    {
        $membres = [];
        foreach ($this->membres($match) as $membre) {
            $membres[$membre->getId()] = $membre;
        }
        $voulus = [];
        foreach ($identifiants as $identifiant) {
            if (!\is_scalar($identifiant) || !ctype_digit((string) $identifiant) || !isset($membres[(int) $identifiant])) {
                throw new SaisieInvalide('Un des joueurs choisis ne fait pas partie de l\'équipe : rechargez la page.');
            }
            $voulus[(int) $identifiant] = $membres[(int) $identifiant];
        }
        $places = $this->joueursPlaces($feuille);
        foreach ($feuille->getJoueurs()->toArray() as $joueur) {
            if (!isset($voulus[$joueur->getMembre()->getId()])) {
                if (\in_array($joueur->getId(), $places, true)) {
                    throw new SaisieInvalide('Un joueur placé dans un set ne peut pas être retiré des présents.');
                }
                $feuille->retirerJoueur($joueur);
            }
        }
        foreach ($voulus as $membre) {
            $feuille->ajouterJoueur($membre);
        }
        $feuille->marquerModifiee();
        $this->em->flush();
    }

    /** Joueurs cités dans un set (composition de départ ou remplacement) @return list<int> */
    private function joueursPlaces(FeuilleMatch $feuille): array
    {
        $ids = [];
        foreach ($feuille->getSets() as $set) {
            array_push($ids, ...array_filter($set->getPostes(), fn ($id) => $id !== null));
            foreach ($set->getEvenements() as $evenement) {
                $donnees = $evenement->getDonnees();
                foreach (['sort', 'entre'] as $cle) {
                    if (isset($donnees[$cle])) {
                        $ids[] = $donnees[$cle];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
