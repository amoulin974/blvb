<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Entity\ParametresSite;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\Support\TestFonctionnel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * LIM-02 à LIM-09, LIM-11 : limitation des essais de connexion (App\Security\LimiteurConnexion, D61).
 */
final class LimiteConnexionTest extends TestFonctionnel
{
    use ClockSensitiveTrait;

    private const COMPTE = 'limite@blvb.test';
    private const MOT_DE_PASSE = 'Bon-mot-de-passe-1!';

    private MockClock $horloge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->horloge = self::mockTime('2026-11-02 18:00:00');
        UserFactory::createOne(['email' => self::COMPTE, 'password' => self::MOT_DE_PASSE]);
        $this->limiter(true);
    }

    private function limiter(bool $active): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $parametres = $em->getRepository(ParametresSite::class)->courants()->setConnexionLimitee($active);
        $em->persist($parametres);
        $em->flush();
    }

    /** Envoie le formulaire ; renvoie le message d'erreur affiché, '' si la connexion a réussi. */
    private function essai(string $motDePasse = 'mauvais', string $email = self::COMPTE): string
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->reduce(fn ($f) => $f->filter('input[name="_password"]')->count() > 0)->form();
        $form['_username'] = $email;
        $form['_password'] = $motDePasse;
        $this->client->submit($form);
        $this->client->followRedirect();
        $alerte = $this->client->getCrawler()->filter('[role="alert"]');

        return $alerte->count() > 0 ? trim($alerte->text()) : '';
    }

    private function echecs(int $nombre, string $email = self::COMPTE): string
    {
        $message = '';
        for ($i = 0; $i < $nombre; ++$i) {
            $message = $this->essai(email: $email);
        }

        return $message;
    }

    private function ip(string $ip): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', $ip);
    }

    private function seDeconnecter(): void
    {
        $this->client->request('POST', '/logout');
    }

    #[TestDox('LIM-02 Les 2 premiers échecs n\'imposent rien ; après le 3e, même le bon mot de passe est refusé pendant 30 s, puis accepté')]
    public function test_attente_apres_le_seuil(): void
    {
        self::assertStringNotContainsString('Trop d\'essais', $this->echecs(2));
        $this->essai();

        $message = $this->essai(self::MOT_DE_PASSE);
        self::assertStringStartsWith('Trop d\'essais de connexion. Réessayez dans 30 secondes.', $message);
        self::assertStringContainsString('J’ai oublié mon mot de passe', $message);

        $this->horloge->sleep(31);
        self::assertSame('', $this->essai(self::MOT_DE_PASSE), 'attente terminée');
    }

    #[TestDox('LIM-03 L\'attente double à chaque nouvel échec, et un essai pendant l\'attente ne la prolonge pas')]
    public function test_attente_exponentielle(): void
    {
        $this->echecs(3);

        $this->horloge->sleep(31);
        $this->essai();
        self::assertStringContainsString('1 minute', $this->essai());

        $this->horloge->sleep(61);
        $this->essai();
        self::assertStringContainsString('2 minutes', $this->essai());

        // Essais pendant l'attente : ni plus long, ni comptés
        $this->horloge->sleep(100);
        self::assertStringContainsString('20 secondes', $this->essai());
        $this->horloge->sleep(21);
        self::assertSame('', $this->essai(self::MOT_DE_PASSE));
    }

    #[TestDox('LIM-04 Une connexion réussie remet le compteur du compte à zéro')]
    public function test_reussite_efface(): void
    {
        $this->echecs(2);
        self::assertSame('', $this->essai(self::MOT_DE_PASSE));
        $this->seDeconnecter();

        self::assertStringNotContainsString('Trop d\'essais', $this->echecs(2), 'de nouveau 2 échecs sans attente');
    }

    #[TestDox('LIM-05 Le blocage ne touche ni un autre compte sur la même IP, ni le même compte depuis une autre IP, et ne dépend pas de la casse')]
    public function test_portee(): void
    {
        $this->ip('203.0.113.1');
        $this->echecs(3);
        self::assertStringContainsString('Trop d\'essais', $this->essai(email: strtoupper(self::COMPTE)), 'casse différente : même compteur');

        self::assertStringNotContainsString('Trop d\'essais', $this->essai(email: 'autre@blvb.test'), 'autre compte');

        $this->ip('203.0.113.2');
        self::assertSame('', $this->essai(self::MOT_DE_PASSE), 'un attaquant ne bloque pas le vrai titulaire depuis une autre IP');
    }

    #[TestDox('LIM-08 En IPv6, les adresses d\'un même /64 partagent leur compteur ; un autre /64 n\'est pas touché')]
    public function test_ipv6(): void
    {
        $this->ip('2001:db8:1:2::1');
        $this->echecs(3);

        $this->ip('2001:db8:1:2::ffff');
        self::assertStringContainsString('Trop d\'essais', $this->essai(), 'même /64');

        $this->ip('2001:db8:1:3::1');
        self::assertSame('', $this->essai(self::MOT_DE_PASSE), 'autre /64');
    }

    #[TestDox('LIM-09 Changer son mot de passe par le lien reçu par e-mail lève l\'attente, même depuis une autre IP')]
    public function test_reinitialisation(): void
    {
        $this->ip('203.0.113.5');
        $this->echecs(3);
        self::assertStringContainsString('Trop d\'essais', $this->essai());

        $em = self::getContainer()->get('doctrine')->getManager();
        $compte = $em->getRepository(User::class)->findOneBy(['email' => self::COMPTE]);
        $jeton = self::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($compte)->getToken();

        $this->ip('203.0.113.6');
        $this->client->request('GET', '/reset-password/reset/'.$jeton);
        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form')->reduce(fn ($f) => $f->filter('input[type="password"]')->count() > 0)->form();
        $form['change_password_form[plainPassword][first]'] = 'Nouveau-mot-de-passe-2!';
        $form['change_password_form[plainPassword][second]'] = 'Nouveau-mot-de-passe-2!';
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->ip('203.0.113.5');
        self::assertSame('', $this->essai('Nouveau-mot-de-passe-2!'), 'plus d\'attente, sur l\'IP qui était bloquée');
    }

    #[TestDox('LIM-10 Un essai refusé pour son jeton de sécurité ne compte pas : une page piégée ne peut pas bloquer un visiteur')]
    public function test_jeton_refuse_non_compte(): void
    {
        $this->client->request('GET', '/login');
        for ($i = 0; $i < 5; ++$i) {
            $this->client->getHistory()->clear();
            $this->client->request('POST', '/login', ['_username' => self::COMPTE, '_password' => 'mauvais', '_csrf_token' => 'faux'], [], ['HTTP_ORIGIN' => 'https://piege.example']);
        }

        self::assertStringNotContainsString('Trop d\'essais', $this->echecs(2));
    }

    #[TestDox('LIM-06 Une adresse inconnue est limitée comme une adresse existante : le blocage ne révèle pas quels comptes existent')]
    public function test_adresse_inconnue(): void
    {
        $this->echecs(3, 'inconnu@blvb.test');

        self::assertStringStartsWith('Trop d\'essais de connexion. Réessayez dans 30 secondes.', $this->essai(email: 'inconnu@blvb.test'));
    }

    #[TestDox('LIM-07 20 échecs sur des comptes différents bloquent l\'IP, sans toucher les autres ; une connexion réussie ne l\'efface pas')]
    public function test_limite_par_ip(): void
    {
        $this->ip('203.0.113.3');
        for ($i = 1; $i <= 19; ++$i) {
            $this->essai(email: "essai$i@blvb.test");
        }
        self::assertSame('', $this->essai(self::MOT_DE_PASSE), 'connexion avec son propre compte');
        $this->seDeconnecter();
        $this->essai(email: 'essai20@blvb.test');

        self::assertStringContainsString('Trop d\'essais', $this->essai(self::MOT_DE_PASSE), 'IP bloquée, quel que soit le compte');

        $this->ip('203.0.113.4');
        self::assertSame('', $this->essai(self::MOT_DE_PASSE), 'autre IP');
    }

    #[TestDox('LIM-11 : désactivée (D62), aucun essai n\'est compté ni bloqué, et une attente en cours ne s\'applique plus')]
    public function test_desactivee(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->essai();
        }
        self::assertStringContainsString('Trop d\'essais', $this->essai());

        $this->limiter(false);
        for ($i = 0; $i < 5; ++$i) {
            self::assertStringNotContainsString('Trop d\'essais', $this->essai());
        }
        self::assertSame('', $this->essai(self::MOT_DE_PASSE));
    }
}
