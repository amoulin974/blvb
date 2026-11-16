<?php

namespace App\Tests\Unitaire\Competition\Classement;

use App\Competition\Classement\CritereDepartage as C;
use App\Entity\Saison;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Liste des critères de départage d'une saison (App\Competition\Classement\CritereDepartage, D16 et D17). */
final class CritereDepartageTest extends TestCase
{
    #[TestDox('Par défaut : le départage d\'avant le paramétrage (points, différence de sets, confrontation directe, ordre alphabétique)')]
    public function test_par_defaut(): void
    {
        self::assertSame([C::POINTS, C::DIFFERENCE_SETS, C::CONFRONTATION_DIRECTE, C::ALPHABETIQUE], C::parDefaut());
        self::assertSame(C::parDefaut(), (new Saison())->getDepartage(), 'nouvelle saison');
        self::assertSame(C::parDefaut(), C::depuisCodes(null), 'aucune valeur enregistrée');
        self::assertSame(C::parDefaut(), C::depuisCodes([]), 'liste vide');
    }

    #[TestDox('Liste enregistrée : codes inconnus ignorés, doublons retirés, ordre alphabétique toujours en dernier')]
    public function test_normalisation(): void
    {
        self::assertSame(
            [C::POINTS, C::QUOTIENT_SETS, C::MINI_CLASSEMENT, C::ALPHABETIQUE],
            C::depuisCodes(['points', 'alphabetique', 'inconnu', 'quotient_sets', 'points', 42, 'mini_classement']),
        );
        self::assertSame([C::POINTS, C::ALPHABETIQUE], C::normaliser([C::POINTS]));
    }

    #[TestDox('La saison enregistre les codes et relit les critères')]
    public function test_saison(): void
    {
        $saison = (new Saison())->setDepartage([C::POINTS, C::MINI_CLASSEMENT]);

        self::assertSame([C::POINTS, C::MINI_CLASSEMENT, C::ALPHABETIQUE], $saison->getDepartage());
    }

    #[TestDox('Seuls points, différence et quotient de sets se calculent par équipe')]
    public function test_valeurs(): void
    {
        $parEquipe = array_values(array_filter(C::cases(), fn (C $c) => $c->estUneValeur()));

        self::assertSame([C::POINTS, C::DIFFERENCE_SETS, C::QUOTIENT_SETS], $parEquipe);
    }
}
