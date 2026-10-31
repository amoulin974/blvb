<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\ParametresSite;
use App\Tests\Support\TestFonctionnel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * PAR-01, PAR-02, PAR-04 à PAR-09 : page « Paramètres du site » (App\Controller\Admin\ParametresSiteController, D62).
 */
final class ParametresSiteTest extends TestFonctionnel
{
    use ClockSensitiveTrait;

    private const URL = '/admin/parametres';
    private const IP_PUBLIQUE = '8.8.8.8';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    private const WEBP = 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA';

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossierLogos());
        parent::tearDown();
    }

    private function dossierLogos(): string
    {
        return \dirname(__DIR__, 3).'/var/test/uploads/site';
    }

    private function parametres(): ParametresSite
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(ParametresSite::class)->courants();
    }

    private function modifierParametres(callable $modification): void
    {
        $parametres = $this->parametres();
        $modification($parametres);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($parametres);
        $em->flush();
    }

    private function verifier(string $ip = self::IP_PUBLIQUE): void
    {
        $this->client->setServerParameter('REMOTE_ADDR', $ip);
        $jeton = $this->client->request('GET', self::URL)->filter('[data-verification-connexion-jeton-value]')->attr('data-verification-connexion-jeton-value');
        $this->client->request('POST', self::URL.'/verifier-connexion', ['_token' => $jeton]);
    }

    private function jetonActivation(): string
    {
        return $this->client->request('GET', self::URL)->filter('form[action$="/activer"] input[name="_token"]')->attr('value');
    }

    private function envoyerLogo(string $nom, string $contenu): void
    {
        $chemin = sys_get_temp_dir().'/'.uniqid('logo-').'-'.$nom;
        file_put_contents($chemin, $contenu);
        $crawler = $this->client->request('GET', self::URL);
        $form = $crawler->selectButton('Envoyer le logo')->form();
        $form['logo_site[fichier]']->upload($chemin);
        $this->client->submit($form);
        @unlink($chemin);
    }

    /** @return list<string> fichiers du dossier des logos */
    private function fichiersLogos(): array
    {
        return is_dir($this->dossierLogos()) ? array_values(array_diff(scandir($this->dossierLogos()), ['.', '..'])) : [];
    }

    #[TestDox('PAR-01 : seuls les admins ouvrent la page ; le menu y mène')]
    public function test_acces(): void
    {
        $this->client->request('GET', self::URL);
        $this->assertRedirigeVersConnexion();

        $this->connecter('cap.a1');
        $this->client->request('GET', self::URL);
        $this->assertAccesRefuse();

        $this->connecter('admin');
        $crawler = $this->client->request('GET', self::URL);
        self::assertResponseIsSuccessful();
        self::assertSame('page', $crawler->filter('a[href="'.self::URL.'"]')->attr('aria-current'));
    }

    #[TestDox('PAR-01 : les actions de la page sont refusées à un capitaine')]
    public function test_actions_refusees(): void
    {
        $this->connecter('cap.a1');
        foreach (['/verifier-connexion', '/connexion-limitee/activer', '/connexion-limitee/desactiver', '/logo/retirer'] as $action) {
            $this->client->request('POST', self::URL.$action);
            $this->assertAccesRefuse($action);
        }
    }

    #[TestDox('PAR-02 : au départ, la connexion limitée est désactivée et l\'activation n\'est pas proposée')]
    public function test_etat_initial(): void
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', self::URL);

        self::assertFalse($this->parametres()->isConnexionLimitee());
        self::assertSelectorTextContains('[aria-labelledby="titre-connexion"] .badge', 'Désactivée');
        self::assertCount(0, $crawler->filter('form[action$="/activer"]'));
    }

    #[TestDox('PAR-02 : le tableau de bord signale une limitation désactivée, et ne dit plus rien une fois activée')]
    public function test_alerte_tableau_de_bord(): void
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/dashboard');
        $ligne = $crawler->filter('[data-a-faire="connexion"]');
        self::assertStringContainsString('Les essais de connexion ne sont pas limités.', $ligne->text());
        self::assertSame(self::URL, $ligne->filter('a')->attr('href'));

        $this->modifierParametres(fn (ParametresSite $p) => $p->setConnexionLimitee(true));
        $crawler = $this->client->request('GET', '/admin/dashboard');
        self::assertCount(0, $crawler->filter('[data-a-faire="connexion"]'));
    }

    #[TestDox('PAR-04 : une vérification réussie est enregistrée, reste affichée une heure, et ouvre l\'activation')]
    public function test_verification_reussie(): void
    {
        $horloge = self::mockTime('2026-11-02 18:00:00');
        $this->connecter('admin');
        $this->verifier();

        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode($this->client->getResponse()->getContent(), true)['reussie']);
        self::assertEquals(new \DateTimeImmutable('2026-11-02 18:00:00'), $this->parametres()->getConnexionVerifieeLe());

        $crawler = $this->client->request('GET', self::URL);
        self::assertSelectorTextContains('ul[aria-label="Résultat de la vérification"]', self::IP_PUBLIQUE);
        self::assertCount(1, $crawler->filter('form[action$="/activer"]'));

        $crawler = $this->client->request('GET', self::URL);
        self::assertCount(1, $crawler->filter('ul[aria-label="Résultat de la vérification"]'), 'toujours là au rechargement');

        $horloge->sleep(3601);
        $crawler = $this->client->request('GET', self::URL);
        self::assertCount(0, $crawler->filter('ul[aria-label="Résultat de la vérification"]'));
        self::assertCount(0, $crawler->filter('form[action$="/activer"]'));
    }

    #[TestDox('PAR-04 : une vérification échouée efface la précédente et n\'ouvre pas l\'activation')]
    public function test_verification_echouee(): void
    {
        $this->connecter('admin');
        $this->verifier();
        $this->verifier('172.18.0.1');

        self::assertFalse(json_decode($this->client->getResponse()->getContent(), true)['reussie']);
        self::assertNull($this->parametres()->getConnexionVerifieeLe());

        $crawler = $this->client->request('GET', self::URL);
        self::assertSelectorTextContains('ul[aria-label="Résultat de la vérification"]', 'adresse privée');
        self::assertCount(0, $crawler->filter('form[action$="/activer"]'));
    }

    #[TestDox('PAR-05 : activation après vérification et confirmation ; les échecs passés sont oubliés')]
    public function test_activation(): void
    {
        $this->connecter('admin');
        $this->verifier();
        $cache = self::getContainer()->get('connexions.cache');
        $ancien = $cache->getItem('ancien_compteur')->set(1);
        $cache->save($ancien);

        $this->client->request('POST', self::URL.'/connexion-limitee/activer', ['_token' => $this->jetonActivation(), 'confirmation' => '1']);

        self::assertResponseRedirects(self::URL);
        self::assertTrue($this->parametres()->isConnexionLimitee());
        self::assertFalse(self::getContainer()->get('connexions.cache')->hasItem('ancien_compteur'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby="titre-connexion"] .badge', 'Activée');
    }

    #[TestDox('PAR-05 : activer de nouveau depuis un onglet resté ouvert n\'efface pas les compteurs en cours')]
    public function test_activation_deja_active(): void
    {
        $this->connecter('admin');
        $this->verifier();
        $jeton = $this->jetonActivation();
        $this->client->request('POST', self::URL.'/connexion-limitee/activer', ['_token' => $jeton, 'confirmation' => '1']);
        $cache = self::getContainer()->get('connexions.cache');
        $cache->save($cache->getItem('compteur_en_cours')->set(1));

        $this->client->request('POST', self::URL.'/connexion-limitee/activer', ['_token' => $jeton, 'confirmation' => '1']);

        self::assertResponseRedirects(self::URL);
        self::assertTrue(self::getContainer()->get('connexions.cache')->hasItem('compteur_en_cours'));
    }

    #[TestDox('PAR-05 : activation refusée sans la case, sans vérification de moins d\'une heure, ou depuis une adresse privée')]
    public function test_activation_refusee(): void
    {
        $horloge = self::mockTime('2026-11-02 18:00:00');
        $this->connecter('admin');
        $this->verifier();
        $jeton = $this->jetonActivation();
        $activer = fn (array $champs = ['confirmation' => '1']) => $this->client->request('POST', self::URL.'/connexion-limitee/activer', ['_token' => $jeton] + $champs);

        $activer([]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'cochez la case');
        self::assertFalse($this->parametres()->isConnexionLimitee());

        $this->client->setServerParameter('REMOTE_ADDR', '172.18.0.1');
        $activer();
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'relancez la vérification');
        self::assertFalse($this->parametres()->isConnexionLimitee());

        $this->client->setServerParameter('REMOTE_ADDR', self::IP_PUBLIQUE);
        $horloge->sleep(3601);
        $activer();
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'relancez la vérification');
        self::assertFalse($this->parametres()->isConnexionLimitee());
    }

    #[TestDox('PAR-05 : la désactivation n\'exige pas de vérification')]
    public function test_desactivation(): void
    {
        $this->modifierParametres(fn (ParametresSite $p) => $p->setConnexionLimitee(true));
        $this->connecter('admin');
        $crawler = $this->client->request('GET', self::URL);
        $jeton = $crawler->filter('[data-confirmation-action-param$="/desactiver"]')->attr('data-confirmation-token-param');

        $this->client->request('POST', self::URL.'/connexion-limitee/desactiver', ['_token' => $jeton]);

        self::assertResponseRedirects(self::URL);
        self::assertFalse($this->parametres()->isConnexionLimitee());
    }

    #[TestDox('PAR-06 : un jeton de sécurité manquant est refusé pour chaque action')]
    public function test_jetons(): void
    {
        $this->modifierParametres(fn (ParametresSite $p) => $p->setConnexionLimitee(true)->setLogo('logo.png'));
        $this->connecter('admin');
        $this->client->setServerParameter('REMOTE_ADDR', self::IP_PUBLIQUE);

        foreach (['/verifier-connexion', '/connexion-limitee/activer', '/connexion-limitee/desactiver', '/logo/retirer'] as $action) {
            $this->client->request('POST', self::URL.$action, ['_token' => 'faux', 'confirmation' => '1']);
            self::assertResponseStatusCodeSame(403, $action);
        }
        self::assertTrue($this->parametres()->isConnexionLimitee());
        self::assertSame('logo.png', $this->parametres()->getLogo());
    }

    #[TestDox('PAR-06 : le formulaire de l\'adresse de contact est refusé sans jeton de sécurité')]
    public function test_jeton_formulaire(): void
    {
        $this->connecter('admin');
        $this->client->request('POST', self::URL, ['parametres_contact' => ['adresseContact' => 'pirate@exemple.org']]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->parametres()->getAdresseContact());
    }

    #[TestDox('PAR-07 : la commande de secours coupe la limite, et le dit si elle l\'était déjà')]
    public function test_commande_de_secours(): void
    {
        $this->modifierParametres(fn (ParametresSite $p) => $p->setConnexionLimitee(true));
        $commande = new CommandTester((new Application(self::$kernel))->find('app:connexion-limitee:desactiver'));

        $commande->execute([]);
        $commande->assertCommandIsSuccessful();
        self::assertStringContainsString('désactivée', $commande->getDisplay());
        self::assertFalse($this->parametres()->isConnexionLimitee());

        $commande->execute([]);
        $commande->assertCommandIsSuccessful();
        self::assertStringContainsString('déjà', $commande->getDisplay());
    }

    #[TestDox('PAR-08 : l\'adresse de contact s\'affiche en pied de page ; la vider l\'en retire ; une adresse invalide est refusée')]
    public function test_contact(): void
    {
        $this->connecter('admin');
        $envoyer = function (string $adresse): void {
            $form = $this->client->request('GET', self::URL)->selectButton('Enregistrer l\'adresse')->form();
            $form['parametres_contact[adresseContact]'] = $adresse;
            $this->client->submit($form);
        };

        $envoyer('pas-une-adresse');
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->parametres()->getAdresseContact());

        $envoyer('x?bcc=tiers@exemple.fr');
        self::assertResponseStatusCodeSame(422, 'destinataire caché dans le lien mailto');
        self::assertNull($this->parametres()->getAdresseContact());

        $envoyer(' contact@blvb.test ');
        self::assertResponseRedirects(self::URL);
        self::assertSame('contact@blvb.test', $this->parametres()->getAdresseContact());
        $this->client->request('GET', '/');
        self::assertSelectorExists('footer a[href="mailto:contact@blvb.test"]');

        $envoyer('');
        self::assertResponseRedirects(self::URL);
        self::assertNull($this->parametres()->getAdresseContact());
        $crawler = $this->client->request('GET', '/');
        self::assertCount(0, $crawler->filter('footer a[href^="mailto:"]'));
    }

    #[TestDox('PAR-09 : un logo PNG ou WebP remplace celui de l\'en-tête, du site et de l\'admin ; l\'ancien fichier est supprimé')]
    public function test_logo(): void
    {
        $this->connecter('admin');

        $this->envoyerLogo('logo.png', base64_decode(self::PNG));
        self::assertResponseRedirects(self::URL);
        $png = $this->parametres()->getLogo();
        self::assertMatchesRegularExpression('/^logo-[0-9a-f]{16}\.png$/', $png);
        self::assertSame([$png], $this->fichiersLogos());

        $this->envoyerLogo('logo.webp', base64_decode(self::WEBP));
        self::assertResponseRedirects(self::URL);
        $webp = $this->parametres()->getLogo();
        self::assertStringEndsWith('.webp', $webp);
        self::assertSame([$webp], $this->fichiersLogos());

        foreach (['/', self::URL] as $page) {
            $crawler = $this->client->request('GET', $page);
            self::assertGreaterThan(0, $crawler->filter('img[src$="/uploads/site/'.$webp.'"]')->count(), $page);
        }
    }

    #[TestDox('PAR-09 : un SVG, un faux PNG, un fichier trop lourd ou trop grand, ou aucun fichier sont refusés')]
    public function test_logo_refuse(): void
    {
        $this->connecter('admin');
        $image = imagecreatetruecolor(2001, 1);
        ob_start();
        imagepng($image);
        $tropLarge = ob_get_clean();

        foreach ([
            'logo.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'faux.png' => '<?php echo 1;',
            'lourd.png' => base64_decode(self::PNG).str_repeat('0', 1_100_000),
            'large.png' => $tropLarge,
        ] as $nom => $contenu) {
            $this->envoyerLogo($nom, $contenu);
            self::assertResponseStatusCodeSame(422, $nom);
            self::assertNull($this->parametres()->getLogo(), $nom);
        }
        self::assertSame([], $this->fichiersLogos());

        $this->client->submit($this->client->request('GET', self::URL)->selectButton('Envoyer le logo')->form());
        self::assertResponseStatusCodeSame(422);
    }

    #[TestDox('PAR-09 : revenir au logo d\'origine supprime le fichier et rétablit le dessin')]
    public function test_logo_retire(): void
    {
        $this->connecter('admin');
        $this->envoyerLogo('logo.png', base64_decode(self::PNG));
        $crawler = $this->client->request('GET', self::URL);
        $jeton = $crawler->filter('[data-confirmation-action-param$="/logo/retirer"]')->attr('data-confirmation-token-param');

        $this->client->request('POST', self::URL.'/logo/retirer', ['_token' => $jeton]);

        self::assertResponseRedirects(self::URL);
        self::assertNull($this->parametres()->getLogo());
        self::assertSame([], $this->fichiersLogos());
        $crawler = $this->client->request('GET', '/');
        self::assertCount(0, $crawler->filter('img[src*="/uploads/site/"]'));
        self::assertGreaterThan(0, $crawler->filter('svg[aria-label^="BLVB"]')->count());
    }
}
