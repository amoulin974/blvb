<?php

namespace App\Tests\Support;

use App\Tests\Support\Story\ChampionnatDeTest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Base des tests fonctionnels : requêtes HTTP de bout en bout (sans navigateur) sur le championnat de référence.
 * Chaque test part d'un client neuf, non connecté, et d'une base remise à l'état de ChampionnatDeTest.
 */
abstract class TestFonctionnel extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // Le client doit être créé avant tout accès à la base (il démarre le noyau)
        $this->client = static::createClient();
        ChampionnatDeTest::load();
    }

    /** Se connecter avec un compte du championnat de référence (admin, cap.a1, joueur.a1…). */
    protected function connecter(string $compte): void
    {
        $this->client->loginUser(ChampionnatDeTest::compte($compte));
    }

    protected function assertRedirigeVersConnexion(string $message = ''): void
    {
        self::assertResponseRedirects(message: $message);
        self::assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'), $message);
    }

    protected function assertAccesRefuse(string $message = ''): void
    {
        self::assertResponseStatusCodeSame(403, $message);
    }
}
