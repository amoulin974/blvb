<?php

namespace App\Reglement\Bloc;

use App\Competition\Sport\Bareme;
use App\Competition\Sport\Volley;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Reglement\BlocInapplicable;
use App\Reglement\Texte;
use Twig\Environment;

/**
 * [bareme] : points au classement par résultat et format de match, un tableau par format distinct (phases regroupées),
 * match interrompu (règle 1R) ; phase sans classement (barrages, élimination) : une phrase (D58).
 */
final class BlocBareme extends BlocParPhase
{
    public function __construct(private Environment $twig)
    {
    }

    public function code(): string
    {
        return 'bareme';
    }

    public function libelle(): string
    {
        return 'Barème des points';
    }

    public function description(): string
    {
        return 'Les points gagnés au classement selon le résultat d\'un match, et le nombre de sets gagnants de chaque phase.';
    }

    public function rendre(Saison $saison, array $parametres): string
    {
        $phases = self::phases($saison, $parametres);
        if ($phases === []) {
            throw new BlocInapplicable('la saison n\'a pas encore de phase.');
        }

        $tableaux = [];
        $sansPoints = [];
        foreach ($phases as $phase) {
            $sets = $phase->getSetsGagnants();
            if ($phase->getType() !== PhaseType::CHAMPIONNAT) {
                $sansPoints[$sets][] = (string) $phase->getNom();
                continue;
            }
            $bareme = Bareme::dePhase($phase);
            $cle = $sets.'|'.json_encode($bareme);
            $tableaux[$cle] ??= ['phases' => [], 'sets' => $sets, 'lignes' => $this->lignes($saison, $bareme, $sets)];
            $tableaux[$cle]['phases'][] = (string) $phase->getNom();
        }

        return $this->twig->render('reglement/blocs/bareme.html.twig', [
            'tableaux' => array_map(fn (array $t) => $t + ['titre' => Texte::enumeration($t['phases'])], array_values($tableaux)),
            'sansPoints' => array_map(fn (int $sets, array $noms) => sprintf('%s : pas de points au classement, seul compte le vainqueur du match (%d sets gagnants).', Texte::enumeration($noms), $sets),
                array_keys($sansPoints), $sansPoints),
        ]);
    }

    /** @return list<array{resultat: string, points: string, regle1R?: bool}> */
    private function lignes(Saison $saison, Bareme $bareme, int $sets): array
    {
        $court = $sets === Phase::SETS_GAGNANTS_COURT;
        $lignes = [
            ['resultat' => $court ? 'Victoire 2-0' : 'Victoire 3-0 ou 3-1', 'points' => (string) $bareme->victoireForte],
            ['resultat' => $court ? 'Victoire 2-1' : 'Victoire 3-2', 'points' => (string) $bareme->victoireFaible],
            ['resultat' => $court ? 'Défaite 1-2' : 'Défaite 2-3', 'points' => (string) $bareme->defaiteForte],
            ['resultat' => $court ? 'Défaite 0-2' : 'Défaite 1-3 ou 0-3', 'points' => (string) $bareme->defaiteFaible],
            ['resultat' => 'Victoire par forfait', 'points' => (string) $bareme->victoireForte],
            ['resultat' => 'Forfait (équipe absente)', 'points' => (string) $bareme->forfait],
        ];
        if ($saison->getSport() !== Volley::CODE) {
            return $lignes;
        }

        // Match interrompu (règle 1R) : points fixés par le règlement, score vu du côté de l'équipe, scores de même effet
        // regroupés (« 1-1, 1-0 ou 0-1 »)
        $parPoints = [];
        foreach (Volley::pointsInterrompus($sets) as $score => $pour) {
            $parPoints[$pour][] = $score;
        }
        krsort($parPoints);
        foreach ($parPoints as $pour => $scores) {
            $dernier = array_pop($scores);
            $lignes[] = ['resultat' => 'Match interrompu à '.($scores === [] ? $dernier : implode(', ', $scores).' ou '.$dernier), 'points' => (string) $pour, 'regle1R' => true];
        }

        return $lignes;
    }
}
