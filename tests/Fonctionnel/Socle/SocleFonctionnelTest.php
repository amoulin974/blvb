<?php

namespace App\Tests\Fonctionnel\Socle;

use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Lot 0 : le client HTTP, le championnat de référence et la connexion fonctionnent ensemble.
 * Les vérifications d'accès complètes sont l'objet du lot 1 (SEC-*).
 */
final class SocleFonctionnelTest extends TestFonctionnel
{
    #[TestDox('L\'accueil s\'affiche pour un visiteur, avec la saison affichée sur le site')]
    public function test_accueil_visiteur(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'EQUIPE A1');
    }

    #[TestDox('L\'administration renvoie un visiteur vers la connexion')]
    public function test_admin_visiteur(): void
    {
        $this->client->request('GET', '/admin/dashboard');

        $this->assertRedirigeVersConnexion();
    }

    #[TestDox('L\'administration refuse un simple joueur connecté')]
    public function test_admin_joueur(): void
    {
        $this->connecter('joueur.a1');
        $this->client->request('GET', '/admin/dashboard');

        $this->assertAccesRefuse();
    }

    #[TestDox('L\'administration s\'ouvre pour un administrateur')]
    public function test_admin_administrateur(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', '/admin/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }
}
