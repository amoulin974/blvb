<?php

namespace App\Tests\Fonctionnel\Front;

use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** CGU-01 : page des conditions d'utilisation (mentions légales, données personnelles, cookies). */
final class ConditionsTest extends TestFonctionnel
{
    #[TestDox('CGU-01 La page des conditions est publique et liée depuis le pied de page de toutes les pages')]
    public function test_page_publique(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertCount(1, $crawler->filter('footer a[href="/conditions-utilisation"]'));

        $this->client->request('GET', '/conditions-utilisation');
        self::assertResponseIsSuccessful();
        foreach (['#mentions-legales', '#utilisation', '#donnees-personnelles', '#cookies'] as $section) {
            self::assertSelectorExists($section);
        }
        self::assertSelectorTextContains('#mentions-legales', 'OVH SAS');
        self::assertSelectorTextContains('#mentions-legales', '2 rue Kellermann, 59100 Roubaix');
        self::assertSelectorTextContains('#cookies', 'PHPSESSID');
        self::assertSelectorTextContains('#donnees-personnelles', 'CNIL');
    }
}
