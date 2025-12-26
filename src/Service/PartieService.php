<?php

namespace App\Service;
use App\Entity\Poule;
use App\Entity\Partie;
use App\Enum\PhaseType;
use App\Entity\Journee;
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


    public function createCalendar(Poule $poule): ?string
    {
        // 1. Nettoyage des anciens matchs
        $this->deleteMatches($poule);

        // 2. Choix de l'algorithme selon le type de poule
        // On imagine que tu as un champ 'type' ou 'isFinale' dans ton entité Poule
        if ($poule->getPhase()->getType() === PhaseType::CHAMPIONNAT) {

            return $this->generateMatchJourneePhaseChampionnat($poule);
        }else{

            return $this->generateMatchJourneePhaseFinale($poule);
        }

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
                    $homeIndex = ($round + $match) % ($nbEquipe - 1);
                    $awayIndex = ($nbEquipe - 1 - $match + $round) % ($nbEquipe - 1);

                    // La dernière équipe reste fixe
                    if ($match == 0) {
                        $awayIndex = $nbEquipe - 1;
                    }

                    // Si l'une des équipes est fictive, on ne crée pas le match
                    if ($equipeArray[$homeIndex]->getNom() === "BYE" || $equipeArray[$awayIndex]->getNom() === "BYE") {
                        continue;
                    }

                    $partie = new \App\Entity\Partie();

                    $dateMatch = $this->calculerDateMatch(
                        $journee->getDateDebut(),
                        $equipeArray[$homeIndex]->getLieu()->getCreneaux()[0]->getJourSemaine(),
                        $equipeArray[$homeIndex]->getLieu()->getCreneaux()[0]->getHeureDebut(),
                    );
                    $partie->setDate($dateMatch);
                    $partie->setLieu($equipeArray[$homeIndex]->getLieu());
                    $partie->setIdEquipeRecoit($equipeArray[$homeIndex]);
                    $partie->setIdEquipeDeplace($equipeArray[$awayIndex]);
                    $partie->setIdJournee($journee);
                    $partie->setPoule($poule);
                    $this->em->persist($partie);
                }
            }
            $this->em->flush();

        }
    }
    private function generateMatchJourneePhaseFinale(){

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
