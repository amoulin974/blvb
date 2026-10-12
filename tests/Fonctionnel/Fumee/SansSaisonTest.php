<?php

namespace App\Tests\Fonctionnel\Fumee;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/** FUM-02 : base sans aucune saison (installation neuve) — sans le championnat de référence. */
final class SansSaisonTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[TestDox('FUM-02 Sans aucune saison, les pages publiques renvoient vers « Aucune saison », qui s\'affiche')]
    public function test_aucune_saison(): void
    {
        $client = static::createClient();
        static::getContainer()->get('cache.app')->clear();

        foreach (['/', '/equipes', '/calendrier', '/calendrierlieu', '/classement', '/reglement', '/qui-sommes-nous'] as $page) {
            $client->request('GET', $page);
            self::assertResponseRedirects('/aucune-saison', message: $page);
        }
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        foreach (['/clubs', '/login'] as $page) {
            $client->request('GET', $page);
            self::assertResponseIsSuccessful($page);
        }
    }
}
