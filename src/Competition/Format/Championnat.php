<?php

namespace App\Competition\Format;

use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Championnat aller simple (format A1 de docs/formats-competition.md) : chaque équipe rencontre une fois chaque
 * autre équipe, une journée par semaine, en sautant les semaines indisponibles de la saison. Les matchs sont
 * normalement placés par l'optimiseur de PartieService ; genererMatchs est la génération simple, sans optimisation.
 */
final class Championnat implements GenerateurFormat
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function supporte(Phase $phase): bool
    {
        return $phase->getType() === PhaseType::CHAMPIONNAT;
    }

    public function estOptimisable(): bool
    {
        return true;
    }

    public function creerJournees(Poule $poule): ?string
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
                return "La phase est trop courte pour contenir toutes les journées.";
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

    public function genererMatchs(Poule $poule): void
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
                if ($equipeHome->getNomSansClub() === "BYE" || $equipeAway->getNomSansClub() === "BYE") {
                    continue;
                }

                // 4. Création de la partie
                $partie = new Partie();

                // Calcul de la date selon les préférences de l'équipe qui REÇOIT
                $lieuMatch = $equipeHome->getLieu();
                $creneau = $lieuMatch->getCreneaux()[0]; // On suppose qu'il y a au moins un créneau

                $dateMatch = DateMatch::calculer(
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
}
