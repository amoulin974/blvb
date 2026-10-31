<?php

namespace App\VolleyStats;

use App\Entity\FeuilleMatch;
use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;

/**
 * État de la page du match pour le contrôleur Stimulus (feuille_match_controller.js) : joueurs présents, sets avec leur état
 * recalculé et leur historique lisible. Les joueurs sortis de l'équipe depuis apparaissent « Joueur retiré ».
 */
final class VueFeuille
{
    public const ACTIONS = ['reception' => 'réception', 'passe' => 'passe', 'attaque' => 'attaque', 'defense' => 'défense', 'block' => 'block', 'renvoi' => 'renvoi'];

    /**
     * @param array<int, string> $noms identifiant du membre => nom court
     * @return array<string, mixed>
     */
    public static function tableau(FeuilleMatch $feuille, array $noms, string $nomNous, string $nomEux, int $seuil = 80): array
    {
        $joueurs = [];
        foreach ($feuille->getJoueurs() as $joueur) {
            $joueurs[$joueur->getId()] = $noms[$joueur->getMembre()->getId()] ?? 'Joueur retiré';
        }
        $nom = fn (?int $id) => $id === null ? 'Place libre' : ($joueurs[$id] ?? 'Joueur retiré');
        $sets = [];
        foreach ($feuille->getSets() as $set) {
            $sets[] = [
                'numero' => $set->getNumero(),
                'etat' => $set->getEtat(),
                'postes' => $set->getPostes(),
                'premierService' => $set->getPremierService(),
                'scoreDepart' => $set->getScoreDepart(),
                'pointsFin' => $set->getPointsFin(),
                'saisie' => $set->getSaisie(),
                'calcul' => EtatSet::calculer($set)->versTableau(),
                'historique' => self::historique($set, $nom, $nomNous, $nomEux),
            ];
        }

        // Stats des sets notés « le score et les touches » (D68) : équipe et joueurs, visibles de l'équipe et du responsable
        $parSet = [];
        foreach ($feuille->getSets() as $set) {
            if ($set->noteLesTouches() && $set->getEtat() !== FeuilleMatchSet::COMPOSITION) {
                $parSet[] = StatsFeuille::calculer($set) + ['numero' => $set->getNumero()];
            }
        }
        $total = StatsFeuille::cumuler($parSet);
        $joueursStats = [];
        foreach ($total['joueurs'] as $id => $st) {
            $joueursStats[] = ['nom' => $joueurs[$id] ?? 'Joueur retiré', 'efficacite' => StatsFeuille::efficaciteAttaque($st), 'receptionSansFaute' => StatsFeuille::receptionSansFaute($st)] + $st;
        }
        usort($joueursStats, fn ($a, $b) => [$b['pointsMarques'], $a['nom']] <=> [$a['pointsMarques'], $b['nom']]);

        return [
            'stats' => $parSet === [] ? null : [
                'joueurs' => $joueursStats,
                'equipe' => $total['equipe'] + ['efficacite' => StatsFeuille::efficaciteAttaque($total['equipe']), 'receptionSansFaute' => StatsFeuille::receptionSansFaute($total['equipe'])],
                'acesSubis' => $total['acesSubis'],
                'sets' => array_map(fn ($st) => ['numero' => $st['numero'], 'echanges' => $st['echanges'], 'detailles' => $st['detailles'], 'taux' => $st['echanges'] ? (int) round(100 * $st['detailles'] / $st['echanges']) : 0], $parSet),
                'seuil' => $seuil,
            ],
            'seuil' => $seuil,
            'etat' => $feuille->getEtat(),
            'joueurs' => array_map(fn ($id, $n) => ['id' => $id, 'nom' => $n], array_keys($joueurs), $joueurs),
            'sets' => $sets,
            'setsGagnes' => FeuilleMatchService::setsGagnes($feuille),
            'setsGagnants' => FeuilleMatchService::setsGagnants($feuille),
            'nous' => $nomNous,
            'eux' => $nomEux,
        ];
    }

    /**
     * Historique du set, du premier au dernier événement, avec le score après chacun ; les annulés sont gardés (barrés).
     *
     * @return list<array<string, mixed>>
     */
    private static function historique(FeuilleMatchSet $set, callable $nom, string $nomNous, string $nomEux): array
    {
        $lignes = [];
        $score = $set->getScoreDepart();
        foreach ($set->getEvenements() as $e) {
            $d = $e->getDonnees();
            if (!$e->estAnnule()) {
                if ($e->getType() === FeuilleMatchEvenement::POINT) {
                    ++$score[$d['pour'] === FeuilleMatchSet::NOUS ? 0 : 1];
                } elseif ($e->getType() === FeuilleMatchEvenement::CORRECTION) {
                    $score = [$d['nous'], $d['eux']];
                }
            }
            $texte = match ($e->getType()) {
                FeuilleMatchEvenement::POINT => 'Point pour '.($d['pour'] === FeuilleMatchSet::NOUS ? $nomNous : $nomEux),
                FeuilleMatchEvenement::REMPLACEMENT => $d['sort'] === null ? $nom($d['entre']).' entre sur la place libre' : $nom($d['entre']).' entre, '.$nom($d['sort']).' sort',
                FeuilleMatchEvenement::CORRECTION => sprintf('Score corrigé : %d–%d, service %s', $d['nous'], $d['eux'], $d['service'] === FeuilleMatchSet::NOUS ? $nomNous : $nomEux),
                FeuilleMatchEvenement::ROTATION => 'Joueurs tournés d\'un poste',
                FeuilleMatchEvenement::ACTION => $nom($d['joueurs'][0]).' : '.self::ACTIONS[$d['action']],
                FeuilleMatchEvenement::REJOUER => 'Balle à rejouer',
                FeuilleMatchEvenement::MODIFICATION => 'Touche corrigée',
                default => 'Saisie',
            };
            $lignes[] = ['uuid' => $e->getUuid(), 'ordre' => $e->getOrdre(), 'type' => $e->getType(), 'texte' => $texte, 'annule' => $e->estAnnule(), 'donnees' => $d,
                'score' => $e->estAnnule() ? null : $score, 'entre' => $e->getType() === FeuilleMatchEvenement::REMPLACEMENT ? $d['entre'] : null];
        }

        return $lignes;
    }
}
