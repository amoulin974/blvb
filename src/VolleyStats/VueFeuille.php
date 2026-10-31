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
    /**
     * @param array<int, string> $noms identifiant du membre => nom court
     * @return array<string, mixed>
     */
    public static function tableau(FeuilleMatch $feuille, array $noms, string $nomNous, string $nomEux): array
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
                'calcul' => EtatSet::calculer($set)->versTableau(),
                'historique' => self::historique($set, $nom, $nomNous, $nomEux),
            ];
        }

        return [
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
                FeuilleMatchEvenement::REMPLACEMENT => $nom($d['entre']).' entre, '.$nom($d['sort']).($d['sort'] === null ? ' remplie' : ' sort'),
                FeuilleMatchEvenement::CORRECTION => sprintf('Score corrigé : %d–%d, service %s', $d['nous'], $d['eux'], $d['service'] === FeuilleMatchSet::NOUS ? $nomNous : $nomEux),
                FeuilleMatchEvenement::ROTATION => 'Joueurs tournés d\'un poste',
                default => 'Saisie',
            };
            $lignes[] = ['uuid' => $e->getUuid(), 'ordre' => $e->getOrdre(), 'type' => $e->getType(), 'texte' => $texte, 'annule' => $e->estAnnule(),
                'score' => $e->estAnnule() ? null : $score, 'entre' => $e->getType() === FeuilleMatchEvenement::REMPLACEMENT ? $d['entre'] : null];
        }

        return $lignes;
    }
}
