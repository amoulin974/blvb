<?php

namespace App\Service;

use App\Competition\Sport\Bareme;
use App\Competition\Sport\RegleSport;
use App\Competition\Sport\Sports;
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
        private EntityManagerInterface $em,
        private Sports $sports,
    ) {}


    /**
     * Met à jour le classement pour une poule entière.
     */
    public function mettreAJourClassementPoule(Poule $poule): void
    {
        $classements = [];
        $saison = $poule->getPhase()->getSaison();
        $sport = $this->sports->pourSaison($saison);
        foreach ($poule->getEquipes() as $equipe) {
            [$setsGagnes, $setsPerdus] = $this->statistiquesEquipe($equipe, $sport, $poule);
            $classements[] = [
                'equipe'      => $equipe,
                'points'      => $this->calculerPointsEquipe($equipe, $saison, $poule),
                'setsGagnes'  => $setsGagnes,
                'setsPerdus'  => $setsPerdus,
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
        $sport = $this->sports->pourSaison($saison);
        $bareme = Bareme::deSaison($saison);
        $points = 0;
        foreach ($this->scoresEquipe($equipe, $pouleContext) as [$pour, $contre]) {
            $points += $sport->points($pour, $contre, $bareme);
        }

        return $points;
    }

    /**
     * Statistiques de départage de l'équipe (volley : sets gagnés et sets perdus), selon les règles du sport.
     *
     * @return array{int, int}
     */
    private function statistiquesEquipe(Equipe $equipe, RegleSport $sport, ?Poule $pouleContext = null): array
    {
        $marques = $encaisses = 0;
        foreach ($this->scoresEquipe($equipe, $pouleContext) as [$pour, $contre]) {
            [$m, $e] = $sport->statistiques($pour, $contre);
            $marques += $m;
            $encaisses += $e;
        }

        return [$marques, $encaisses];
    }

    /**
     * Scores des matchs de l'équipe de son point de vue (ce qu'elle a marqué, ce que l'adversaire a marqué),
     * à domicile puis à l'extérieur, limités à la poule si elle est donnée.
     *
     * @return iterable<array{?int, ?int}>
     */
    private function scoresEquipe(Equipe $equipe, ?Poule $pouleContext): iterable
    {
        foreach ($equipe->getPartiesReception() as $partie) {
            if (!$pouleContext || $partie->getPoule() === $pouleContext) {
                yield [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];
            }
        }
        foreach ($equipe->getPartiesDeplacement() as $partie) {
            if (!$pouleContext || $partie->getPoule() === $pouleContext) {
                yield [$partie->getNbSetGagnantDeplacement(), $partie->getNbSetGagnantReception()];
            }
        }
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
