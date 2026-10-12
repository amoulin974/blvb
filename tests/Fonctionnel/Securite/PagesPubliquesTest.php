<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/** SEC-03 : pages publiques ouvertes à tous ; SEC-04 : « Mon profil » réservé aux comptes connectés. */
final class PagesPubliquesTest extends TestFonctionnel
{
    /** @return list<string> */
    private function pagesPubliques(): array
    {
        $routeur = self::getContainer()->get('router');
        $saison = ChampionnatDeTest::get('saison')->getId();
        $a1 = ChampionnatDeTest::get('equipe.A1')->getId();

        return [
            '/', '/equipes', '/equipe/'.$a1, '/calendrier', '/calendrierlieu', '/classement', '/reglement', '/qui-sommes-nous',
            '/clubs', '/club/'.ChampionnatDeTest::get('club')->getId(),
            $routeur->generate('calendar_ics_equipe', ['saison' => $saison, 'equipe' => $a1]),
            $routeur->generate('calendar_ics', ['poule' => ChampionnatDeTest::get('poule.A')->getId(), 'equipe' => $a1]),
        ];
    }

    /** @return array<string, array{?string}> */
    public static function profils(): array
    {
        return [
            'visiteur' => [null],
            'simple joueur' => ['joueur.a1'],
            'capitaine' => ['cap.a1'],
            'responsable de club' => ['resp.club'],
            'administrateur' => ['admin'],
        ];
    }

    #[DataProvider('profils')]
    #[TestDox('SEC-03 Les pages publiques et les agendas .ics s\'ouvrent (200) : $_dataName')]
    public function test_pages_publiques(?string $compte): void
    {
        if ($compte !== null) {
            $this->connecter($compte);
        }
        $echecs = [];
        foreach ($this->pagesPubliques() as $page) {
            $this->client->request('GET', $page);
            if ($this->client->getResponse()->getStatusCode() !== 200) {
                $echecs[] = $page.' → '.$this->client->getResponse()->getStatusCode();
            }
        }

        self::assertSame([], $echecs);
    }

    #[TestDox('SEC-04 « Mon profil » : visiteur renvoyé vers la connexion, compte connecté accepté')]
    public function test_mon_profil(): void
    {
        $this->client->request('GET', '/mon-profil');
        $this->assertRedirigeVersConnexion('visiteur');

        foreach (['joueur.a1', 'sans.equipe', 'resp.club'] as $compte) {
            $this->connecter($compte);
            $this->client->request('GET', '/mon-profil');
            self::assertResponseIsSuccessful($compte);
        }
    }
}
