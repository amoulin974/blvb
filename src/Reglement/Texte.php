<?php

namespace App\Reglement;

use App\Competition\Place;
use App\Competition\PlageVide;
use App\Competition\NotationInvalide;
use App\Entity\Poule;

/**
 * Mots du règlement public (relecture de l'ergonome, docs/wizard-saison/reglement-maquettes.md) : rangs en chiffres
 * quand l'effectif est connu, jamais « antépénultième » ni notation (« P2.A#1..-3 »), énumérations à la française.
 */
final class Texte
{
    /** 1 → « 1er », 3 → « 3e » */
    public static function ordinal(int $rang): string
    {
        return $rang === 1 ? '1er' : $rang.'e';
    }

    /**
     * Les rangs d'une place, en mots publics, et s'ils désignent plusieurs équipes : « 1er et 2e », « du 3e au 6e »,
     * « les 2 derniers », « tous sauf le dernier », « à partir du 3e, sauf les 2 derniers ».
     *
     * @param int|null $nbEquipes effectif de la poule, s'il est connu
     * @return array{texte: string, pluriel: bool}
     */
    public static function rangs(Place $place, ?int $nbEquipes): array
    {
        if ($nbEquipes !== null && $nbEquipes > 0) {
            try {
                $rangs = $place->rangsPour($nbEquipes);
                [$debut, $fin] = [$rangs[0], $rangs[\count($rangs) - 1]];

                return match (true) {
                    $debut === $fin => ['texte' => self::ordinal($debut), 'pluriel' => false],
                    $debut === 1 && $fin === $nbEquipes => ['texte' => 'toutes les équipes', 'pluriel' => true],
                    $fin === $debut + 1 => ['texte' => self::ordinal($debut).' et '.self::ordinal($fin), 'pluriel' => true],
                    default => ['texte' => sprintf('du %s au %s', self::ordinal($debut), self::ordinal($fin)), 'pluriel' => true],
                };
            } catch (NotationInvalide|PlageVide) {
                // effectif incompatible : décrit sans l'effectif
            }
        }

        [$debut, $fin] = [$place->rangDebut, $place->rangFin];
        if ($debut > 0 && $fin > 0) {
            return match (true) {
                $debut === $fin => ['texte' => self::ordinal($debut), 'pluriel' => false],
                $fin === $debut + 1 => ['texte' => self::ordinal($debut).' et '.self::ordinal($fin), 'pluriel' => true],
                default => ['texte' => sprintf('du %s au %s', self::ordinal($debut), self::ordinal($fin)), 'pluriel' => true],
            };
        }
        if ($debut < 0 && $fin < 0) {
            return match (true) {
                $debut === -1 && $fin === -1 => ['texte' => 'dernier', 'pluriel' => false],
                $debut === -2 && $fin === -2 => ['texte' => 'avant-dernier', 'pluriel' => false],
                $debut === $fin => ['texte' => sprintf('%s en partant de la fin', self::ordinal(-$debut)), 'pluriel' => false],
                $fin === -1 => ['texte' => sprintf('les %d derniers', -$debut), 'pluriel' => true],
                default => ['texte' => sprintf('du %s au %s en partant de la fin', self::ordinal(-$debut), self::ordinal(-$fin)), 'pluriel' => true],
            };
        }
        // Du début vers la fin : $debut > 0, $fin < 0
        $exclus = -$fin - 1;
        $sauf = match ($exclus) {
            0 => '',
            1 => 'sauf le dernier',
            default => sprintf('sauf les %d derniers', $exclus),
        };
        if ($debut === 1) {
            return ['texte' => $sauf === '' ? 'toutes les équipes' : 'tous '.$sauf, 'pluriel' => true];
        }

        return ['texte' => $sauf === '' ? sprintf('du %s au dernier', self::ordinal($debut)) : sprintf('à partir du %s, %s', self::ordinal($debut), $sauf), 'pluriel' => true];
    }

    /**
     * Rangs en sujet de phrase, avec l'article : « 1er » → « le 1er », « avant-dernier » → « l'avant-dernier »,
     * « 1er et 2e » → « les 1er et 2e », « à partir du 3e, sauf les 2 derniers » → « à partir du 3e, tous sauf les
     * 2 derniers » (rangs au masculin, relecture de l'ergonome) ; « du 3e au 6e », « tous sauf … », « toutes les équipes » restent tels quels
     */
    public static function sujet(string $rangs): string
    {
        return match (true) {
            str_starts_with($rangs, 'à partir') => str_replace(', sauf', ', tous sauf', $rangs),
            preg_match('/^(du |tous |toutes |les )/u', $rangs) === 1 => $rangs,
            str_contains($rangs, ' et ') => 'les '.$rangs,
            preg_match('/^[aeiouéè]/u', $rangs) === 1 => 'l\''.$rangs,
            default => 'le '.$rangs,
        };
    }

    /** « poule A », « tableau 1 », « barrage A / B », sinon le nom entre guillemets (« « Élite des as » ») */
    public static function poule(Poule $poule): string
    {
        $nom = trim((string) $poule->getNom());
        if (preg_match('/^(poule|tableau|barrage)\b/iu', $nom)) {
            return mb_strtolower(mb_substr($nom, 0, 1)).mb_substr($nom, 1);
        }

        return '« '.$nom.' »';
    }

    /** « en poule A », « dans le tableau 1 », « dans « Élite des as » » */
    public static function dansPoule(Poule $poule): string
    {
        $nom = self::poule($poule);

        return match (true) {
            str_starts_with($nom, 'poule') => 'en '.$nom,
            str_starts_with($nom, 'tableau'), str_starts_with($nom, 'barrage') => 'dans le '.$nom,
            default => 'dans '.$nom,
        };
    }

    /** « de la poule A », « du tableau 1 », « de « Élite des as » » */
    public static function dePoule(Poule $poule): string
    {
        $nom = self::poule($poule);

        return match (true) {
            str_starts_with($nom, 'poule') => 'de la '.$nom,
            str_starts_with($nom, 'tableau'), str_starts_with($nom, 'barrage') => 'du '.$nom,
            default => 'de '.$nom,
        };
    }

    /** ['A', 'B', 'C'] → « A, B et C » */
    public static function enumeration(array $elements): string
    {
        $elements = array_values($elements);
        if (\count($elements) <= 1) {
            return $elements[0] ?? '';
        }
        $dernier = array_pop($elements);

        return implode(', ', $elements).' et '.$dernier;
    }

    /** Date en français selon un motif ICU : « dd/MM/yyyy », « EEEE d MMMM yyyy » (vendredi 22 mai 2026) */
    public static function date(\DateTimeInterface $date, string $motif = 'dd/MM/yyyy'): string
    {
        return (string) (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, $motif))->format($date);
    }

    /** « du 01/09/2025 au 11/01/2026 » */
    public static function periode(?\DateTimeInterface $debut, ?\DateTimeInterface $fin): string
    {
        return $debut !== null && $fin !== null ? sprintf('du %s au %s', self::date($debut), self::date($fin)) : '';
    }

    /** « 19:00 » → « 19 h », « 18:30 » → « 18 h 30 » */
    public static function heure(string $heure): string
    {
        [$h, $m] = array_map('intval', explode(':', $heure) + [1 => 0]);

        return $m === 0 ? sprintf('%d h', $h) : sprintf('%d h %02d', $h, $m);
    }

    /** Effectif d'une poule : équipes placées, sinon nombre prévu ; null s'il n'est pas connu */
    public static function effectif(Poule $poule): ?int
    {
        $n = $poule->getEquipes()->count();

        return $n > 0 ? $n : $poule->getNbEquipesPrevu();
    }
}
