<?php

namespace App\Tests\Fonctionnel\Front;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Les URL utilisées par le JavaScript viennent du routeur (path()), jamais d'un chemin écrit en dur :
 * en production, l'application est servie sous le préfixe /blvb (config/routes.yaml de la branche prod).
 * Régression : le lien « Mon équipe » du menu pointait vers /equipe/27 au lieu de /blvb/equipe/27.
 */
final class UrlsGenereesTest extends TestFonctionnel
{
    #[TestDox('Le lien « Mon équipe » du menu est construit à partir de l\'URL fournie par le routeur')]
    public function test_lien_mon_equipe(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $attendu = self::getContainer()->get('router')->generate('front_equipe_detail', ['id' => '__ID__']);
        self::assertSame($attendu, $crawler->filter('body')->attr('data-mon-equipe-url-equipe-value'));
    }

    #[TestDox('Le bouton de saisie du score transmet l\'URL de l\'API fournie par le routeur')]
    public function test_url_saisie_score(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/calendrier');

        self::assertResponseIsSuccessful();
        $partie = ChampionnatDeTest::get('match.reference');
        $bouton = $crawler->filter(sprintf('[data-partie-id="%d"]', $partie->getId()))->first();
        $attendu = self::getContainer()->get('router')->generate('front_api_score_update', ['id' => $partie->getId()]);
        self::assertSame($attendu, $bouton->attr('data-url-score'));
    }
}
