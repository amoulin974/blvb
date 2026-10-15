<?php

namespace App\Competition\Format;

use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Élimination directe (format B1 de docs/formats-competition.md), la phase finale actuelle : tableau ramené à la
 * puissance de 2 inférieure par un tour de barrages, une journée par tour, matchs reliés par Partie::parentMatch1/2.
 * Les équipes sont placées dans l'ordre de la poule (les premières sont exemptées du barrage).
 */
final class Elimination implements GenerateurFormat
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function supporte(Phase $phase): bool
    {
        return $phase->getType() === PhaseType::FINALE;
    }

    public function estOptimisable(): bool
    {
        return false;
    }

    //Méthode utilisée pour crééer les journée d'une phase du type finale
    public function nombreMatchs(Poule $poule): int
    {
        $n = count($poule->getEquipes());

        return $n < 2 ? 0 : $n - 1;
    }

    public function nombreJournees(Poule $poule): int
    {
        $n = count($poule->getEquipes());
        if ($n < 2) {
            return 0;
        }
        $puissance = (int) pow(2, floor(log($n, 2)));
        $tours = (int) log($puissance, 2);

        return $n === $puissance ? $tours : $tours + 1;
    }

    public function creerJournees(Poule $poule): ?string
    {
        $equipes = $poule->getEquipes();
        $nbEquipe = count($equipes);

        if ($nbEquipe < 2) {
            return "Il faut au moins 2 équipes pour une phase finale.";
        }

        // 1. Calculer la puissance de 2 inférieure la plus proche
        // Exemple : pour 12, log2(12) = 3.58 -> floor = 3 -> 2^3 = 8.
        $puissanceInf = pow(2, floor(log($nbEquipe, 2)));

        // 2. Déterminer s'il y a des barrages
        $aDesBarrages = ($nbEquipe != $puissanceInf);

        // 3. Calculer le nombre total de journées
        // Si barrages : 1 (barrage) + log2(puissanceInf)
        // Exemple 12 équipes : 1 (barrage) + 3 (1/4, 1/2, F) = 4 journées.
        $nbJourneesFinales = (int)log($puissanceInf, 2);
        $totalJournees = $aDesBarrages ? $nbJourneesFinales + 1 : $nbJourneesFinales;

        $debutPhase = $poule->getPhase()->getDateDebut();
        $currentDate = clone $debutPhase;

        for ($i = 1; $i <= $totalJournees; $i++) {
            $journee = new Journee();

            $nom = $this->getNomJournee($i, $totalJournees, $aDesBarrages);
            $journee->setNom($nom);
            $journee->setNumero($i);

            $journee->setDateDebut(new \DateTimeImmutable($currentDate->format('Y-m-d')));
            $journee->setDateFin(new \DateTimeImmutable($currentDate->modify('+6 days')->format('Y-m-d')));
            $journee->setPoule($poule);

            $this->em->persist($journee);
            $currentDate = $currentDate->modify('+1 week');
        }

        $this->em->flush();
        return null;
    }

    //Fonction qui nomme les journées
    private function getNomJournee(int $index, int $total, bool $hasBarrage): string
    {
        if ($hasBarrage && $index === 1) {
            return "Barrages";
        }

        // On calcule l'index réel par rapport à la finale (la finale est toujours le dernier tour)
        $distanceDeLaFinale = $total - $index;

        return match ($distanceDeLaFinale) {
            0 => "Finale",
            1 => "Demi-finale",
            2 => "Quart de finale",
            3 => "8ème de finale",
            4 => "16ème de finale",
            default => "Tour " . $index,
        };
    }

    public function genererMatchs(Poule $poule): void
    {
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
                    $dateMatch = DateMatch::calculer(
                        $match->getJournee()->getDateDebut(),
                        $match->getIdEquipeRecoit()->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $match->getIdEquipeRecoit()->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $match->setDate($dateMatch);

                }else{
                    //On fixe la date au premier jour de la semaine à 20h
                    $match->setLieu($lieuRepository->getLieuByDefaut());
                    $dateMatch = DateMatch::calculer(
                        $match->getJournee()->getDateDebut(),
                        $match->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $match->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $match->setDate($dateMatch);

                }

            }
        }

        $this->em->flush();
    }
}
