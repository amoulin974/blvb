<?php

namespace App\Service;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Partie;
use App\Entity\Lieu;
use App\Entity\Creneau;
use App\Enum\PhaseType;
use App\Entity\Journee;
use App\Repository\LieuRepository;
use App\Entity\Equipe;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class PartieService
{
    private const JOURS_MAP = [
        'lundi'     => 'monday',
        'mardi'     => 'tuesday',
        'mercredi'  => 'wednesday',
        'jeudi'     => 'thursday',
        'vendredi'  => 'friday',
        'samedi'    => 'saturday',
        'dimanche'  => 'sunday',
        // Support des index numériques (si 1 = Lundi)
        '1' => 'monday',
        '2' => 'tuesday',
        '3' => 'wednesday',
        '4' => 'thursday',
        '5' => 'friday',
        '6' => 'saturday',
        '7' => 'sunday',
    ];


    public function __construct(
        private EntityManagerInterface $em,
        private OptimisationService $optimisationService
    ) {}


    //Crée les matchs pour une poule
    public function createCalendar(Poule $poule): ?string
    {
        // 1. Nettoyage des anciens matchs
        $this->deleteMatches($poule);

        // 2. Choix de l'algorithme selon le type de poule
        // On imagine que tu as un champ 'type' ou 'isFinale' dans ton entité Poule
        if ($poule->getPhase()->getType() === PhaseType::CHAMPIONNAT) {

            $this->generateMatchJourneePhaseChampionnat($poule);

        }else{

            $this->generateMatchJourneePhaseFinale($poule);
        }
        return null;

    }

    // Poids qui garantit qu'aucun nombre de breaks ne peut jamais valoir la peine d'accepter
    // une surcharge de lieu supplémentaire : la surcharge est toujours résolue en priorité.
    private const POIDS_SURCHARGE = 100000;

    /**
     * Version "optimisée" de createCalendar qui traite toutes les poules d'une phase en une fois.
     * Contrairement à createCalendar (poule par poule, alternance domicile/extérieur naïve, sans
     * se soucier des autres poules ni de la capacité des lieux), cette version :
     *  - priorise l'absence de surcharge de lieu : seul le créneau le PLUS PRIORITAIRE de chaque
     *    lieu est utilisé (pas de repli sur un créneau secondaire) ; une surcharge ne peut être
     *    résolue qu'en choisissant une autre équipe à domicile, jamais en dégradant le créneau ;
     *  - minimise ensuite, parmi les solutions qui n'augmentent pas la surcharge, le nombre de
     *    breaks (suites de rondes consécutives à l'extérieur), problématique étudiée par de Werra
     *    pour la planification des compétitions sportives par rondes.
     *
     * Les poules de championnat sont optimisées ENSEMBLE (pas poule par poule) car elles peuvent
     * partager des lieux : décider qui est à domicile dans une poule peut désaturer ou saturer un
     * lieu utilisé par une autre poule de la même phase. Les poules de phase finale (non
     * round-robin) ne sont pas concernées par cette recherche : elles sont générées avec
     * l'algorithme existant (generateMatchJourneePhaseFinale), et leurs matchs déjà placés sont
     * pris en compte comme occupation fixe pour les poules de championnat.
     *
     * @return array<int, array{poule: string, type: string, scoreContrainte: ?float, nbBreaks: ?int, surcharges: string[]}>
     *         un rapport par poule, utile pour vérifier le résultat (pas encore branché à l'UI)
     */
    public function creerCalendrierOptimise(Phase $phase): array
    {
        $scores = $this->optimisationService->calculerScoresContraintes($phase);

        $poulesChampionnat = [];
        $rapportParPouleId = [];
        $occupationFixe = [];

        foreach ($phase->getPoules() as $poule) {
            $this->deleteMatches($poule);

            if ($poule->getPhase()->getType() !== PhaseType::CHAMPIONNAT) {
                $this->generateMatchJourneePhaseFinale($poule);
                $rapportParPouleId[$poule->getId()] = [
                    'poule' => $poule->getNom(),
                    'type' => 'finale',
                    'scoreContrainte' => $scores[$poule->getId()]['score'] ?? null,
                    'nbBreaks' => null,
                    'surcharges' => [],
                ];
                $this->ajouterOccupation($occupationFixe, $poule->getParties());
                continue;
            }

            if (count($poule->getEquipes()) < 2) {
                $rapportParPouleId[$poule->getId()] = [
                    'poule' => $poule->getNom(),
                    'type' => 'championnat',
                    'scoreContrainte' => $scores[$poule->getId()]['score'] ?? null,
                    'nbBreaks' => 0,
                    'surcharges' => [],
                ];
                continue;
            }

            $poulesChampionnat[] = $poule;
        }

        if ($poulesChampionnat !== []) {
            $resultat = $this->optimiserPoulesChampionnat($poulesChampionnat, $occupationFixe);

            foreach ($poulesChampionnat as $pouleIdx => $poule) {
                $rapportParPouleId[$poule->getId()] = [
                    'poule' => $poule->getNom(),
                    'type' => 'championnat',
                    'scoreContrainte' => $scores[$poule->getId()]['score'] ?? null,
                    'nbBreaks' => $resultat['breaksParPoule'][$pouleIdx],
                    'surcharges' => $resultat['surchargesParPoule'][$pouleIdx],
                ];
            }
        }

        // On restitue le rapport dans l'ordre d'origine des poules de la phase.
        $rapport = [];
        foreach ($phase->getPoules() as $poule) {
            $rapport[] = $rapportParPouleId[$poule->getId()];
        }

        return $rapport;
    }

    /**
     * Version "optimisée" de createCalendar, mais limitée à une seule poule (par exemple pour le
     * bouton "Optimiser" d'une poule donnée, plutôt que toute la phase). Applique les mêmes
     * principes que creerCalendrierOptimise (priorité à l'absence de surcharge de lieu, puis
     * minimisation des breaks) mais ne régénère que cette poule : les matchs déjà existants dans
     * les AUTRES poules de la phase sont conservés tels quels, et pris en compte comme occupation
     * fixe pour ne pas créer de surcharge invisible sur un lieu qu'elles partagent.
     *
     * @return array{poule: string, type: string, nbBreaks: ?int, surcharges: string[]}
     */
    public function creerCalendrierOptimisePourPoule(Poule $poule): array
    {
        $occupationFixe = [];
        foreach ($poule->getPhase()->getPoules() as $autrePoule) {
            if ($autrePoule === $poule) {
                continue;
            }
            $this->ajouterOccupation($occupationFixe, $autrePoule->getParties());
        }

        $this->deleteMatches($poule);

        if ($poule->getPhase()->getType() !== PhaseType::CHAMPIONNAT) {
            $this->generateMatchJourneePhaseFinale($poule);

            return ['poule' => $poule->getNom(), 'type' => 'finale', 'nbBreaks' => null, 'surcharges' => []];
        }

        if (count($poule->getEquipes()) < 2) {
            return ['poule' => $poule->getNom(), 'type' => 'championnat', 'nbBreaks' => 0, 'surcharges' => []];
        }

        $resultat = $this->optimiserPoulesChampionnat([$poule], $occupationFixe);

        return [
            'poule' => $poule->getNom(),
            'type' => 'championnat',
            'nbBreaks' => $resultat['breaksParPoule'][0],
            'surcharges' => $resultat['surchargesParPoule'][0],
        ];
    }

    /**
     * Ajoute à $occupation (indexé par "lieuId|dateYmd") le décompte d'une liste de matchs déjà
     * placés (poules non concernées par l'optimisation en cours, mais dont les matchs occupent
     * tout de même des créneaux partagés).
     *
     * @param array<string, array{lieu: Lieu, count: int}> $occupation modifié par référence
     * @param iterable<Partie> $parties
     */
    private function ajouterOccupation(array &$occupation, iterable $parties): void
    {
        foreach ($parties as $partie) {
            $lieu = $partie->getLieu();
            if (!$lieu || !$partie->getDate()) {
                continue;
            }

            $cle = $lieu->getId() . '|' . $partie->getDate()->format('Y-m-d');
            if (!isset($occupation[$cle])) {
                $occupation[$cle] = ['lieu' => $lieu, 'count' => 0];
            }
            $occupation[$cle]['count']++;
        }
    }

    /**
     * Cœur de l'optimisation. Construit un round-robin pour chaque poule donnée, puis cherche
     * l'affectation domicile/extérieur qui, par ordre de priorité strict, (1) minimise la
     * surcharge des lieux utilisés par CES poules (en tenant compte de $occupationFixe, qui
     * représente des matchs déjà fixés ailleurs dans la phase) en n'utilisant que le créneau le
     * plus prioritaire de chaque lieu, puis (2) minimise le nombre de breaks. Persiste ensuite les
     * matchs générés et retourne un rapport par poule.
     *
     * Recherche locale sur trois mouvements, qui ne changent jamais qui affronte qui :
     *  a) inverser le domicile/extérieur d'un seul match ;
     *  b) inverser le domicile/extérieur de deux matchs à la fois (utile quand inverser un match
     *     seul dégrade, mais qu'inverser les deux ensemble améliore) — les deux matchs peuvent
     *     appartenir à des poules différentes puisqu'elles partagent potentiellement des lieux ;
     *  c) échanger la ronde de deux journées d'une même poule (l'ordre chronologique des rondes
     *     influence directement la séquence domicile/extérieur de chaque équipe, et les lieux
     *     utilisés chaque semaine).
     * Un mouvement n'est conservé que s'il réduit strictement le score combiné ; la pondération
     * de la surcharge (POIDS_SURCHARGE) est assez grande pour qu'aucune réduction de breaks ne
     * puisse jamais compenser une surcharge supplémentaire.
     *
     * @param Poule[] $poules
     * @param array<string, array{lieu: Lieu, count: int}> $occupationFixe
     * @return array{breaksParPoule: int[], surchargesParPoule: array<int, string[]>}
     */
    private function optimiserPoulesChampionnat(array $poules, array $occupationFixe): array
    {
        $equipesParPoule = [];
        $journeesParPoule = [];
        $schedulesByPoule = [];

        foreach ($poules as $pouleIdx => $poule) {
            $equipes = $poule->getEquipes()->toArray();
            $equipesParPoule[$pouleIdx] = $equipes;

            $journees = $poule->getJournees()->toArray();
            usort($journees, fn($j1, $j2) => $j1->getNumero() <=> $j2->getNumero());
            $journeesParPoule[$pouleIdx] = $journees;

            $pairingsByRound = $this->genererPairingsRoundRobin(count($equipes));

            // Affectation initiale : alternance simple par parité de la ronde.
            $schedule = [];
            foreach ($pairingsByRound as $round => $paires) {
                $ligne = [];
                foreach ($paires as [$a, $b]) {
                    $ligne[] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
                }
                $schedule[$round] = $ligne;
            }
            $schedulesByPoule[$pouleIdx] = $schedule;
        }

        $meilleur = $this->calculerScoreCombine($schedulesByPoule, $equipesParPoule, $journeesParPoule, $occupationFixe);

        for ($cycle = 0; $cycle < 10; $cycle++) {
            $amelioreCycle = false;

            // a) Inversion d'un seul match
            foreach ($schedulesByPoule as $pouleIdx => $schedule) {
                foreach ($schedule as $round => $paires) {
                    foreach ($paires as $i => [$home, $away]) {
                        $schedulesByPoule[$pouleIdx][$round][$i] = [$away, $home];
                        $nouveau = $this->calculerScoreCombine($schedulesByPoule, $equipesParPoule, $journeesParPoule, $occupationFixe);

                        if ($nouveau['score'] < $meilleur['score']) {
                            $meilleur = $nouveau;
                            $amelioreCycle = true;
                        } else {
                            $schedulesByPoule[$pouleIdx][$round][$i] = [$home, $away];
                        }
                    }
                }
            }

            // b) Inversion de deux matchs à la fois, éventuellement dans des poules différentes
            $references = [];
            foreach ($schedulesByPoule as $pouleIdx => $schedule) {
                foreach ($schedule as $round => $paires) {
                    foreach (array_keys($paires) as $i) {
                        $references[] = [$pouleIdx, $round, $i];
                    }
                }
            }

            foreach ($references as $a => [$pA, $rA, $iA]) {
                foreach ($references as $b => [$pB, $rB, $iB]) {
                    if ($b <= $a) {
                        continue;
                    }

                    [$homeA, $awayA] = $schedulesByPoule[$pA][$rA][$iA];
                    [$homeB, $awayB] = $schedulesByPoule[$pB][$rB][$iB];

                    $schedulesByPoule[$pA][$rA][$iA] = [$awayA, $homeA];
                    $schedulesByPoule[$pB][$rB][$iB] = [$awayB, $homeB];
                    $nouveau = $this->calculerScoreCombine($schedulesByPoule, $equipesParPoule, $journeesParPoule, $occupationFixe);

                    if ($nouveau['score'] < $meilleur['score']) {
                        $meilleur = $nouveau;
                        $amelioreCycle = true;
                    } else {
                        $schedulesByPoule[$pA][$rA][$iA] = [$homeA, $awayA];
                        $schedulesByPoule[$pB][$rB][$iB] = [$homeB, $awayB];
                    }
                }
            }

            // c) Échange de deux rondes entières, au sein d'une même poule
            foreach ($schedulesByPoule as $pouleIdx => $schedule) {
                $nbRounds = count($schedule);
                for ($r1 = 0; $r1 < $nbRounds; $r1++) {
                    for ($r2 = $r1 + 1; $r2 < $nbRounds; $r2++) {
                        $tmp = $schedulesByPoule[$pouleIdx][$r1];
                        $schedulesByPoule[$pouleIdx][$r1] = $schedulesByPoule[$pouleIdx][$r2];
                        $schedulesByPoule[$pouleIdx][$r2] = $tmp;

                        $nouveau = $this->calculerScoreCombine($schedulesByPoule, $equipesParPoule, $journeesParPoule, $occupationFixe);

                        if ($nouveau['score'] < $meilleur['score']) {
                            $meilleur = $nouveau;
                            $amelioreCycle = true;
                        } else {
                            $tmp = $schedulesByPoule[$pouleIdx][$r1];
                            $schedulesByPoule[$pouleIdx][$r1] = $schedulesByPoule[$pouleIdx][$r2];
                            $schedulesByPoule[$pouleIdx][$r2] = $tmp;
                        }
                    }
                }
            }

            if (!$amelioreCycle) {
                break;
            }
        }

        // Persistance des matchs à partir de l'affectation retenue, et suivi de l'occupation
        // réellement utilisée par chaque poule (pour attribuer les surcharges à qui de droit).
        $breaksParPoule = [];
        $clesUtiliseesParPoule = [];

        foreach ($schedulesByPoule as $pouleIdx => $schedule) {
            $poule = $poules[$pouleIdx];
            $equipes = $equipesParPoule[$pouleIdx];
            $journees = $journeesParPoule[$pouleIdx];
            $clesUtiliseesParPoule[$pouleIdx] = [];

            foreach ($schedule as $round => $paires) {
                if (!isset($journees[$round])) {
                    // Pas assez de journées créées pour couvrir toutes les rondes : on arrête là.
                    break;
                }
                $journee = $journees[$round];

                foreach ($paires as [$homeIdx, $awayIdx]) {
                    $equipeHome = $equipes[$homeIdx];
                    $equipeAway = $equipes[$awayIdx];

                    [$lieuMatch, $dateMatch] = $this->calculerLieuEtDateDomicile($equipeHome, $journee->getDateDebut());
                    $clesUtiliseesParPoule[$pouleIdx][$lieuMatch->getId() . '|' . $dateMatch->format('Y-m-d')] = true;

                    $partie = new Partie();
                    $partie->setDate($dateMatch);
                    $partie->setLieu($lieuMatch);
                    $partie->setIdEquipeRecoit($equipeHome);
                    $partie->setIdEquipeDeplace($equipeAway);
                    $partie->setJournee($journee);
                    $partie->setPoule($poule);

                    $this->em->persist($partie);
                }
            }

            $breaksParPoule[$pouleIdx] = $this->compterBreaks($schedule, count($equipes), $equipes);
        }

        $this->em->flush();

        // Messages de surcharge, attribués uniquement aux poules qui ont effectivement
        // un match sur le lieu et la date concernés.
        $messagesParCle = [];
        foreach ($meilleur['occupation'] as $cle => $info) {
            $capacite = $this->capacitePrioritaire($info['lieu']);
            if ($info['count'] > $capacite) {
                $messagesParCle[$cle] = sprintf(
                    '%s : %d match(s) prévu(s) alors que le créneau prioritaire ne peut en accueillir que %d',
                    $info['lieu']->getNom(),
                    $info['count'],
                    $capacite
                );
            }
        }

        $surchargesParPoule = [];
        foreach ($schedulesByPoule as $pouleIdx => $schedule) {
            $surcharges = [];
            foreach ($clesUtiliseesParPoule[$pouleIdx] as $cle => $_) {
                if (isset($messagesParCle[$cle])) {
                    $surcharges[] = $messagesParCle[$cle];
                }
            }
            $surchargesParPoule[$pouleIdx] = $surcharges;
        }

        return ['breaksParPoule' => $breaksParPoule, 'surchargesParPoule' => $surchargesParPoule];
    }

    /**
     * Génère, pour n équipes réelles (indices 0..n-1), les affrontements de chaque ronde
     * d'un round-robin simple via la méthode du cercle (équipe pivot = dernier index).
     * Si n est impair, une équipe BYE virtuelle est ajoutée puis les matchs l'impliquant
     * sont retirés. Ne décide pas du domicile/extérieur : retourne uniquement des paires.
     *
     * @return array<int, array<int, array{0: int, 1: int}>> [round => [[idxA, idxB], ...]]
     */
    private function genererPairingsRoundRobin(int $nbEquipesReel): array
    {
        $nbEquipe = $nbEquipesReel;
        $byeIndex = null;
        if ($nbEquipe % 2 !== 0) {
            $byeIndex = $nbEquipe;
            $nbEquipe++;
        }

        $rounds = $nbEquipe - 1;
        $matchesPerRound = intdiv($nbEquipe, 2);
        $pairingsByRound = [];

        for ($round = 0; $round < $rounds; $round++) {
            $paires = [];
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $idx1 = ($round + $match) % ($nbEquipe - 1);
                $idx2 = ($nbEquipe - 1 - $match + $round) % ($nbEquipe - 1);

                if ($match === 0) {
                    $idx2 = $nbEquipe - 1;
                }

                if ($idx1 === $byeIndex || $idx2 === $byeIndex) {
                    continue;
                }

                $paires[] = [$idx1, $idx2];
            }
            $pairingsByRound[$round] = $paires;
        }

        return $pairingsByRound;
    }

    /**
     * Calcule un score qui priorise l'absence de surcharge de lieu (créneau le plus prioritaire
     * uniquement, $occupationFixe inclus) sur la minimisation des breaks : la pondération de la
     * surcharge est assez grande pour qu'aucun nombre de breaks ne puisse jamais justifier
     * d'accepter une surcharge supplémentaire.
     *
     * @param array<int, array<int, array<int, array{0: int, 1: int}>>> $schedulesByPoule [pouleIdx => [round => [[homeIdx, awayIdx], ...]]]
     * @param array<int, Equipe[]> $equipesParPoule
     * @param array<int, Journee[]> $journeesParPoule
     * @param array<string, array{lieu: Lieu, count: int}> $occupationFixe
     * @return array{score: float, breaks: int, surchargeExcess: int, occupation: array<string, array{lieu: Lieu, count: int}>}
     */
    private function calculerScoreCombine(
        array $schedulesByPoule,
        array $equipesParPoule,
        array $journeesParPoule,
        array $occupationFixe
    ): array {
        $occupation = $occupationFixe;
        $breaksTotal = 0;

        foreach ($schedulesByPoule as $pouleIdx => $schedule) {
            $equipes = $equipesParPoule[$pouleIdx];
            $journees = $journeesParPoule[$pouleIdx];

            $breaksTotal += $this->compterBreaks($schedule, count($equipes), $equipes);

            foreach ($schedule as $round => $paires) {
                if (!isset($journees[$round])) {
                    continue;
                }
                $journee = $journees[$round];

                foreach ($paires as [$homeIdx, $awayIdx]) {
                    $equipeHome = $equipes[$homeIdx];
                    [$lieu, $date] = $this->calculerLieuEtDateDomicile($equipeHome, $journee->getDateDebut());

                    $cle = $lieu->getId() . '|' . $date->format('Y-m-d');
                    if (!isset($occupation[$cle])) {
                        $occupation[$cle] = ['lieu' => $lieu, 'count' => 0];
                    }
                    $occupation[$cle]['count']++;
                }
            }
        }

        $surchargeExcess = 0;
        foreach ($occupation as $info) {
            $surchargeExcess += max(0, $info['count'] - $this->capacitePrioritaire($info['lieu']));
        }

        return [
            'score' => self::POIDS_SURCHARGE * $surchargeExcess + $breaksTotal,
            'breaks' => $breaksTotal,
            'surchargeExcess' => $surchargeExcess,
            'occupation' => $occupation,
        ];
    }

    /**
     * Compte le nombre total de breaks sur l'ensemble des équipes, en ignorant les rondes où
     * une équipe ne joue pas (BYE), qui n'interrompent pas la suite de son statut.
     *
     * Seules les suites de rondes consécutives à l'extérieur (Ext/Ext) comptent comme des
     * breaks : rester deux fois de suite à domicile n'impose aucun déplacement, ce n'est donc
     * pas un problème. Un derby (les deux équipes du match partagent le même lieu) est traité
     * comme un match à domicile pour les deux équipes, puisqu'il ne représente pas de
     * déplacement réel.
     *
     * @param array<int, array<int, array{0: int, 1: int}>> $schedule [round => [[homeIdx, awayIdx], ...]]
     * @param Equipe[] $equipes équipes réelles indexées comme dans $schedule
     */
    private function compterBreaks(array $schedule, int $nbEquipesReel, array $equipes): int
    {
        $statuts = array_fill(0, $nbEquipesReel, []);
        foreach ($schedule as $round => $paires) {
            foreach ($paires as [$home, $away]) {
                $lieuHome = $equipes[$home]->getLieu();
                $lieuAway = $equipes[$away]->getLieu();
                $derby = $lieuHome && $lieuAway && $lieuHome === $lieuAway;

                $statuts[$home][$round] = 'H';
                $statuts[$away][$round] = $derby ? 'H' : 'A';
            }
        }

        $total = 0;
        foreach ($statuts as $sequenceParRound) {
            ksort($sequenceParRound);
            $sequence = array_values($sequenceParRound);
            for ($i = 1, $n = count($sequence); $i < $n; $i++) {
                if ($sequence[$i] === 'A' && $sequence[$i - 1] === 'A') {
                    $total++;
                }
            }
        }

        return $total;
    }

    /**
     * Détermine le lieu et la date d'un match à partir de l'équipe qui reçoit, en utilisant
     * UNIQUEMENT le créneau le plus prioritaire de son lieu : plus de repli sur un créneau
     * secondaire. Une surcharge sur ce créneau doit être résolue en choisissant une autre équipe
     * à domicile (c'est le rôle de la recherche locale), jamais en dégradant le créneau utilisé.
     *
     * @return array{0: Lieu, 1: DateTimeImmutable}
     */
    private function calculerLieuEtDateDomicile(Equipe $equipe, DateTimeImmutable $debutJournee): array
    {
        $lieu = $equipe->getLieu();
        if (!$lieu || $lieu->getCreneaux()->isEmpty()) {
            $lieu = $this->em->getRepository(Lieu::class)->getLieuByDefaut();
        }

        $creneau = $this->creneauPrioritaire($lieu);
        $date = $this->calculerDateMatch($debutJournee, (string) $creneau->getJourSemaine(), $creneau->getHeureDebut());

        return [$lieu, $date];
    }

    /**
     * Retourne le créneau le plus prioritaire d'un lieu (la plus grande valeur de getPrioritaire()).
     * On suppose qu'un lieu utilisé a toujours au moins un créneau (même hypothèse que le reste
     * du service).
     */
    private function creneauPrioritaire(Lieu $lieu): Creneau
    {
        $meilleur = null;
        foreach ($lieu->getCreneaux() as $creneau) {
            if ($meilleur === null || $creneau->getPrioritaire() > $meilleur->getPrioritaire()) {
                $meilleur = $creneau;
            }
        }

        return $meilleur;
    }

    private function capacitePrioritaire(Lieu $lieu): int
    {
        return $this->creneauPrioritaire($lieu)->getCapacite();
    }

    private function generateMatchJourneePhaseChampionnat(Poule $poule)
    {
        $equipes = $poule->getEquipes()->toArray();
        $nbEquipe = count($equipes);

        if ($nbEquipe < 2) {
            // Idéalement, lever une exception ou gérer l'erreur via un retour
            return;
        }

        // 1. Préparation du tableau d'équipes (Gestion du BYE)
        $equipeArray = $equipes;
        if ($nbEquipe % 2 != 0) {
            $equipeBye = new Equipe();
            $equipeBye->setNom("BYE");
            $equipeArray[] = $equipeBye;
            $nbEquipe++;
        }

        $rounds = $nbEquipe - 1;
        $matchesPerRound = $nbEquipe / 2;
        $journees = $poule->getJournees()->toArray();

        usort($journees, function($j1, $j2){
            return $j1->getNumero() <=> $j2->getNumero();
        });

        // 2. Algorithme de la Ronde (Circle Method)
        for ($round = 0; $round < $rounds; $round++) {
            $journee = $journees[$round];

            for ($match = 0; $match < $matchesPerRound; $match++) {
                // Calcul des indices théoriques
                $idx1 = ($round + $match) % ($nbEquipe - 1);
                $idx2 = ($nbEquipe - 1 - $match + $round) % ($nbEquipe - 1);

                // Cas particulier de l'équipe Pivot (la dernière du tableau)
                if ($match == 0) {
                    $idx2 = $nbEquipe - 1;
                }

                // LOGIQUE D'ALTERNANCE DOMICILE/EXTÉRIEUR
                // On détermine qui est 'home' et qui est 'away'
                if ($match == 0) {
                    // Pour le pivot : il reçoit lors des rounds pairs, se déplace les rounds impairs
                    if ($round % 2 == 0) {
                        $homeIdx = $idx2;
                        $awayIdx = $idx1;
                    } else {
                        $homeIdx = $idx1;
                        $awayIdx = $idx2;
                    }
                } else {
                    // Pour les autres matchs : on alterne selon la parité du round
                    if ($round % 2 == 1) {
                        $homeIdx = $idx1;
                        $awayIdx = $idx2;
                    } else {
                        $homeIdx = $idx2;
                        $awayIdx = $idx1;
                    }
                }

                $equipeHome = $equipeArray[$homeIdx];
                $equipeAway = $equipeArray[$awayIdx];

                // 3. Exclusion du match si c'est un BYE
                if ($equipeHome->getNom() === "BYE" || $equipeAway->getNom() === "BYE") {
                    continue;
                }

                // 4. Création de la partie
                $partie = new \App\Entity\Partie();

                // Calcul de la date selon les préférences de l'équipe qui REÇOIT
                $lieuMatch = $equipeHome->getLieu();
                $creneau = $lieuMatch->getCreneaux()[0]; // On suppose qu'il y a au moins un créneau

                $dateMatch = $this->calculerDateMatch(
                    $journee->getDateDebut(),
                    $creneau->getJourSemaine(),
                    $creneau->getHeureDebut(),
                );

                $partie->setDate($dateMatch);
                $partie->setLieu($lieuMatch);
                $partie->setIdEquipeRecoit($equipeHome);
                $partie->setIdEquipeDeplace($equipeAway);
                $partie->setJournee($journee);
                $partie->setPoule($poule);

                $this->em->persist($partie);
            }
        }
        $this->em->flush();
    }
    private function generateMatchJourneePhaseFinale(Poule $poule ){
        $lieuRepository = $this->em->getRepository(Lieu::class);
        $equipes = $poule->getEquipes()->toArray();
        $nbEquipes = count($equipes);
        $journees = $poule->getJournees()->toArray();
        usort($journees, fn($j1, $j2) => $j1->getNumero() <=> $j2->getNumero());

        // 1. Calculer la structure
        // puissance de 2 inférieure (ex: pour 12 c'est 8)
        $p = pow(2, floor(log($nbEquipes, 2)));
        $nbBarrages = $nbEquipes - $p;
        $nbExemptes = $p - $nbBarrages;

        $matchsParTour = [];

        // 2. Création de tous les matchs "vides" par tour
        foreach ($journees as $indexJournee => $journee) {
            $tourMatchs = [];

            // Déterminer combien de matchs dans ce tour
            if ($indexJournee === 0 && $nbBarrages > 0) {
                $nbMatchsAcreer = $nbBarrages; // Tour de barrages
            } else {
                // Pour les tours suivants, on divise par 2 à chaque fois
                // Le premier tour complet a $p/2$ matchs (ex: 8 équipes -> 4 matchs)
                $distanceFinale = count($journees) - 1 - $indexJournee;
                $nbMatchsAcreer = pow(2, $distanceFinale);
            }

            for ($i = 0; $i < $nbMatchsAcreer; $i++) {
                $match = new Partie();
                $match->setJournee($journee);
                $match->setPoule($poule);
                $match->setNom($journee->getNom() . ' - Match ' . ($i + 1));




                $this->em->persist($match);
                $tourMatchs[] = $match;
            }
            $matchsParTour[$indexJournee] = $tourMatchs;
        }

// ... (Sections 1 et 2 inchangées) ...

// 3 & 4. Liaison et Remplissage des équipes de départ
        $equipeIndex = 0;
        $tourCible = ($nbBarrages > 0) ? 1 : 0;
        $matchsDuTourSuivant = $matchsParTour[$tourCible];

// A. On place d'abord les EXEMPTÉS (Têtes de série)
// On les répartit : d'abord tous les "Recoit", puis si on en a encore, les "Deplace"
        foreach ($matchsDuTourSuivant as $match) {
            if ($equipeIndex < $nbExemptes) {
                $match->setIdEquipeRecoit($equipes[$equipeIndex++]);
            }
        }
        foreach ($matchsDuTourSuivant as $match) {
            if ($equipeIndex < $nbExemptes && $match->getIdEquipeDeplace() === null) {
                $match->setIdEquipeDeplace($equipes[$equipeIndex++]);
            }
        }

// B. On remplit les BARRAGES (Round 0) et on les lie aux places LIBRES du tour suivant
        if ($nbBarrages > 0) {
            $barrageIndex = 0;
            $matchsBarrage = $matchsParTour[0];

            // 1. Remplissage des matchs de barrage avec les équipes restantes
            foreach ($matchsBarrage as $matchB) {
                $matchB->setIdEquipeRecoit($equipes[$equipeIndex++]);
                $matchB->setIdEquipeDeplace($equipes[$equipeIndex++]);
            }

            // 2. LIAISON : On lie chaque match de barrage à une place vide (null) dans le tour suivant
            foreach ($matchsDuTourSuivant as $matchS) {
                // Si le slot Recoit est vide, on y lie un barrage
                if ($matchS->getIdEquipeRecoit() === null && $barrageIndex < $nbBarrages) {
                    $matchS->setParentMatch1($matchsBarrage[$barrageIndex++]);
                }
                // Si le slot Deplace est vide, on y lie un barrage
                if ($matchS->getIdEquipeDeplace() === null && $barrageIndex < $nbBarrages) {
                    $matchS->setParentMatch2($matchsBarrage[$barrageIndex++]);
                }
            }
        }

// C. Liaison des tours suivants (Quarts vers Demis, Demis vers Finale)
// On commence après le tour de barrage ou le premier tour
        $startTour = $tourCible;
        for ($t = $startTour; $t < count($matchsParTour) - 1; $t++) {
            foreach ($matchsParTour[$t] as $indexMatch => $matchActuel) {
                $prochainTour = $matchsParTour[$t + 1];
                $targetMatch = $prochainTour[floor($indexMatch / 2)];

                if ($indexMatch % 2 === 0) {
                    $targetMatch->setParentMatch1($matchActuel);
                } else {
                    $targetMatch->setParentMatch2($matchActuel);
                }
            }
        }

        // 5. Remplissage deu lieu et de la date
        foreach ($matchsParTour as $tourMatchs) {
            /** @var Partie $match */
            foreach ($tourMatchs as $match) {
                //Soit on sait qui reçoit donc on peut fixer le lieu et calculer la date
                if ($match->getIdEquipeRecoit() !== null) {
                    $match->setLieu($match->getIdEquipeRecoit()->getLieu());
                    $dateMatch = $this->calculerDateMatch(
                        $match->getJournee()->getDateDebut(),
                        $match->getIdEquipeRecoit()->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $match->getIdEquipeRecoit()->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $match->setDate($dateMatch);

                }else{
                    //On fixe la date au premier jour de la semaine à 20h


                    $match->setLieu($lieuRepository->getLieuByDefaut());
                    $dateMatch = $this->calculerDateMatch(
                        $match->getJournee()->getDateDebut(),
                        $match->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $match->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $match->setDate($dateMatch);

                }

            }
        }

        $this->em->flush();
        return null;


    }
    private function deleteMatches(Poule $poule): void
    {
        foreach ($poule->getJournees() as $journee) {
            //On supprime les matchs de cette journée
            $oldParties = $journee->getParties();
            foreach ($oldParties as $partie) {
                $this->em->remove($partie);
            }
        }
        $this->em->flush();
    }

    /**
     * Calcule la date du match en fonction de la journée, du jour du lieu et de l'heure.
     */
    public function calculerDateMatch(DateTimeImmutable $dateDebut, string $jourFr, DateTimeInterface $heure): DateTimeImmutable
    {
        $jourFr = strtolower(trim($jourFr));

        if (!isset(self::JOURS_MAP[$jourFr])) {
            throw new \InvalidArgumentException("Jour invalide : $jourFr");
        }

        $jourEn = self::JOURS_MAP[$jourFr];

        // Si la date de début tombe déjà ce jour
        if (strtolower($dateDebut->format('l')) === $jourEn) {
            $dateMatch = $dateDebut;
        } else {
            $dateMatch = $dateDebut->modify("next $jourEn");
        }

        // Applique l'heure
        return $dateMatch->setTime(
            (int) $heure->format('H'),
            (int) $heure->format('i')
        );
    }
}
