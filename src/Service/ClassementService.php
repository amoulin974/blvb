<?php

namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Poule;
use App\Entity\Classement;
use App\Entity\Saison;
use App\Repository\ClassementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class ClassementService
{
    public function __construct(
        private ClassementRepository $classementRepository,
        private EntityManagerInterface $em
    ) {}

    /** 
    * Ajoute un champ classement à toutes les équipes de chaque poules dans chaque phase d'une saison donnéee en paamètre
    */
    public function getClassement(Saison $saison){
        /** Phase $phase */
        foreach ($saison->getPhases() as $phase){
            /** Poule $poule */
            foreach ($phase->getPoules() as $poule){
                //Ordonancement des classements dans l'ordre d
                $this->OrderClassementPouleByPosition($poule);
                /** Equipe $equipe */
                foreach ($poule->getEquipes() as $equipe){
                    /** Classement $classment */
                    $position = 0;
                    foreach ($equipe->getClassements() as $classement){
                        if ($classement->getPoule()==$poule){
                            $equipe->position=$classement->getPosition();
                            $position=1;
                        }
                        
                    }
                    if ($position === 0){
                            $equipe->position="non défini";
                        }
                }

            }
        }
    }

    /**Ordonne les classement d'une poule en fonction de leur position */
    public function OrderClassementPouleByPosition(Poule $poule){
        if (! is_null($poule->getClassements())){
            $classements=$poule->getClassements()->toArray();
            usort($classements, function($a, $b) {
            $posA=$a->getPosition();
            $posB=$b->getPosition();
            return $posA <=> $posB;
             });
             $poule->setClassements(new ArrayCollection($classements));
        }
        
    }
    /**
     * Met à jour le classement pour une poule entière.
     */
    public function mettreAJourClassementPoule(Poule $poule): void
    {
        $classements = [];

        foreach ($poule->getEquipes() as $equipe) {
            $classements[] = [
                'equipe'      => $equipe,
                'points'      => $this->calculerPointsEquipe($equipe),
                'setsGagnes'  => $this->calculerTotalSetsGagnes($equipe),
                'setsPerdus'  => $this->calculerTotalSetsPerdus($equipe),
            ];
        }

        // Tri par points DESC puis par ratio sets
        usort($classements, function ($a, $b) use ($poule) {

            // 1) Tri par points
            if ($b['points'] !== $a['points']) {
                return $b['points'] <=> $a['points'];
            }

            // 2) Différence de sets (sets gagnés - sets perdus)
            $diffA = $a['setsGagnes'] - $a['setsPerdus'];
            $diffB = $b['setsGagnes'] - $b['setsPerdus'];

            if ($diffB !== $diffA) {
                return $diffB <=> $diffA;
            }

            // 3) Confrontation directe (si les deux équipes se sont déjà rencontrées)
            return $this->confrontationDirecte($a['equipe'], $b['equipe'], $poule);
        });

        // Mise à jour / insertion classement
        $position = 1;
        foreach ($classements as $data) {

            $classement = $this->classementRepository->findOneBy([
                'poule' => $poule,
                'equipe' => $data['equipe']
            ]);

            if (!$classement) {
                $classement = new Classement();
                $classement->setPoule($poule);
                $classement->setEquipe($data['equipe']);
            }

            $classement->setPoints($data['points']);
            $classement->setSetGagnes($data['setsGagnes']);
            $classement->setSetPerdus($data['setsPerdus']);
            $classement->setPosition($position++);

            $this->em->persist($classement);
        }

        $this->em->flush();
    }

    /**
     * Calcule les points pour une équipe (domicile + extérieur).
     */
    public function calculerPointsEquipe(Equipe $equipe): int
    {
        $points = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            $points += $this->pointsPourMatch(
                $partie->getNbSetGagnantReception(),
                $partie->getNbSetGagnantDeplacement()
            );
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            $points += $this->pointsPourMatch(
                $partie->getNbSetGagnantDeplacement(),
                $partie->getNbSetGagnantReception()
            );
        }

        return $points;
    }

    /**
     * Retourne les points du match selon le score.
     */
    private function pointsPourMatch(?int $setsGagnes, ?int $setsPerdus): int
    {
        if ($setsGagnes === null || $setsPerdus === null) {
            return 0; // match non joué
        }

        return match([$setsGagnes, $setsPerdus]) {
            [3,0], [3,1] => 3,
            [3,2] => 2,
            [2,3] => 1,
            default => 0
        };
    }

    /**
     * Total sets gagnés.
     */
    private function calculerTotalSetsGagnes(Equipe $equipe): int
    {
        $total = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            $total += $partie->getNbSetGagnantReception() ?? 0;
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            $total += $partie->getNbSetGagnantDeplacement() ?? 0;
        }

        return $total;
    }

    /**
     * Total sets perdus.
     */
    private function calculerTotalSetsPerdus(Equipe $equipe): int
    {
        $total = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            $total += $partie->getNbSetGagnantDeplacement() ?? 0;
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            $total += $partie->getNbSetGagnantReception() ?? 0;
        }

        return $total;
    }

    /**
         * Compare deux équipes sur la base de leur confrontation directe.
         * Retourne :
         *  -1 si $equipeA doit passer après $equipeB
         *  +1 si $equipeA doit passer avant $equipeB
         *   0 si aucune info / égalité parfaite
         */
        private function confrontationDirecte(Equipe $equipeA, Equipe $equipeB, Poule $poule): int
        {
            foreach ($poule->getParties() as $partie) {

                // Cas A reçoit B
                if ($partie->getIdEquipeRecoit() === $equipeA && $partie->getIdEquipeDeplace() === $equipeB) {
                    if ($partie->getNbSetGagnantReception() > $partie->getNbSetGagnantDeplacement()) {
                        return +1; // A a gagné
                    } else {
                        return -1; // B a gagné
                    }
                }

                // Cas B reçoit A
                if ($partie->getIdEquipeRecoit() === $equipeB && $partie->getIdEquipeDeplace() === $equipeA) {
                    if ($partie->getNbSetGagnantReception() > $partie->getNbSetGagnantDeplacement()) {
                        return -1; // B a gagné en recevant
                    } else {
                        return +1; // A a gagné en déplacement
                    }
                }
            }

            return 0; // aucune confrontation ou scores non saisis → aucune préférence
        }
}
