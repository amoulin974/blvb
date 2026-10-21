<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;

/**
 * Étape 4 du wizard (Passages) : prépare l'affichage de chaque transition « phase → phase suivante » et applique la
 * saisie (docs/wizard-saison/archive/ecrans.md, D29). Une poule est saisie en mode simple (montées, descentes, autres :
 * PassagesSimples) ou en mode détaillé (une ligne par règle : du rang… au rang… → destination).
 *
 * Champs envoyés, par poule source (identifiant) : p[id][mode] = simple|detail ; mode simple : montee, versMontee,
 * descente, versDescente, versAutres (identifiants de poules) ; vers une phase finale : versAutres (tableau),
 * saisonSuivante, niveauMontee, niveauDescente, niveauAutres ; mode détaillé : p[id][r][k][du|au|dest], dest = « p:id »
 * (poule) ou « n:niveau » (saison suivante).
 */
final class EtapePassages
{
    /** Niveau de poule le plus faible accepté (saisie de l'étape 3, destinations « saison suivante »). */
    public const NIVEAU_MAX = 20;

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

    /** Destination (« p:identifiant ») du vainqueur ou du perdant d'un match de barrage, ou vide si elle n'est pas choisie. */
    private static function destinationDeMatch(Phase $phase, Poule $poule, string $issue): string
    {
        $source = $phase->getCode().'.'.$poule->getCode().'.'.$issue;
        foreach ($phase->getReglesDePassage() as $regle) {
            if ($regle->getSource() === $source && $regle->getPouleDestination() !== null) {
                return 'p:'.$regle->getPouleDestination()->getId();
            }
        }

        return '';
    }

    /**
     * Transitions à afficher : pour chaque phase qui a une suivante, ses poules (valeurs simples ou lignes détaillées),
     * les destinations possibles, le contrôle des effectifs reçus et les problèmes relevés par la vérification.
     *
     * @param list<int> $detail poules forcées en mode détaillé
     */
    public static function transitions(Saison $saison, array $detail, array $figees = []): array
    {
        $phases = self::phasesDansLOrdre($saison);
        $niveaux = [];
        foreach ($phases as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $niveaux[] = (int) $poule->getNiveau();
            }
        }
        $niveauMax = min(self::NIVEAU_MAX, max([1, ...$niveaux]));

        $fins = self::barragesFinDeSaison($phases);
        $transitions = [];
        foreach ($phases as $i => $phase) {
            $suivante = $phases[$i + 1] ?? null;
            if ($suivante === null || $phase->getType() === PhaseType::FINALE || self::passagesFiges($phase, $suivante, $figees)) {
                continue; // une phase finale ne transmet pas d'équipes (D50) ; passages figés en mode ajout (D51)
            }
            $versFinale = $suivante->getType() === PhaseType::FINALE;
            $barrage = $phase->getType() === PhaseType::BARRAGE;           // les poules sont des matchs (D42)
            $versBarrage = $suivante->getType() === PhaseType::BARRAGE;    // toujours en mode détaillé (D32)
            // Barrages de fin de saison alimentés par cette phase : saisis dans leur propre section (D53)
            $phasesFin = self::finsDe($phase, $fins);

            // Destinations : poules des phases suivantes, puis niveaux de la saison suivante ; jamais les barrages de fin de
            // saison, qui se saisissent dans leur propre section (D53)
            $destinations = [];
            foreach (\array_slice($phases, $i + 1) as $phaseSuivante) {
                if (\in_array($phaseSuivante, array_column($fins, 'barrage'), true)) {
                    continue;
                }
                $destinations[$phaseSuivante->getNom()] = array_map(fn (Poule $p) => ['valeur' => 'p:'.$p->getId(), 'libelle' => $p->getNom().' ('.$phaseSuivante->getNom().')'], self::poules($phaseSuivante));
            }
            $destinations['Saison suivante'] = array_map(fn (int $n) => ['valeur' => 'n:'.$n, 'libelle' => "Niveau $n la saison prochaine"], range(1, $niveauMax));

            $poules = [];
            foreach (self::poules($phase) as $poule) {
                if ($barrage) {
                    // Un match : où vont le vainqueur et le perdant
                    $poules[] = ['poule' => $poule, 'taille' => 2, 'simple' => null, 'mode' => 'barrage', 'lignes' => [], 'rangs' => [],
                        'vainqueur' => self::destinationDeMatch($phase, $poule, Place::VAINQUEUR),
                        'perdant' => self::destinationDeMatch($phase, $poule, Place::PERDANT)];
                    continue;
                }
                $prefixe = $phase->getCode().'.'.$poule->getCode().'#';
                $regles = array_values(array_filter(self::reglesDe($phase, $poule), fn (RegleDePassage $r) => !self::versPhases($r, $phasesFin)));
                $simple = PassagesSimples::lire($prefixe, $regles, $versFinale);
                $taille = self::taille($poule);
                $poules[] = [
                    'poule' => $poule,
                    'taille' => $taille,
                    'simple' => $simple,
                    'mode' => $versBarrage || $simple === null || \in_array($poule->getId(), $detail, true) ? 'detail' : 'simple',
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
                'barrage' => $barrage,
                'versBarrage' => $versBarrage,
                // Barrages de fin de saison après cette phase : pas de case « saison suivante » (D53)
                'finDeSaison' => $phasesFin !== [],
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
    public static function appliquer(Saison $saison, array $saisie, string $action, array &$detail, array $figees = [], array $saisieFin = []): array
    {
        $phases = self::phasesDansLOrdre($saison);
        $fins = self::barragesFinDeSaison($phases);
        $poulesParId = [];
        foreach ($phases as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $poulesParId[$poule->getId()] = $poule;
            }
        }
        $problemes = [];

        foreach ($phases as $i => $phase) {
            $suivante = $phases[$i + 1] ?? null;
            if ($suivante === null || $phase->getType() === PhaseType::FINALE || self::passagesFiges($phase, $suivante, $figees)) {
                continue; // une phase finale ne transmet pas d'équipes (D50) ; passages figés en mode ajout (D51)
            }
            $versFinale = $suivante->getType() === PhaseType::FINALE;
            $versBarrage = $suivante->getType() === PhaseType::BARRAGE;
            // Barrages de fin de saison alimentés par cette phase : leurs règles se saisissent dans leur section (D53) et
            // sont conservées telles quelles ici
            $phasesFin = self::finsDe($phase, $fins);
            // Destinations permises : poules des phases suivantes, sauf les barrages de fin de saison (D53)
            $permises = [];
            foreach (\array_slice($phases, $i + 1) as $p) {
                if (\in_array($p, array_column($fins, 'barrage'), true)) {
                    continue;
                }
                foreach ($p->getPoules() as $poule) {
                    $permises[$poule->getId()] = $poule;
                }
            }

            if ($phase->getType() === PhaseType::BARRAGE) {
                // Barrages : une carte par match, vainqueur et perdant vers une poule (D32, D42)
                $nouvelles = [];
                foreach (self::poules($phase) as $poule) {
                    $champs = \is_array($saisie[$poule->getId()] ?? null) ? $saisie[$poule->getId()] : null;
                    foreach ([Place::VAINQUEUR, Place::PERDANT] as $issue) {
                        $source = $phase->getCode().'.'.$poule->getCode().'.'.$issue;
                        if ($champs === null) {
                            // match non envoyé : règles inchangées
                            foreach ($phase->getReglesDePassage() as $existante) {
                                if ($existante->getSource() === $source) {
                                    $nouvelles[] = $existante;
                                }
                            }
                            continue;
                        }
                        $dest = \is_string($champs[$issue] ?? null) ? $champs[$issue] : ''; // un tableau envoyé = valeur absente
                        if (preg_match('/^p:(\d+)$/', $dest, $m) && isset($permises[(int) $m[1]])) {
                            $nouvelles[] = (new RegleDePassage())->setSource($source)->setPouleDestination($permises[(int) $m[1]]);
                        } else {
                            $problemes[] = sprintf('%s : indiquez où va le %s.', $poule->getNom(), $issue);
                        }
                    }
                }
                self::remplacer($phase, $nouvelles);
                continue;
            }

            if (!$versBarrage && $action === 'retablir-'.$phase->getId()) {
                // Règles par défaut, sans saison suivante s'il y a des barrages de fin de saison ; leurs règles restent
                $defaut = array_filter(ReglesParDefaut::pourPhase($phase, $suivante), fn (RegleDePassage $r) => $phasesFin === [] || !$r->versSaisonSuivante());
                $fin = array_filter($phase->getReglesDePassage()->toArray(), fn (RegleDePassage $r) => self::versPhases($r, $phasesFin));
                self::remplacer($phase, [...$defaut, ...$fin]);
                foreach ($phase->getPoules() as $poule) {
                    $detail = array_values(array_diff($detail, [$poule->getId()]));
                }
                continue;
            }

            $nouvelles = [];
            foreach (self::poules($phase) as $poule) {
                $id = $poule->getId();
                $prefixe = $phase->getCode().'.'.$poule->getCode().'#';
                $champs = \is_array($saisie[$id] ?? null) ? $saisie[$id] : null;
                $reglesPoule = self::reglesDe($phase, $poule);
                $reglesFin = array_values(array_filter($reglesPoule, fn (RegleDePassage $r) => self::versPhases($r, $phasesFin)));
                $reglesPoule = array_values(array_filter($reglesPoule, fn (RegleDePassage $r) => !self::versPhases($r, $phasesFin)));

                if (!$versBarrage && $action === 'simple-'.$id) {
                    $detail = array_values(array_diff($detail, [$id]));
                    $regles = $reglesPoule;
                    if (PassagesSimples::lire($prefixe, $regles, $versFinale) === null) {
                        // Règles détaillées : on repart des règles par défaut de cette poule
                        $regles = array_values(array_filter(ReglesParDefaut::pourPhase($phase, $suivante), fn (RegleDePassage $r) => str_starts_with($r->getSource(), $prefixe) && ($phasesFin === [] || !$r->versSaisonSuivante())));
                    }
                    array_push($nouvelles, ...$regles, ...$reglesFin);
                    continue;
                }
                if ($action === 'detail-'.$id) {
                    $detail[] = $id;
                }

                if ($champs === null) {
                    array_push($nouvelles, ...$reglesPoule); // poule non envoyée : règles inchangées
                } elseif (!$versBarrage && ($champs['mode'] ?? 'simple') === 'simple') {
                    $valeurs = [
                        'montee' => (int) ($champs['montee'] ?? 0),
                        'descente' => (int) ($champs['descente'] ?? 0),
                        'versMontee' => $permises[(int) ($champs['versMontee'] ?? 0)] ?? null,
                        'versDescente' => $permises[(int) ($champs['versDescente'] ?? 0)] ?? null,
                        'versAutres' => $permises[(int) ($champs['versAutres'] ?? 0)] ?? null,
                        // Case masquée quand des barrages de fin de saison suivent (D53) : pas de saison suivante
                        'saisonSuivante' => $phasesFin === [] && !empty($champs['saisonSuivante']),
                        'niveauMontee' => self::niveau($champs['niveauMontee'] ?? 1),
                        'niveauDescente' => self::niveau($champs['niveauDescente'] ?? 1),
                        'niveauAutres' => self::niveau($champs['niveauAutres'] ?? 1),
                    ];
                    array_push($nouvelles, ...PassagesSimples::regles($prefixe, $valeurs, $versFinale));
                } else {
                    $lignes = \is_array($champs['r'] ?? null) ? array_values($champs['r']) : [];
                    $premiere = self::poules($suivante)[0] ?? null;
                    $lignes = self::agirSurLignes($action, $id, $lignes, $premiere ? 'p:'.$premiere->getId() : '');
                    foreach ($lignes as $ligne) {
                        $regle = \is_array($ligne) ? self::regleDepuisLigne($prefixe, $ligne, $permises) : null;
                        if ($regle === null) {
                            $problemes[] = sprintf('%s : une règle incomplète a été ignorée.', $poule->getNom());
                            continue;
                        }
                        $nouvelles[] = $regle;
                    }
                }
                array_push($nouvelles, ...$reglesFin);
            }
            self::remplacer($phase, $nouvelles);
        }

        // Barrages de fin de saison : seules leurs règles sont remplacées (D53)
        foreach ($fins as ['barrage' => $barrage, 'source' => $source]) {
            $champs = $saisieFin[$barrage->getId()] ?? null;
            if (!\is_array($champs) || $source->getClose() || \in_array($barrage, $figees, true)) {
                continue; // section non envoyée, ou passages figés (D51) : règles inchangées
            }
            array_push($problemes, ...self::appliquerFin($barrage, $source, $champs));
        }

        $detail = array_values(array_unique($detail));

        return $problemes;
    }

    /**
     * Barrages de fin de saison (D53) : phase de barrages dont la phase précédente est une phase finale ; ses équipes
     * viennent de la dernière phase de championnat placée avant elle (la source).
     *
     * @param list<Phase> $phases dans l'ordre de la saison
     * @return list<array{barrage: Phase, source: Phase}>
     */
    public static function barragesFinDeSaison(array $phases): array
    {
        $fins = [];
        foreach ($phases as $i => $phase) {
            if ($phase->getType() !== PhaseType::BARRAGE || ($phases[$i - 1] ?? null)?->getType() !== PhaseType::FINALE) {
                continue;
            }
            for ($j = $i - 1; $j >= 0; --$j) {
                if ($phases[$j]->getType() === PhaseType::CHAMPIONNAT) {
                    $fins[] = ['barrage' => $phase, 'source' => $phases[$j]];
                    break;
                }
            }
        }

        return $fins;
    }

    /**
     * Sections « source → barrages de fin de saison » de l'étape Passages (D53) : choix (rang et poule de la source, en
     * un seul choix), deux équipes par match ; pré-remplies selon le schéma du bureau s'il n'y a encore aucune règle.
     * Sans section quand la source est clôturée ou les barrages déjà commencés (mode ajout, D51).
     *
     * @param list<Phase> $figees
     * @return list<array{source: Phase, barrage: Phase, choix: array<string, list<array{valeur: string, libelle: string}>>, matchs: list<array{poule: Poule, valeurs: list<string>}>, propose: bool}>
     */
    public static function sectionsFinDeSaison(Saison $saison, array $figees = []): array
    {
        $sections = [];
        foreach (self::barragesFinDeSaison(self::phasesDansLOrdre($saison)) as ['barrage' => $barrage, 'source' => $source]) {
            if ($source->getClose() || \in_array($barrage, $figees, true)) {
                continue;
            }
            $poulesSource = self::poules($source);
            $choix = [];
            foreach ($poulesSource as $poule) {
                $choix[$poule->getNom()] = array_map(fn (int $rang) => [
                    'valeur' => $poule->getCode().'#'.$rang,
                    'libelle' => self::equipeEnClair($rang, $poule, true),
                ], self::rangsProposes($poule));
            }

            // Équipes déjà choisies : règles « un rang d'une poule → ce match »
            $existantes = [];
            foreach ($source->getReglesDePassage() as $regle) {
                $match = $regle->getPouleDestination();
                if ($match === null || $match->getPhase() !== $barrage) {
                    continue;
                }
                try {
                    $place = NotationPlace::analyser($regle->getSource());
                } catch (NotationInvalide) {
                    continue;
                }
                if ($place->estRang() && $place->rangDebut === $place->rangFin) {
                    $existantes[$match->getId()][] = $place->poule.'#'.$place->rangDebut;
                }
            }

            // Schéma du bureau : avant-dernier de la poule N contre 2e de la poule N+1 (D44, D50)
            $propose = $existantes === [];
            $matchs = [];
            $problemes = [];
            foreach (self::poules($barrage) as $k => $match) {
                $valeurs = $existantes[$match->getId()] ?? [];
                if ($propose) {
                    $valeurs = array_values(array_filter([
                        isset($poulesSource[$k]) ? $poulesSource[$k]->getCode().'#-2' : null,
                        isset($poulesSource[$k + 1]) ? $poulesSource[$k + 1]->getCode().'#2' : null,
                    ]));
                }
                $matchs[] = ['poule' => $match, 'valeurs' => $valeurs];
                if (\count($valeurs) !== 2) {
                    $problemes[] = sprintf('%s : choisissez les deux équipes.', $match->getNom());
                }
            }

            $sections[] = ['source' => $source, 'barrage' => $barrage, 'choix' => $choix, 'matchs' => $matchs, 'propose' => $propose, 'problemes' => $problemes];
        }

        return $sections;
    }

    /**
     * Enregistre les équipes des matchs d'une phase de barrages de fin de saison : remplace les seules règles de la source
     * qui y mènent. Contrôles : deux équipes par match, différentes, une équipe dans un seul barrage.
     *
     * @return list<string> problèmes de saisie
     */
    private static function appliquerFin(Phase $barrage, Phase $source, array $champs): array
    {
        // Places proposées par la page, par code de poule : seules celles-là sont acceptées
        $proposees = [];
        foreach ($source->getPoules() as $poule) {
            foreach (self::rangsProposes($poule) as $rang) {
                $proposees[$poule->getCode().'#'.$rang] = [$poule, $rang];
            }
        }
        $problemes = [];
        $nouvelles = [];
        $deja = [];
        foreach (self::poules($barrage) as $match) {
            $valeurs = \is_array($champs[$match->getId()] ?? null) ? $champs[$match->getId()] : [];
            $choisies = [];
            foreach ([0, 1] as $k) {
                $valeur = \is_string($valeurs[$k] ?? null) ? $valeurs[$k] : '';
                if (isset($proposees[$valeur])) {
                    [$poule, $rang] = $proposees[$valeur];
                    // Même équipe sous deux libellés (6e et avant-dernier d'une poule de 7) : même clé
                    $choisies[] = ['poule' => $poule, 'rang' => $rang, 'cle' => $poule->getCode().'#'.self::rangReel($rang, self::taille($poule))];
                }
            }
            if (\count($choisies) < 2) {
                $problemes[] = sprintf('%s : choisissez les deux équipes.', $match->getNom());
            } elseif ($choisies[0]['cle'] === $choisies[1]['cle']) {
                $problemes[] = sprintf('%s : choisissez deux équipes différentes.', $match->getNom());
                array_pop($choisies);
            }
            foreach ($choisies as $choisie) {
                if (isset($deja[$choisie['cle']])) {
                    $problemes[] = sprintf('%s est déjà dans %s : une équipe ne joue qu\'un barrage.', ucfirst(self::equipeEnClair($choisie['rang'], $choisie['poule'], false)), $deja[$choisie['cle']]->getNom());
                    continue;
                }
                $deja[$choisie['cle']] = $match;
                $nouvelles[] = (new RegleDePassage())->setSource($source->getCode().'.'.$choisie['poule']->getCode().'#'.$choisie['rang'])->setPouleDestination($match);
            }
        }

        $autres = array_filter($source->getReglesDePassage()->toArray(), fn (RegleDePassage $r) => !self::versPhases($r, [$barrage]));
        self::remplacer($source, [...$autres, ...$nouvelles]);

        return $problemes;
    }

    /**
     * « 2e – Poule A » (choix d'une liste) ou « le 2e de Poule A » / « l'avant-dernier de Poule A » (phrase) : sans
     * article devant le nom saisi de la poule, qui peut être « Poule A » comme « Excellence ».
     */
    private static function equipeEnClair(int $rang, Poule $poule, bool $choix): string
    {
        $rangEnClair = Place::rangEnClair($rang);
        if ($choix) {
            return ucfirst($rangEnClair).' – '.$poule->getNom();
        }

        return (preg_match('/^[aeiouyéè]/iu', $rangEnClair) ? 'l\'' : 'le ').$rangEnClair.' de '.$poule->getNom();
    }

    /** Rangs proposés pour une poule de la source (mêmes listes à l'affichage et à l'enregistrement). @return list<int> */
    private static function rangsProposes(Poule $poule): array
    {
        return array_map(fn (array $r) => (int) $r['valeur'], self::rangs(self::taille($poule), []));
    }

    /** Rang compté depuis le début : -2 dans une poule de 7 → 6 ; taille inconnue : rang tel quel. */
    private static function rangReel(int $rang, ?int $taille): int
    {
        return $rang < 0 && $taille !== null ? $taille + 1 + $rang : $rang;
    }

    /** Phases de barrages de fin de saison alimentées par cette phase. @return list<Phase> */
    private static function finsDe(Phase $phase, array $fins): array
    {
        return array_values(array_map(fn (array $f) => $f['barrage'], array_filter($fins, fn (array $f) => $f['source'] === $phase)));
    }

    /** La règle mène-t-elle à une poule de l'une de ces phases ? @param list<Phase> $phases */
    private static function versPhases(RegleDePassage $regle, array $phases): bool
    {
        return $phases !== [] && \in_array($regle->getPouleDestination()?->getPhase(), $phases, true);
    }

    /**
     * Passages qui ne se modifient plus (mode ajout d'une saison lancée, D51) : la phase est clôturée (ses passages sont
     * appliqués) ou la phase suivante est figée (déjà composée ou commencée).
     *
     * @param list<Phase> $figees phases figées du mode ajout (ModeAjout::phasesFigees)
     */
    public static function passagesFiges(Phase $phase, Phase $suivante, array $figees): bool
    {
        return (bool) $phase->getClose() || \in_array($suivante, $figees, true);
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
        if (preg_match('/^n:([1-9]\d?)$/', $dest, $m) && (int) $m[1] <= self::NIVEAU_MAX) {
            return $regle->setNiveauSaisonSuivante((int) $m[1]);
        }

        return null;
    }

    /** Niveau de la saison suivante saisi, ramené entre 1 et NIVEAU_MAX. */
    private static function niveau(mixed $valeur): int
    {
        return max(1, min(self::NIVEAU_MAX, \is_scalar($valeur) ? (int) $valeur : 1));
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
        // Une poule peut recevoir des équipes de plusieurs phases (barrages : la phase 2 reçoit de la phase 1 et des
        // barrages) : on compte les règles de toutes les phases qui précèdent la phase suivante
        foreach (self::phasesDansLOrdre($suivante->getSaison()) as $origine) {
            if ($origine->getOrdre() >= $suivante->getOrdre()) {
                continue;
            }
            foreach ($origine->getReglesDePassage() as $regle) {
                $destination = $regle->getPouleDestination();
                if ($destination === null || $destination->getPhase() !== $suivante) {
                    continue;
                }
                try {
                    $place = NotationPlace::analyser($regle->getSource());
                } catch (NotationInvalide) {
                    continue;
                }
                $cle = spl_object_id($destination);
                if (!$place->estRang()) {
                    $recues[$cle] = ($recues[$cle] ?? 0) + 1; // vainqueur ou perdant d'un match : une équipe
                    continue;
                }
                $source = null;
                foreach ($origine->getPoules() as $poule) {
                    if ($poule->getCode() === $place->poule) {
                        $source = $poule;
                    }
                }
                $taille = $source ? self::taille($source) : null;
                if ($taille === null) {
                    $inconnu[$cle] = true;
                    continue;
                }
                try {
                    $recues[$cle] = ($recues[$cle] ?? 0) + \count($place->rangsPour($taille));
                } catch (NotationInvalide) {
                }
            }
        }

        return array_map(fn (Poule $p) => [
            'poule' => $p,
            'recues' => isset($inconnu[spl_object_id($p)]) ? null : ($recues[spl_object_id($p)] ?? 0),
            'prevues' => $p->getNbEquipesPrevu(),
        ], self::poules($suivante));
    }
}
