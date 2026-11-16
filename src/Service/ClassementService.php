<?php

namespace App\Service;

use App\Competition\Classement\CritereDepartage;
use App\Competition\Sport\Bareme;
use App\Competition\Sport\RegleSport;
use App\Competition\Sport\Sports;
use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Phase;
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
     * Met à jour le classement pour une poule entière : barème de la phase (à défaut celui de la saison), départage
     * dans l'ordre choisi pour la saison (App\Competition\Classement\CritereDepartage).
     */
    public function mettreAJourClassementPoule(Poule $poule): void
    {
        $classements = [];
        $saison = $poule->getPhase()->getSaison();
        $sport = $this->sports->pourSaison($saison);
        $bareme = Bareme::dePhase($poule->getPhase());
        foreach ($poule->getEquipes() as $equipe) {
            [$setsGagnes, $setsPerdus] = $this->statistiquesEquipe($equipe, $sport, $poule);
            $classements[] = [
                'equipe'      => $equipe,
                'points'      => $this->pointsEquipe($equipe, $sport, $bareme, $poule),
                'setsGagnes'  => $setsGagnes,
                'setsPerdus'  => $setsPerdus,
                'mini'        => [0, 0],
            ];
        }

        $criteres = $saison->getDepartage();
        if (\in_array(CritereDepartage::MINI_CLASSEMENT, $criteres, true)) {
            $this->calculerMiniClassements($classements, $criteres, $poule, $sport, $bareme);
        }

        usort($classements, function (array $a, array $b) use ($criteres, $poule): int {
            foreach ($criteres as $critere) {
                $ordre = match ($critere) {
                    // Confrontation directe : le dernier match joué entre les deux équipes (aller ou retour)
                    CritereDepartage::CONFRONTATION_DIRECTE => $this->confrontationDirecte($a['equipe'], $b['equipe'], $poule),
                    CritereDepartage::MINI_CLASSEMENT => $b['mini'] <=> $a['mini'],
                    // Ordre alphabétique, pour un classement toujours identique d'un calcul à l'autre
                    CritereDepartage::ALPHABETIQUE => strcasecmp((string) $a['equipe']->getNomSansClub(), (string) $b['equipe']->getNomSansClub()),
                    default => self::valeur($critere, $b) <=> self::valeur($critere, $a),
                };
                if ($ordre !== 0) {
                    return $ordre;
                }
            }

            return 0;
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
        $bareme = $pouleContext ? Bareme::dePhase($pouleContext->getPhase()) : Bareme::deSaison($saison);

        return $this->pointsEquipe($equipe, $this->sports->pourSaison($saison), $bareme, $pouleContext);
    }

    private function pointsEquipe(Equipe $equipe, RegleSport $sport, Bareme $bareme, ?Poule $pouleContext): int
    {
        $points = 0;
        foreach ($this->scoresEquipe($equipe, $pouleContext) as [$pour, $contre, $setsGagnants]) {
            $points += $sport->points($pour, $contre, $bareme, $setsGagnants);
        }

        return $points;
    }

    /** Valeur d'une ligne de classement pour un critère qui se calcule par équipe (plus grand = mieux classé). */
    private static function valeur(CritereDepartage $critere, array $ligne): int|float
    {
        return match ($critere) {
            CritereDepartage::POINTS => $ligne['points'],
            CritereDepartage::DIFFERENCE_SETS => $ligne['setsGagnes'] - $ligne['setsPerdus'],
            // Sets gagnés / sets perdus ; aucun set perdu : infini s'il y a des sets gagnés, 0 sinon
            CritereDepartage::QUOTIENT_SETS => $ligne['setsPerdus'] > 0 ? $ligne['setsGagnes'] / $ligne['setsPerdus'] : ($ligne['setsGagnes'] > 0 ? INF : 0),
            default => throw new \LogicException("Le critère {$critere->value} ne se calcule pas par équipe."),
        };
    }

    /**
     * Mini-classement (D16) : pour chaque groupe d'équipes à égalité sur les critères par équipe qui précèdent le
     * mini-classement dans le départage, points puis différence de sets sur les seuls matchs joués entre elles.
     * Résultat dans $classements[…]['mini'] = [points, différence de sets].
     *
     * @param list<CritereDepartage> $criteres
     */
    private function calculerMiniClassements(array &$classements, array $criteres, Poule $poule, RegleSport $sport, Bareme $bareme): void
    {
        $precedents = [];
        foreach ($criteres as $critere) {
            if ($critere === CritereDepartage::MINI_CLASSEMENT) {
                break;
            }
            if ($critere->estUneValeur()) {
                $precedents[] = $critere;
            }
        }

        $setsGagnants = $poule->getPhase()?->getSetsGagnants() ?? Phase::SETS_GAGNANTS_DEFAUT;
        $groupes = [];
        foreach ($classements as $i => $ligne) {
            $cle = json_encode(array_map(fn (CritereDepartage $c) => (string) self::valeur($c, $ligne), $precedents));
            $groupes[$cle][] = $i;
        }

        foreach ($groupes as $indices) {
            if (\count($indices) < 2) {
                continue;
            }
            $equipes = array_map(fn (int $i) => $classements[$i]['equipe'], $indices);
            foreach ($indices as $i) {
                $points = $sets = 0;
                foreach ($poule->getParties() as $partie) {
                    $recoit = $partie->getIdEquipeRecoit();
                    $deplace = $partie->getIdEquipeDeplace();
                    if (!\in_array($recoit, $equipes, true) || !\in_array($deplace, $equipes, true)) {
                        continue;
                    }
                    if ($recoit === $classements[$i]['equipe']) {
                        [$pour, $contre] = [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];
                    } elseif ($deplace === $classements[$i]['equipe']) {
                        [$pour, $contre] = [$partie->getNbSetGagnantDeplacement(), $partie->getNbSetGagnantReception()];
                    } else {
                        continue;
                    }
                    $points += $sport->points($pour, $contre, $bareme, $setsGagnants);
                    [$gagnes, $perdus] = $sport->statistiques($pour, $contre, $setsGagnants);
                    $sets += $gagnes - $perdus;
                }
                $classements[$i]['mini'] = [$points, $sets];
            }
        }
    }

    /**
     * Statistiques de départage de l'équipe (volley : sets gagnés et sets perdus), selon les règles du sport.
     *
     * @return array{int, int}
     */
    private function statistiquesEquipe(Equipe $equipe, RegleSport $sport, ?Poule $pouleContext = null): array
    {
        $marques = $encaisses = 0;
        foreach ($this->scoresEquipe($equipe, $pouleContext) as [$pour, $contre, $setsGagnants]) {
            [$m, $e] = $sport->statistiques($pour, $contre, $setsGagnants);
            $marques += $m;
            $encaisses += $e;
        }

        return [$marques, $encaisses];
    }

    private static function setsGagnants(Partie $partie): int
    {
        return $partie->getPoule()?->getPhase()?->getSetsGagnants() ?? Phase::SETS_GAGNANTS_DEFAUT;
    }

    /**
     * Scores des matchs de l'équipe de son point de vue (ce qu'elle a marqué, ce que l'adversaire a marqué) et sets à
     * gagner dans la phase du match (D48), à domicile puis à l'extérieur, limités à la poule si elle est donnée.
     *
     * @return iterable<array{?int, ?int, int}>
     */
    private function scoresEquipe(Equipe $equipe, ?Poule $pouleContext): iterable
    {
        foreach ($equipe->getPartiesReception() as $partie) {
            if (!$pouleContext || $partie->getPoule() === $pouleContext) {
                yield [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement(), self::setsGagnants($partie)];
            }
        }
        foreach ($equipe->getPartiesDeplacement() as $partie) {
            if (!$pouleContext || $partie->getPoule() === $pouleContext) {
                yield [$partie->getNbSetGagnantDeplacement(), $partie->getNbSetGagnantReception(), self::setsGagnants($partie)];
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
