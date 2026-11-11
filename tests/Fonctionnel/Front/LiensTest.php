<?php

namespace App\Tests\Fonctionnel\Front;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LIE-01, LIE-02 : convention « souligné = lien » (noms d'équipes, gymnases), lisible au doigt sur téléphone.
 */
final class LiensTest extends TestFonctionnel
{
    #[TestDox('LIE-01 Calendrier : noms d\'équipes et gymnases en classe « lien » (soulignés), sans infobulle ; le gymnase annonce la carte dans un nouvel onglet')]
    public function test_calendrier(): void
    {
        $crawler = $this->client->request('GET', '/calendrier');
        $equipe = $crawler->filter(sprintf('a[href="/equipe/%d"]', ChampionnatDeTest::get('equipe.A1')->getId()));
        self::assertGreaterThan(0, $equipe->count());
        $equipe->each(fn ($a) => self::assertStringContainsString('lien', (string) $a->attr('class')));
        self::assertCount(0, $crawler->filter('a[title="Voir la fiche de l\'équipe"]'));
        $gymnase = $crawler->filter('a[href*="google.com/maps"]')->first();
        self::assertStringContainsString('lien', (string) $gymnase->attr('class'));
        self::assertStringContainsString('(carte, nouvel onglet)', $gymnase->text());
        self::assertCount(1, $gymnase->filter('svg[aria-hidden="true"]'), 'épingle');
        self::assertCount(0, $crawler->filter('main a.link-hover'));
    }

    #[TestDox('LIE-02 Fiche équipe : dans son calendrier, l\'adversaire est un lien vers sa fiche, l\'équipe affichée ne l\'est pas')]
    public function test_fiche_equipe(): void
    {
        $a1 = ChampionnatDeTest::get('equipe.A1')->getId();
        $a2 = ChampionnatDeTest::get('equipe.A2')->getId();
        $crawler = $this->client->request('GET', '/equipe/'.$a1);
        $calendrier = $crawler->filterXPath('//section[.//h2[contains(., "Calendrier")]]');
        self::assertGreaterThan(0, $calendrier->filter(sprintf('a.lien[href="/equipe/%d"]', $a2))->count(), 'adversaire cliquable');
        self::assertCount(0, $calendrier->filter(sprintf('a[href="/equipe/%d"]', $a1)), 'équipe affichée non cliquable');
    }
}
