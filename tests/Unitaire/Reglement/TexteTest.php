<?php

namespace App\Tests\Unitaire\Reglement;

use App\Competition\NotationPlace;
use App\Reglement\Texte;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** RGL-10 : rangs d'une règle de passage en mots publics (App\Reglement\Texte, relecture de l'ergonome, D58). */
final class TexteTest extends TestCase
{
    public static function rangs(): array
    {
        return [
            'un rang' => ['P1.A#1', null, '1er', false],
            'deux rangs' => ['P1.A#1..2', null, '1er et 2e', true],
            'plage' => ['P1.A#3..6', null, 'du 3e au 6e', true],
            'dernier' => ['P1.A#-1', null, 'dernier', false],
            'avant-dernier' => ['P1.A#-2', null, 'avant-dernier', false],
            'les 2 derniers' => ['P1.A#-2..-1', null, 'les 2 derniers', true],
            'tous sauf les 2 derniers' => ['P1.A#1..-3', null, 'tous sauf les 2 derniers', true],
            'à partir du 3e' => ['P1.A#3..-3', null, 'à partir du 3e, sauf les 2 derniers', true],
            'toute la poule' => ['P1.A#1..-1', null, 'toutes les équipes', true],
            'du 3e au dernier' => ['P1.A#3..-1', null, 'du 3e au dernier', true],
            'effectif connu : plage en chiffres' => ['P1.A#2..-3', 8, 'du 2e au 6e', true],
            'effectif connu : avant-dernier' => ['P1.A#-2', 8, '7e', false],
            'effectif connu : les 2 derniers' => ['P1.A#-2..-1', 9, '8e et 9e', true],
            'effectif connu : toute la poule' => ['P1.A#1..-1', 8, 'toutes les équipes', true],
            'effectif trop petit : sans effectif' => ['P1.A#-3', 2, '3e en partant de la fin', false],
        ];
    }

    #[DataProvider('rangs')]
    #[TestDox('RGL-10 Rangs en mots publics, jamais « antépénultième » ni notation : $notation ($nb équipes) → « $attendu »')]
    public function test_rangs(string $notation, ?int $nb, string $attendu, bool $pluriel): void
    {
        self::assertSame(['texte' => $attendu, 'pluriel' => $pluriel], Texte::rangs(NotationPlace::analyser($notation), $nb));
    }

    #[TestDox('RGL-10 Énumération à la française, heure et période en clair')]
    public function test_mots(): void
    {
        self::assertSame('A, B et C', Texte::enumeration(['A', 'B', 'C']));
        self::assertSame('A', Texte::enumeration(['A']));
        self::assertSame('19 h', Texte::heure('19:00'));
        self::assertSame('18 h 30', Texte::heure('18:30'));
        self::assertSame('du 01/09/2025 au 11/01/2026', Texte::periode(new \DateTimeImmutable('2025-09-01'), new \DateTimeImmutable('2026-01-11')));
        self::assertSame('vendredi 22 mai 2026', Texte::date(new \DateTimeImmutable('2026-05-22'), 'EEEE d MMMM yyyy'));
    }
}
