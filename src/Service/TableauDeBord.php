<?php

namespace App\Service;

use App\Competition\CompositionPoules;
use App\Competition\VerificationSaison;
use App\Controller\Admin\SaisonWizardController;
use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\MembreEquipe;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\ResponsableClub;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\FermetureGymnaseRepository;
use App\Repository\ParametresSiteRepository;
use App\Repository\PartieRepository;
use App\Repository\ReglementRepository;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tableau de bord de l'admin (D64) : ce qu'il y a à faire, l'état des saisons suivies, les prochaines fermetures et les
 * chiffres des accès rapides. Lecture seule.
 *
 * Saisons suivies : la saison affichée sur le site, les brouillons du wizard et les saisons validées non terminées. La
 * grande carte revient à la saison affichée, sauf si elle est terminée et qu'une autre se prépare (D64.2).
 */
final class TableauDeBord
{
    public const BROUILLON = 'brouillon';
    public const MISE_EN_PLACE = 'mise_en_place';
    public const PRETE = 'prete';
    public const EN_COURS = 'en_cours';
    public const TERMINEE = 'terminee';

    /** Un score absent n'est signalé qu'après ce délai, comme le rappel automatique (D64.4). */
    public const SEUIL_SCORES_JOURS = 2;

    /** Lignes « À faire » visibles avant « Voir les N autres ». */
    public const LIGNES_VISIBLES = 5;

    /** @var array<int, string> mémoïsation des états, le temps d'une requête */
    private array $etats = [];

    public function __construct(
        private EntityManagerInterface $em,
        private SaisonRepository $saisons,
        private PartieRepository $parties,
        private FermetureGymnaseRepository $fermetures,
        private ReglementRepository $reglements,
        private ParametresSiteRepository $parametres,
        private PhaseService $phaseService,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array{
     *     principale: ?array<string, mixed>,
     *     secondaires: list<array<string, mixed>>,
     *     aFaire: list<array<string, mixed>>,
     *     fermetures: list<\App\Entity\FermetureGymnase>,
     *     nombreFermetures: int,
     *     tuiles: array<string, mixed>,
     * }
     */
    public function donnees(): array
    {
        [$principale, $secondaires] = $this->saisonsSuivies();
        $suivies = array_values(array_filter([$principale, ...$secondaires]));
        $affichee = $this->saisons->findFavoritePubliee();

        return [
            'principale' => $principale ? $this->carte($principale) : null,
            'secondaires' => array_map($this->carte(...), $secondaires),
            'aFaire' => $this->aFaire($suivies, $affichee),
            'fermetures' => $this->fermetures->prochaines(3),
            'nombreFermetures' => $this->fermetures->nombreEnCoursOuAVenir(),
            'tuiles' => $this->tuiles($affichee ?? $this->saisons->findCourante()),
        ];
    }

    /** Saisons dont on suit les matchs (scores à saisir) : suivies, validées et non terminées, plus la saison affichée. @return list<Saison> */
    public function saisonsDesMatchs(): array
    {
        [$principale, $secondaires] = $this->saisonsSuivies();
        $affichee = $this->saisons->findFavoritePubliee();

        return array_values(array_filter([$principale, ...$secondaires],
            fn (?Saison $s) => $s !== null && !$s->isBrouillon() && ($s === $affichee || $this->etat($s) !== self::TERMINEE)));
    }

    public function etat(Saison $saison): string
    {
        return $this->etats[$saison->getId()] ??= match (true) {
            $saison->isBrouillon() => self::BROUILLON,
            $this->estTerminee($saison) => self::TERMINEE,
            (bool) $saison->getFavori() => self::EN_COURS,
            $this->miseEnPlaceTerminee($saison) => self::PRETE,
            default => self::MISE_EN_PLACE,
        };
    }

    /**
     * Terminée (D64.1) : date de fin dépassée, ou dernière phase dont tous les matchs ont un score. Aucun état en base.
     */
    public function estTerminee(Saison $saison): bool
    {
        if ($saison->getDateFin() !== null && $saison->getDateFin()->format('Y-m-d') < date('Y-m-d')) {
            return true;
        }
        $derniere = $saison->getPhases()->last();
        if (!$derniere) {
            return false;
        }
        $bilan = $this->parties->bilanPhase($derniere);

        return $bilan['total'] > 0 && $bilan['sansScore'] === 0;
    }

    /** @return array{0: ?Saison, 1: list<Saison>} grande carte, puis les autres saisons suivies */
    private function saisonsSuivies(): array
    {
        $affichee = $this->saisons->findFavoritePubliee();
        $autres = [];
        foreach ($this->saisons->findAll() as $saison) {
            if ($saison !== $affichee && ($saison->isBrouillon() || $this->etat($saison) !== self::TERMINEE)) {
                $autres[] = $saison;
            }
        }
        // La plus récemment modifiée d'abord
        usort($autres, fn (Saison $a, Saison $b) => [$b->getModifieLe(), $b->getId()] <=> [$a->getModifieLe(), $a->getId()]);

        if ($affichee === null) {
            $principale = array_shift($autres) ?? $this->saisons->findPlusRecentePubliee();

            return [$principale, $autres];
        }
        if ($autres !== [] && $this->etat($affichee) === self::TERMINEE) {
            return [array_shift($autres), [$affichee, ...$autres]];
        }

        return [$affichee, $autres];
    }

    /** @return array<string, mixed> données d'une carte de saison, mises en forme par le gabarit */
    private function carte(Saison $saison): array
    {
        $etat = $this->etat($saison);
        $carte = ['saison' => $saison, 'etat' => $etat];
        if ($etat === self::BROUILLON) {
            $etape = max(2, (int) $saison->getEtapeWizard());

            return $carte + [
                'etape' => $etape,
                'etapes' => \count(SaisonWizardController::ETAPES),
                'etapeLibelle' => SaisonWizardController::ETAPES[$etape] ?? '',
                'erreurs' => \count(VerificationSaison::verifier($saison)['erreurs']),
            ];
        }
        $phase = CompositionPoules::phaseACompleter($saison);
        $miseEnPlace = $phase ? $this->miseEnPlace($phase) : null;

        return $carte + [
            'phase' => $phase,
            'miseEnPlace' => $miseEnPlace,
            'urlMiseEnPlace' => $phase ? $this->urlMiseEnPlace($saison, $phase, $miseEnPlace) : null,
            'bilan' => $this->parties->bilanSaison($saison),
        ];
    }

    /**
     * Mise en place d'une phase : poules composées (deux équipes au moins dans chacune), journées et matchs créés dans
     * chaque poule. Comptages par poule, sans charger les équipes.
     *
     * @return array{poules: bool, journees: bool, matchs: bool}
     */
    public function miseEnPlace(Phase $phase): array
    {
        $poules = $this->em->createQuery('SELECT COUNT(po.id) FROM '.Poule::class.' po WHERE po.phase = :phase')
            ->setParameter('phase', $phase)->getSingleScalarResult();
        if (!$poules) {
            return ['poules' => false, 'journees' => false, 'matchs' => false];
        }
        $completes = fn (string $dql) => (int) $this->em->createQuery($dql)->setParameter('phase', $phase)->getSingleScalarResult() === (int) $poules;

        return [
            'poules' => $completes('SELECT COUNT(po.id) FROM '.Poule::class.' po WHERE po.phase = :phase AND SIZE(po.equipes) >= 2'),
            'journees' => $completes('SELECT COUNT(DISTINCT IDENTITY(j.poule)) FROM '.Journee::class.' j JOIN j.poule po WHERE po.phase = :phase'),
            'matchs' => $completes('SELECT COUNT(DISTINCT IDENTITY(m.poule)) FROM '.Partie::class.' m JOIN m.poule po WHERE po.phase = :phase'),
        ];
    }

    private function miseEnPlaceTerminee(Saison $saison): bool
    {
        $phase = CompositionPoules::phaseACompleter($saison);

        return $phase === null || !\in_array(false, $this->miseEnPlace($phase), true);
    }

    /** Écran de mise en place où reprendre : la première étape incomplète. */
    private function urlMiseEnPlace(Saison $saison, Phase $phase, array $miseEnPlace): string
    {
        $etape = match (false) {
            $miseEnPlace['poules'] => 'poules',
            $miseEnPlace['journees'] => 'journees',
            default => 'matchs',
        };

        return $this->urls->generate('admin_saison_miseenplace_'.$etape, ['id' => $saison->getId(), 'phase' => $phase->getId()]);
    }

    /**
     * Lignes « À faire », de la plus urgente à la moins urgente (tableau-de-bord.md §2, D64).
     *
     * @param list<Saison> $suivies
     * @return list<array{code: string, texte: string, bouton: string, url: string, objet: string, urgent?: true, matchs?: list<Partie>}>
     */
    private function aFaire(array $suivies, ?Saison $affichee): array
    {
        $plusieurs = \count($suivies) > 1;
        $matchs = $this->saisonsDesMatchs();
        $lignes = [];

        $fermes = $this->parties->enGymnaseFermeAVenir($matchs);
        if ($fermes !== []) {
            $n = \count($fermes);
            $lignes[] = ['code' => 'gymnase_ferme', 'urgent' => true, 'matchs' => $fermes, 'bouton' => $n === 1 ? 'Afficher le match' : 'Afficher les '.$n.' matchs', 'url' => '', 'objet' => $n === 1 ? 'programmé dans un gymnase fermé' : 'programmés dans un gymnase fermé',
                'texte' => $n === 1 ? '1 match est programmé dans un gymnase fermé.' : $n.' matchs sont programmés dans un gymnase fermé.'];
        }

        if ($affichee === null && $this->saisons->findPlusRecentePubliee() !== null) {
            $lignes[] = ['code' => 'aucune_affichee', 'urgent' => true, 'texte' => 'Aucune saison n\'est affichée sur le site.', 'bouton' => 'Choisir la saison',
                'url' => $this->urls->generate('admin_saison_index'), 'objet' => 'à afficher sur le site'];
        }

        $miseEnPlace = $rappels = $clotures = $reglements = [];
        foreach ($suivies as $saison) {
            $etat = $this->etat($saison);
            // Une seule forme de préfixe, dans l'ordre des textes lus (« de Phase 1, Saison test »)
            $prefixe = $plusieurs ? $saison->getNom().', ' : '';
            // Règlement : signalé à partir de la validation, pas pendant les semaines de préparation du brouillon
            if (!\in_array($etat, [self::BROUILLON, self::TERMINEE], true) && $this->reglements->findDeSaison($saison) === null) {
                $reglements[] = ['code' => 'reglement', 'texte' => $saison->getNom().' : le règlement n\'est pas encore rédigé.', 'bouton' => 'Rédiger le règlement',
                    'url' => $this->urls->generate('admin_reglement_edit', ['id' => $saison->getId()]), 'objet' => 'de '.$saison->getNom()];
            }
            if (\in_array($etat, [self::BROUILLON, self::TERMINEE], true)) {
                continue;
            }
            $phase = CompositionPoules::phaseACompleter($saison);
            if ($phase === null) {
                continue;
            }
            $etatPhase = $this->miseEnPlace($phase);
            if (\in_array(false, $etatPhase, true)) {
                $miseEnPlace[] = ['code' => 'mise_en_place', 'texte' => $prefixe.$phase->getNom().' : la mise en place n\'est pas terminée.', 'bouton' => 'Continuer la mise en place',
                    'url' => $this->urlMiseEnPlace($saison, $phase, $etatPhase), 'objet' => 'de '.$phase->getNom().', '.$saison->getNom()];
            }
            if ($this->parties->bilanSaison($saison)['total'] === 0) {
                $rappels[] = ['code' => 'rappel_fermetures', 'texte' => ($plusieurs ? $saison->getNom().' : avant' : 'Avant').' de générer les matchs, demandez aux clubs leurs fermetures de gymnase.', 'bouton' => 'Voir les gymnases',
                    'url' => $this->urls->generate('admin_lieu_index'), 'objet' => 'et leurs fermetures'];
            }
            $bilanPhase = $this->parties->bilanPhase($phase);
            if ($phase !== $saison->getPhases()->last() && $bilanPhase['total'] > 0 && $bilanPhase['sansScore'] === 0 && $this->phaseService->estCloturable($phase)) {
                $clotures[] = ['code' => 'cloture', 'texte' => $prefixe.$phase->getNom().' : tous les scores sont saisis. Vous pouvez la clôturer.', 'bouton' => 'Aller à la clôture',
                    'url' => $this->urls->generate('admin_saison_show', ['id' => $saison->getId()]).'#phase_'.$phase->getId(), 'objet' => 'de '.$phase->getNom().', '.$saison->getNom()];
            }
        }
        array_push($lignes, ...$miseEnPlace, ...$rappels, ...$clotures);

        $enRetard = $this->parties->nombreSansScoreAvant(array_values(array_filter($matchs, fn (Saison $s) => $this->etat($s) !== self::TERMINEE)),
            new \DateTimeImmutable(sprintf('-%d days', self::SEUIL_SCORES_JOURS)));
        if ($enRetard > 0) {
            $lignes[] = ['code' => 'scores', 'bouton' => 'Voir les matchs', 'url' => $this->urls->generate('admin_scores_index'), 'objet' => 'sans score',
                'texte' => $enRetard === 1 ? sprintf('1 score manque depuis plus de %d jours.', self::SEUIL_SCORES_JOURS) : sprintf('%d scores manquent depuis plus de %d jours.', $enRetard, self::SEUIL_SCORES_JOURS)];
        }

        // Responsables et capitaines : désignés pour la saison affichée seulement (D64.5)
        if ($affichee !== null && $this->etat($affichee) !== self::TERMINEE) {
            $clubs = $this->clubsSansResponsable($affichee);
            if ($clubs > 0) {
                $lignes[] = ['code' => 'responsables', 'bouton' => 'Voir les clubs', 'url' => $this->urls->generate('admin_club_index'), 'objet' => 'sans responsable',
                    'texte' => $clubs === 1 ? '1 club engagé n\'a pas de responsable pour la saison affichée.' : $clubs.' clubs engagés n\'ont pas de responsable pour la saison affichée.'];
            }
            $equipes = $this->equipesSansCapitaine($affichee);
            if ($equipes > 0) {
                $lignes[] = ['code' => 'capitaines', 'bouton' => 'Voir les équipes', 'url' => $this->urls->generate('admin_equipe_index'), 'objet' => 'sans capitaine',
                    'texte' => $equipes === 1 ? '1 équipe engagée n\'a pas de capitaine pour la saison affichée.' : $equipes.' équipes engagées n\'ont pas de capitaine pour la saison affichée.'];
            }
        }
        array_push($lignes, ...$reglements);

        $parametres = $this->parametres->courants();
        if (!$parametres->isConnexionLimitee()) {
            $lignes[] = ['code' => 'connexion', 'texte' => 'Les essais de connexion ne sont pas limités.', 'bouton' => 'Vérifier et activer la limitation',
                'url' => $this->urls->generate('admin_parametres_index'), 'objet' => ''];
        }
        if (trim((string) $parametres->getAdresseContact()) === '') {
            $lignes[] = ['code' => 'contact', 'texte' => 'Aucune adresse de contact : le site n\'en affiche pas, et les messages des responsables de club arrivent sur les adresses de connexion des admins.',
                'bouton' => 'Renseigner l\'adresse', 'url' => $this->urls->generate('admin_parametres_index').'#titre-contact', 'objet' => 'de contact'];
        }

        return $lignes;
    }

    /** Clubs ayant une équipe engagée dans la saison, sans responsable désigné pour elle. */
    public function clubsSansResponsable(Saison $saison): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(DISTINCT c.id) FROM '.Club::class.' c JOIN c.equipes e JOIN e.Poules po JOIN po.phase ph
             WHERE ph.saison = :saison AND NOT EXISTS (SELECT r.id FROM '.ResponsableClub::class.' r WHERE r.club = c AND r.saison = :saison)'
        )->setParameter('saison', $saison)->getSingleScalarResult();
    }

    /** Équipes engagées dans la saison sans capitaine pour elle. */
    public function equipesSansCapitaine(Saison $saison): int
    {
        return (int) $this->em->createQuery(
            'SELECT COUNT(DISTINCT e.id) FROM '.Equipe::class.' e JOIN e.Poules po JOIN po.phase ph
             WHERE ph.saison = :saison AND NOT EXISTS (SELECT m.id FROM '.MembreEquipe::class.' m WHERE m.equipe = e AND m.saison = :saison AND m.capitaine = true)'
        )->setParameter('saison', $saison)->getSingleScalarResult();
    }

    /** @return array<string, mixed> chiffres des accès rapides, pour la saison affichée quand il y en a une */
    private function tuiles(?Saison $saison): array
    {
        $compter = fn (string $classe) => (int) $this->em->createQuery('SELECT COUNT(x.id) FROM '.$classe.' x')->getSingleScalarResult();
        $tuiles = [
            'saison' => $saison,
            'equipes' => $compter(Equipe::class),
            'clubs' => $compter(Club::class),
            'gymnases' => $compter(Lieu::class),
            'gymnasesFermes' => $this->fermetures->nombreGymnasesFermesAujourdhui(),
            'utilisateurs' => $compter(User::class),
            'joueurs' => null,
            'clubsSansResponsable' => null,
        ];
        if ($saison !== null) {
            $tuiles['equipes'] = (int) $this->em->createQuery(
                'SELECT COUNT(DISTINCT e.id) FROM '.Equipe::class.' e JOIN e.Poules po JOIN po.phase ph WHERE ph.saison = :saison'
            )->setParameter('saison', $saison)->getSingleScalarResult();
            $tuiles['joueurs'] = (int) $this->em->createQuery(
                'SELECT COUNT(DISTINCT IDENTITY(m.joueur)) FROM '.MembreEquipe::class.' m WHERE m.saison = :saison'
            )->setParameter('saison', $saison)->getSingleScalarResult();
            $tuiles['clubsSansResponsable'] = $this->clubsSansResponsable($saison);
        }

        return $tuiles;
    }
}
