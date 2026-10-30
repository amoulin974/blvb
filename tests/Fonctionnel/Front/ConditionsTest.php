<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\ParametresSite;
use App\Tests\Support\TestFonctionnel;
use Doctrine\ORM\EntityManagerInterface;
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

    #[TestDox('CGU-02 L\'adresse de contact des paramètres du site (D62) y est reprise ; la page décrit les fermetures de gymnase')]
    public function test_contact_et_fermetures(): void
    {
        $this->client->request('GET', '/conditions-utilisation');
        self::assertSelectorTextContains('#mentions-legales', 'À compléter : adresse e-mail de contact');
        self::assertSelectorTextContains('#utilisation', 'Fermetures de gymnase');
        self::assertSelectorTextContains('#donnees-personnelles', 'changement de saison affichée');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($em->getRepository(ParametresSite::class)->courants()->setAdresseContact('contact@blvb.test'));
        $em->flush();

        $this->client->request('GET', '/conditions-utilisation');
        self::assertSelectorTextContains('#mentions-legales', 'contact@blvb.test');
        self::assertSelectorTextContains('#donnees-personnelles', 'contact@blvb.test');
    }
}
