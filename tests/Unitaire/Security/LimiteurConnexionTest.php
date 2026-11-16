<?php

namespace App\Tests\Unitaire\Security;

use App\Security\LimiteurConnexion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** LIM-01 : durée d'attente et sa formulation. */
final class LimiteurConnexionTest extends TestCase
{
    public static function attentes(): iterable
    {
        yield 'aucun échec' => [0, 0];
        yield 'sous le seuil' => [2, 0];
        yield 'au seuil' => [3, 30];
        yield 'le double' => [4, 60];
        yield 'encore le double' => [5, 120];
        yield 'dix échecs' => [10, 3840];
        yield 'plafond de 24 h' => [15, 86400];
        yield 'plafond, sans débordement' => [100000, 86400];
    }

    #[DataProvider('attentes')]
    #[TestDox('LIM-01 Attente de $attendu s après $echecs échecs (seuil de 3)')]
    public function test_attente(int $echecs, int $attendu): void
    {
        self::assertSame($attendu, LimiteurConnexion::attente($echecs, 3));
    }

    public static function durees(): iterable
    {
        yield [1, '1 seconde'];
        yield [30, '30 secondes'];
        yield [60, '1 minute'];
        yield [61, '2 minutes'];
        yield [3600, '1 heure'];
        yield [3601, '2 heures'];
    }

    #[DataProvider('durees')]
    #[TestDox('LIM-01 $secondes s s\'écrivent « $attendu »')]
    public function test_duree_en_clair(int $secondes, string $attendu): void
    {
        self::assertSame($attendu, LimiteurConnexion::dureeEnClair($secondes));
    }
}
