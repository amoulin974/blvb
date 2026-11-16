<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\User;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\DomCrawler\Crawler;

/**
 * PRE-01 à PRE-05 : page « Ce que vous pouvez faire » et encart d'accueil (D71).
 */
final class PresentationTest extends TestFonctionnel
{
    /** @return list<string> titres des sections de la page */
    private function sections(): array
    {
        $crawler = $this->client->request('GET', '/ce-que-vous-pouvez-faire');
        self::assertResponseIsSuccessful();

        return $crawler->filter('main h2')->each(fn (Crawler $h) => trim($h->text()));
    }

    private function encart(): int
    {
        return $this->client->request('GET', '/')->filter('#titre-presentation')->count();
    }

    private function compte(string $nom): User
    {
        return self::getContainer()->get('doctrine')->getManager()->find(User::class, ChampionnatDeTest::compte($nom)->getId());
    }

    #[TestDox('PRE-01 Visiteur : la page ne montre que « Sans compte, tout le monde peut », avec une invitation à se connecter ; pas d\'encart')]
    public function test_visiteur(): void
    {
        self::assertSame(['Sans compte, tout le monde peut :'], $this->sections());
        self::assertSelectorExists('main a[href="/login"]');
        self::assertSame(0, $this->encart());
    }

    #[TestDox('PRE-02 Sections selon les droits réels : joueur ; capitaine (en plus du joueur) ; responsable de club ; administrateur ; Volley Stats n\'est pas annoncé')]
    public function test_sections_par_role(): void
    {
        $tous = 'Sans compte, tout le monde peut :';
        $joueur = 'Avec votre compte de joueur, vous pouvez aussi :';
        $attendus = [
            'joueur.a1' => [$tous, $joueur],
            'cap.a1' => [$tous, $joueur, 'Capitaine, vous pouvez aussi :'],
        ];
        foreach ($attendus as $compte => $sections) {
            $this->connecter($compte);
            self::assertSame($sections, $this->sections(), $compte);
        }

        $this->connecter('resp.club');
        self::assertContains('Responsable de club, vous pouvez aussi :', $this->sections());
        self::assertSelectorExists('main a[href$="/fermetures"]');

        $this->connecter('admin');
        self::assertContains('Administrateur, vous pouvez aussi :', $this->sections());
        self::assertStringNotContainsStringIgnoringCase('page du match', $this->client->getCrawler()->filter('main')->text());
        self::assertStringNotContainsStringIgnoringCase('statistique', $this->client->getCrawler()->filter('main')->text());
    }

    #[TestDox('PRE-03 Encart d\'accueil : affiché tant que la page n\'a pas été vue, retenu pour le compte, de retour quand le compte gagne un rôle')]
    public function test_encart_une_fois_par_role(): void
    {
        $this->connecter('cap.a1');
        self::assertSame(1, $this->encart(), 'première visite');

        $this->sections();
        self::assertSame(0, $this->encart(), 'page vue : plus d\'encart');
        self::assertFalse($this->compte('cap.a1')->presentationAVoir(['joueur', 'capitaine']), 'retenu en base');

        // Le compte n'avait vu que le rôle de joueur : l'encart revient pour le rôle de capitaine
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $this->compte('cap.a1');
        (new \ReflectionProperty(User::class, 'presentationVue'))->setValue($user, ['joueur']);
        $em->flush();
        self::assertSame(1, $this->encart(), 'nouveau rôle');
    }

    #[TestDox('PRE-04 Fermer l\'encart : jeton CSRF obligatoire, réservé aux comptes connectés ; fermé, il ne revient plus')]
    public function test_fermer(): void
    {
        $this->client->request('POST', '/ce-que-vous-pouvez-faire/fermer');
        self::assertResponseRedirects(); // visiteur : renvoyé à la connexion

        $this->connecter('joueur.a1');
        $this->client->request('POST', '/ce-que-vous-pouvez-faire/fermer', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->encart(), 'jeton faux : encart toujours là');

        $this->client->submit($this->client->getCrawler()->filter('form[action$="/ce-que-vous-pouvez-faire/fermer"]')->form());
        self::assertResponseRedirects('/');
        self::assertSame(0, $this->encart(), 'fermé');
    }

    #[TestDox('PRE-05 Lien « Ce que vous pouvez faire » : menu du compte, menu latéral, « Mon profil » ; « Le championnat » et menu latéral pour le visiteur')]
    public function test_liens(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertCount(1, $crawler->filter('.drawer-side a[href="/ce-que-vous-pouvez-faire"]'), 'visiteur');
        self::assertCount(1, $crawler->filter('header a[href="/ce-que-vous-pouvez-faire"]'), 'visiteur, « Le championnat » sur ordinateur');

        $this->connecter('joueur.a1');
        $crawler = $this->client->request('GET', '/');
        self::assertCount(2, $crawler->filter('header a[href="/ce-que-vous-pouvez-faire"]'), '« Le championnat » et menu du compte');
        self::assertCount(1, $crawler->filter('.drawer-side a[href="/ce-que-vous-pouvez-faire"]'), 'menu latéral');
        $crawler = $this->client->request('GET', '/mon-profil');
        self::assertCount(1, $crawler->filter('main a[href="/ce-que-vous-pouvez-faire"]'), 'Mon profil');
    }
}
