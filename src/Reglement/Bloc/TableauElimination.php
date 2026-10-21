<?php

namespace App\Reglement\Bloc;

use App\Competition\Format\Elimination;

/**
 * Déroulé d'un tableau à élimination pour le règlement (D58) : tours et matchs numérotés, places en clair, exemptions,
 * petite finale. Même construction que App\Competition\Format\Elimination::genererMatchs (tableau complet de 2^k
 * places, placement standard des têtes de série, une place vide donne une exemption), sans rien enregistrer.
 */
final class TableauElimination
{
    /**
     * @param list<string> $places libellé de chaque tête de série, dans l'ordre (« 1er », « 1er de la poule B »…)
     * @return array{tours: list<array{nom: string, matchs: list<array{nom: string, equipes: array{0: string, 1: string}}>}>, exemptes: int}
     */
    public static function construire(array $places, bool $petiteFinale): array
    {
        $n = \count($places);
        if ($n < 2) {
            return ['tours' => [], 'exemptes' => 0];
        }
        $taille = (int) 2 ** (int) ceil(log($n, 2));
        $nbTours = (int) log($taille, 2);
        $barrage = $taille !== $n;
        $tetes = Elimination::placementStandard($taille);

        $tours = [];
        // Nœud : libellé d'une place (équipe connue ou exemptée) ou référence au vainqueur d'un match
        $noeuds = [];
        $matchs = [];
        for ($i = 0; $i < $taille / 2; ++$i) {
            [$a, $b] = [$tetes[2 * $i], $tetes[2 * $i + 1]];
            if ($b > $n) {
                $noeuds[] = $places[$a - 1];
                continue;
            }
            $matchs[] = [$places[$a - 1], $places[$b - 1]];
            $noeuds[] = \count($matchs) - 1;
        }
        $tours[] = self::tour(1, $nbTours, $barrage, $matchs);

        $demis = $nbTours === 2 ? $tours[0] : null;
        for ($t = 2; $t <= $nbTours; ++$t) {
            $precedent = $tours[$t - 2];
            $suivants = [];
            $matchs = [];
            for ($j = 0; $j < \count($noeuds); $j += 2) {
                $equipes = [];
                foreach ([$noeuds[$j], $noeuds[$j + 1]] as $noeud) {
                    $equipes[] = \is_int($noeud) ? 'vainqueur '.$precedent['matchs'][$noeud]['de'] : $noeud;
                }
                $matchs[] = $equipes;
                $suivants[] = \count($matchs) - 1;
            }
            $tours[] = self::tour($t, $nbTours, $barrage, $matchs);
            if ($t === $nbTours - 1) {
                $demis = $tours[$t - 1];
            }
            $noeuds = $suivants;
        }

        if ($petiteFinale && $n >= 4 && $demis !== null && \count($demis['matchs']) === 2) {
            $tours[$nbTours - 1]['matchs'][] = [
                'nom' => 'Petite finale (3e place)',
                'de' => 'de la petite finale',
                'equipes' => ['perdant '.$demis['matchs'][0]['de'], 'perdant '.$demis['matchs'][1]['de']],
            ];
        }

        return [
            'tours' => array_map(fn (array $tour) => [
                'nom' => $tour['nom'],
                'matchs' => array_map(fn (array $m) => ['nom' => $m['nom'], 'equipes' => $m['equipes']], $tour['matchs']),
            ], $tours),
            'exemptes' => $barrage ? $taille - $n : 0,
        ];
    }

    /**
     * Noms d'un tour (comme Elimination::getNomJournee) et de ses matchs : « Quarts de finale », « Quart de finale 2 »,
     * « du quart de finale 2 » pour y renvoyer depuis le tour suivant ; un match seul n'est pas numéroté.
     *
     * @param list<array{0: string, 1: string}> $matchs
     * @return array{nom: string, matchs: list<array{nom: string, de: string, equipes: array{0: string, 1: string}}>}
     */
    private static function tour(int $index, int $total, bool $barrage, array $matchs): array
    {
        [$titre, $nom, $de] = match (true) {
            $barrage && $index === 1 => ['Barrages', 'Barrage', 'du barrage'],
            $total - $index === 0 => ['Finale', 'Finale', 'de la finale'],
            $total - $index === 1 => ['Demi-finales', 'Demi-finale', 'de la demi-finale'],
            $total - $index === 2 => ['Quarts de finale', 'Quart de finale', 'du quart de finale'],
            $total - $index === 3 => ['Huitièmes de finale', 'Huitième de finale', 'du huitième de finale'],
            $total - $index === 4 => ['Seizièmes de finale', 'Seizième de finale', 'du seizième de finale'],
            default => ['Tour '.$index, 'Tour '.$index.', match', 'du tour '.$index.', match'],
        };
        $seul = \count($matchs) === 1;
        if ($seul && $titre === 'Barrages') {
            $titre = 'Barrage';
        }
        if ($seul && $titre === 'Tour '.$index) {
            [$nom, $de] = [$titre, 'du tour '.$index];
        }

        $liste = [];
        foreach ($matchs as $i => $equipes) {
            $numero = $seul ? '' : ' '.($i + 1);
            $liste[] = ['nom' => $nom.$numero, 'de' => $de.$numero, 'equipes' => $equipes];
        }

        return ['nom' => $titre, 'matchs' => $liste];
    }
}
