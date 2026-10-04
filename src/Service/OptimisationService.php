<?php

namespace App\Service;

use App\Entity\Phase;

class OptimisationService
{
    // Poids utilisés pour pondérer les différents facteurs du score de contrainte d'une poule.
    // Ajustables sans toucher à la logique : ils fixent juste l'importance relative de chaque critère.
    private const POIDS_DEFICIT_JOURNEES = 2.0;
    private const POIDS_RATIO_LIEU = 3.0;
    private const POIDS_DERBIES = 1.5;

    /**
     * Calcule, pour chaque poule d'une phase, un score de contrainte qui sert à décider
     * dans quel ordre planifier les poules (la plus contrainte d'abord) :
     *  - augmente avec le nombre d'équipes (plus de rondes, plus de combinaisons à placer)
     *  - augmente si la poule a moins de journées que les autres poules de la phase
     *    (calendrier plus serré pour étaler les contraintes de lieu)
     *  - augmente avec le nombre d'équipes jouant dans un lieu où le ratio équipes/capacité
     *    disponible est mauvais (plusieurs équipes de la poule se partagent peu de créneaux)
     *  - diminue avec le nombre de derbies (équipes de la poule partageant le même lieu,
     *    donc plus simples à regrouper logistiquement)
     *
     * @return array<int, array{score: float, nbEquipes: int, journeeDeficit: int, penaliteRatio: float, nbDerbies: int}>
     *         indexé par id de poule
     */
    public function calculerScoresContraintes(Phase $phase): array
    {
        $poules = $phase->getPoules()->toArray();

        $nbJourneesParPoule = [];
        foreach ($poules as $poule) {
            $nbJourneesParPoule[$poule->getId()] = count($poule->getJournees());
        }
        $maxJournees = $nbJourneesParPoule === [] ? 0 : max($nbJourneesParPoule);

        $scores = [];
        foreach ($poules as $poule) {
            $equipes = $poule->getEquipes()->toArray();
            $nbEquipes = count($equipes);

            $journeeDeficit = $maxJournees - ($nbJourneesParPoule[$poule->getId()] ?? 0);

            // Regroupement des équipes de la poule par lieu, pour détecter les derbies
            // (équipes partageant le même lieu) et le ratio équipes/capacité par lieu.
            $equipesParLieu = [];
            foreach ($equipes as $equipe) {
                $lieu = $equipe->getLieu();
                if (!$lieu) {
                    continue;
                }
                $equipesParLieu[$lieu->getId()]['lieu'] ??= $lieu;
                $equipesParLieu[$lieu->getId()]['equipes'][] = $equipe;
            }

            $nbDerbies = 0;
            $penaliteRatio = 0.0;

            foreach ($equipesParLieu as $groupe) {
                $nbEquipesCeLieu = count($groupe['equipes']);
                $nbDerbies += intdiv($nbEquipesCeLieu * ($nbEquipesCeLieu - 1), 2);

                $capacite = 0;
                foreach ($groupe['lieu']->getCreneaux() as $creneau) {
                    $capacite += $creneau->getCapacite();
                }
                $capacite = max(1, $capacite);

                $ratio = $nbEquipesCeLieu / $capacite;
                if ($ratio > 1) {
                    $penaliteRatio += $nbEquipesCeLieu * ($ratio - 1);
                }
            }

            $score = $nbEquipes
                + self::POIDS_DEFICIT_JOURNEES * $journeeDeficit
                + self::POIDS_RATIO_LIEU * $penaliteRatio
                - self::POIDS_DERBIES * $nbDerbies;

            $scores[$poule->getId()] = [
                'score' => $score,
                'nbEquipes' => $nbEquipes,
                'journeeDeficit' => $journeeDeficit,
                'penaliteRatio' => $penaliteRatio,
                'nbDerbies' => $nbDerbies,
            ];
        }

        return $scores;
    }

    /**
     * Pour chaque poule d'une phase, construit le tableau équipe x journée
     * avec, à chaque intersection, l'adversaire et le statut (Dom/Ext/Der).
     */
    public function getAlternanceParPoule(Phase $phase): array
    {
        $result = [];

        foreach ($phase->getPoules() as $poule) {
            $journees = $poule->getJournees()->toArray();
            usort($journees, fn($a, $b) => $a->getNumero() <=> $b->getNumero());

            $equipes = $poule->getEquipes()->toArray();
            usort($equipes, fn($a, $b) => strcmp($a->getNom(), $b->getNom()));

            $totalDomParJournee = [];
            $totalExtParJournee = [];
            foreach ($journees as $journee) {
                $totalDomParJournee[$journee->getId()] = 0;
                $totalExtParJournee[$journee->getId()] = 0;
            }

            $rows = [];
            foreach ($equipes as $equipe) {
                $cells = [];
                $totalDom = 0;
                $totalExt = 0;

                foreach ($journees as $journee) {
                    $cell = null;

                    foreach ($journee->getParties() as $partie) {
                        $recoit = $partie->getIdEquipeRecoit();
                        $deplace = $partie->getIdEquipeDeplace();

                        if ($recoit !== $equipe && $deplace !== $equipe) {
                            continue;
                        }

                        $estDomicile = $recoit === $equipe;
                        $adversaire = $estDomicile ? $deplace : $recoit;

                        $memeLieu = $recoit && $deplace && $recoit->getLieu() && $deplace->getLieu()
                            && $recoit->getLieu() === $deplace->getLieu();

                        $label = $memeLieu ? 'Der' : ($estDomicile ? 'Dom' : 'Ext');

                        $cell = [
                            'adversaire' => $adversaire ? $adversaire->getNom() : $partie->getNom(),
                            'label' => $label,
                        ];

                        // Les derbies sont cumulés avec les matchs à domicile
                        if ($label === 'Ext') {
                            $totalExt++;
                            $totalExtParJournee[$journee->getId()]++;
                        } else {
                            $totalDom++;
                            $totalDomParJournee[$journee->getId()]++;
                        }

                        break;
                    }

                    $cells[$journee->getId()] = $cell;
                }

                $rows[] = [
                    'equipe' => $equipe,
                    'cells' => $cells,
                    'totalDom' => $totalDom,
                    'totalExt' => $totalExt,
                ];
            }

            $result[] = [
                'poule' => $poule,
                'journees' => $journees,
                'rows' => $rows,
                'totalDomParJournee' => $totalDomParJournee,
                'totalExtParJournee' => $totalExtParJournee,
            ];
        }

        return $result;
    }
}
