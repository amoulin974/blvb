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
     * Met à jour le classement pour une poule entière.
     */
    public function mettreAJourClassementPoule(Poule $poule): void
    {
        $classements = [];
        $saison = $poule->getPhase()->getSaison();
        foreach ($poule->getEquipes() as $equipe) {
            $classements[] = [
                'equipe'      => $equipe,
                'points'      => $this->calculerPointsEquipe($equipe, $saison, $poule),
                'setsGagnes'  => $this->calculerTotalSetsGagnes($equipe, $poule),
                'setsPerdus'  => $this->calculerTotalSetsPerdus($equipe, $poule),
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

            // 3) Confrontation directe : le dernier match joué entre les deux équipes (aller ou retour)
            if (($direct = $this->confrontationDirecte($a['equipe'], $b['equipe'], $poule)) !== 0) {
                return $direct;
            }

            // 4) Ordre alphabétique, pour un classement toujours identique d'un calcul à l'autre
            return strcasecmp((string) $a['equipe']->getNomSansClub(), (string) $b['equipe']->getNomSansClub());
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
    public function calculerPointsEquipe(Equipe $equipe, Saison $saison, ?Poule $pouleContext = null): int
    {
        $points = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            $points += $this->pointsPourMatch(
                $partie->getNbSetGagnantReception(),
                $partie->getNbSetGagnantDeplacement(),
                $saison
            );
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            $points += $this->pointsPourMatch(
                $partie->getNbSetGagnantDeplacement(),
                $partie->getNbSetGagnantReception(),
                $saison
            );
        }

        return $points;
    }

    /**
     * Retourne les points du match selon le score, du point de vue de l'équipe qui a gagné $setsGagnes sets.
     * Règlement (règle 1D) : 3-0 / 3-1 → victoire forte, 3-2 → victoire faible, 2-3 → défaite forte,
     * 1-3 / 0-3 → défaite faible ; « Partie forfait = 3 points pour le gagnant, -1 point pour le perdant ».
     * Un forfait est enregistré à -1 set pour l'équipe forfait (api_score_update) : le gagnant reçoit
     * les points d'une victoire forte, l'équipe forfait les points de forfait de la saison.
     */
    private function pointsPourMatch(?int $setsGagnes, ?int $setsPerdus, Saison $saison): int
    {
        if ($setsGagnes === null || $setsPerdus === null) {
            return 0; // match non joué
        }

        if ($setsGagnes === -1) {
            return $saison->getPointsForfait(); // équipe déclarée forfait
        }
        if ($setsPerdus === -1) {
            return $saison->getPointsVictoireForte(); // victoire par forfait de l'adversaire
        }

        // Match interrompu (règle 1R) : points fixés par le règlement
        if (($interrompu = RegleScore::pointsInterrompu($setsGagnes, $setsPerdus)) !== null) {
            return $interrompu;
        }

        return match([$setsGagnes, $setsPerdus]) {
            [3,0], [3,1] => $saison->getPointsVictoireForte(),
            [3,2] => $saison->getPointsVictoireFaible(),
            [2,3] => $saison->getPointsDefaiteForte(),
            [1,3], [0,3] => $saison->getPointsDefaiteFaible(),
            default => 0, // score hors règlement (refusé à la saisie depuis octobre 2026)
        };
    }

    /**
     * Total sets gagnés.
     */
    private function calculerTotalSetsGagnes(Equipe $equipe, ?Poule $pouleContext = null): int
    {
        $total = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            if (!RegleScore::setsComptes($partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement())) {
                continue; // match interrompu à 2-2 : set-average inchangé (règle 1R)
            }
            $val = $partie->getNbSetGagnantReception();
            // Si c'est un forfait (-1), on ajoute 0, sinon on ajoute la valeur
            $total += ($val === -1) ? 0 : ($val ?? 0);
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            if (!RegleScore::setsComptes($partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement())) {
                continue; // match interrompu à 2-2 : set-average inchangé (règle 1R)
            }
            $val = $partie->getNbSetGagnantDeplacement();
            $total += ($val === -1) ? 0 : ($val ?? 0);
        }

        return $total;
    }

    /**
     * Total sets perdus.
     */
    private function calculerTotalSetsPerdus(Equipe $equipe, ?Poule $pouleContext = null): int
    {
        $total = 0;

        foreach ($equipe->getPartiesReception() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            if (!RegleScore::setsComptes($partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement())) {
                continue; // match interrompu à 2-2 : set-average inchangé (règle 1R)
            }
            $valAdversaire = $partie->getNbSetGagnantDeplacement();
            // L'équipe perd les sets que l'adversaire a gagnés.
            // Si l'adversaire est forfait (-1), l'équipe n'en perd aucun (0).
            $total += ($valAdversaire === -1) ? 0 : ($valAdversaire ?? 0);
        }

        foreach ($equipe->getPartiesDeplacement() as $partie) {
            if ($pouleContext && $partie->getPoule() !== $pouleContext) {
                continue;
            }
            if (!RegleScore::setsComptes($partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement())) {
                continue; // match interrompu à 2-2 : set-average inchangé (règle 1R)
            }
            $valAdversaire = $partie->getNbSetGagnantReception();
            $total += ($valAdversaire === -1) ? 0 : ($valAdversaire ?? 0);
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
    /**
     * Départage par la confrontation directe : seul le DERNIER match joué entre les deux équipes dans la poule compte
     * (aller ou retour, décision d'octobre 2026). Comparateur pour usort : -1 si A l'a gagné (A devant), +1 si B l'a
     * gagné, 0 si aucun match joué entre elles ou match nul (match interrompu à 2-2 ou 1-1).
     */
    private function confrontationDirecte(Equipe $equipeA, Equipe $equipeB, Poule $poule): int
    {
        $dernier = null;
        foreach ($poule->getParties() as $partie) {
            $entreElles = ($partie->getIdEquipeRecoit() === $equipeA && $partie->getIdEquipeDeplace() === $equipeB)
                || ($partie->getIdEquipeRecoit() === $equipeB && $partie->getIdEquipeDeplace() === $equipeA);
            $joue = $partie->getNbSetGagnantReception() !== null && $partie->getNbSetGagnantDeplacement() !== null;
            if ($entreElles && $joue && ($dernier === null || $partie->getDate() > $dernier->getDate())) {
                $dernier = $partie;
            }
        }
        if ($dernier === null) {
            return 0;
        }

        $setsA = $dernier->getIdEquipeRecoit() === $equipeA ? $dernier->getNbSetGagnantReception() : $dernier->getNbSetGagnantDeplacement();
        $setsB = $dernier->getIdEquipeRecoit() === $equipeA ? $dernier->getNbSetGagnantDeplacement() : $dernier->getNbSetGagnantReception();

        return $setsB <=> $setsA; // forfait : -1 set, donc perdant
    }
}
