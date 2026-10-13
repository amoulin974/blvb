<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;

/**
 * Codes des phases et des poules d'une saison, utilisés par la notation des places (P1.A#2) : phases P1, P2… dans
 * l'ordre, F pour une phase finale (F2, F3… s'il y en a plusieurs) ; poules : la lettre finale du nom (« Poule A » → A),
 * sinon la première lettre libre (A, B, C…), dans l'ordre des niveaux (docs/wizard-saison/decisions.md, D20).
 *
 * Seuls les codes manquants sont attribués : un code existant (saisi dans le wizard) n'est jamais changé.
 * Le calcul (calculer) travaille sur des tableaux, pour servir aussi à la migration des saisons existantes.
 */
final class CodesCompetition
{
    /** Attribue les codes manquants des phases et poules de la saison (les entités sont modifiées, sans flush). */
    public static function attribuer(Saison $saison): void
    {
        $phases = [];
        $entites = [];
        foreach ($saison->getPhases() as $i => $phase) {
            $poules = [];
            foreach ($phase->getPoules() as $j => $poule) {
                $poules[] = ['cle' => $j, 'code' => $poule->getCode(), 'nom' => $poule->getNom(), 'niveau' => $poule->getNiveau()];
                $entites["$i/$j"] = $poule;
            }
            $phases[] = ['cle' => $i, 'code' => $phase->getCode(), 'finale' => $phase->getType() === PhaseType::FINALE, 'ordre' => $phase->getOrdre(), 'poules' => $poules];
            $entites[$i] = $phase;
        }

        foreach (self::calculer($phases) as $phase) {
            $entites[$phase['cle']]->setCode($phase['code']);
            foreach ($phase['poules'] as $poule) {
                $entites[$phase['cle'].'/'.$poule['cle']]->setCode($poule['code']);
            }
        }
    }

    /**
     * @param list<array{cle: int|string, code: ?string, finale: bool, ordre: ?int, poules: list<array{cle: int|string, code: ?string, nom: ?string, niveau: ?int}>}> $phases
     * @return list<array{cle: int|string, code: string, poules: list<array{cle: int|string, code: string}>}> mêmes clés, codes renseignés
     */
    public static function calculer(array $phases): array
    {
        usort($phases, fn (array $a, array $b) => [$a['ordre'] ?? 0, $a['cle']] <=> [$b['ordre'] ?? 0, $b['cle']]);

        $pris = array_filter(array_column($phases, 'code'));
        $resultat = [];
        $championnat = $finale = 0;
        foreach ($phases as $phase) {
            if ($phase['finale']) {
                ++$finale;
            } else {
                ++$championnat;
            }
            $code = $phase['code'];
            if ($code === null || $code === '') {
                $code = $phase['finale'] ? 'F'.($finale > 1 ? $finale : '') : 'P'.$championnat;
                while (\in_array($code, $pris, true)) {
                    $code .= '_';
                }
                $pris[] = $code;
            }
            $resultat[] = ['cle' => $phase['cle'], 'code' => $code, 'poules' => self::codesPoules($phase['poules'])];
        }

        return $resultat;
    }

    /**
     * @param list<array{cle: int|string, code: ?string, nom: ?string, niveau: ?int}> $poules
     * @return list<array{cle: int|string, code: string}>
     */
    private static function codesPoules(array $poules): array
    {
        usort($poules, fn (array $a, array $b) => [$a['niveau'] ?? PHP_INT_MAX, $a['cle']] <=> [$b['niveau'] ?? PHP_INT_MAX, $b['cle']]);

        $pris = array_values(array_filter(array_column($poules, 'code')));
        // Lettre tirée du nom, si elle n'est pas déjà prise
        $codes = [];
        foreach ($poules as $poule) {
            $code = $poule['code'];
            if (($code === null || $code === '') && preg_match('/\b([A-Z])$/u', trim((string) $poule['nom']), $m) && !\in_array($m[1], $pris, true)) {
                $code = $m[1];
                $pris[] = $code;
            }
            $codes[$poule['cle']] = $code;
        }
        // Les autres : première lettre libre
        $lettre = 'A';
        foreach ($poules as $poule) {
            if ($codes[$poule['cle']] === null || $codes[$poule['cle']] === '') {
                while (\in_array($lettre, $pris, true)) {
                    ++$lettre;
                }
                $codes[$poule['cle']] = $lettre;
                $pris[] = $lettre;
            }
        }

        return array_map(fn (array $p) => ['cle' => $p['cle'], 'code' => $codes[$p['cle']]], $poules);
    }
}
