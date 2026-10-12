<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Factory\UserFactory;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** SEC-09 : connexion par le formulaire ; SEC-10 : déconnexion. */
final class ConnexionTest extends TestFonctionnel
{
    private const MOT_DE_PASSE = 'Bon-mot-de-passe-1!';

    protected function setUp(): void
    {
        parent::setUp();
        UserFactory::createOne(['email' => 'connexion.admin@blvb.test', 'password' => self::MOT_DE_PASSE, 'roles' => ['ROLE_ADMIN']]);
        UserFactory::createOne(['email' => 'connexion.joueur@blvb.test', 'password' => self::MOT_DE_PASSE]);
    }

    private function seConnecter(string $email, string $motDePasse): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->reduce(fn ($f) => $f->filter('input[name="_password"]')->count() > 0)->form();
        $form['_username'] = $email;
        $form['_password'] = $motDePasse;
        $this->client->submit($form);
    }

    private function messageErreur(): string
    {
        $this->client->followRedirect();

        return trim($this->client->getCrawler()->filter('[role="alert"]')->text(''));
    }

    #[TestDox('SEC-09 Identifiants faux : même message en français, que l\'adresse existe ou non (rien ne dit lequel est faux)')]
    public function test_identifiants_faux(): void
    {
        $this->seConnecter('connexion.joueur@blvb.test', 'mauvais');
        $mauvaisMotDePasse = $this->messageErreur();
        $this->seConnecter('inconnu@blvb.test', 'mauvais');
        $adresseInconnue = $this->messageErreur();

        self::assertNotSame('', $mauvaisMotDePasse);
        self::assertSame($mauvaisMotDePasse, $adresseInconnue, 'aucune différence entre adresse inconnue et mauvais mot de passe');
        self::assertStringNotContainsString('Invalid', $mauvaisMotDePasse, 'message traduit');
        self::assertMatchesRegularExpression('/identifiants|invalide/i', $mauvaisMotDePasse);
        self::assertNull($this->client->getContainer()->get('security.token_storage')->getToken());
    }

    #[TestDox('SEC-09 Connexion réussie : le lien « Administration » n\'apparaît que pour un administrateur')]
    public function test_connexion_et_lien_administration(): void
    {
        $this->seConnecter('connexion.admin@blvb.test', self::MOT_DE_PASSE);
        self::assertResponseRedirects();
        $crawler = $this->client->request('GET', '/');
        self::assertGreaterThan(0, $crawler->filter('a:contains("Administration")')->count(), 'administrateur');

        $this->client->request('POST', '/logout');
        $this->seConnecter('connexion.joueur@blvb.test', self::MOT_DE_PASSE);
        $crawler = $this->client->request('GET', '/');
        self::assertSame(0, $crawler->filter('a:contains("Administration")')->count(), 'simple joueur');
        self::assertGreaterThan(0, $crawler->filter('form[action$="/logout"]')->count(), 'connecté : bouton de déconnexion');
    }

    #[TestDox('SEC-10 La déconnexion termine la session : l\'admin et « Mon profil » redemandent la connexion')]
    public function test_deconnexion(): void
    {
        $this->seConnecter('connexion.admin@blvb.test', self::MOT_DE_PASSE);
        $this->client->request('GET', '/admin/dashboard');
        self::assertResponseIsSuccessful('connecté');

        $this->client->request('POST', '/logout');
        self::assertResponseRedirects();

        $this->client->request('GET', '/admin/dashboard');
        $this->assertRedirigeVersConnexion('admin après déconnexion');
        $this->client->request('GET', '/mon-profil');
        $this->assertRedirigeVersConnexion('profil après déconnexion');
    }
}
