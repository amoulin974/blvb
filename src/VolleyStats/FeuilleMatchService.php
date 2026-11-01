<?php

namespace App\VolleyStats;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;
use App\Entity\MembreEquipe;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use App\Repository\FeuilleMatchRepository;
use App\Repository\MembreEquipeRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Écritures de la page du match (D67) : création, joueurs présents, composition des sets, journal (points, remplacements,
 * corrections, rotations ; « Précédent » et « Suivant »), fin de set et de match. Chaque identifiant reçu est vérifié côté serveur
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
            // Créée au même instant par un autre téléphone : rien n'est enregistré ici, la page se recharge sur celle-là
            $this->doctrine->resetManager();
            throw new SaisieInvalide('La page du match vient d\'être préparée sur un autre téléphone : vérifiez les joueurs présents.');
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

    /**
     * Sets joués par chaque membre de l'équipe sur la saison, d'après les pages du match : composition de départ et entrées
     * en remplacement (D67). Visible de l'équipe seulement.
     *
     * @return array<int, int> identifiant du membre => nombre de sets
     */
    public function setsJoues(Equipe $equipe, Saison $saison): array
    {
        $feuilles = $this->feuilles->createQueryBuilder('f')
            ->innerJoin('f.partie', 'p')->innerJoin('p.poule', 'po')->innerJoin('po.phase', 'ph')
            ->andWhere('f.equipe = :equipe')->andWhere('ph.saison = :saison')
            ->setParameter('equipe', $equipe)->setParameter('saison', $saison)
            ->getQuery()->getResult();
        $compte = [];
        foreach ($feuilles as $feuille) {
            foreach ($feuille->getSets() as $set) {
                if ($set->getEtat() === FeuilleMatchSet::COMPOSITION) {
                    continue;
                }
                $joueurs = array_filter($set->getPostes(), fn ($id) => $id !== null);
                foreach ($set->getEvenements() as $e) {
                    if (!$e->estAnnule() && $e->getType() === FeuilleMatchEvenement::REMPLACEMENT) {
                        $joueurs[] = $e->getDonnees()['entre'];
                    }
                }
                foreach (array_unique($joueurs) as $id) {
                    $membre = $feuille->joueur($id)?->getMembre();
                    if ($membre !== null) {
                        $compte[$membre->getId()] = ($compte[$membre->getId()] ?? 0) + 1;
                    }
                }
            }
        }

        return $compte;
    }

    /** Plafond par set : un téléphone qui boucle ne remplit pas la base (relecture sécurité) */
    public const EVENEMENTS_MAX = 1500;

    /** Sets gagnants du format de la phase (D48) */
    public static function setsGagnants(FeuilleMatch $feuille): int
    {
        return $feuille->getPartie()->getPoule()?->getPhase()?->getSetsGagnants() ?? Phase::SETS_GAGNANTS_DEFAUT;
    }

    /** 15 points pour le set décisif (5ᵉ en 3 sets gagnants, 3ᵉ en 2), 25 sinon ; sets secs (règlement BLVB, D67) */
    public static function pointsFin(FeuilleMatch $feuille, int $numero): int
    {
        return $numero === 2 * self::setsGagnants($feuille) - 1 ? 15 : 25;
    }

    /**
     * Composition d'un set : crée le set suivant s'il n'existe pas (le précédent doit être terminé), ou modifie celui en
     * composition. Postes : 6 joueurs présents distincts, au plus une place libre ; premier service nous / eux ou pas encore
     * choisi ; score de départ parmi 0–0, 0–8, 8–0.
     *
     * @param array<string, mixed> $donnees
     */
    public function composition(FeuilleMatch $feuille, int $numero, array $donnees): FeuilleMatchSet
    {
        $resultat = null;
        $this->transaction(function () use ($feuille, $numero, $donnees, &$resultat): void {
            // Verrou sur la page : deux téléphones ne créent pas le même set
            $this->em->refresh($feuille, LockMode::PESSIMISTIC_WRITE);
            $resultat = $this->composer($feuille, $numero, $donnees);
        });

        return $resultat;
    }

    /** @param array<string, mixed> $donnees */
    private function composer(FeuilleMatch $feuille, int $numero, array $donnees): FeuilleMatchSet
    {
        $set = $feuille->set($numero);
        if ($set !== null) {
            $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE);
        }
        if ($set === null) {
            $dernier = $feuille->getSets()->last() ?: null;
            if ($numero !== $feuille->getSets()->count() + 1 || ($dernier !== null && $dernier->getEtat() !== FeuilleMatchSet::TERMINE)
                || $numero > 2 * self::setsGagnants($feuille) - 1 || $this->vainqueur($feuille) !== null) {
                throw new SaisieInvalide('Ce set ne peut pas encore être composé : terminez d\'abord le set précédent.');
            }
            $set = new FeuilleMatchSet($feuille, $numero, self::pointsFin($feuille, $numero));
            $feuille->ajouterSet($set);
            $this->em->persist($set);
        } elseif ($set->getEtat() !== FeuilleMatchSet::COMPOSITION) {
            throw new SaisieInvalide('Ce set est déjà commencé : sa composition de départ ne se modifie plus.');
        }
        self::cles($donnees, ['postes', 'premierService', 'scoreDepart']);
        $set->setPostes($this->postesValides($feuille, $donnees['postes'] ?? null));
        $service = $donnees['premierService'] ?? null;
        if (!\in_array($service, [null, FeuilleMatchSet::NOUS, FeuilleMatchSet::EUX], true)) {
            throw new SaisieInvalide('Premier service non reconnu.');
        }
        $set->setPremierService($service);
        $depart = $donnees['scoreDepart'] ?? [0, 0];
        if (!\in_array($depart, FeuilleMatchSet::SCORES_DEPART, true)) {
            throw new SaisieInvalide('Score de départ non reconnu : 0–0, 0–8 ou 8–0.');
        }
        $set->setScoreDepart($depart);
        $feuille->marquerModifiee();

        return $set;
    }

    /** Commencer le set : composition complète (ou une seule place libre si l'équipe commence à 5), premier service choisi */
    public function commencer(FeuilleMatchSet $set, bool $aCinq, string $saisie = FeuilleMatchSet::SAISIE_SCORE): void
    {
        if (!\in_array($saisie, [FeuilleMatchSet::SAISIE_SCORE, FeuilleMatchSet::SAISIE_TOUCHES], true)) {
            throw new SaisieInvalide('Choisissez ce que vous notez : le score seulement, ou le score et les touches.');
        }
        $this->transaction(fn () => $this->commencerSousVerrou($set, $aCinq, $saisie));
    }

    private function commencerSousVerrou(FeuilleMatchSet $set, bool $aCinq, string $saisie): void
    {
        $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE);
        if ($set->getEtat() !== FeuilleMatchSet::COMPOSITION) {
            throw new SaisieInvalide('Ce set est déjà commencé.');
        }
        $libres = \count(array_filter($set->getPostes(), fn ($id) => $id === null));
        if ($libres > ($aCinq ? 1 : 0) || ($aCinq && $libres === 0)) {
            throw new SaisieInvalide($aCinq ? 'Pour commencer à 5 joueurs, laissez exactement une place libre.' : 'Toutes les places doivent être remplies.');
        }
        if ($set->getPremierService() === null) {
            throw new SaisieInvalide('Choisissez le premier service.');
        }
        $set->setEtat(FeuilleMatchSet::EN_COURS)->setSaisie($saisie);
        $set->getFeuille()->setEtat(FeuilleMatch::EN_COURS)->marquerModifiee();
    }

    /**
     * « Noter aussi les touches » (D72) : un set en « score seulement » passe aux touches, à partir du prochain échange ; ses
     * points déjà notés deviennent des échanges non détaillés (score et rotation inchangés). Sans retour : « Point sans
     * détail » couvre le cas inverse.
     */
    public function noterLesTouches(FeuilleMatchSet $set): void
    {
        $this->transaction(function () use ($set): void {
            $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE);
            if ($set->getEtat() !== FeuilleMatchSet::EN_COURS) {
                throw new SaisieInvalide('Ce set n\'est pas en cours.');
            }
            if ($set->noteLesTouches()) {
                return; // déjà fait (envoi rejoué après une coupure, autre téléphone)
            }
            if (EtatSet::calculer($set)->finAtteinte) {
                throw new SaisieInvalide('Ce set est fini : il n\'y a plus de touches à noter.');
            }
            foreach ($set->getEvenements() as $evenement) {
                if ($evenement->getType() === FeuilleMatchEvenement::POINT) {
                    $evenement->marquerSansDetail();
                }
            }
            $set->setSaisie(FeuilleMatchSet::SAISIE_TOUCHES);
            $set->getFeuille()->marquerModifiee();
        });
    }

    /**
     * Ajoute un événement au journal d'un set en cours. $dernierOrdre : dernier ordre connu du téléphone (null si aucun) ;
     * s'il a changé entre-temps, refus (l'autre téléphone a saisi) et la page se recharge. Un uuid déjà reçu (envoi rejoué
     * après une coupure) est sans effet.
     *
     * @param array<string, mixed> $donnees
     */
    public function evenement(FeuilleMatchSet $set, string $uuid, string $type, array $donnees, ?int $dernierOrdre, ?User $auteur, ?string $saisieConnue = null): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
        }
        $this->transaction(function () use ($set, $uuid, $type, $donnees, $dernierOrdre, $auteur, $saisieConnue): void {
            $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE); // verrou et journal relu : un autre téléphone a pu saisir
            $existant = $this->em->getRepository(FeuilleMatchEvenement::class)->findOneBy(['uuid' => strtolower($uuid)]);
            if ($existant !== null) {
                if ($existant->getSet() !== $set) {
                    throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
                }

                return; // envoi rejoué après une coupure : déjà enregistré
            }
            // Set terminé : seules les corrections d'actions (D68, relecture sécurité) ; le gel est vérifié par le voter
            $termine = $set->getEtat() === FeuilleMatchSet::TERMINE && $type === FeuilleMatchEvenement::MODIFICATION;
            if ($set->getEtat() !== FeuilleMatchSet::EN_COURS && !$termine) {
                throw new SaisieInvalide('Ce set n\'est pas en cours.');
            }
            if ($set->getEvenements()->count() >= self::EVENEMENTS_MAX) {
                throw new SaisieInvalide('Ce set compte trop de saisies : rechargez la page.');
            }
            $this->verifierOrdre($set, $dernierOrdre);
            if ($saisieConnue !== null && $saisieConnue !== $set->getSaisie()) {
                throw new SaisieConcurrente('Les touches sont maintenant notées dans ce set : la page vient d\'être rechargée.'); // autre téléphone (D72)
            }
            $donnees = $this->donneesValides($set, $type, $donnees);
            $ordre = ($set->getEvenements()->last() ?: null)?->getOrdre() + 1;
            $evenement = new FeuilleMatchEvenement($set, $ordre, strtolower($uuid), $type, $donnees, $auteur);
            $set->ajouterEvenement($evenement);
            $this->em->persist($evenement);
            $set->getFeuille()->marquerModifiee();
        });
    }

    /** « Précédent » : annule le dernier événement actif, s'il est bien celui que montre le téléphone */
    public function precedent(FeuilleMatchSet $set, string $uuid): void
    {
        $this->transaction(function () use ($set, $uuid): void {
            $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE); // verrou et journal relu : un autre téléphone a pu saisir
            $actifs = array_filter($set->getEvenements()->toArray(), fn (FeuilleMatchEvenement $e) => !$e->estAnnule());
            $dernier = end($actifs) ?: null;
            if ($set->getEtat() !== FeuilleMatchSet::EN_COURS || $dernier === null || $dernier->getUuid() !== strtolower($uuid)) {
                throw new SaisieInvalide('Rien à annuler, ou la saisie a changé entre-temps : rechargez la page.');
            }
            $dernier->annuler();
            $set->getFeuille()->marquerModifiee();
        });
    }

    /**
     * « Suivant » : rétablit le dernier événement annulé, tant qu'aucun événement n'a été saisi après lui (sinon il reste
     * définitivement barré).
     */
    public function suivant(FeuilleMatchSet $set, string $uuid): void
    {
        $this->transaction(function () use ($set, $uuid): void {
            $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE); // verrou et journal relu : un autre téléphone a pu saisir
            $retablissable = null;
            foreach (array_reverse($set->getEvenements()->toArray()) as $evenement) {
                if (!$evenement->estAnnule()) {
                    break;
                }
                $retablissable = $evenement;
            }
            if ($set->getEtat() !== FeuilleMatchSet::EN_COURS || $retablissable === null || $retablissable->getUuid() !== strtolower($uuid)) {
                throw new SaisieInvalide('Rien à rétablir, ou la saisie a changé entre-temps : rechargez la page.');
            }
            $retablissable->retablir();
            $set->getFeuille()->marquerModifiee();
        });
    }

    /** Fin du set (proposée quand le score de fin est atteint, D67) ; la page passe à « terminée » quand le match est gagné */
    public function terminerSet(FeuilleMatchSet $set): void
    {
        $this->transaction(fn () => $this->terminerSousVerrou($set));
    }

    private function terminerSousVerrou(FeuilleMatchSet $set): void
    {
        $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE); // un point saisi au même instant compte
        if ($set->getEtat() !== FeuilleMatchSet::EN_COURS) {
            throw new SaisieInvalide('Ce set n\'est pas en cours.');
        }
        if (!EtatSet::calculer($set)->finAtteinte) {
            throw new SaisieInvalide(sprintf('Le set se termine à %d points.', $set->getPointsFin()));
        }
        $set->setEtat(FeuilleMatchSet::TERMINE);
        $feuille = $set->getFeuille();
        if ($this->vainqueur($feuille) !== null) {
            $feuille->setEtat(FeuilleMatch::TERMINEE);
        }
        $feuille->marquerModifiee();
    }

    /** Rouvre le dernier set terminé (point oublié) ; la page redevient « en cours » */
    public function rouvrirSet(FeuilleMatchSet $set): void
    {
        $this->transaction(fn () => $this->rouvrirSousVerrou($set));
    }

    private function rouvrirSousVerrou(FeuilleMatchSet $set): void
    {
        $this->em->refresh($set, LockMode::PESSIMISTIC_WRITE);
        if ($set->getEtat() !== FeuilleMatchSet::TERMINE || $set !== $set->getFeuille()->getSets()->last()) {
            throw new SaisieInvalide('Seul le dernier set terminé peut être rouvert.');
        }
        $set->setEtat(FeuilleMatchSet::EN_COURS);
        $set->getFeuille()->setEtat(FeuilleMatch::EN_COURS)->marquerModifiee();
        $this->em->flush();
    }

    /**
     * Transaction qui laisse l'EntityManager ouvert quand la saisie est refusée (wrapInTransaction le fermerait) : rien
     * n'est modifié avant les contrôles, l'annulation suffit.
     */
    private function transaction(callable $travail): void
    {
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();
        try {
            $travail();
            $this->em->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }
    }

    /** Sets gagnés par chaque équipe @return array{0: int, 1: int} */
    public static function setsGagnes(FeuilleMatch $feuille): array
    {
        $gagnes = [0, 0];
        foreach ($feuille->getSets() as $set) {
            if ($set->getEtat() === FeuilleMatchSet::TERMINE) {
                $score = EtatSet::calculer($set)->score;
                ++$gagnes[$score[0] > $score[1] ? 0 : 1];
            }
        }

        return $gagnes;
    }

    /** nous / eux si le match est gagné, sinon null */
    private function vainqueur(FeuilleMatch $feuille): ?string
    {
        $gagnes = self::setsGagnes($feuille);
        $n = self::setsGagnants($feuille);

        return $gagnes[0] >= $n ? FeuilleMatchSet::NOUS : ($gagnes[1] >= $n ? FeuilleMatchSet::EUX : null);
    }

    private function verifierOrdre(FeuilleMatchSet $set, ?int $dernierOrdre): void
    {
        $actuel = ($set->getEvenements()->last() ?: null)?->getOrdre();
        if ($actuel !== $dernierOrdre) {
            throw new SaisieConcurrente('La page a été modifiée sur un autre téléphone : elle vient d\'être rechargée.');
        }
    }

    /**
     * Données d'un événement en schéma strict (clé inconnue refusée), joueurs de cette page seulement.
     *
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private function donneesValides(FeuilleMatchSet $set, string $type, array $donnees): array
    {
        $etat = EtatSet::calculer($set);
        $touches = $set->noteLesTouches();
        $echange = $touches ? $this->echangeEnCours($set) : [];
        if (\in_array($type, [FeuilleMatchEvenement::REMPLACEMENT, FeuilleMatchEvenement::CORRECTION, FeuilleMatchEvenement::ROTATION], true) && $echange !== []) {
            throw new SaisieInvalide('Notez d\'abord le point de l\'échange en cours.');
        }
        if (!$touches && \in_array($type, [FeuilleMatchEvenement::ACTION, FeuilleMatchEvenement::REJOUER, FeuilleMatchEvenement::MODIFICATION], true)) {
            throw new SaisieInvalide('Ce set ne note que le score.');
        }
        switch ($type) {
            case FeuilleMatchEvenement::POINT:
                return $touches ? $this->pointDetaille($set, $etat, $echange, $donnees) : $this->pointSimple($donnees);
            case FeuilleMatchEvenement::ACTION:
                self::cles($donnees, ['action', 'joueurs']);
                if (!\in_array($donnees['action'] ?? null, FeuilleMatchEvenement::ACTIONS, true)) {
                    throw new SaisieInvalide('Action non reconnue.');
                }
                if ($etat->finAtteinte) {
                    throw new SaisieInvalide(sprintf('Le set est fini (%d–%d) : terminez-le, ou touchez « Précédent ».', ...$etat->score));
                }
                $joueur = $this->joueurSurLeTerrain($set->getFeuille(), $etat, $donnees['joueurs'] ?? null, $donnees['action'] === 'block');

                return ['action' => $donnees['action'], 'joueurs' => [$joueur]];
            case FeuilleMatchEvenement::REJOUER:
                self::cles($donnees, []);

                return [];
            case FeuilleMatchEvenement::MODIFICATION:
                return $this->modificationValide($set, $donnees);
            case FeuilleMatchEvenement::REMPLACEMENT:
                self::cles($donnees, ['sort', 'entre']);
                $sort = $donnees['sort'] ?? null;
                $entre = $this->joueurDeLaFeuille($set->getFeuille(), $donnees['entre'] ?? null);
                if ($sort !== null) {
                    $sort = $this->joueurDeLaFeuille($set->getFeuille(), $sort);
                }
                if (!\in_array($sort, $etat->postes, true)) {
                    throw new SaisieInvalide('Le joueur qui sort doit être sur le terrain.');
                }
                if (\in_array($entre, $etat->postes, true)) {
                    throw new SaisieInvalide('Le joueur qui entre est déjà sur le terrain.');
                }

                return ['sort' => $sort, 'entre' => $entre];
            case FeuilleMatchEvenement::CORRECTION:
                self::cles($donnees, ['nous', 'eux', 'service']);
                foreach (['nous', 'eux'] as $cle) {
                    if (!\is_int($donnees[$cle] ?? null) || $donnees[$cle] < 0 || $donnees[$cle] > 99) {
                        throw new SaisieInvalide('Score non reconnu.');
                    }
                }
                if (!\in_array($donnees['service'] ?? null, [FeuilleMatchSet::NOUS, FeuilleMatchSet::EUX], true)) {
                    throw new SaisieInvalide('Indiquez qui sert maintenant.');
                }

                return ['nous' => $donnees['nous'], 'eux' => $donnees['eux'], 'service' => $donnees['service']];
            case FeuilleMatchEvenement::ROTATION:
                self::cles($donnees, []);

                return [];
        }
        throw new SaisieInvalide('Saisie non reconnue.');
    }

    /** @param array<string, mixed> $donnees @return array<string, mixed> */
    private function pointSimple(array $donnees): array
    {
        self::cles($donnees, ['pour']);
        if (!\in_array($donnees['pour'] ?? null, [FeuilleMatchSet::NOUS, FeuilleMatchSet::EUX], true)) {
            throw new SaisieInvalide('Point non reconnu.');
        }

        return ['pour' => $donnees['pour']];
    }

    /**
     * Point d'un set où l'on note les touches (D68) : sur quoi il porte (la dernière action de l'échange, le service, rien),
     * le détail d'un point perdu (contrée, faute, faute de règlement), le deuxième bloqueur d'un block gagnant, ou « sans
     * détail ». Toute référence est cherchée dans CE set.
     *
     * @param list<FeuilleMatchEvenement> $echange
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private function pointDetaille(FeuilleMatchSet $set, EtatSet $etat, array $echange, array $donnees): array
    {
        self::cles($donnees, ['pour', 'sur', 'detail', 'avec', 'fautif', 'sansDetail']);
        $point = $this->pointSimple(['pour' => $donnees['pour'] ?? null]);
        $nous = $point['pour'] === FeuilleMatchSet::NOUS;
        $sur = $donnees['sur'] ?? null;
        $detail = $donnees['detail'] ?? null;
        $derniere = $echange === [] ? null : $echange[array_key_last($echange)];
        if (($donnees['sansDetail'] ?? false) === true) {
            if ($sur !== null || $detail !== null || isset($donnees['avec']) || isset($donnees['fautif']) || $echange !== []) {
                throw new SaisieInvalide('Des touches sont notées dans cet échange : choisissez son issue.');
            }

            return $point + ['sansDetail' => true];
        }
        if (isset($donnees['sansDetail'])) {
            throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
        }
        if ($sur === 'service') {
            if ($echange !== [] || $etat->service !== FeuilleMatchSet::NOUS || $etat->serveur() === null) {
                throw new SaisieInvalide('Le point sur le service ne vaut que pour notre service, avant toute touche.');
            }
            $point['sur'] = 'service';
        } elseif ($sur !== null) {
            if ($derniere === null || !\is_string($sur) || $derniere->getUuid() !== strtolower($sur)) {
                throw new SaisieInvalide('Le point porte sur la dernière touche de l\'échange : rechargez la page.');
            }
            $point['sur'] = $derniere->getUuid();
        }
        $action = isset($point['sur']) && $point['sur'] !== 'service' ? $derniere->getDonnees()['action'] : null;
        if ($detail !== null) {
            $surAttaque = $action === 'attaque' && \in_array($detail, ['contree', 'faute'], true);
            $faute = \in_array($detail, [...FeuilleMatchEvenement::FAUTES_JOUEUR, ...FeuilleMatchEvenement::FAUTES_EQUIPE], true);
            if ($nous || (!$surAttaque && !$faute)) {
                throw new SaisieInvalide('Détail du point non reconnu.');
            }
            $point['detail'] = $detail;
        }
        if (isset($donnees['fautif'])) {
            if (!\in_array($detail, FeuilleMatchEvenement::FAUTES_JOUEUR, true) || isset($point['sur'])) {
                throw new SaisieInvalide('Faute non reconnue.');
            }
            $point['fautif'] = $this->joueurSurLeTerrain($set->getFeuille(), $etat, [$donnees['fautif']], false);
        } elseif (\in_array($detail, FeuilleMatchEvenement::FAUTES_JOUEUR, true) && !isset($point['sur'])) {
            throw new SaisieInvalide('Touchez le joueur fautif.');
        }
        if (isset($donnees['avec'])) {
            // Deuxième bloqueur, seulement pour un block gagnant (D68)
            $premier = $derniere?->getDonnees()['joueurs'][0] ?? null;
            if (!$nous || $action !== 'block') {
                throw new SaisieInvalide('Le deuxième bloqueur ne se note que pour un block gagnant.');
            }
            $avec = $this->joueurSurLeTerrain($set->getFeuille(), $etat, [$donnees['avec']], true);
            if ($avec === $premier) {
                throw new SaisieInvalide('Choisissez un autre joueur pour le deuxième bloqueur.');
            }
            $point['avec'] = $avec;
        }

        return $point;
    }

    /**
     * Correction d'une action d'un échange terminé de CE set (D68) : changer le joueur, l'action, ou la retirer. Refusée sur
     * l'action qui a fait le point (« Corriger le score »).
     *
     * @param array<string, mixed> $donnees
     * @return array<string, mixed>
     */
    private function modificationValide(FeuilleMatchSet $set, array $donnees): array
    {
        self::cles($donnees, ['cible', 'joueurs', 'action', 'retirer']);
        $cible = null;
        foreach ($set->getEvenements() as $e) {
            if (\is_string($donnees['cible'] ?? null) && $e->getUuid() === strtolower($donnees['cible'])) {
                $cible = $e;
            }
        }
        if ($cible === null || $cible->estAnnule() || $cible->getType() !== FeuilleMatchEvenement::ACTION || \in_array($cible, $this->echangeEnCours($set), true)
            || $this->dansUnEchangeRejoue($set, $cible)) {
            throw new SaisieInvalide('Cette touche ne peut pas être corrigée : rechargez la page.');
        }
        // Action et joueur actuels de la touche (dernière correction active)
        $actuelle = ['action' => $cible->getDonnees()['action'], 'joueur' => $cible->getDonnees()['joueurs'][0]];
        foreach ($set->getEvenements() as $e) {
            if (!$e->estAnnule() && $e->getType() === FeuilleMatchEvenement::MODIFICATION && $e->getDonnees()['cible'] === $cible->getUuid()) {
                $actuelle = ['action' => $e->getDonnees()['action'] ?? $actuelle['action'], 'joueur' => $e->getDonnees()['joueurs'][0] ?? $actuelle['joueur']];
            }
        }
        $avant = EtatSet::calculer($set, $cible->getOrdre());
        foreach ($set->getEvenements() as $e) {
            if (!$e->estAnnule() && $e->getType() === FeuilleMatchEvenement::POINT && ($e->getDonnees()['sur'] ?? null) === $cible->getUuid()) {
                throw new SaisieInvalide('Cette touche a fait le point : corrigez le score à la place.');
            }
        }
        $choix = array_filter(['joueurs' => $donnees['joueurs'] ?? null, 'action' => $donnees['action'] ?? null, 'retirer' => $donnees['retirer'] ?? null], fn ($v) => $v !== null);
        if (\count($choix) !== 1) {
            throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
        }
        if (isset($choix['retirer'])) {
            if ($choix['retirer'] !== true) {
                throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
            }

            return ['cible' => $cible->getUuid(), 'retirer' => true];
        }
        if (isset($choix['action'])) {
            if (!\in_array($choix['action'], FeuilleMatchEvenement::ACTIONS, true)) {
                throw new SaisieInvalide('Action non reconnue.');
            }
            if ($choix['action'] === 'block') {
                $this->joueurSurLeTerrain($set->getFeuille(), $avant, [$actuelle['joueur']], true); // un arrière ne contre pas
            }

            return ['cible' => $cible->getUuid(), 'action' => $choix['action']];
        }
        // Joueur sur le terrain AU MOMENT de l'action corrigée
        $joueur = $this->joueurSurLeTerrain($set->getFeuille(), $avant, $choix['joueurs'], $actuelle['action'] === 'block');

        return ['cible' => $cible->getUuid(), 'joueurs' => [$joueur]];
    }

    /** La touche appartient-elle à un échange clos par une balle à rejouer (ses touches ne comptent pas) ? */
    private function dansUnEchangeRejoue(FeuilleMatchSet $set, FeuilleMatchEvenement $cible): bool
    {
        foreach ($set->getEvenements() as $e) {
            if ($e->getOrdre() <= $cible->getOrdre() || $e->estAnnule()) {
                continue;
            }
            if (\in_array($e->getType(), [FeuilleMatchEvenement::POINT, FeuilleMatchEvenement::CORRECTION], true)) {
                return false;
            }
            if ($e->getType() === FeuilleMatchEvenement::REJOUER) {
                return true;
            }
        }

        return false;
    }

    /**
     * Actions actives de l'échange en cours : après le dernier point, la dernière balle à rejouer ou la dernière correction.
     *
     * @return list<FeuilleMatchEvenement>
     */
    public function echangeEnCours(FeuilleMatchSet $set): array
    {
        $echange = [];
        foreach ($set->getEvenements() as $e) {
            if ($e->estAnnule()) {
                continue;
            }
            if (\in_array($e->getType(), [FeuilleMatchEvenement::POINT, FeuilleMatchEvenement::REJOUER, FeuilleMatchEvenement::CORRECTION], true)) {
                $echange = [];
            } elseif ($e->getType() === FeuilleMatchEvenement::ACTION) {
                $echange[] = $e;
            }
        }

        return $echange;
    }

    /**
     * Un seul joueur de CETTE page, sur le terrain dans l'état donné ; au block, un avant seulement (poste 2, 3 ou 4 : un
     * arrière ne contre pas).
     *
     * @return int identifiant de FeuilleMatchJoueur
     */
    private function joueurSurLeTerrain(FeuilleMatch $feuille, EtatSet $etat, mixed $joueurs, bool $block): int
    {
        if (!\is_array($joueurs) || !array_is_list($joueurs) || \count($joueurs) !== 1) {
            throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
        }
        $id = $this->joueurDeLaFeuille($feuille, $joueurs[0]);
        $poste = array_search($id, $etat->postes, true);
        if ($poste === false) {
            throw new SaisieInvalide('Ce joueur n\'est pas sur le terrain.');
        }
        if ($block && !\in_array($poste, [1, 2, 3], true)) {
            throw new SaisieInvalide('Un joueur arrière ne peut pas contrer.');
        }

        return $id;
    }

    /** @param mixed $postes @return list<?int> */
    private function postesValides(FeuilleMatch $feuille, mixed $postes): array
    {
        if (!\is_array($postes) || !array_is_list($postes) || \count($postes) !== 6) {
            throw new SaisieInvalide('La composition doit compter six places.');
        }
        $valides = array_map(fn ($id) => $id === null ? null : $this->joueurDeLaFeuille($feuille, $id), $postes);
        $places = array_filter($valides, fn ($id) => $id !== null);
        if (\count($places) !== \count(array_unique($places))) {
            throw new SaisieInvalide('Un joueur est placé deux fois.');
        }
        if (6 - \count($places) > 1) {
            throw new SaisieInvalide('Une seule place peut rester libre (équipe à 5).');
        }

        return $valides;
    }

    /** Identifiant d'un joueur présent de CETTE page, sinon refus (message neutre, sans nom) */
    private function joueurDeLaFeuille(FeuilleMatch $feuille, mixed $id): int
    {
        if (!\is_int($id) || $feuille->joueur($id) === null) {
            throw new SaisieInvalide('Un des joueurs choisis n\'est pas présent sur cette page : rechargez la page.');
        }

        return $id;
    }

    /** @param array<string, mixed> $donnees @param list<string> $permises */
    private static function cles(array $donnees, array $permises): void
    {
        if (array_diff(array_keys($donnees), $permises) !== []) {
            throw new SaisieInvalide('Saisie non reconnue : rechargez la page.');
        }
    }
}
