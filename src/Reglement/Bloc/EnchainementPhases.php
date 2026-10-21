<?php

namespace App\Reglement\Bloc;

use App\Competition\NotationInvalide;
use App\Competition\NotationPlace;
use App\Competition\Place;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;
use App\Reglement\Texte;

/**
 * Enchaînement des phases, en tête de [schema_phases] (demande de l'utilisateur, relecture de l'ergonome, D58) : une
 * étape par date de début (phases qui commencent le même jour réunies : phase finale et barrages de fin de saison), et
 * sous chaque flèche, qui passe d'une étape à la suivante, résumé à partir des règles de passage (« Le 1er de chaque
 * poule monte, le dernier descend. Le 7e joue un barrage contre le 2e de la poule du dessous. »). Seuls les mouvements
 * communs à plusieurs poules sont résumés ; le détail reste dans les cartes des poules.
 */
final class EnchainementPhases
{
    /**
     * @param list<Phase> $phases dans l'ordre
     * @return list<array{transition: ?string, ensemble: bool, phases: list<array{nom: string, resume: string, finales: ?string, periode: string}>}>
     */
    public static function etapes(array $phases): array
    {
        $groupes = [];
        $debut = null;
        foreach ($phases as $phase) {
            $jour = $phase->getDatedebut()?->format('Y-m-d');
            if ($groupes === [] || $jour === null || $jour !== $debut) {
                $groupes[] = [];
                $debut = $jour;
            }
            $groupes[\count($groupes) - 1][] = $phase;
        }

        $etapes = [];
        foreach ($groupes as $i => $groupe) {
            $etapes[] = [
                'transition' => $i === 0 ? null : self::transition($groupes[$i - 1]),
                'ensemble' => \count($groupe) > 1,
                'phases' => array_map(fn (Phase $phase) => self::cases($phase, $phases), $groupe),
            ];
        }

        return $etapes;
    }

    /**
     * @param list<Phase> $phases toutes les phases, dans l'ordre
     * @return array{nom: string, resume: string, finales: ?string, periode: string}
     */
    private static function cases(Phase $phase, array $phases): array
    {
        $n = $phase->getPoules()->count();
        $effectif = self::effectifCommun($phase);
        $resume = match ($phase->getType()) {
            PhaseType::BARRAGE => $n.($n > 1 ? ' matchs de barrage' : ' match de barrage'),
            PhaseType::FINALE => $n.($n > 1 ? ' tableaux à élimination directe' : ' tableau à élimination directe'),
            default => $n.($n > 1 ? ' poules' : ' poule').($effectif !== null ? sprintf(' de %d équipes', $effectif) : '')
                .($phase->estAllerRetour() ? ', matchs aller et retour' : ''),
        };
        // Barrages qui suivent une phase finale (D53) : ils décident des poules de la saison suivante (D50)
        $precedente = null;
        foreach ($phases as $autre) {
            if ($autre === $phase) {
                break;
            }
            $precedente = $autre;
        }
        if ($phase->getType() === PhaseType::BARRAGE && $precedente?->getType() === PhaseType::FINALE) {
            $resume .= ', pour les poules de la saison prochaine';
        }
        $finales = $phase->getType() === PhaseType::FINALE ? $phase->getJourneeFinales() : null;

        return [
            'nom' => (string) $phase->getNom(),
            'resume' => $resume,
            'finales' => $finales !== null && $finales['date'] !== null ? 'Journée des finales : '.Texte::date($finales['date']) : null,
            'periode' => self::majuscule(Texte::periode($phase->getDatedebut(), $phase->getDatefin())),
        ];
    }

    /** @param list<Phase> $sources phases de l'étape précédente */
    private static function transition(array $sources): ?string
    {
        $phrases = [];
        foreach ($sources as $phase) {
            $phrases = array_merge($phrases, match ($phase->getType()) {
                PhaseType::BARRAGE => self::depuisBarrages($phase),
                PhaseType::CHAMPIONNAT => self::depuisChampionnat($phase),
                default => [],
            });
        }

        return $phrases === [] ? null : implode(' ', $phrases);
    }

    /**
     * Mouvements communs à plusieurs poules d'un championnat : montées, descentes, barrages (contre la poule du dessous),
     * phase finale, saison prochaine ; le maintien n'est pas répété
     *
     * @return list<string>
     */
    private static function depuisChampionnat(Phase $phase): array
    {
        $poules = [];
        foreach ($phase->getPoules() as $poule) {
            $poules[$poule->getCode()] = $poule;
        }
        $effectif = self::effectifCommun($phase);
        $seuil = min(2, \count($poules));
        $mouvements = [];
        foreach ($phase->getReglesDePassage() as $regle) {
            $place = self::place($regle);
            $source = $place !== null && $place->estRang() ? $poules[$place->poule] ?? null : null;
            if ($source === null) {
                continue;
            }
            $rangs = Texte::rangs($place, $effectif);
            $destination = $regle->getPouleDestination();
            $arrivee = $destination?->getPhase();
            if ($destination !== null && $arrivee?->getType() === PhaseType::CHAMPIONNAT) {
                $sens = self::sens($source->getNiveau(), $destination->getNiveau());
                if ($sens !== null) {
                    $mouvements[$sens.'|'.$rangs['texte']][] = $rangs;
                }
            } elseif ($destination !== null && $arrivee?->getType() === PhaseType::BARRAGE) {
                $adversaire = self::adversaireDuDessous($regle, $destination, $source, $effectif);
                if ($adversaire !== null) {
                    $finDeSaison = str_contains(mb_strtolower((string) $arrivee->getNom()), 'fin de saison');
                    $mouvements['barrage|'.$rangs['texte'].'|'.$adversaire.'|'.($finDeSaison ? 'fin' : '')][] = $rangs;
                }
            } elseif ($destination !== null && $arrivee?->getType() === PhaseType::FINALE) {
                if ($place->rangDebut === 1 && $place->rangFin === -1 && $destination->getNiveau() === $source->getNiveau()) {
                    $mouvements['finale|'.self::laPhase($arrivee)][] = $rangs;
                }
            } elseif ($regle->getNiveauSaisonSuivante() !== null) {
                $sens = self::sens($source->getNiveau(), $regle->getNiveauSaisonSuivante());
                if ($sens !== null) {
                    $mouvements['suivante-'.$sens.'|'.$rangs['texte']][] = $rangs;
                }
            }
        }
        $mouvements = array_filter($mouvements, fn (array $occurrences) => \count($occurrences) >= $seuil);

        $sujet = fn (array $rangs) => self::majuscule(Texte::sujet($rangs['texte']));
        $verbe = fn (array $rangs, string $singulier, string $pluriel) => $rangs['pluriel'] ? $pluriel : $singulier;
        $phrases = [];
        $niveaux = [];
        foreach (['monte' => ['monte', 'montent'], 'descend' => ['descend', 'descendent']] as $sens => [$s, $p]) {
            foreach ($mouvements as $cle => $occurrences) {
                if (str_starts_with($cle, $sens.'|')) {
                    $niveaux[] = ($niveaux === [] ? $sujet($occurrences[0]).' de chaque poule' : Texte::sujet($occurrences[0]['texte'])).' '.$verbe($occurrences[0], $s, $p);
                }
            }
        }
        if ($niveaux !== []) {
            $phrases[] = implode(', ', $niveaux).'.';
        }
        $finale = false;
        foreach ($mouvements as $cle => $occurrences) {
            if (str_starts_with($cle, 'finale|')) {
                $phrases[] = sprintf('Chaque équipe joue %s dans le tableau de son niveau.', substr($cle, \strlen('finale|')));
                $finale = true;
            }
        }
        foreach ($mouvements as $cle => $occurrences) {
            if (str_starts_with($cle, 'barrage|')) {
                [, , $adversaire, $fin] = explode('|', $cle);
                $phrases[] = sprintf('%s %s %s contre %s de la poule du dessous.', $sujet($occurrences[0]), $verbe($occurrences[0], 'joue', 'jouent'),
                    ($finale ? 'aussi ' : '').($fin === 'fin' ? 'un barrage de fin de saison' : 'un barrage'), $adversaire);
            }
        }
        $suivante = [];
        foreach (['monte' => ['monte', 'montent'], 'descend' => ['descend', 'descendent']] as $sens => [$s, $p]) {
            foreach ($mouvements as $cle => $occurrences) {
                if (str_starts_with($cle, 'suivante-'.$sens.'|')) {
                    $suivante[] = ($suivante === [] ? $sujet($occurrences[0]).' de chaque poule' : Texte::sujet($occurrences[0]['texte'])).' '.$verbe($occurrences[0], $s, $p);
                }
            }
        }
        if ($suivante !== []) {
            $phrases[] = implode(', ', $suivante).' la saison prochaine.';
        }

        return $phrases;
    }

    /**
     * Issues des barrages, si tous suivent le même schéma : vainqueur dans la poule du dessus, perdant dans celle du
     * dessous (D44)
     *
     * @return list<string>
     */
    private static function depuisBarrages(Phase $phase): array
    {
        $arrivee = null;
        $coherents = 0;
        foreach ($phase->getPoules() as $barrage) {
            $niveaux = [];
            foreach ($phase->getSaison()->getPhases() as $autre) {
                foreach ($autre->getReglesDePassage() as $regle) {
                    $place = self::place($regle);
                    if ($regle->getPouleDestination() === $barrage && $place?->estRang()) {
                        foreach ($autre->getPoules() as $poule) {
                            if ($poule->getCode() === $place->poule && $poule->getNiveau() !== null) {
                                $niveaux[] = $poule->getNiveau();
                            }
                        }
                    }
                }
            }
            $issues = [];
            foreach ($phase->getReglesDePassage() as $regle) {
                $place = self::place($regle);
                if ($place !== null && !$place->estRang() && $place->match === $barrage->getCode() && $regle->getPouleDestination() !== null) {
                    $issues[$place->issue] = $regle->getPouleDestination();
                }
            }
            if ($niveaux === [] || !isset($issues[Place::VAINQUEUR], $issues[Place::PERDANT])) {
                return [];
            }
            if ($issues[Place::VAINQUEUR]->getNiveau() !== min($niveaux) || $issues[Place::PERDANT]->getNiveau() !== max($niveaux)) {
                return [];
            }
            $arrivee = $issues[Place::VAINQUEUR]->getPhase();
            ++$coherents;
        }

        return $coherents === 0 || $arrivee === null ? [] : [sprintf('Le vainqueur de %s joue %s dans la poule du dessus, le perdant dans celle du dessous.',
            $coherents > 1 ? 'chaque barrage' : 'le barrage', self::laPhase($arrivee))];
    }

    /** « le 2e » de la poule du niveau juste en dessous qui joue le même barrage, sinon null (vu depuis l'autre poule) */
    private static function adversaireDuDessous(RegleDePassage $regle, Poule $barrage, Poule $source, ?int $effectif): ?string
    {
        foreach ($regle->getPhase()?->getReglesDePassage() ?? [] as $autre) {
            $place = self::place($autre);
            if ($autre === $regle || $autre->getPouleDestination() !== $barrage || $place === null || !$place->estRang()) {
                continue;
            }
            foreach ($regle->getPhase()->getPoules() as $poule) {
                if ($poule->getCode() === $place->poule && $source->getNiveau() !== null && $poule->getNiveau() === $source->getNiveau() + 1) {
                    return Texte::sujet(Texte::rangs($place, $effectif)['texte']);
                }
            }
        }

        return null;
    }

    /** « monte », « descend » (1 = niveau le plus haut), null pour un maintien ou un niveau inconnu */
    private static function sens(?int $depart, ?int $arrivee): ?string
    {
        return match (true) {
            $depart === null || $arrivee === null || $depart === $arrivee => null,
            $arrivee < $depart => 'monte',
            default => 'descend',
        };
    }

    /** Effectif commun à toutes les poules de la phase (équipes placées, sinon prévues), null s'il diffère ou manque */
    private static function effectifCommun(Phase $phase): ?int
    {
        $effectifs = array_unique(array_map(fn (Poule $p) => Texte::effectif($p), $phase->getPoules()->toArray()));

        return \count($effectifs) === 1 && ($n = reset($effectifs)) !== null ? $n : null;
    }

    /** « la phase 2 », « la phase finale », sinon « « Brassage » » */
    private static function laPhase(Phase $phase): string
    {
        $nom = trim((string) $phase->getNom());

        return preg_match('/^phase\b/iu', $nom) ? 'la '.mb_strtolower($nom) : '« '.$nom.' »';
    }

    private static function place(RegleDePassage $regle): ?Place
    {
        try {
            return NotationPlace::analyser($regle->getSource());
        } catch (NotationInvalide) {
            return null;
        }
    }

    private static function majuscule(string $texte): string
    {
        return mb_strtoupper(mb_substr($texte, 0, 1)).mb_substr($texte, 1);
    }
}
