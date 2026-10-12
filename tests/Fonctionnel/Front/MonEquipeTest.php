<?php

namespace App\Tests\Fonctionnel\Front;

use App\Factory\EquipeFactory;
use App\Factory\MembreEquipeFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * MEQ-01 à MEQ-03 : données de « Mon équipe » fournies au navigateur (mon_equipe_defaut, mon_equipe_choix),
 * lues dans la page : attribut data-mon-equipe-defaut-value de <body> et liste de choix.
 */
final class MonEquipeTest extends TestFonctionnel
{
    private function defaut(): array
    {
        $crawler = $this->client->request('GET', '/');

        return json_decode((string) $crawler->filter('body')->attr('data-mon-equipe-defaut-value'), true);
    }

    #[TestDox('MEQ-01 Équipe par défaut : celle du capitaine connecté ; {"id": null} pour un visiteur ou un simple joueur (jamais [])')]
    public function test_defaut(): void
    {
        self::assertSame(['id' => null], $this->defaut(), 'visiteur');

        $this->connecter('joueur.a1');
        self::assertSame(['id' => null], $this->defaut(), 'simple joueur');

        $this->connecter('cap.a1');
        self::assertSame(ChampionnatDeTest::get('equipe.A1')->getId(), $this->defaut()['id']);
    }

    #[TestDox('MEQ-02 Capitaine de deux équipes : la première par nom, toujours la même')]
    public function test_capitaine_de_deux_equipes(): void
    {
        $avant = EquipeFactory::createOne(['nom' => 'AAA PREMIERE', 'lieu' => null]);
        MembreEquipeFactory::createOne(['equipe' => $avant, 'saison' => ChampionnatDeTest::get('saison'), 'joueur' => ChampionnatDeTest::get('joueur.cap.a1'), 'capitaine' => true]);

        $this->connecter('cap.a1');
        self::assertSame($avant->getId(), $this->defaut()['id']);
        self::assertSame($avant->getId(), $this->defaut()['id'], 'même choix au second affichage');
    }

    #[TestDox('MEQ-03 Liste de choix : les équipes de la saison, triées par nom')]
    public function test_liste_de_choix(): void
    {
        $crawler = $this->client->request('GET', '/');
        $noms = $crawler->filter('#mon-equipe-dialogue option[value!=""]')->each(fn ($o) => trim($o->text()));

        self::assertSame(['EQUIPE A1 (CTA)', 'EQUIPE A2', 'EQUIPE A3 (CTV)', 'EQUIPE A4', 'EQUIPE B1 (CTA)', 'EQUIPE B2', 'EQUIPE B3'], $noms);
    }
}
