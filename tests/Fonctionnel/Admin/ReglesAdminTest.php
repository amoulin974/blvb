<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Entity\User;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** Décisions d'octobre 2026 sur les anomalies A6 (saison affichée) et A7 (inscription libre). */
final class ReglesAdminTest extends TestFonctionnel
{
    #[TestDox('A6 La saison affichée sur le site ne peut pas être supprimée, même par une requête directe avec un jeton valide')]
    public function test_saison_affichee_protegee(): void
    {
        $this->connecter('admin');
        $id = ChampionnatDeTest::get('saison')->getId();
        $crawler = $this->client->request('GET', '/admin/saison/'.$id.'/edit');
        $jeton = $crawler->filter('[data-confirmation-action-param$="/admin/saison/'.$id.'"]')->attr('data-confirmation-token-param');

        $this->client->request('POST', '/admin/saison/'.$id, ['_token' => $jeton]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'est celle affichée sur le site');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertNotNull($em->find(Saison::class, $id), 'saison toujours là');
    }

    #[TestDox('A7 L\'inscription libre est fermée : /register renvoie vers la connexion, aucun compte n\'est créé')]
    public function test_inscription_fermee(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $avant = $em->getRepository(User::class)->count([]);

        $this->client->request('GET', '/register');
        self::assertResponseRedirects('/login');
        $this->client->request('POST', '/register', ['registration_form' => ['email' => 'pirate@exemple.fr', 'plainPassword' => 'Motdepasse-1!', 'agreeTerms' => '1']]);
        self::assertResponseRedirects('/login');

        self::assertSame($avant, $em->getRepository(User::class)->count([]));
    }
}
