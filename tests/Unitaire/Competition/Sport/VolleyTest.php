<?php

namespace App\Tests\Unitaire\Competition\Sport;

use App\Competition\Sport\Bareme;
use App\Competition\Sport\Volley;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Règles du volley-ball (App\Competition\Sport\Volley) : match complet, match interrompu (règle 1R), forfait.
 * Les points par score sont aussi vérifiés au travers du classement (tests/Unitaire/Service/ClassementServicePointsTest).
 */
final class VolleyTest extends TestCase
{
    private Volley $volley;

    protected function setUp(): void
    {
        $this->volley = new Volley();
    }

    public static function scoresValides(): iterable
    {
        foreach ([[3, 0], [3, 1], [3, 2], [0, 3], [1, 3], [2, 3]] as $s) {
            yield 'complet '.implode('-', $s) => $s;
        }
        foreach ([[2, 1], [1, 2], [2, 2], [1, 1], [1, 0], [0, 1]] as $s) {
            yield 'interrompu '.implode('-', $s) => $s;
        }
        yield 'forfait de l\'équipe qui reçoit' => [-1, 3];
        yield 'forfait de l\'équipe qui se déplace' => [3, -1];
    }

    #[DataProvider('scoresValides')]
    #[TestDox('Score accepté : $_dataName')]
    public function test_score_valide(int $reception, int $deplacement): void
    {
        self::assertTrue($this->volley->estValide($reception, $deplacement));
    }

    public static function scoresInvalides(): iterable
    {
        foreach ([[3, 3], [0, 0], [2, 0], [0, 2], [4, 0], [3, 4], [-1, -1], [-1, 2], [3, -2]] as $s) {
            yield implode('-', $s) => $s;
        }
    }

    #[DataProvider('scoresInvalides')]
    #[TestDox('Score refusé : $_dataName')]
    public function test_score_invalide(int $reception, int $deplacement): void
    {
        self::assertFalse($this->volley->estValide($reception, $deplacement));
    }

    #[TestDox('Un match complet n\'est pas un match interrompu, et inversement')]
    public function test_categories(): void
    {
        self::assertTrue(Volley::estComplet(3, 2));
        self::assertFalse(Volley::estInterrompu(3, 2));
        self::assertTrue(Volley::estInterrompu(2, 2));
        self::assertFalse(Volley::estComplet(2, 2));
    }

    #[TestDox('Forfait : -1 set pour l\'équipe forfait, 3 pour l\'autre ; score accepté')]
    public function test_score_forfait(): void
    {
        self::assertSame([-1, 3], $this->volley->scoreForfait(true));
        self::assertSame([3, -1], $this->volley->scoreForfait(false));
        self::assertTrue($this->volley->estValide(...$this->volley->scoreForfait(true)));
    }

    #[TestDox('Match interrompu : points fixés par le règlement, quel que soit le barème')]
    public function test_points_interrompu(): void
    {
        $bareme = new Bareme(9, 8, 7, 6, 5, -9);
        foreach (['2-1' => 2, '1-2' => 1, '2-2' => 1, '1-1' => 0, '1-0' => 0, '0-1' => 0] as $score => $points) {
            [$pour, $contre] = array_map('intval', explode('-', $score));
            self::assertSame($points, $this->volley->points($pour, $contre, $bareme), $score);
        }
    }

    public static function statistiques(): iterable
    {
        yield '3-1' => [3, 1, [3, 1]];
        yield '2-3' => [2, 3, [2, 3]];
        yield 'interrompu 2-1 : sets comptés' => [2, 1, [2, 1]];
        yield 'interrompu 2-2 : set-average inchangé' => [2, 2, [0, 0]];
        yield 'victoire par forfait : 3 sets gagnés, aucun perdu' => [3, -1, [3, 0]];
        yield 'forfait : aucun set gagné, 3 perdus' => [-1, 3, [0, 3]];
        yield 'non joué' => [null, null, [0, 0]];
    }

    #[DataProvider('statistiques')]
    #[TestDox('Sets gagnés et perdus comptés pour le départage : $_dataName')]
    public function test_statistiques(?int $pour, ?int $contre, array $attendu): void
    {
        self::assertSame($attendu, $this->volley->statistiques($pour, $contre));
    }
}
