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
        $poule->getJournees()->clear();
        $this->em->flush();

        $phase = $poule->getPhase();


        if ($phase->getType() === PhaseType::CHAMPIONNAT) {
            $this->creerJourneesChampionnat($poule);
            return null;
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
        $firstPoule=$poulesPhase->first();
        $nbEquipe = count($poule->getEquipes());

        //Si la première poule a le même nombre d'équipes et des journées on les copie
        if (count($firstPoule->getEquipes())==$nbEquipe && count($firstPoule->getJournees())>0){

            foreach($firstPoule->getJournees() as $journeefirstpoule){
                $newJournee=new Journee;
                $newJournee->setDateDebut($journeefirstpoule->getDateDebut());
                $newJournee->setDateFin($journeefirstpoule->getDateFin());
                $newJournee->setNumero($journeefirstpoule->getNumero());
                $poule->addJournee($newJournee);
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

                // Récupération des indisponibilités de la saison
                $indisponibilites = $poule->getPhase()->getSaison()->getIndisponibilites();

                // Génération des journées
                $currentDate = clone $debutPhase;

                for ($i = 1; $i <= $nbJournee; $i++) {

                    // BOUCLE DE SAUT : On avance d'une semaine tant qu'on est sur une période indisponible
                    while ($currentDate <= $finPhase && $this->isSemaineIndisponible($currentDate, $indisponibilites)) {
                        $currentDate = $currentDate->modify('+1 week');
                    }

                    // Sécurité : si on a épuisé le calendrier de la phase en sautant des semaines
                    if ($currentDate > $finPhase) {
                        return "La phase est trop courte pour placer les $nbJournee journées avec les indisponibilités saisies.";
                    }

                    $journee = new Journee();
                    $journee->setNumero($i);

                    // Début de la semaine (lundi)
                    $debutSemaine = $currentDate->modify('Monday this week');
                    $finSemaine = $currentDate->modify('Sunday this week');

                    $journee->setDateDebut(new \DateTimeImmutable($debutSemaine->format('Y-m-d')));
                    $journee->setDateFin(new \DateTimeImmutable($finSemaine->format('Y-m-d')));
                    $poule->addJournee($journee);

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
    /**
     * Vérifie si une semaine donnée chevauche une période d'indisponibilité.
     */
    private function isSemaineIndisponible(\DateTimeImmutable $date, iterable $indisponibilites): bool
    {
        // On définit la plage de la semaine entière (du lundi 00:00 au dimanche 23:59)
        $debutSemaine = $date->modify('monday this week')->setTime(0, 0, 0);
        $finSemaine = $date->modify('sunday this week')->setTime(23, 59, 59);

        foreach ($indisponibilites as $indispo) {
            //
            $debutIndispo = $indispo->getDateDebut()->setTime(0, 0, 0);
            $finIndispo = $indispo->getDateFin()->setTime(23, 59, 59);

            // Formule de chevauchement de deux périodes : (DébutA <= FinB) ET (FinA >= DébutB)
            if ($debutSemaine <= $finIndispo && $finSemaine >= $debutIndispo) {
                return true;
            }
        }
        return false;
    }

}
