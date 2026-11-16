<?php

namespace App\Tests\Unitaire\Competition\Sport;

use App\Competition\Sport\Bareme;
use App\Competition\Sport\Volley;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * SET-01 : règles du volley en format court, 2 sets gagnants (D48). Le format à 3 sets est couvert par VolleyTest.
 */
final class VolleyDeuxSetsTest extends TestCase
{
    private Volley $volley;

    protected function setUp(): void
    {
        $this->volley = new Volley();
    }

    public static function scoresValides(): iterable
    {
        foreach ([[2, 0], [2, 1], [0, 2], [1, 2]] as $s) {
            yield 'complet '.implode('-', $s) => $s;
        }
        foreach ([[1, 1], [1, 0], [0, 1]] as $s) {
            yield 'interrompu '.implode('-', $s) => $s;
        }
        yield 'forfait de l\'équipe qui reçoit' => [-1, 2];
        yield 'forfait de l\'équipe qui se déplace' => [2, -1];
    }

    #[DataProvider('scoresValides')]
    #[TestDox('SET-01 Score accepté en 2 sets gagnants : $_dataName')]
    public function test_score_valide(int $reception, int $deplacement): void
    {
        self::assertTrue($this->volley->estValide($reception, $deplacement, 2));
    }

    public static function scoresInvalides(): iterable
    {
        foreach ([[3, 0], [3, 1], [3, 2], [2, 2], [0, 0], [2, 3], [-1, 3], [3, -1], [-1, 0], [-1, -1]] as $s) {
            yield implode('-', $s) => $s;
        }
    }

    #[DataProvider('scoresInvalides')]
    #[TestDox('SET-01 Score refusé en 2 sets gagnants : $_dataName')]
    public function test_score_invalide(int $reception, int $deplacement): void
    {
        self::assertFalse($this->volley->estValide($reception, $deplacement, 2));
    }

    #[TestDox('SET-01 Un score valide dans un format ne l\'est pas forcément dans l\'autre')]
    public function test_les_deux_formats_sont_distincts(): void
    {
        self::assertTrue(Volley::estComplet(2, 1, 2), '2-1 est un match complet en 2 sets');
        self::assertFalse(Volley::estComplet(2, 1, 3), '2-1 n\'est pas complet en 3 sets');
        self::assertTrue(Volley::estInterrompu(2, 1, 3), '2-1 est un match interrompu en 3 sets');
        self::assertFalse(Volley::estInterrompu(2, 1, 2), '2-1 n\'est pas un match interrompu en 2 sets');
        self::assertFalse($this->volley->estValide(2, 0, 3), '2-0 est refusé en 3 sets');
        self::assertFalse($this->volley->estValide(3, 0, 2), '3-0 est refusé en 2 sets');
        self::assertFalse($this->volley->estValide(2, 2, 2), '2-2 (interrompu en 3 sets) est refusé en 2 sets');
    }

    #[TestDox('SET-01 Forfait en 2 sets : -1 pour l\'équipe forfait, 2 pour l\'autre ; score accepté')]
    public function test_score_forfait(): void
    {
        self::assertSame([-1, 2], $this->volley->scoreForfait(true, 2));
        self::assertSame([2, -1], $this->volley->scoreForfait(false, 2));
        self::assertTrue($this->volley->estValide(...$this->volley->scoreForfait(true, 2), setsGagnants: 2));
        self::assertSame([-1, 3], $this->volley->scoreForfait(true), 'le format par défaut reste à 3 sets');
    }

    #[TestDox('SET-01 Message d\'erreur adapté au format')]
    public function test_message(): void
    {
        self::assertStringContainsString('2 sets', $this->volley->messageScoreInvalide(2));
        self::assertStringContainsString('3 sets', $this->volley->messageScoreInvalide(3));
        self::assertStringNotContainsString('2-2', $this->volley->messageScoreInvalide(2));
    }

    public static function points(): iterable
    {
        yield '2-0 victoire forte' => [2, 0, 5];
        yield '2-1 victoire faible' => [2, 1, 4];
        yield '1-2 défaite forte' => [1, 2, 2];
        yield '0-2 défaite faible' => [0, 2, 1];
        yield 'victoire par forfait' => [2, -1, 5];
        yield 'équipe forfait' => [-1, 2, -3];
        yield 'interrompu 1-1' => [1, 1, 0];
        yield 'interrompu 1-0' => [1, 0, 0];
        yield 'interrompu 0-1' => [0, 1, 0];
        yield 'non joué' => [null, null, 0];
    }

    #[DataProvider('points')]
    #[TestDox('SET-01 Points au barème 5/4/2/1/-3 en 2 sets gagnants : $_dataName')]
    public function test_points(?int $pour, ?int $contre, int $attendu): void
    {
        // Barème de test aux valeurs toutes différentes : une inversion de catégorie se voit
        $bareme = new Bareme(5, 4, 3, 2, 1, -3);
        self::assertSame($attendu, $this->volley->points($pour, $contre, $bareme, 2));
    }

    public static function statistiques(): iterable
    {
        yield '2-0' => [2, 0, [2, 0]];
        yield '1-2' => [1, 2, [1, 2]];
        yield 'interrompu 1-1 : sets comptés' => [1, 1, [1, 1]];
        yield 'victoire par forfait' => [2, -1, [2, 0]];
        yield 'non joué' => [null, null, [0, 0]];
    }

    #[DataProvider('statistiques')]
    #[TestDox('SET-01 Sets gagnés et perdus en 2 sets gagnants : $_dataName')]
    public function test_statistiques(?int $pour, ?int $contre, array $attendu): void
    {
        self::assertSame($attendu, $this->volley->statistiques($pour, $contre, 2));
    }
}
