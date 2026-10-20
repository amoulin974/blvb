<?php

namespace App\Reglement\Bloc;

use App\Competition\NotationInvalide;
use App\Competition\NotationPlace;
use App\Competition\Place;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Reglement\BlocInapplicable;
use App\Reglement\Texte;
use Twig\Environment;

/**
 * [schema_phases] : phases dans l'ordre, sorties de chaque poule (« 1er et 2e : montent en poule A »), composition des
 * barrages, tableaux des phases finales (tours, matchs, places, sans noms d'équipes) ; D58, maquettes relues par
 * l'ergonome (reglement-maquettes.md).
 */
final class SchemaPhases extends BlocParPhase
{
    public function __construct(private Environment $twig)
    {
    }

    public function code(): string
    {
        return 'schema_phases';
    }

    public function libelle(): string
    {
        return 'Schéma des phases';
    }

    public function description(): string
    {
        return 'Les phases de la saison, ce que deviennent les équipes de chaque poule, les barrages et les tableaux des phases finales.';
    }

    public function rendre(Saison $saison, array $parametres): string
    {
        $phases = self::phases($saison, $parametres);
        if ($phases === []) {
            throw new BlocInapplicable('la saison n\'a pas encore de phase.');
        }
        $regles = [];
        foreach (self::phasesDansLOrdre($saison) as $phase) {
            foreach ($phase->getReglesDePassage() as $regle) {
                $regles[] = $regle;
            }
        }

        return $this->twig->render('reglement/blocs/schema_phases.html.twig', [
            'phases' => array_map(fn (Phase $phase) => $this->phase($phase, $regles), $phases),
        ]);
    }

    /** @param list<RegleDePassage> $regles toutes les règles de la saison */
    private function phase(Phase $phase, array $regles): array
    {
        $type = $phase->getType() ?? PhaseType::CHAMPIONNAT;
        $infos = array_filter([
            match ($type) {
                PhaseType::CHAMPIONNAT => $phase->estAllerRetour() ? 'Championnat aller-retour' : 'Championnat aller simple',
                PhaseType::BARRAGE => 'Barrages : un match par barrage',
                PhaseType::FINALE => 'Élimination directe',
            },
            Texte::periode($phase->getDatedebut(), $phase->getDatefin()),
            sprintf('%d sets gagnants', $phase->getSetsGagnants()),
        ]);

        $vue = ['nom' => $phase->getNom(), 'type' => $type->name, 'infos' => implode(' · ', $infos), 'poules' => [], 'barrages' => [],
            'tableaux' => [], 'reception' => null, 'terminale' => false];
        $poules = self::poulesParNiveau($phase);
        if ($type === PhaseType::CHAMPIONNAT) {
            foreach ($poules as $poule) {
                $vue['poules'][] = ['nom' => $poule->getNom(), 'effectif' => self::effectif($poule), 'groupes' => $this->sorties($phase, $poule)];
            }
        } elseif ($type === PhaseType::BARRAGE) {
            $vue['reception'] = match ($phase->getReception()) {
                Phase::RECEPTION_MIEUX_CLASSE => 'L\'équipe la mieux classée reçoit.',
                Phase::RECEPTION_NEUTRE => 'Les matchs se jouent sur terrain neutre.',
                default => 'L\'équipe de la poule la plus haute reçoit.',
            };
            foreach ($poules as $poule) {
                $vue['barrages'][] = $this->barrage($phase, $poule, $regles);
            }
        } else {
            $vue['terminale'] = $phase->getReglesDePassage()->isEmpty();
            foreach ($poules as $poule) {
                $vue['tableaux'][] = $this->tableau($phase, $poule, $regles);
            }
        }

        return $vue;
    }

    /**
     * Sorties d'une poule de championnat, groupées par phase d'arrivée (« Phase 2 », « Barrages », « Saison prochaine »)
     *
     * @return list<array{titre: string, sorties: list<string>}>
     */
    private function sorties(Phase $phase, Poule $poule): array
    {
        $effectif = Texte::effectif($poule);
        $groupes = [];
        foreach ($phase->getReglesDePassage() as $regle) {
            $place = self::place($regle);
            if ($place === null || !$place->estRang() || $place->poule !== $poule->getCode()) {
                continue;
            }
            if (($destination = $regle->getPouleDestination()) !== null) {
                $arrivee = $destination->getPhase();
                $cle = sprintf('phase-%03d-%d', $arrivee?->getOrdre() ?? 0, $arrivee?->getId() ?? 0);
                $titre = (string) $arrivee?->getNom();
                $vers = 'poule-'.spl_object_id($destination);
                $texte = match ($arrivee?->getType()) {
                    PhaseType::BARRAGE => fn (bool $pluriel) => ($pluriel ? 'jouent le ' : 'joue le ').Texte::poule($destination),
                    PhaseType::FINALE => fn (bool $pluriel) => ($pluriel ? 'vont ' : 'va ').Texte::dansPoule($destination),
                    default => fn (bool $pluriel) => self::verbe($poule->getNiveau(), $destination->getNiveau(), $pluriel).' '.Texte::dansPoule($destination),
                };
            } elseif (($niveau = $regle->getNiveauSaisonSuivante()) !== null) {
                $cle = 'saison-suivante';
                $titre = 'Saison prochaine';
                $vers = 'niveau-'.$niveau;
                $texte = fn (bool $pluriel) => self::verbe($poule->getNiveau(), $niveau, $pluriel).' '.self::niveauSuivant($phase, $niveau);
            } else {
                continue;
            }
            try {
                $rangs = $effectif !== null ? $place->rangsPour($effectif) : null;
            } catch (NotationInvalide) {
                $rangs = null;
            }
            $groupes[$cle] ??= ['titre' => $titre, 'entrees' => []];
            $entrees = &$groupes[$cle]['entrees'];
            $derniere = $entrees === [] ? null : \count($entrees) - 1;
            // Rangs voisins vers la même destination : une seule ligne (« du 1er au 6e » plutôt que « 1er » et « du 2e au 6e »)
            $precedente = $derniere !== null && $entrees[$derniere]['vers'] === $vers ? $entrees[$derniere] : null;
            if ($precedente !== null && $rangs !== null && $precedente['rangs'] !== null && max($precedente['rangs']) + 1 === min($rangs)) {
                $entrees[$derniere]['rangs'] = array_merge($precedente['rangs'], $rangs);
                $entrees[$derniere]['place'] = Place::rang($place->phase, $place->poule, min($entrees[$derniere]['rangs']), max($entrees[$derniere]['rangs']));
            } elseif ($precedente !== null && $rangs === null && $precedente['rangs'] === null
                && $precedente['place']->rangFin * $place->rangDebut > 0 && $place->rangDebut === $precedente['place']->rangFin + 1) {
                // Effectif inconnu : rangs voisins comptés du même bout (« 1er » puis « du 2e … ») ou depuis la fin (-2 puis -1)
                $entrees[$derniere]['place'] = Place::rang($place->phase, $place->poule, $precedente['place']->rangDebut, $place->rangFin);
            } else {
                $entrees[] = ['vers' => $vers, 'rangs' => $rangs, 'place' => $place, 'texte' => $texte];
            }
            unset($entrees);
        }
        // Saison prochaine en dernier
        ksort($groupes);
        uksort($groupes, fn ($a, $b) => ($a === 'saison-suivante') <=> ($b === 'saison-suivante'));

        return array_values(array_map(fn (array $groupe) => [
            'titre' => $groupe['titre'],
            'sorties' => array_map(function (array $entree) use ($effectif) {
                $rangs = Texte::rangs($entree['place'], $effectif);

                return self::majuscule($rangs['texte']).' : '.($entree['texte'])($rangs['pluriel']);
            }, $groupe['entrees']),
        ], $groupes));
    }

    /** @param list<RegleDePassage> $regles */
    private function barrage(Phase $phase, Poule $poule, array $regles): array
    {
        $adversaires = [];
        foreach ($regles as $regle) {
            if ($regle->getPouleDestination() === $poule && ($origine = $this->origine($regle)) !== null) {
                $adversaires[] = $origine['texte'];
            }
        }
        $issues = [];
        foreach ($phase->getReglesDePassage() as $regle) {
            $place = self::place($regle);
            if ($place === null || $place->estRang() || $place->match !== $poule->getCode()) {
                continue;
            }
            $qui = $place->issue === Place::VAINQUEUR ? 'Vainqueur' : 'Perdant';
            if (($destination = $regle->getPouleDestination()) !== null) {
                $issues[] = sprintf('%s : va %s (%s)', $qui, Texte::dansPoule($destination), $destination->getPhase()?->getNom());
            } elseif ($regle->getNiveauSaisonSuivante() !== null) {
                $issues[] = sprintf('%s : %s la saison prochaine', $qui, self::niveauSuivant(self::championnatAvant($phase) ?? $phase, $regle->getNiveauSaisonSuivante()));
            }
        }

        return ['nom' => $poule->getNom(), 'adversaires' => $adversaires === [] ? null : self::majuscule(implode(' contre ', $adversaires)), 'issues' => $issues];
    }

    /** @param list<RegleDePassage> $regles */
    private function tableau(Phase $phase, Poule $tableau, array $regles): array
    {
        // Têtes de série : rang dans la poule d'origine, puis niveau de cette poule (Elimination::ordreDeTete)
        $entrees = [];
        $sources = [];
        $connues = true;
        foreach ($regles as $regle) {
            if ($regle->getPouleDestination() !== $tableau || ($origine = $this->origine($regle)) === null) {
                continue;
            }
            $sources[] = $origine['texte'];
            if ($origine['poule'] === null) {
                $entrees[] = [PHP_INT_MAX, PHP_INT_MAX, $origine['texte']];
                continue;
            }
            $effectif = Texte::effectif($origine['poule']);
            try {
                $rangs = $effectif !== null ? $origine['place']->rangsPour($effectif) : null;
            } catch (NotationInvalide) {
                $rangs = null;
            }
            if ($rangs === null) {
                $connues = false;
                continue;
            }
            foreach ($rangs as $rang) {
                $entrees[] = [$rang, $origine['poule']->getNiveau() ?? PHP_INT_MAX, $origine['poule'], $origine['phase']];
            }
        }
        usort($entrees, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $pouleUnique = \count(array_unique(array_map(fn ($e) => isset($e[3]) ? spl_object_id($e[2]) : -1, $entrees))) === 1 && isset($entrees[0][3]);

        $places = [];
        if ($connues && $entrees !== []) {
            foreach ($entrees as $entree) {
                $places[] = isset($entree[3])
                    ? Texte::ordinal($entree[0]).($pouleUnique ? '' : ' '.Texte::dePoule($entree[2]))
                    : $entree[2];
            }
        } else {
            $n = Texte::effectif($tableau) ?? 0;
            for ($i = 1; $i <= $n; ++$i) {
                $places[] = sprintf('tête de série %d', $i);
            }
        }
        $n = \count($places);

        if ($pouleUnique && $connues) {
            $legende = sprintf('%d équipes %s (%s), placées selon leur classement.', $n, Texte::dePoule($entrees[0][2]), $entrees[0][3]->getNom());
        } elseif ($sources !== []) {
            $legende = ($n > 0 ? sprintf('%d équipes : ', $n).Texte::enumeration($sources) : self::majuscule(Texte::enumeration($sources))).', placées selon leur classement.';
        } else {
            $legende = null;
        }

        $deroule = TableauElimination::construire($places, $phase->aPetiteFinale());
        foreach ($deroule['tours'] as &$tour) {
            foreach ($tour['matchs'] as &$match) {
                $match['equipes'] = array_map(self::majuscule(...), $match['equipes']);
            }
            unset($match);
        }
        unset($tour);
        $exemption = null;
        if ($deroule['exemptes'] > 0 && isset($deroule['tours'][1])) {
            $tour = self::singulier($deroule['tours'][1]['nom']);
            $exemption = $pouleUnique && $connues
                ? ($deroule['exemptes'] === 1 ? sprintf('Le 1er va directement en %s.', $tour) : sprintf('Les %d premiers vont directement en %s.', $deroule['exemptes'], $tour))
                : sprintf('Les %d équipes les mieux placées vont directement en %s.', $deroule['exemptes'], $tour);
        }

        // Dates des tours : la journée du même nom (Elimination::getNomJournee), sinon la journée de même rang par date ;
        // journée des finales à sa date fixe
        $journees = $tableau->getJournees()->toArray();
        usort($journees, fn ($a, $b) => [$a->getDateDebut(), $a->getNumero()] <=> [$b->getDateDebut(), $b->getNumero()]);
        $parNom = [];
        foreach ($journees as $journee) {
            $parNom[BlocCalendrier::tour((string) $journee->getNom())] = $journee;
        }
        $finales = $phase->getJourneeFinales();
        $nomsConnus = $parNom !== [] && array_diff(array_keys($parNom), BlocCalendrier::TOURS) === [];
        foreach ($deroule['tours'] as $i => &$tour) {
            $journee = $nomsConnus ? ($parNom[$tour['nom']] ?? ($tour['nom'] === 'Barrage' ? $parNom['Barrages'] ?? null : null)) : ($journees[$i] ?? null);
            $tour['date'] = null;
            if ($journee === null) {
                continue;
            }
            if ($finales !== null && $finales['date'] !== null && $journee->getDateDebut()?->format('Y-m-d') === $finales['date']->format('Y-m-d')) {
                $tour['date'] = sprintf('Journée des finales : %s, %s', Texte::date($finales['date'], 'EEEE dd/MM/yyyy'), Texte::heure($finales['heure']));
            } elseif ($journee->getDateFin() !== null) {
                $tour['date'] = 'À jouer avant le '.Texte::date($journee->getDateFin());
            }
        }
        unset($tour);

        return ['nom' => $tableau->getNom(), 'legende' => $legende, 'exemption' => $exemption, 'tours' => $deroule['tours'], 'colonnes' => \count($deroule['tours'])];
    }

    /**
     * Origine d'une règle, en mots publics : « 7e de la poule A (Phase 1) », « vainqueur du barrage A / B (Barrages) »
     *
     * @return array{texte: string, place: Place, poule: ?Poule, phase: ?Phase}|null
     */
    private function origine(RegleDePassage $regle): ?array
    {
        $place = self::place($regle);
        $phase = $regle->getPhase();
        if ($place === null || $phase === null) {
            return null;
        }
        $poule = null;
        foreach ($phase->getPoules() as $p) {
            if ($p->getCode() === ($place->estRang() ? $place->poule : $place->match)) {
                $poule = $p;
            }
        }
        if (!$place->estRang()) {
            $texte = sprintf('%s %s (%s)', $place->issue, $poule !== null ? Texte::dePoule($poule) : 'du match '.$place->match, $phase->getNom());

            return ['texte' => $texte, 'place' => $place, 'poule' => null, 'phase' => $phase];
        }
        $rangs = Texte::rangs($place, $poule !== null ? Texte::effectif($poule) : null);
        $texte = sprintf('%s %s (%s)', $rangs['texte'], $poule !== null ? Texte::dePoule($poule) : 'de la poule '.$place->poule, $phase->getNom());

        return ['texte' => $texte, 'place' => $place, 'poule' => $poule, 'phase' => $phase];
    }

    private static function place(RegleDePassage $regle): ?Place
    {
        try {
            return NotationPlace::analyser($regle->getSource());
        } catch (NotationInvalide) {
            return null;
        }
    }

    /**
     * Niveau d'une poule de la saison suivante, désigné par la poule de même niveau de la phase : « au niveau de la
     * poule A », sinon « au niveau 2 (sur 4, 1 = le plus haut) » ; jamais le niveau 0 des saisons reprises (ergonome)
     */
    private static function niveauSuivant(Phase $phase, int $niveau): string
    {
        $niveaux = [];
        foreach ($phase->getPoules() as $poule) {
            if ($poule->getNiveau() === $niveau) {
                return 'au niveau '.Texte::dePoule($poule);
            }
            if ($poule->getNiveau() !== null) {
                $niveaux[$poule->getNiveau()] = true;
            }
        }
        $niveaux[$niveau] = true;
        ksort($niveaux);
        $rang = array_search($niveau, array_keys($niveaux), true) + 1;

        return sprintf('au niveau %d (sur %d, 1 = le plus haut)', $rang, \count($niveaux));
    }

    /** Dernière phase de championnat placée avant la phase (source des barrages, D53) */
    private static function championnatAvant(Phase $phase): ?Phase
    {
        $avant = null;
        foreach (self::phasesDansLOrdre($phase->getSaison()) as $autre) {
            if ($autre === $phase) {
                break;
            }
            if ($autre->getType() === PhaseType::CHAMPIONNAT) {
                $avant = $autre;
            }
        }

        return $avant;
    }

    /** « montent », « reste », « descend » selon les niveaux (1 = le plus haut) ; « va » si un niveau manque */
    private static function verbe(?int $depart, ?int $arrivee, bool $pluriel): string
    {
        return match (true) {
            $depart === null || $arrivee === null => $pluriel ? 'vont' : 'va',
            $arrivee < $depart => $pluriel ? 'montent' : 'monte',
            $arrivee > $depart => $pluriel ? 'descendent' : 'descend',
            default => $pluriel ? 'restent' : 'reste',
        };
    }

    /** @return list<Poule> */
    private static function poulesParNiveau(Phase $phase): array
    {
        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [$a->getNiveau() ?? PHP_INT_MAX, (string) $a->getNom()] <=> [$b->getNiveau() ?? PHP_INT_MAX, (string) $b->getNom()]);

        return array_values($poules);
    }

    private static function effectif(Poule $poule): ?string
    {
        $placees = $poule->getEquipes()->count();
        if ($placees > 0) {
            return $placees.($placees > 1 ? ' équipes' : ' équipe');
        }
        $prevu = $poule->getNbEquipesPrevu();

        return $prevu !== null ? $prevu.($prevu > 1 ? ' équipes prévues' : ' équipe prévue') : null;
    }

    /** « Quarts de finale » → « quart de finale », « Demi-finales » → « demi-finale », « Finale » → « finale » */
    private static function singulier(string $tour): string
    {
        return mb_strtolower(preg_replace(['/^Quarts/', '/^Huitièmes/', '/^Seizièmes/', '/^Demi-finales$/'], ['Quart', 'Huitième', 'Seizième', 'Demi-finale'], $tour));
    }

    private static function majuscule(string $texte): string
    {
        return mb_strtoupper(mb_substr($texte, 0, 1)).mb_substr($texte, 1);
    }
}
