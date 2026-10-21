<?php

namespace App\Competition;

/**
 * Lecture de la notation des places (docs/formats-competition.md, partie G) :
 *
 *   PHASE.POULE#RANG            P1.A#2, P1.A#-1
 *   PHASE.POULE#RANG..RANG      P1.A#1..-3
 *   PHASE.MATCH.vainqueur       BAR1.AB.vainqueur
 *   PHASE.MATCH.perdant         BAR1.AB.perdant
 *
 * Les codes (phase, poule, match) sont faits de lettres, de chiffres et de « _ ». Un rang négatif est compté
 * depuis la fin (-1 = dernier). La notation est enregistrée telle quelle en base ; l'administrateur ne la tape pas,
 * le wizard la construit.
 */
final class NotationPlace
{
    private const CODE = '[A-Za-z0-9_]+';
    private const RANG = '-?[1-9][0-9]*';

    /** @throws NotationInvalide */
    public static function analyser(string $notation): Place
    {
        $notation = trim($notation);

        $rang = '/^(?<phase>'.self::CODE.')\.(?<poule>'.self::CODE.')#(?<debut>'.self::RANG.')(?:\.\.(?<fin>'.self::RANG.'))?$/';
        if (preg_match($rang, $notation, $m)) {
            return Place::rang($m['phase'], $m['poule'], (int) $m['debut'], isset($m['fin']) ? (int) $m['fin'] : null);
        }

        $issue = '/^(?<phase>'.self::CODE.')\.(?<match>'.self::CODE.')\.(?<issue>'.Place::VAINQUEUR.'|'.Place::PERDANT.')$/';
        if (preg_match($issue, $notation, $m)) {
            return Place::issueDeMatch($m['phase'], $m['match'], $m['issue']);
        }

        throw new NotationInvalide(sprintf(
            'Notation de place invalide « %s ». Formes attendues : P1.A#2, P1.A#-1, P1.A#1..-3, BAR1.AB.vainqueur, BAR1.AB.perdant.',
            $notation,
        ));
    }

    public static function estValide(string $notation): bool
    {
        try {
            self::analyser($notation);

            return true;
        } catch (NotationInvalide) {
            return false;
        }
    }
}
