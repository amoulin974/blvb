<?php

namespace App\Tests\Unitaire\Competition\Sport;

use App\Competition\Sport\Sports;
use App\Competition\Sport\Volley;
use App\Entity\Saison;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Sport d'une saison (App\Competition\Sport\Sports, D13). */
final class SportsTest extends TestCase
{
    #[TestDox('Une nouvelle saison est en volley-ball ; le sport est trouvé par le code enregistré dans la saison')]
    public function test_sport_de_la_saison(): void
    {
        $sports = new Sports();

        self::assertSame(Volley::CODE, (new Saison())->getSport());
        self::assertInstanceOf(Volley::class, $sports->pourSaison(new Saison()));
        self::assertInstanceOf(Volley::class, $sports->pourSaison(null));
    }

    #[TestDox('Code inconnu (sport retiré) : volley-ball, pour ne pas casser une saison archivée')]
    public function test_code_inconnu(): void
    {
        self::assertInstanceOf(Volley::class, (new Sports())->pourSaison((new Saison())->setSport('curling')));
    }

    #[TestDox('Liste des sports proposés : code et libellé')]
    public function test_liste(): void
    {
        self::assertSame(['volley' => 'Volley-ball'], (new Sports())->liste());
    }
}
