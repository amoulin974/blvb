<?php

namespace App\Competition;

use App\Entity\Saison;
use App\Enum\PhaseType;

/**
 * Vérification d'une saison avant sa validation dans le wizard (étape 6, D5, D29, D30) : les erreurs bloquent la
 * validation, les avertissements non. Chaque problème indique l'étape où le corriger.
 * Fournit aussi le schéma de la saison par niveaux pour le récapitulatif.
 */
final class VerificationSaison
{
    /** @return array{erreurs: list<array{texte: string, etape: int}>, avertissements: list<array{texte: string, etape: int}>} */
    public static function verifier(Saison $saison): array
    {
        $erreurs = [];
        $avertissements = [];
        $phases = EtapePassages::phasesDansLOrdre($saison);
        $format = fn (\DateTimeInterface $d) => $d->format('d/m/Y');
        $jour = fn (\DateTimeInterface $d) => $d->format('Y-m-d'); // comparaisons au jour près, sans l'heure

        if ($phases === []) {
            $erreurs[] = ['texte' => 'Ajoutez au moins une phase.', 'etape' => 3];
        }
        $sansPrevu = 0;
        foreach ($phases as $i => $phase) {
            if ($phase->getPoules()->isEmpty()) {
                $erreurs[] = ['texte' => sprintf('%s : ajoutez au moins une poule.', $phase->getNom()), 'etape' => 3];
            }
            foreach ($phase->getPoules() as $poule) {
                $sansPrevu += $poule->getNbEquipesPrevu() === null ? 1 : 0;
            }
            if ($jour($phase->getDateFin()) <= $jour($phase->getDateDebut())) {
                $erreurs[] = ['texte' => sprintf('%s : la date de fin doit être après la date de début.', $phase->getNom()), 'etape' => 3];
            } elseif ($jour($phase->getDateDebut()) < $jour($saison->getDateDebut()) || $jour($phase->getDateFin()) > $jour($saison->getDateFin())) {
                $erreurs[] = ['texte' => sprintf('%s : les dates doivent être comprises dans la saison (du %s au %s).', $phase->getNom(), $format($saison->getDateDebut()), $format($saison->getDateFin())), 'etape' => 3];
            }
            $suivante = $phases[$i + 1] ?? null;
            if ($suivante !== null && $jour($suivante->getDateDebut()) < $jour($phase->getDateDebut())) {
                // L'ordre des phases décide des passages : il doit suivre les dates (phases en parallèle : même début)
                $erreurs[] = ['texte' => sprintf('%s est placée avant %s mais commence plus tard (%s contre %s) : changez l\'ordre des phases ou leurs dates.', $phase->getNom(), $suivante->getNom(), $format($phase->getDateDebut()), $format($suivante->getDateDebut())), 'etape' => 3];
            } elseif ($suivante !== null && $jour($phase->getDateFin()) > $jour($suivante->getDateDebut())) {
                $avertissements[] = ['texte' => sprintf('%s et %s se chevauchent.', $phase->getNom(), $suivante->getNom()), 'etape' => 3];
            }

            if ($suivante === null) {
                continue;
            }
            $versSuivante = array_filter($phase->getReglesDePassage()->toArray(), fn ($r) => $r->getPouleDestination()?->getPhase() === $suivante);
            if ($versSuivante === []) {
                $erreurs[] = ['texte' => sprintf('Aucun passage de « %s » vers « %s ».',$phase->getNom(), $suivante->getNom()), 'etape' => 4];
            }
            $tailles = [];
            foreach ($phase->getPoules() as $poule) {
                $tailles[$poule->getCode()] = EtapePassages::taille($poule);
            }
            foreach (VerificationPassages::verifier($phase, $phase->getReglesDePassage(), $tailles) as $probleme) {
                // « Poule A : … » → « Poule A (Phase 1) : … » : plusieurs phases ont souvent une Poule A
                $texte = str_starts_with($probleme, 'Règle ')
                    ? sprintf('%s : %s', $phase->getNom(), $probleme)
                    : preg_replace('/^(.+?) : /', '$1 ('.addcslashes($phase->getNom(), '\\$').') : ', $probleme, 1);
                $erreurs[] = ['texte' => $texte, 'etape' => 4];
            }
            foreach (EtapePassages::effectifs($phase, $suivante) as $e) {
                if ($e['prevues'] !== null && $e['recues'] !== null && $e['recues'] !== $e['prevues']) {
                    $avertissements[] = ['texte' => sprintf('%s (%s) : %d équipe%s reçue%s sur %d prévue%s.', $e['poule']->getNom(), $suivante->getNom(),
                        $e['recues'], $e['recues'] > 1 ? 's' : '', $e['recues'] > 1 ? 's' : '', $e['prevues'], $e['prevues'] > 1 ? 's' : ''), 'etape' => 4];
                }
            }
        }
        if ($sansPrevu > 0) {
            $avertissements[] = ['texte' => sprintf('Nombre d\'équipes prévu non indiqué pour %d poule%s : leurs passages ne sont pas contrôlés en détail.', $sansPrevu, $sansPrevu > 1 ? 's' : ''), 'etape' => 3];
        }

        return ['erreurs' => $erreurs, 'avertissements' => $avertissements];
    }

    /**
     * Schéma de la saison par niveaux : une colonne par phase (puis « Saison suivante » si des montées et descentes y
     * sont prévues), une ligne par niveau ; chaque case : poule, équipes prévues, montées (▲) et descentes (▼) en mode
     * simple.
     *
     * @return array{colonnes: list<string>, lignes: array<int, list<?array{nom: string, code: string, prevues: ?int, recues: ?int, montee: int, descente: int}>>}
     */
    public static function schema(Saison $saison): array
    {
        $phases = EtapePassages::phasesDansLOrdre($saison);
        $colonnes = array_map(fn ($p) => (string) $p->getNom(), $phases);
        $lignes = [];
        $saisonSuivante = [];
        // Équipes que chaque poule recevra d'après les passages, pour signaler l'écart avec le nombre prévu
        $recues = [];
        foreach ($phases as $i => $phase) {
            if (isset($phases[$i + 1])) {
                foreach (EtapePassages::effectifs($phase, $phases[$i + 1]) as $e) {
                    $recues[spl_object_id($e['poule'])] = $e['recues'];
                }
            }
        }
        foreach ($phases as $i => $phase) {
            $suivante = $phases[$i + 1] ?? null;
            $versFinale = $suivante?->getType() === PhaseType::FINALE;
            foreach (EtapePassages::poules($phase) as $poule) {
                $simple = $suivante ? PassagesSimples::lire($phase->getCode().'.'.$poule->getCode().'#', EtapePassages::reglesDe($phase, $poule), $versFinale) : null;
                $niveau = (int) $poule->getNiveau();
                $lignes[$niveau][$i] = [
                    'nom' => $poule->getNom(), 'code' => (string) $poule->getCode(), 'prevues' => $poule->getNbEquipesPrevu(),
                    'recues' => $recues[spl_object_id($poule)] ?? null,
                    'montee' => $simple && !$versFinale ? $simple['montee'] : 0, 'descente' => $simple && !$versFinale ? $simple['descente'] : 0,
                ];
                if ($simple && $versFinale && $simple['saisonSuivante']) {
                    $saisonSuivante[$niveau] = ['nom' => 'Niveau '.$niveau, 'code' => '', 'prevues' => null, 'recues' => null, 'montee' => $simple['montee'], 'descente' => $simple['descente']];
                }
            }
        }
        if ($saisonSuivante !== []) {
            $colonnes[] = 'Saison suivante';
            foreach ($saisonSuivante as $niveau => $case) {
                $lignes[$niveau][\count($phases)] = $case;
            }
        }
        ksort($lignes);
        foreach ($lignes as $niveau => $cases) {
            $lignes[$niveau] = array_map(fn ($c) => $cases[$c] ?? null, array_keys($colonnes));
        }

        return ['colonnes' => $colonnes, 'lignes' => $lignes];
    }
}
