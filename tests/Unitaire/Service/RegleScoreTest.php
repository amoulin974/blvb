<?php

namespace App\Tests\Unitaire\Service;

use App\Service\RegleScore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Règles de score du règlement (App\Service\RegleScore) : match complet, match interrompu (règle 1R), forfait. */
final class RegleScoreTest extends TestCase
{
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
        self::assertTrue(RegleScore::estValide($reception, $deplacement));
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
        self::assertFalse(RegleScore::estValide($reception, $deplacement));
    }

    #[TestDox('Un match complet n\'est pas un match interrompu, et inversement')]
    public function test_categories(): void
    {
        self::assertTrue(RegleScore::estComplet(3, 2));
        self::assertFalse(RegleScore::estInterrompu(3, 2));
        self::assertTrue(RegleScore::estInterrompu(2, 2));
        self::assertFalse(RegleScore::estComplet(2, 2));
        self::assertNull(RegleScore::pointsInterrompu(3, 1), 'match complet : barème de la saison');
    }

    #[TestDox('Un match interrompu à 2-2 ne compte pas dans le set-average ; les autres scores comptent')]
    public function test_sets_comptes(): void
    {
        self::assertFalse(RegleScore::setsComptes(2, 2));
        foreach ([[2, 1], [1, 1], [3, 2], [-1, 3], [null, null]] as [$r, $d]) {
            self::assertTrue(RegleScore::setsComptes($r, $d));
        }
    }
}
