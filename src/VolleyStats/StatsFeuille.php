<?php

namespace App\VolleyStats;

use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;

/**
 * Statistiques d'un set noté « le score et les touches » (D68, specification-actions.md §4), calculées depuis le journal,
 * jamais stockées : événements annulés ignorés, corrections de touches appliquées, touches d'une balle à rejouer non
 * comptées. Le service n'est jamais saisi : il revient au joueur au poste 1 quand nous servons.
 */
final class StatsFeuille
{
    /** Compteurs d'un joueur (ou de l'équipe) */
    public const VIDE = [
        'services' => 0, 'aces' => 0, 'fautesService' => 0,
        'receptions' => 0, 'fautesReception' => 0,
        'passes' => 0, 'fautesPasse' => 0, 'passesDecisives' => 0,
        'attaques' => 0, 'attaquesGagnantes' => 0, 'fautesAttaque' => 0, 'attaquesContrees' => 0,
        'blocks' => 0, 'blocksGagnants' => 0, 'fautesBlock' => 0,
        'defenses' => 0, 'fautesDefense' => 0,
        'renvois' => 0, 'fautesRenvoi' => 0,
        'pointsMarques' => 0, 'fautesReglement' => 0,
    ];

    private const COMPTEURS = [
        'reception' => ['receptions', 'fautesReception'], 'passe' => ['passes', 'fautesPasse'], 'attaque' => ['attaques', 'fautesAttaque'],
        'block' => ['blocks', 'fautesBlock'], 'defense' => ['defenses', 'fautesDefense'], 'renvoi' => ['renvois', 'fautesRenvoi'],
    ];

    /**
     * @return array{joueurs: array<int, array<string, int>>, equipe: array<string, int>, echanges: int, detailles: int, acesSubis: int}
     */
    public static function calculer(FeuilleMatchSet $set): array
    {
        $resultat = ['joueurs' => [], 'equipe' => self::VIDE, 'echanges' => 0, 'detailles' => 0, 'acesSubis' => 0];
        if (!$set->noteLesTouches()) {
            return $resultat;
        }
        $evenements = array_values(array_filter($set->getEvenements()->toArray(), fn (FeuilleMatchEvenement $e) => !$e->estAnnule()));
        $corrections = self::corrections($evenements);

        $postes = $set->getPostes();
        $service = $set->getPremierService();
        $echange = [];
        $ajouter = function (?int $joueur, string $cle, int $n = 1) use (&$resultat): void {
            if ($joueur === null) {
                return;
            }
            $resultat['joueurs'][$joueur] ??= self::VIDE;
            $resultat['joueurs'][$joueur][$cle] += $n;
            $resultat['equipe'][$cle] += $n;
        };

        foreach ($evenements as $e) {
            $d = $e->getDonnees();
            switch ($e->getType()) {
                case FeuilleMatchEvenement::ACTION:
                    // Touche corrigée (null : retirée), sinon telle que saisie
                    $action = \array_key_exists($e->getUuid(), $corrections) ? $corrections[$e->getUuid()] : ['action' => $d['action'], 'joueur' => $d['joueurs'][0]];
                    if ($action !== null) {
                        $echange[] = $action + ['uuid' => $e->getUuid()];
                    }
                    break;
                case FeuilleMatchEvenement::REJOUER:
                case FeuilleMatchEvenement::CORRECTION:
                    $echange = []; // touches non comptées
                    if ($e->getType() === FeuilleMatchEvenement::CORRECTION) {
                        $service = $d['service'];
                    }
                    break;
                case FeuilleMatchEvenement::REMPLACEMENT:
                    $place = array_search($d['sort'], $postes, true);
                    if ($place !== false) {
                        $postes[$place] = $d['entre'];
                    }
                    break;
                case FeuilleMatchEvenement::ROTATION:
                    $postes = EtatSet::tourner($postes);
                    break;
                case FeuilleMatchEvenement::POINT:
                    self::echange($echange, $d, $service, $postes[0], $ajouter, $resultat);
                    $echange = [];
                    if ($d['pour'] === FeuilleMatchSet::NOUS) {
                        if ($service === FeuilleMatchSet::EUX) {
                            $postes = EtatSet::tourner($postes);
                        }
                        $service = FeuilleMatchSet::NOUS;
                    } else {
                        $service = FeuilleMatchSet::EUX;
                    }
                    break;
            }
        }

        return $resultat;
    }

    /**
     * Un échange terminé par un point : service, touches, issue.
     *
     * @param list<array{action: string, joueur: int, uuid: string}> $echange
     * @param array<string, mixed> $point
     * @param array<string, mixed> $resultat
     */
    private static function echange(array $echange, array $point, ?string $service, ?int $serveur, callable $ajouter, array &$resultat): void
    {
        ++$resultat['echanges'];
        if (($point['sansDetail'] ?? false) === true) {
            return; // échange non détaillé : rien n'est compté, ni le service
        }
        ++$resultat['detailles'];
        $nous = $point['pour'] === FeuilleMatchSet::NOUS;
        $sur = $point['sur'] ?? null;

        if ($service === FeuilleMatchSet::NOUS) {
            $ajouter($serveur, 'services');
            if ($sur === 'service') {
                $ajouter($serveur, $nous ? 'aces' : 'fautesService');
                if ($nous) {
                    $ajouter($serveur, 'pointsMarques');
                }
            }
        } elseif (!$nous && $sur === null && $echange === [] && !isset($point['detail'])) {
            ++$resultat['acesSubis']; // ace subi sans contact : aucun joueur crédité
        }

        foreach ($echange as $i => $touche) {
            [$tentees, $fautes] = self::COMPTEURS[$touche['action']];
            $ajouter($touche['joueur'], $tentees);
            if ($touche['uuid'] !== $sur) {
                continue;
            }
            if ($nous) {
                $ajouter($touche['joueur'], 'pointsMarques');
                if ($touche['action'] === 'attaque') {
                    $ajouter($touche['joueur'], 'attaquesGagnantes');
                    // Passe décisive : la passe juste avant l'attaque gagnante, dans la même possession
                    $precedente = $echange[$i - 1] ?? null;
                    if ($precedente !== null && $precedente['action'] === 'passe') {
                        $ajouter($precedente['joueur'], 'passesDecisives');
                    }
                } elseif ($touche['action'] === 'block') {
                    $ajouter($touche['joueur'], 'blocksGagnants');
                    if (isset($point['avec'])) {
                        // Deuxième bloqueur (D68) : crédité du block gagnant comme le premier
                        $ajouter($point['avec'], 'blocks');
                        $ajouter($point['avec'], 'blocksGagnants');
                        $ajouter($point['avec'], 'pointsMarques');
                    }
                }
            } elseif (($point['detail'] ?? null) === 'contree') {
                $ajouter($touche['joueur'], 'attaquesContrees');
            } else {
                $ajouter($touche['joueur'], $fautes);
            }
        }
        if (isset($point['fautif'])) {
            $ajouter($point['fautif'], 'fautesReglement');
        }
    }

    /**
     * Corrections actives (D68) : la dernière vaut. null = touche retirée.
     *
     * @param list<FeuilleMatchEvenement> $evenements
     * @return array<string, ?array{action: string, joueur: int}>
     */
    private static function corrections(array $evenements): array
    {
        $originales = [];
        foreach ($evenements as $e) {
            if ($e->getType() === FeuilleMatchEvenement::ACTION) {
                $originales[$e->getUuid()] = ['action' => $e->getDonnees()['action'], 'joueur' => $e->getDonnees()['joueurs'][0]];
            }
        }
        $corrigees = [];
        foreach ($evenements as $e) {
            if ($e->getType() !== FeuilleMatchEvenement::MODIFICATION) {
                continue;
            }
            $d = $e->getDonnees();
            $courante = \array_key_exists($d['cible'], $corrigees) ? $corrigees[$d['cible']] : ($originales[$d['cible']] ?? null);
            if ($courante === null) {
                continue;
            }
            $corrigees[$d['cible']] = match (true) {
                isset($d['retirer']) => null,
                isset($d['action']) => ['action' => $d['action'], 'joueur' => $courante['joueur']],
                default => ['action' => $courante['action'], 'joueur' => $d['joueurs'][0]],
            };
        }

        return $corrigees;
    }

    /**
     * Cumul de plusieurs sets (match, saison) et pourcentages utiles à l'affichage.
     *
     * @param list<array{joueurs: array<int, array<string, int>>, equipe: array<string, int>, echanges: int, detailles: int, acesSubis: int}> $sets
     * @return array{joueurs: array<int, array<string, int>>, equipe: array<string, int>, echanges: int, detailles: int, acesSubis: int}
     */
    public static function cumuler(array $sets): array
    {
        $total = ['joueurs' => [], 'equipe' => self::VIDE, 'echanges' => 0, 'detailles' => 0, 'acesSubis' => 0];
        foreach ($sets as $s) {
            foreach ($s['joueurs'] as $joueur => $stats) {
                $total['joueurs'][$joueur] ??= self::VIDE;
                foreach ($stats as $cle => $n) {
                    $total['joueurs'][$joueur][$cle] += $n;
                }
            }
            foreach ($s['equipe'] as $cle => $n) {
                $total['equipe'][$cle] += $n;
            }
            foreach (['echanges', 'detailles', 'acesSubis'] as $cle) {
                $total[$cle] += $s[$cle];
            }
        }

        return $total;
    }

    /** Efficacité d'attaque (gagnantes − fautes − contrées) ÷ tentées, en %, null sans attaque @param array<string, int> $s */
    public static function efficaciteAttaque(array $s): ?int
    {
        return $s['attaques'] > 0 ? (int) round(100 * ($s['attaquesGagnantes'] - $s['fautesAttaque'] - $s['attaquesContrees']) / $s['attaques']) : null;
    }

    /** % de réceptions sans faute, null sans réception @param array<string, int> $s */
    public static function receptionSansFaute(array $s): ?int
    {
        return $s['receptions'] > 0 ? (int) round(100 * ($s['receptions'] - $s['fautesReception']) / $s['receptions']) : null;
    }
}
