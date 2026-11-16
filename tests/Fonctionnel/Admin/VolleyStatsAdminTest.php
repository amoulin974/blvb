<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\ParametresSite;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** VST-20 : réglages de Volley Stats dans l'admin (seuil d'échanges détaillés, D68) et section du tableau de bord. */
final class VolleyStatsAdminTest extends TestFonctionnel
{
    private const URL = '/admin/volley-stats';

    private function seuil(): int
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(ParametresSite::class)->find(ParametresSite::ID)->getSeuilEchangesDetailles();
    }

    #[TestDox('VST-20 : seul l\'admin règle le seuil (80 % par défaut) ; valeurs hors 0–100, décimale, texte et jeton absent refusés ; le menu et le tableau de bord y mènent')]
    public function test_reglages(): void
    {
        foreach (['joueur.a1', 'resp.club'] as $compte) {
            $this->connecter($compte);
            $this->client->request('GET', self::URL);
            $this->assertAccesRefuse($compte);
        }
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/dashboard');
        self::assertCount(1, $crawler->filter('nav a[href="'.self::URL.'"]'), 'menu');
        self::assertStringContainsString('seuil des classements : 80 %', $crawler->filter('#titre-volley-stats')->closest('section')->text());

        $crawler = $this->client->request('GET', self::URL);
        self::assertSame('80', $crawler->filter('input[name="volley_stats_reglages[seuilEchangesDetailles]"]')->attr('value'));
        $form = $crawler->selectButton('Enregistrer')->form();
        foreach (['-1', '101', '12.5', 'beaucoup'] as $valeur) {
            $form['volley_stats_reglages[seuilEchangesDetailles]'] = $valeur;
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(422, $valeur);
        }
        self::assertSame(80, $this->seuil(), 'rien d\'enregistré');
        $this->client->request('POST', self::URL, ['volley_stats_reglages' => ['seuilEchangesDetailles' => '50']]);
        self::assertResponseStatusCodeSame(422, 'jeton absent');

        $form['volley_stats_reglages[seuilEchangesDetailles]'] = '70';
        $this->client->submit($form);
        self::assertResponseRedirects(self::URL);
        self::assertSame(70, $this->seuil());
    }
}
