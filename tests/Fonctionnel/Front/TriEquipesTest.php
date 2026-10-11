<?php

namespace App\Tests\Fonctionnel\Front;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Page « Les équipes » : tri par nom (défaut) ou par classement, validé par l'ergonome.
 * Le tri lui-même est fait dans le navigateur (tri_equipes_controller.js) ; ici, le balisage dont il dépend.
 */
final class TriEquipesTest extends TestFonctionnel
{
    #[TestDox('Chaque poule a son tri : en-têtes Équipe et Classement en boutons, aria-sort seulement sur la colonne triée')]
    public function test_entetes_triables(): void
    {
        $crawler = $this->client->request('GET', '/equipes');
        self::assertResponseIsSuccessful();

        $poule = $crawler->filter('[data-controller="tri-equipes"]')->first();
        self::assertCount(2, $poule->filter('th button[data-action="tri-equipes#trier"]'), 'Équipe et Classement seulement');
        self::assertSame('ascending', $poule->filter('th[data-cle="nom"]')->attr('aria-sort'), 'tri alphabétique annoncé au chargement');
        self::assertNull($poule->filter('th[data-cle="classement"]')->attr('aria-sort'));
        self::assertSame('true', $poule->filter('th [data-tri-equipes-target="fleche"]')->attr('aria-hidden'), 'flèche non lue');
        self::assertSame(0, $poule->filter('th')->reduce(fn ($th) => str_contains($th->text(), 'Gymnase'))->filter('button')->count(), 'Gymnase non triable');
    }

    #[TestDox('Les lignes et les cartes portent les valeurs de tri (nom enregistré, classement) et sont d\'abord dans l\'ordre alphabétique')]
    public function test_valeurs_de_tri(): void
    {
        $crawler = $this->client->request('GET', '/equipes');
        $poule = $crawler->filter('[data-controller="tri-equipes"]')->first();

        $listes = $poule->filter('[data-tri-equipes-target="liste"]');
        self::assertCount(2, $listes, 'tableau et cartes mobiles');
        $noms = $listes->first()->children()->each(fn ($ligne) => $ligne->attr('data-tri-nom'));
        self::assertSame(['equipe a1', 'equipe a2', 'equipe a3', 'equipe a4'], $noms);
        self::assertSame($noms, $listes->last()->children()->each(fn ($carte) => $carte->attr('data-tri-nom')));
        foreach ($listes->first()->children() as $ligne) {
            self::assertTrue($ligne->hasAttribute('data-tri-classement'));
            self::assertTrue($ligne->hasAttribute('data-equipe'), 'surlignage « Mon équipe » conservé');
        }
    }

    #[TestDox('Sur mobile, deux boutons « Trier : Nom | Classement », Nom enfoncé par défaut')]
    public function test_boutons_mobile(): void
    {
        $crawler = $this->client->request('GET', '/equipes');
        $groupe = $crawler->filter('[data-controller="tri-equipes"] [role="group"]')->first();

        self::assertSame('Trier les équipes', $groupe->attr('aria-label'));
        self::assertSame(['Nom' => 'true', 'Classement' => 'false'], array_combine(
            $groupe->filter('button')->each(fn ($b) => trim($b->text())),
            $groupe->filter('button')->each(fn ($b) => $b->attr('aria-pressed')),
        ));
    }
}
