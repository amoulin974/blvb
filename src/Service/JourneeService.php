<?php

namespace App\Service;

use App\Entity\Poule;
use App\Entity\Phase;
use App\Enum\PhaseType;
use App\Entity\Journee;
use Doctrine\ORM\EntityManagerInterface;

class JourneeService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function creerJournees(Poule $poule): ?string
    {
        $nbEquipe = count($poule->getEquipes());
        if ($nbEquipe < 2) {
            return "Il faut au moins deux équipes dans la poule";
        }

        // Supprime les anciennes journées
        foreach ($poule->getJournees() as $journee) {
            $this->em->remove($journee);
        }
        $this->em->flush();

        $phase = $poule->getPhase();

        if ($phase->getType() === PhaseType::CHAMPIONNAT) {
            return $this->creerJourneesChampionnat($poule);
        } elseif ($phase->getType() === PhaseType::FINALE) {
            return $this->creerJourneesFinales($poule);
        }else{
            return "Type de phase inconnu";
        }


    }

    private function creerJourneesChampionnat(Poule $poule): ?string
    {
        //On vérifie si la première poule de la phase à déjà des journées et si le nombre d'équipe est identique. Si c'est le cas on copie les journées
        $phase=$poule->getPhase();
        $poulesPhase=$phase->getPoules();
        $firstPoule=$poulesPhase[0];
        $nbEquipe = count($poule->getEquipes());

        //Si la première poule a le même nombre d'équipes et des journées on les copie
        if (count($firstPoule->getEquipes())==$nbEquipe && count($firstPoule->getJournees())>0){
            foreach($firstPoule->getJournees() as $journeefirstpoule){
                $newJournee=new Journee;
                $newJournee->setDateDebut($journeefirstpoule->getDateDebut());
                $newJournee->setDateFin($journeefirstpoule->getDateFin());
                $newJournee->setNumero($journeefirstpoule->getNumero());
                $newJournee->setPoule($poule);
                $this->em->persist($newJournee);

            }
            $this->em->flush();
        }else{
            //Calcul du nombre de journées nécessaires
            $nbMatch=$nbEquipe*($nbEquipe-1)/2;
            if ($nbEquipe % 2 == 0){
                $nbMatchParJour = $nbEquipe/2;
            }else{
                $nbMatchParJour = ($nbEquipe-1)/2;
            }
            $nbJournee = $nbMatch/$nbMatchParJour;


            // Dates de la phase
            $debutPhase = $poule->getPhase()->getDateDebut();
            $finPhase = $poule->getPhase()->getDateFin();

            // Vérifie si la phase peut contenir toutes les journées (1 semaine par journée)
            $dureePhaseEnSemaines = intval($finPhase->diff($debutPhase)->days / 7) + 1;
            if ($dureePhaseEnSemaines < $nbJournee) {
                $error = "La phase est trop courte pour contenir toutes les journées.";
            } else {

                $this->em->flush();

                // Génération des journées
                $currentDate = clone $debutPhase;
                for ($i = 1; $i <= $nbJournee; $i++) {

                    // Skip Noël et Jour de l'an
                    $annee = (int)$currentDate->format('Y');
                    $noel = new \DateTimeImmutable("$annee-12-25");
                    $jourAn = new \DateTimeImmutable(($annee+1)."-01-01");

                    while (($currentDate <= $finPhase) &&
                        ($currentDate <= $noel && $currentDate >= $noel->modify('-6 days') ||
                            $currentDate <= $jourAn && $currentDate >= $jourAn->modify('-6 days'))
                    ) {
                        $currentDate = $currentDate->modify('+1 week');
                    }

                    $journee = new Journee();
                    $journee->setNumero($i);

                    // Début de la semaine (lundi)
                    $debutSemaine = $currentDate->modify('Monday this week');
                    $finSemaine = $currentDate->modify('Sunday this week');

                    $journee->setDateDebut(new \DateTimeImmutable($debutSemaine->format('Y-m-d')));
                    $journee->setDateFin(new \DateTimeImmutable($finSemaine->format('Y-m-d')));
                    $journee->setPoule($poule);

                    $this->em->persist($journee);

                    // Passe à la semaine suivante
                    $currentDate = $currentDate->modify('+1 week');
                }

                $this->em->flush();
            }
        }
        return null;

    }

    //Méthode utilisée pour crééer les journée d'une phase du type finale
    private function creerJourneesFinales(Poule $poule): ?string
    {
        $nbEquipe = count($poule->getEquipes());
        if (($nbEquipe & ($nbEquipe - 1)) !== 0) { // Vérifie si puissance de 2
            return "Le nombre d'équipes pour les finales doit être une puissance de 2";
        }

        $debutPhase = $poule->getPhase()->getDateDebut();
        $currentDate = clone $debutPhase;
        $nbJournee = (int)log($nbEquipe, 2); // 8 équipes → 3 journées (quart, demi, finale)

        for ($i = 1; $i <= $nbJournee; $i++) {
            $journee = new Journee();
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
}
