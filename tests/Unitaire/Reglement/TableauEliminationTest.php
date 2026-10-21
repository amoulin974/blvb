<?php

namespace App\Tests\Unitaire\Reglement;

use App\Reglement\Bloc\TableauElimination;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** RGL-11 : déroulé d'un tableau à élimination pour le règlement (App\Reglement\Bloc\TableauElimination, D58, B1, B2). */
final class TableauEliminationTest extends TestCase
{
    /** @return list<string> « 1er », « 2e »… */
    private static function places(int $n): array
    {
        return array_map(fn ($i) => $i === 1 ? '1er' : $i.'e', range(1, $n));
    }

    /** @return array<string, list<string>> tour → « Match : A contre B » */
    private static function lire(array $tableau): array
    {
        $lu = [];
        foreach ($tableau['tours'] as $tour) {
            $lu[$tour['nom']] = array_map(fn ($m) => $m['nom'].' : '.$m['equipes'][0].' contre '.$m['equipes'][1], $tour['matchs']);
        }

        return $lu;
    }

    #[TestDox('RGL-11 9 équipes : un barrage (8e contre 9e), les 7 premiers exemptés, quarts au placement standard, petite finale')]
    public function test_neuf_equipes(): void
    {
        $tableau = TableauElimination::construire(self::places(9), true);

        self::assertSame(7, $tableau['exemptes']);
        self::assertSame([
            'Barrage' => ['Barrage : 8e contre 9e'],
            'Quarts de finale' => [
                'Quart de finale 1 : 1er contre vainqueur du barrage',
                'Quart de finale 2 : 4e contre 5e',
                'Quart de finale 3 : 2e contre 7e',
                'Quart de finale 4 : 3e contre 6e',
            ],
            'Demi-finales' => [
                'Demi-finale 1 : vainqueur du quart de finale 1 contre vainqueur du quart de finale 2',
                'Demi-finale 2 : vainqueur du quart de finale 3 contre vainqueur du quart de finale 4',
            ],
            'Finale' => [
                'Finale : vainqueur de la demi-finale 1 contre vainqueur de la demi-finale 2',
                'Petite finale (3e place) : perdant de la demi-finale 1 contre perdant de la demi-finale 2',
            ],
        ], self::lire($tableau));
    }

    #[TestDox('RGL-11 4 équipes avec petite finale ; 2 équipes : une finale ; moins de 2 : aucun tour')]
    public function test_petits_tableaux(): void
    {
        self::assertSame([
            'Demi-finales' => ['Demi-finale 1 : 1er contre 4e', 'Demi-finale 2 : 2e contre 3e'],
            'Finale' => [
                'Finale : vainqueur de la demi-finale 1 contre vainqueur de la demi-finale 2',
                'Petite finale (3e place) : perdant de la demi-finale 1 contre perdant de la demi-finale 2',
            ],
        ], self::lire(TableauElimination::construire(self::places(4), true)));
        self::assertSame(['Finale' => ['Finale : 1er contre 2e']], self::lire(TableauElimination::construire(self::places(2), true)));
        self::assertSame(['tours' => [], 'exemptes' => 0], TableauElimination::construire(['1er'], false));
    }

    #[TestDox('RGL-11 6 équipes : deux barrages numérotés, 2 exemptés')]
    public function test_six_equipes(): void
    {
        $tableau = TableauElimination::construire(self::places(6), false);

        self::assertSame(2, $tableau['exemptes']);
        self::assertSame(['Barrage 1 : 4e contre 5e', 'Barrage 2 : 3e contre 6e'], self::lire($tableau)['Barrages']);
        self::assertSame(['Demi-finale 1 : 1er contre vainqueur du barrage 1', 'Demi-finale 2 : 2e contre vainqueur du barrage 2'], self::lire($tableau)['Demi-finales']);
    }
}
