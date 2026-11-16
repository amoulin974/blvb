<?php

namespace App\Tests\Unitaire\Competition\Sport;

use App\Competition\Sport\Bareme;
use App\Entity\Phase;
use App\Entity\Saison;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Barème de la saison, surchargé valeur par valeur par la phase (App\Competition\Sport\Bareme). */
final class BaremeTest extends TestCase
{
    private function phase(?array $bareme): Phase
    {
        $saison = (new Saison())
            ->setPointsVictoireForte(5)->setPointsVictoireFaible(4)->setPointsNul(3)
            ->setPointsDefaiteForte(2)->setPointsDefaiteFaible(1)->setPointsForfait(-3);

        return (new Phase())->setSaison($saison)->setBareme($bareme);
    }

    #[TestDox('Sans surcharge : le barème de la saison')]
    public function test_sans_surcharge(): void
    {
        self::assertEquals(new Bareme(5, 4, 3, 2, 1, -3), Bareme::dePhase($this->phase(null)));
        self::assertEquals(new Bareme(5, 4, 3, 2, 1, -3), Bareme::dePhase($this->phase([])), 'surcharge vide');
    }

    #[TestDox('Surcharge partielle : seules les valeurs données changent ; clés inconnues et valeurs non entières ignorées')]
    public function test_surcharge_partielle(): void
    {
        $bareme = Bareme::dePhase($this->phase(['victoire_forte' => 0, 'forfait' => -1, 'inconnue' => 9, 'nul' => '7']));

        self::assertEquals(new Bareme(0, 4, 3, 2, 1, -1), $bareme);
    }

    #[TestDox('Un tableau final sans points : toutes les valeurs à 0')]
    public function test_phase_sans_points(): void
    {
        $bareme = Bareme::dePhase($this->phase(array_fill_keys(Bareme::CLES, 0)));

        self::assertEquals(new Bareme(0, 0, 0, 0, 0, 0), $bareme);
    }
}
