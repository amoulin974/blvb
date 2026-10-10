<?php

namespace App\Tests\Integration\Socle;

use App\Repository\MembreEquipeRepository;
use App\Repository\SaisonRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Lot 0 : le championnat de référence est cohérent (si ce test échoue, beaucoup d'autres échoueront).
 */
final class ChampionnatDeTestTest extends TestIntegration
{
    protected function setUp(): void
    {
        ChampionnatDeTest::load();
    }

    #[TestDox('Une seule saison est affichée sur le site : la saison test')]
    public function test_saison_favorite(): void
    {
        $favorites = self::service(SaisonRepository::class)->findBy(['favori' => 1]);

        self::assertCount(1, $favorites);
        self::assertSame('Saison test', $favorites[0]->getNom());
        self::assertSame(-3, $favorites[0]->getPointsForfait());
    }

    #[TestDox('Poule A : 4 équipes, poule B : 3 équipes')]
    public function test_poules(): void
    {
        self::assertCount(4, ChampionnatDeTest::get('poule.A')->getEquipes());
        self::assertCount(3, ChampionnatDeTest::get('poule.B')->getEquipes());
    }

    #[TestDox('Le match de référence : A1 reçoit A2, joué hier, sans score')]
    public function test_match_de_reference(): void
    {
        $match = ChampionnatDeTest::get('match.reference');

        self::assertSame('EQUIPE A1', $match->getIdEquipeRecoit()->getNom());
        self::assertSame('EQUIPE A2', $match->getIdEquipeDeplace()->getNom());
        self::assertLessThan(new \DateTimeImmutable(), $match->getDate());
        self::assertNull($match->getNbSetGagnantReception());
        self::assertGreaterThan(new \DateTimeImmutable(), ChampionnatDeTest::get('match.futur')->getDate());
    }

    #[TestDox('Rôles de capitaine : par équipe et par saison')]
    public function test_capitaines(): void
    {
        $repo = self::service(MembreEquipeRepository::class);
        $a1 = ChampionnatDeTest::get('equipe.A1');
        $saison = ChampionnatDeTest::get('saison');
        $precedente = ChampionnatDeTest::get('saison.precedente');

        self::assertTrue($repo->estCapitaine(ChampionnatDeTest::compte('cap.a1'), $a1, $saison));
        self::assertFalse($repo->estCapitaine(ChampionnatDeTest::compte('cap.a2'), $a1, $saison));
        self::assertFalse($repo->estCapitaine(ChampionnatDeTest::compte('joueur.a1'), $a1, $saison));
        self::assertTrue($repo->estMembre(ChampionnatDeTest::compte('joueur.a1'), $a1, $saison));
        self::assertTrue($repo->estCapitaine(ChampionnatDeTest::compte('cap.a1.precedente'), $a1, $precedente));
        self::assertFalse($repo->estCapitaine(ChampionnatDeTest::compte('cap.a1.precedente'), $a1, $saison));
    }
}
