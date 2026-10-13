<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;

/**
 * Étape 4 du wizard (Passages) : prépare l'affichage de chaque transition « phase → phase suivante » et applique la
 * saisie (docs/wizard-saison/ecrans.md, D29). Une poule est saisie en mode simple (montées, descentes, autres :
 * PassagesSimples) ou en mode détaillé (une ligne par règle : du rang… au rang… → destination).
 *
 * Champs envoyés, par poule source (identifiant) : p[id][mode] = simple|detail ; mode simple : montee, versMontee,
 * descente, versDescente, versAutres (identifiants de poules) ; vers une phase finale : versAutres (tableau),
 * saisonSuivante, niveauMontee, niveauDescente, niveauAutres ; mode détaillé : p[id][r][k][du|au|dest], dest = « p:id »
 * (poule) ou « n:niveau » (saison suivante).
 */
final class EtapePassages
{
    /** @return list<Phase> */
    public static function phasesDansLOrdre(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return array_values($phases);
    }

    /** @return list<Poule> poules d'une phase par niveau */
    public static function poules(Phase $phase): array
    {
        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [$a->getNiveau() ?? PHP_INT_MAX, $a->getCode()] <=> [$b->getNiveau() ?? PHP_INT_MAX, $b->getCode()]);

        return array_values($poules);
    }

    /** Nombre d'équipes d'une poule pour les contrôles : prévu, sinon effectif actuel ; null si inconnu. */
    public static function taille(Poule $poule): ?int
    {
        return $poule->getNbEquipesPrevu() ?? ($poule->getEquipes()->count() ?: null);
    }

    /** Règles de la phase qui partent de cette poule, dans l'ordre. @return list<RegleDePassage> */
    public static function reglesDe(Phase $phase, Poule $poule): array
    {
        $prefixe = $phase->getCode().'.'.$poule->getCode().'#';

        return array_values(array_filter($phase->getReglesDePassage()->toArray(), fn (RegleDePassage $r) => str_starts_with($r->getSource(), $prefixe)));
    }

    /**
     * Transitions à afficher : pour chaque phase qui a une suivante, ses poules (valeurs simples ou lignes détaillées),
     * les destinations possibles, le contrôle des effectifs reçus et les problèmes relevés par la vérification.
     *
     * @param list<int> $detail poules forcées en mode détaillé
     */
    public static function transitions(Saison $saison, array $detail): array
    {
        $phases = self::phasesDansLOrdre($saison);
        $niveaux = [];
        foreach ($phases as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $niveaux[] = (int) $poule->getNiveau();
            }
        }
        $niveauMax = max([1, ...$niveaux]);

        $transitions = [];
        foreach ($phases as $i => $phase) {
            $suivante = $phases[$i + 1] ?? null;
            if ($suivante === null) {
                continue;
            }
            $versFinale = $suivante->getType() === PhaseType::FINALE;

            // Destinations : poules des phases suivantes, puis niveaux de la saison suivante
            $destinations = [];
            foreach (\array_slice($phases, $i + 1) as $phaseSuivante) {
                $destinations[$phaseSuivante->getNom()] = array_map(fn (Poule $p) => ['valeur' => 'p:'.$p->getId(), 'libelle' => $p->getNom().' ('.$phaseSuivante->getNom().')'], self::poules($phaseSuivante));
            }
            $destinations['Saison suivante'] = array_map(fn (int $n) => ['valeur' => 'n:'.$n, 'libelle' => "Niveau $n la saison prochaine"], range(1, $niveauMax));

            $poules = [];
            foreach (self::poules($phase) as $poule) {
                $prefixe = $phase->getCode().'.'.$poule->getCode().'#';
                $regles = self::reglesDe($phase, $poule);
                $simple = PassagesSimples::lire($prefixe, $regles, $versFinale);
                $taille = self::taille($poule);
                $poules[] = [
                    'poule' => $poule,
                    'taille' => $taille,
                    'simple' => $simple,
                    'mode' => $simple === null || \in_array($poule->getId(), $detail, true) ? 'detail' : 'simple',
                    'lignes' => array_map(fn (RegleDePassage $r) => self::ligne($r), $regles),
                    'rangs' => self::rangs($taille, $regles),
                ];
            }

            $tailles = [];
            foreach ($phase->getPoules() as $poule) {
                $tailles[$poule->getCode()] = self::taille($poule);
            }

            $transitions[] = [
                'phase' => $phase,
                'suivante' => $suivante,
                'versFinale' => $versFinale,
                'poules' => $poules,
                'destinations' => $destinations,
                'effectifs' => self::effectifs($phase, $suivante),
                'problemes' => VerificationPassages::verifier($phase, $phase->getReglesDePassage(), $tailles),
            ];
        }

        return $transitions;
    }

    /**
     * Applique la saisie de l'étape à toutes les transitions, puis l'éventuelle action (ajouter, supprimer ou déplacer
     * une ligne, rétablir les règles par défaut d'une transition). Remplace les règles de chaque phase source.
     *
     * @param list<int> $detail poules en mode détaillé (modifié par les actions « detail- » et « simple- »)
     * @return list<string> problèmes de saisie (valeurs ignorées)
     */
    public static function appliquer(Saison $saison, array $saisie, string $action, array &$detail): array
    {
        $phases = self::phasesDansLOrdre($saison);
        $poulesParId = [];
        foreach ($phases as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $poulesParId[$poule->getId()] = $poule;
            }
        }
        $problemes = [];

        foreach ($phases as $i => $phase) {
            $suivante = $phases[$i + 1] ?? null;
            if ($suivante === null) {
                continue;
            }
            $versFinale = $suivante->getType() === PhaseType::FINALE;
            // Destinations permises : poules des phases suivantes
            $permises = [];
            foreach (\array_slice($phases, $i + 1) as $p) {
                foreach ($p->getPoules() as $poule) {
                    $permises[$poule->getId()] = $poule;
                }
            }

            if ($action === 'retablir-'.$phase->getId()) {
                self::remplacer($phase, ReglesParDefaut::pourPhase($phase, $suivante));
                foreach ($phase->getPoules() as $poule) {
                    $detail = array_values(array_diff($detail, [$poule->getId()]));
                }
                continue;
            }

            $nouvelles = [];
            foreach (self::poules($phase) as $poule) {
                $id = $poule->getId();
                $prefixe = $phase->getCode().'.'.$poule->getCode().'#';
                $champs = $saisie[$id] ?? null;

                if ($action === 'simple-'.$id) {
                    $detail = array_values(array_diff($detail, [$id]));
                    $regles = self::reglesDe($phase, $poule);
                    if (PassagesSimples::lire($prefixe, $regles, $versFinale) === null) {
                        // Règles détaillées : on repart des règles par défaut de cette poule
                        $regles = array_values(array_filter(ReglesParDefaut::pourPhase($phase, $suivante), fn (RegleDePassage $r) => str_starts_with($r->getSource(), $prefixe)));
                    }
                    array_push($nouvelles, ...$regles);
                    continue;
                }
                if ($action === 'detail-'.$id) {
                    $detail[] = $id;
                }

                if ($champs === null) {
                    array_push($nouvelles, ...self::reglesDe($phase, $poule)); // poule non envoyée : règles inchangées
                } elseif (($champs['mode'] ?? 'simple') === 'simple') {
                    $valeurs = [
                        'montee' => (int) ($champs['montee'] ?? 0),
                        'descente' => (int) ($champs['descente'] ?? 0),
                        'versMontee' => $permises[(int) ($champs['versMontee'] ?? 0)] ?? null,
                        'versDescente' => $permises[(int) ($champs['versDescente'] ?? 0)] ?? null,
                        'versAutres' => $permises[(int) ($champs['versAutres'] ?? 0)] ?? null,
                        'saisonSuivante' => !empty($champs['saisonSuivante']),
                        'niveauMontee' => max(1, (int) ($champs['niveauMontee'] ?? 1)),
                        'niveauDescente' => max(1, (int) ($champs['niveauDescente'] ?? 1)),
                        'niveauAutres' => max(1, (int) ($champs['niveauAutres'] ?? 1)),
                    ];
                    array_push($nouvelles, ...PassagesSimples::regles($prefixe, $valeurs, $versFinale));
                } else {
                    $lignes = array_values($champs['r'] ?? []);
                    $premiere = self::poules($suivante)[0] ?? null;
                    $lignes = self::agirSurLignes($action, $id, $lignes, $premiere ? 'p:'.$premiere->getId() : '');
                    foreach ($lignes as $ligne) {
                        $regle = self::regleDepuisLigne($prefixe, $ligne, $permises);
                        if ($regle === null) {
                            $problemes[] = sprintf('%s : une règle incomplète a été ignorée.', $poule->getNom());
                            continue;
                        }
                        $nouvelles[] = $regle;
                    }
                }
            }
            self::remplacer($phase, $nouvelles);
        }

        $detail = array_values(array_unique($detail));

        return $problemes;
    }

    /** Remplace les règles de la phase, numérotées dans l'ordre. @param list<RegleDePassage> $regles */
    private static function remplacer(Phase $phase, array $regles): void
    {
        foreach ($phase->getReglesDePassage()->toArray() as $ancienne) {
            $phase->removeRegleDePassage($ancienne); // supprimée au flush (orphanRemoval)
        }
        foreach (array_values($regles) as $n => $regle) {
            // Une règle conservée est recopiée : l'ancien objet est supprimé avec les autres
            $nouvelle = (new RegleDePassage())
                ->setSource($regle->getSource())
                ->setPouleDestination($regle->getPouleDestination())
                ->setNiveauSaisonSuivante($regle->getNiveauSaisonSuivante())
                ->setPlacement($regle->getPlacement())
                ->setPointsConserves($regle->getPointsConserves())
                ->setOrdre($n + 1);
            $phase->addRegleDePassage($nouvelle);
        }
    }

    /**
     * Ajoute, supprime ou déplace une ligne du mode détaillé de cette poule. Une ligne ajoutée vise toute la poule et,
     * par défaut, la première poule de la phase suivante (à modifier ensuite).
     */
    private static function agirSurLignes(string $action, int $id, array $lignes, string $destinationParDefaut): array
    {
        if ($action === 'ajouter-regle-'.$id) {
            $lignes[] = ['du' => '1', 'au' => '-1', 'dest' => $destinationParDefaut];
        } elseif (preg_match('/^(supprimer|monter|descendre)-regle-'.$id.'-(\d+)$/', $action, $m)) {
            $k = (int) $m[2];
            if ($m[1] === 'supprimer') {
                array_splice($lignes, $k, 1);
            } else {
                $j = $m[1] === 'monter' ? $k - 1 : $k + 1;
                if (isset($lignes[$k], $lignes[$j])) {
                    [$lignes[$k], $lignes[$j]] = [$lignes[$j], $lignes[$k]];
                }
            }
        }

        return array_values($lignes);
    }

    /** Ligne détaillée → règle ; null si incomplète (rangs nuls, destination absente ou non permise). */
    private static function regleDepuisLigne(string $prefixe, array $ligne, array $permises): ?RegleDePassage
    {
        $du = (int) ($ligne['du'] ?? 0);
        $au = (int) ($ligne['au'] ?? 0);
        $dest = (string) ($ligne['dest'] ?? '');
        if ($du === 0 || $au === 0) {
            return null;
        }
        $regle = (new RegleDePassage())->setSource($prefixe.$du.($au !== $du ? '..'.$au : ''));
        if (preg_match('/^p:(\d+)$/', $dest, $m) && isset($permises[(int) $m[1]])) {
            return $regle->setPouleDestination($permises[(int) $m[1]]);
        }
        if (preg_match('/^n:([1-9]\d*)$/', $dest, $m)) {
            return $regle->setNiveauSaisonSuivante((int) $m[1]);
        }

        return null;
    }

    /** Règle → ligne du mode détaillé. */
    private static function ligne(RegleDePassage $regle): array
    {
        try {
            $place = NotationPlace::analyser($regle->getSource());
        } catch (NotationInvalide) {
            return ['du' => '', 'au' => '', 'dest' => ''];
        }

        return [
            'du' => (string) $place->rangDebut,
            'au' => (string) $place->rangFin,
            'dest' => $regle->getPouleDestination() ? 'p:'.$regle->getPouleDestination()->getId() : 'n:'.$regle->getNiveauSaisonSuivante(),
        ];
    }

    /**
     * Rangs proposés dans les listes du mode détaillé : 1er … n-ième, puis « 3e en partant de la fin », « avant-dernier »,
     * « dernier » ; plus ceux déjà utilisés par les règles.
     *
     * @return list<array{valeur: string, libelle: string}>
     */
    private static function rangs(?int $taille, array $regles): array
    {
        $valeurs = range(1, max(2, $taille ?? 10));
        foreach ($regles as $regle) {
            try {
                $place = NotationPlace::analyser($regle->getSource());
                if ($place->estRang()) {
                    $valeurs[] = $place->rangDebut;
                    $valeurs[] = $place->rangFin;
                }
            } catch (NotationInvalide) {
            }
        }
        $valeurs = array_values(array_unique(array_merge($valeurs, [-3, -2, -1])));
        // Rangs depuis le début (1er, 2e…), puis depuis la fin (… avant-dernier, dernier)
        usort($valeurs, fn (int $a, int $b) => [$a < 0, $a] <=> [$b < 0, $b]);

        return array_map(fn (int $v) => ['valeur' => (string) $v, 'libelle' => Place::rangEnClair($v)], $valeurs);
    }

    /**
     * Contrôle des effectifs de la phase suivante : équipes reçues par poule (d'après les tailles des poules sources)
     * et nombre prévu ; reçues = null si une taille source est inconnue.
     *
     * @return list<array{poule: Poule, recues: ?int, prevues: ?int}>
     */
    public static function effectifs(Phase $phase, Phase $suivante): array
    {
        $recues = [];
        $inconnu = [];
        foreach ($phase->getReglesDePassage() as $regle) {
            $destination = $regle->getPouleDestination();
            if ($destination === null || $destination->getPhase() !== $suivante) {
                continue;
            }
            try {
                $place = NotationPlace::analyser($regle->getSource());
            } catch (NotationInvalide) {
                continue;
            }
            $source = null;
            foreach ($phase->getPoules() as $poule) {
                if ($poule->getCode() === $place->poule) {
                    $source = $poule;
                }
            }
            $taille = $source ? self::taille($source) : null;
            $cle = spl_object_id($destination);
            if ($taille === null) {
                $inconnu[$cle] = true;
                continue;
            }
            try {
                $recues[$cle] = ($recues[$cle] ?? 0) + \count($place->rangsPour($taille));
            } catch (NotationInvalide) {
            }
        }

        return array_map(fn (Poule $p) => [
            'poule' => $p,
            'recues' => isset($inconnu[spl_object_id($p)]) ? null : ($recues[spl_object_id($p)] ?? 0),
            'prevues' => $p->getNbEquipesPrevu(),
        ], self::poules($suivante));
    }
}
