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
            $tableaux[$cle] ??= ['phases' => [], 'sets' => $sets, 'lignes' => $this->lignes($saison, $bareme, $sets),
                'note' => $saison->getSport() === Volley::CODE ? self::note($sets) : null];
            $tableaux[$cle]['phases'][] = (string) $phase->getNom();
        }

        return $this->twig->render('reglement/blocs/bareme.html.twig', [
            'tableaux' => array_map(fn (array $t) => $t + ['titre' => self::titre($t['phases'])], array_values($tableaux)),
            'sansPoints' => array_map(fn (int $sets, array $noms) => sprintf('%s : pas de points au classement, seul compte le vainqueur du match (%d sets gagnants).', Texte::enumeration($noms), $sets),
                array_keys($sansPoints), $sansPoints),
        ]);
    }

    /** « Phases 1 et 2 » plutôt que « Phase 1 et Phase 2 » ; sinon les noms énumérés */
    private static function titre(array $phases): string
    {
        $numeros = [];
        foreach ($phases as $nom) {
            if (!preg_match('/^Phase (\d+)$/u', $nom, $m)) {
                return Texte::enumeration($phases);
            }
            $numeros[] = $m[1];
        }

        return (\count($numeros) > 1 ? 'Phases ' : 'Phase ').Texte::enumeration($numeros);
    }

    /** Note sous le tableau : qui est « gagnant » d'un forfait et d'un match interrompu (relecture de l'ergonome) */
    private static function note(int $sets): string
    {
        return 'Forfait : l\'équipe présente gagne, l\'équipe absente perd. Match interrompu (règle 1R) : le set en cours compte '
            .'pour l\'équipe qui y mène ; le gagnant est l\'équipe qui mène en sets. '
            .($sets === Phase::SETS_GAGNANTS_COURT
                ? 'À égalité (1-1), ou à 1-0, aucune équipe ne marque de point.'
                : 'À égalité (2-2 ou 1-1), il n\'y a pas de gagnant : chaque équipe reçoit les points indiqués.');
    }

    /**
     * Une ligne par résultat de match, avec les points de chaque équipe (choix de l'utilisateur, 20/10/2026) : gagnant et
     * perdant ; pour un match interrompu (règle 1R), l'équipe qui mène et l'autre
     *
     * @return list<array{resultat: string, gagnant: string, perdant: string, regle1R?: bool}>
     */
    private function lignes(Saison $saison, Bareme $bareme, int $sets): array
    {
        $court = $sets === Phase::SETS_GAGNANTS_COURT;
        $lignes = [
            ['resultat' => $court ? '2-0' : '3-0 ou 3-1', 'gagnant' => $bareme->victoireForte, 'perdant' => $bareme->defaiteFaible],
            ['resultat' => $court ? '2-1' : '3-2', 'gagnant' => $bareme->victoireFaible, 'perdant' => $bareme->defaiteForte],
            ['resultat' => 'Forfait (l\'équipe absente perd)', 'gagnant' => $bareme->victoireForte, 'perdant' => $bareme->forfait],
        ];
        if ($saison->getSport() === Volley::CODE) {
            // Match interrompu (règle 1R) : points fixés par le règlement, du point de vue de l'équipe qui mène ; scores de
            // même effet regroupés (« 1-1 ou 1-0 »)
            $points = Volley::pointsInterrompus($sets);
            $parPoints = [];
            foreach ($points as $score => $mene) {
                [$a, $b] = array_map('intval', explode('-', $score));
                if ($a >= $b) {
                    $parPoints[$mene.'|'.($points[$b.'-'.$a] ?? $mene)][] = $score;
                }
            }
            foreach ($parPoints as $cle => $scores) {
                [$mene, $autre] = array_map('intval', explode('|', $cle));
                $dernier = array_pop($scores);
                $lignes[] = ['resultat' => 'Match interrompu à '.($scores === [] ? $dernier : implode(', ', $scores).' ou '.$dernier)
                    .($dernier === '2-2' ? ', égalité dans le 5e set' : ''),
                    'gagnant' => $mene, 'perdant' => $autre, 'regle1R' => true];
            }
        }

        // Vrai signe moins (U+2212) : « tiret 1 » pour certains lecteurs d'écran (ergonome)
        $nombre = fn (int $n) => $n < 0 ? "\u{2212}".(-$n) : (string) $n;

        return array_map(fn (array $l) => ['gagnant' => $nombre($l['gagnant']), 'perdant' => $nombre($l['perdant'])] + $l, $lignes);
    }
}
