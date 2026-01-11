<?php

namespace App\Service;
use App\Entity\Poule;
use App\Entity\Partie;
use App\Entity\Lieu;
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
        private EntityManagerInterface $em
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

    private function generateMatchJourneePhaseChampionnat(Poule $poule){

            $equipes = $poule->getEquipes()->toArray();
        $nbEquipe = count($equipes);
        if ($nbEquipe<2){
            $error="Il faut au moins deux équipes dans la poule";
        }else{
            //Algorithme de la ronde
            $equipeArray = $equipes;
            if ($nbEquipe % 2 != 0) {
                $equipeBye = new Equipe();
                $equipeBye->setNom("BYE");
                $equipeArray[] = $equipeBye;
                $nbEquipe++; // On met à jour le nombre total
            }
            $rounds = $nbEquipe - 1;
            $matchesPerRound = $nbEquipe / 2;
            $journees = $poule->getJournees()->toArray();
            // Tri selon l’attribut "numero"
            usort($journees, function($j1, $j2){
                return $j1->getNumero() <=> $j2->getNumero();
            });

            for ($round = 0; $round < $rounds; $round++) {
                $journee = $journees[$round];
                for ($match = 0; $match < $matchesPerRound; $match++) {
                    $home = ($round + $match) % ($nbEquipe - 1);
                    $away = ($nbEquipe - 1 - $match + $round) % ($nbEquipe - 1);

                    // La dernière équipe reste fixe
                    if ($match == 0) {
                        $away = $nbEquipe - 1;
                    }

                    //LOGIQUE D'ALTERNANCE
                    // Pour le pivot (match 0), on inverse domicile/extérieur chaque round
                    // Pour les autres, on inverse si le numéro de round est impair
                    if ($round % 2 == 1) {
                        $temp = $home;
                        $home = $away;
                        $away = $temp;
                    }


                    // Si l'une des équipes est fictive, on ne crée pas le match
                    if ($equipeArray[$home]->getNom() === "BYE" || $equipeArray[$away]->getNom() === "BYE") {
                        continue;
                    }

                    $partie = new \App\Entity\Partie();
                    $equipeHome = $equipeArray[$home];
                    $equipeAway = $equipeArray[$away];

                    $dateMatch = $this->calculerDateMatch(
                        $journee->getDateDebut(),
                        $equipeHome->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $equipeHome->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $partie->setDate($dateMatch);
                    $partie->setLieu($equipeHome->getLieu());
                    $partie->setIdEquipeRecoit($equipeHome);
                    $partie->setIdEquipeDeplace($equipeAway);
                    $journee->addParty($partie);
                    $poule->addParty($partie);

                    $this->em->persist($partie);
                    $this->em->persist($journee);
                    $this->em->persist($poule);
                }
            }
            $this->em->flush();

        }
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
