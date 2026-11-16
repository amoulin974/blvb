<?php

namespace App\Tests\Unitaire\Competition\Format;

use App\Competition\Format\Elimination;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** ELI-01 : placement standard des têtes de série d'un tableau (App\Competition\Format\Elimination::placementStandard). */
final class EliminationPlacementTest extends TestCase
{
    #[TestDox('Placement standard : 2 → 1-2, 4 → 1-4 et 2-3, 8 → 1-8, 4-5, 2-7, 3-6, 16 : le 1er et le 2e dans des moitiés opposées')]
    public function test_placement_standard(): void
    {
        self::assertSame([1, 2], Elimination::placementStandard(2));
        self::assertSame([1, 4, 2, 3], Elimination::placementStandard(4));
        self::assertSame([1, 8, 4, 5, 2, 7, 3, 6], Elimination::placementStandard(8));

        $seize = Elimination::placementStandard(16);
        self::assertSame([1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11], $seize);
        // chaque rencontre du premier tour réunit deux têtes de série dont la somme vaut taille + 1
        foreach (array_chunk($seize, 2) as [$a, $b]) {
            self::assertSame(17, $a + $b);
        }
        // le 1er en haut, le 2e en bas : ils ne se rencontrent qu'en finale
        self::assertLessThan(8, array_search(1, $seize, true));
        self::assertGreaterThanOrEqual(8, array_search(2, $seize, true));
    }
}
