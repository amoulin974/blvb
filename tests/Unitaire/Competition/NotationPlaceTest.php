<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\NotationInvalide;
use App\Competition\NotationPlace;
use App\Competition\Place;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Notation des places des règles de passage (App\Competition\NotationPlace, docs/formats-competition.md partie G). */
final class NotationPlaceTest extends TestCase
{
    #[TestDox('Un rang dans une poule : phase, poule et rang sont lus')]
    public function test_rang(): void
    {
        $place = NotationPlace::analyser('P1.A#2');

        self::assertTrue($place->estRang());
        self::assertFalse($place->estPlage());
        self::assertSame(['P1', 'A', 2, 2], [$place->phase, $place->poule, $place->rangDebut, $place->rangFin]);
    }

    #[TestDox('Une plage de rangs, avec une fin comptée depuis le dernier')]
    public function test_plage(): void
    {
        $place = NotationPlace::analyser('P1.A#1..-3');

        self::assertTrue($place->estPlage());
        self::assertSame([1, -3], [$place->rangDebut, $place->rangFin]);
    }

    #[TestDox('Le vainqueur et le perdant d\'un match')]
    public function test_issue_de_match(): void
    {
        $vainqueur = NotationPlace::analyser('BAR1.AB.vainqueur');
        $perdant = NotationPlace::analyser('BAR1.AB.perdant');

        self::assertFalse($vainqueur->estRang());
        self::assertSame(['BAR1', 'AB', Place::VAINQUEUR], [$vainqueur->phase, $vainqueur->match, $vainqueur->issue]);
        self::assertSame(Place::PERDANT, $perdant->issue);
    }

    public static function notationsCanoniques(): iterable
    {
        foreach (['P1.A#2', 'P1.A#-1', 'P1.A#1..-3', 'P2.Poule_1#3..6', 'BAR1.AB.vainqueur', 'F.QF1.perdant'] as $n) {
            yield $n => [$n];
        }
    }

    #[DataProvider('notationsCanoniques')]
    #[TestDox('Relue puis réécrite à l\'identique : $_dataName')]
    public function test_aller_retour(string $notation): void
    {
        self::assertSame($notation, (string) NotationPlace::analyser($notation));
    }

    #[TestDox('Les espaces autour de la notation sont ignorés ; une plage d\'un seul rang s\'écrit comme un rang')]
    public function test_normalisation(): void
    {
        self::assertSame('P1.A#2', (string) NotationPlace::analyser('  P1.A#2 '));
        self::assertSame('P1.A#2', (string) NotationPlace::analyser('P1.A#2..2'));
    }

    public static function notationsInvalides(): iterable
    {
        yield 'vide' => [''];
        yield 'sans rang' => ['P1.A'];
        yield 'sans phase' => ['A#2'];
        yield 'rang 0' => ['P1.A#0'];
        yield 'fin de plage 0' => ['P1.A#1..0'];
        yield 'rang avec zéro initial' => ['P1.A#02'];
        yield 'plage incomplète' => ['P1.A#1..'];
        yield 'issue inconnue' => ['BAR1.AB.gagnant'];
        yield 'issue en majuscules' => ['BAR1.AB.Vainqueur'];
        yield 'espace dans un code' => ['P1.Poule A#1'];
        yield 'rang et issue mélangés' => ['P1.A#1.vainqueur'];
    }

    #[DataProvider('notationsInvalides')]
    #[TestDox('Notation refusée : $_dataName')]
    public function test_notation_invalide(string $notation): void
    {
        self::assertFalse(NotationPlace::estValide($notation));
        $this->expectException(NotationInvalide::class);
        NotationPlace::analyser($notation);
    }

    public static function rangs(): iterable
    {
        yield '2e d\'une poule de 8' => ['P1.A#2', 8, [2]];
        yield 'dernier d\'une poule de 8' => ['P1.A#-1', 8, [8]];
        yield 'avant-dernier d\'une poule de 8' => ['P1.A#-2', 8, [7]];
        yield 'avant-dernier d\'une poule de 9' => ['P1.A#-2', 9, [8]];
        yield 'maintien d\'une poule de 8 (1er au 6e)' => ['P1.A#1..-3', 8, [1, 2, 3, 4, 5, 6]];
        yield 'maintien d\'une poule de 9 (1er au 7e)' => ['P1.A#1..-3', 9, [1, 2, 3, 4, 5, 6, 7]];
        yield 'toute la poule' => ['P2.A#1..-1', 4, [1, 2, 3, 4]];
        yield 'plage depuis la fin' => ['P1.A#-3..-2', 8, [6, 7]];
        yield 'poule d\'une seule équipe' => ['P1.A#-1', 1, [1]];
    }

    #[DataProvider('rangs')]
    #[TestDox('Rangs désignés : $_dataName')]
    public function test_rangs_pour(string $notation, int $nbEquipes, array $attendus): void
    {
        self::assertSame($attendus, NotationPlace::analyser($notation)->rangsPour($nbEquipes));
    }

    public static function rangsImpossibles(): iterable
    {
        yield '9e d\'une poule de 8' => ['P1.A#9', 8];
        yield '-9 dans une poule de 8' => ['P1.A#-9', 8];
        yield 'plage vide : 1er à l\'antépénultième d\'une poule de 2' => ['P1.A#1..-3', 2];
        yield 'plage à l\'envers' => ['P1.A#5..2', 8];
        yield 'poule vide' => ['P1.A#1', 0];
    }

    #[DataProvider('rangsImpossibles')]
    #[TestDox('Rang impossible dans la poule : $_dataName')]
    public function test_rang_hors_poule(string $notation, int $nbEquipes): void
    {
        $this->expectException(NotationInvalide::class);
        NotationPlace::analyser($notation)->rangsPour($nbEquipes);
    }

    #[TestDox('Une issue de match n\'a pas de rang')]
    public function test_issue_sans_rang(): void
    {
        $this->expectException(\LogicException::class);
        NotationPlace::analyser('BAR1.AB.vainqueur')->rangsPour(8);
    }
}
