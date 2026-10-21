<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Factory\SaisonFactory;
use App\Reglement\EditionReglement;
use App\Repository\ReglementRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** RGL-16 à RGL-22 : page d'édition du règlement d'une saison (ReglementController, D59). */
final class ReglementAdminTest extends TestFonctionnel
{
    private int $saisonId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saisonId = ChampionnatDeTest::get('saison')->getId();
    }

    private function url(string $suite = ''): string
    {
        return '/admin/saison/'.$this->saisonId.'/reglement'.$suite;
    }

    /** Jeton CSRF et jeton de version lus sur la page d'édition */
    private function ouvrir(): array
    {
        $crawler = $this->client->request('GET', $this->url());
        $editeur = $crawler->filter('[data-controller="reglement-editeur"]');

        return [$editeur->attr('data-reglement-editeur-token-value'), (string) $editeur->attr('data-reglement-editeur-jeton-value')];
    }

    private function poster(string $suite, array $donnees): array
    {
        $this->client->request('POST', $this->url($suite), $donnees, [], ['HTTP_ACCEPT' => 'application/json']);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function reglement(): ?\App\Entity\Reglement
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return self::getContainer()->get(ReglementRepository::class)->findDeSaison($em->find(Saison::class, $this->saisonId));
    }

    #[TestDox('RGL-16 Page d\'édition réservée aux admins : éditeur, panneau des quatre éléments calculés avec leur code, liens d\'accès depuis la saison et le menu')]
    public function test_acces(): void
    {
        $this->client->request('GET', $this->url());
        $this->assertRedirigeVersConnexion();
        $this->connecter('cap.a1');
        $this->client->request('GET', $this->url());
        $this->assertAccesRefuse();

        $this->connecter('admin');
        $crawler = $this->client->request('GET', $this->url());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Règlement de la saison test');
        self::assertSame(['schema_phases', 'bareme', 'departage', 'calendrier'], $crawler->filter('details[data-code]')->each(fn ($n) => $n->attr('data-code')));
        self::assertSelectorTextContains('details[data-code="bareme"] [data-role="code"]', '[bareme]');
        self::assertSelectorTextContains('button[data-reglement-editeur-target="bouton"]', 'Enregistrer et publier');

        $crawler = $this->client->request('GET', '/admin/saison/'.$this->saisonId);
        self::assertCount(1, $crawler->filter('a[href="'.$this->url().'"]:contains("Rédiger le règlement")'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('nav a[href="'.$this->url().'"]')->count(), 'entrée « Règlement » du menu');
    }

    #[TestDox('RGL-17 Enregistrer publie le texte nettoyé ; une autre version publiée entre-temps : 409 avec l\'auteur, sauf « forcer » ; jeton CSRF exigé ; texte trop long refusé (422) ; contenu non nettoyé en base nettoyé dans la page d\'édition')]
    public function test_enregistrer_et_conflit(): void
    {
        $this->connecter('admin');
        [$token, $jeton] = $this->ouvrir();

        self::assertSame([], array_diff_key($this->poster('/enregistrer', ['_token' => 'faux', 'contenu' => '<p>X</p>', 'jeton' => $jeton]), ['message' => 1]));
        self::assertResponseStatusCodeSame(403);

        $reponse = $this->poster('/enregistrer', ['_token' => $token, 'contenu' => '<p onclick="x()">V1</p><script>x()</script>', 'jeton' => $jeton]);
        self::assertResponseIsSuccessful();
        self::assertSame('<p>V1</p>', $this->reglement()->getContenu());
        $jetonV1 = $reponse['jeton'];

        // Un autre admin a publié depuis l'ouverture de la page (ancien jeton) : rien n'est enregistré
        $reponse = $this->poster('/enregistrer', ['_token' => $token, 'contenu' => '<p>V2</p>', 'jeton' => $jeton]);
        self::assertResponseStatusCodeSame(409);
        self::assertTrue($reponse['conflit']);
        self::assertStringContainsString('a publié une autre version à', $reponse['message']);
        self::assertSame('<p>V1</p>', $this->reglement()->getContenu());

        $this->poster('/enregistrer', ['_token' => $token, 'contenu' => '<p>V2</p>', 'jeton' => $jeton, 'forcer' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSame('<p>V2</p>', $this->reglement()->getContenu());
        self::assertCount(1, $this->reglement()->getVersions());
        [, $jetonV2] = $this->ouvrir();
        self::assertNotSame($jetonV1, $jetonV2);

        // Texte plus long que ce que le sanitizer accepte : refusé avec un message, au lieu d'être tronqué sans prévenir
        $long = '<p>'.str_repeat('a', EditionReglement::TAILLE_MAX).'</p>';
        $reponse = $this->poster('/enregistrer', ['_token' => $token, 'contenu' => $long, 'jeton' => $jetonV2]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Le texte est trop long', $reponse['message']);
        self::assertSame('<p>V2</p>', $this->reglement()->getContenu());
        $this->poster('/apercu', ['_token' => $token, 'contenu' => $long]);
        self::assertResponseStatusCodeSame(422);

        // Contenu enregistré sans passer par le nettoyage (reprise, import) : nettoyé de nouveau dans la page d'édition
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($this->reglement()->setContenu('<p>V3</p></template><script>alert(1)</script>'));
        $em->flush();
        $this->client->request('GET', $this->url());
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
    }

    #[TestDox('RGL-18 Aperçu du texte non enregistré : éléments calculés et codes inapplicables, rien n\'est enregistré')]
    public function test_apercu(): void
    {
        $this->connecter('admin');
        [$token] = $this->ouvrir();

        $reponse = $this->poster('/apercu', ['_token' => $token, 'contenu' => '<p>[departage]</p><p>[bareme phase=ZZ]</p>']);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('data-bloc="departage"', $reponse['html']);
        self::assertStringContainsString('Élément calculé non affiché au public', $reponse['html']);
        self::assertStringContainsString('1 code n\'est pas affiché au public', $reponse['inapplicables']);
        self::assertNull($this->reglement());
    }

    #[TestDox('RGL-19 Résultat d\'un élément calculé (« Voir le résultat ») : rendu, encart si la phase n\'existe pas, 404 pour un code inconnu')]
    public function test_element(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', $this->url('/element/departage'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('data-bloc="departage"', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', $this->url('/element/bareme?phase=ZZ'));
        self::assertStringContainsString('aucune phase « ZZ » dans cette saison', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', $this->url('/element/inconnu'));
        self::assertResponseStatusCodeSame(404);
    }

    #[TestDox('RGL-20 Envoi d\'image : JPG, PNG ou WebP (type réel), 2 Mo au plus ; adresse sous /uploads/reglement')]
    public function test_image(): void
    {
        $this->connecter('admin');
        [$token] = $this->ouvrir();
        $png = sys_get_temp_dir().'/reglement-'.bin2hex(random_bytes(3)).'.png';
        imagepng(imagecreatetruecolor(8, 8), $png);
        $faux = sys_get_temp_dir().'/reglement-'.bin2hex(random_bytes(3)).'.png';
        file_put_contents($faux, '<?php echo "piège";');

        $this->client->request('POST', $this->url('/image'), ['_token' => $token], ['image' => new UploadedFile($png, 'filet.png', 'image/png', null, true)]);
        self::assertResponseIsSuccessful();
        $url = json_decode((string) $this->client->getResponse()->getContent(), true)['url'];
        self::assertMatchesRegularExpression('#^/uploads/reglement/[0-9a-f]{24}\.png$#', $url);
        self::assertFileExists(self::getContainer()->getParameter('app.dossier_images_reglement').'/'.basename($url));

        $this->client->request('POST', $this->url('/image'), ['_token' => $token], ['image' => new UploadedFile($faux, 'piege.png', 'image/png', null, true)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Format non accepté : JPG, PNG ou WebP.', json_decode((string) $this->client->getResponse()->getContent(), true)['message']);

        $autre = sys_get_temp_dir().'/reglement-'.bin2hex(random_bytes(3)).'.png';
        imagepng(imagecreatetruecolor(8, 8), $autre);
        $this->client->request('POST', $this->url('/image'), ['_token' => 'faux'], ['image' => new UploadedFile($autre, 'filet.png', 'image/png', null, true)]);
        self::assertResponseStatusCodeSame(403);
    }

    #[TestDox('RGL-21 Historique : version publiée et versions précédentes ; consulter une version ; la restaurer la republie (jeton CSRF) ; version d\'une autre saison : 404')]
    public function test_historique_et_restauration(): void
    {
        $edition = self::getContainer()->get(EditionReglement::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $edition->enregistrer($em->find(Saison::class, $this->saisonId), '<p>Ancienne version</p>', null);
        $edition->enregistrer($em->find(Saison::class, $this->saisonId), '<p>Version actuelle</p>', null);
        $version = $this->reglement()->getVersions()->first();

        $this->connecter('admin');
        $crawler = $this->client->request('GET', $this->url('/historique'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Version publiée');
        $restaurer = $crawler->filter('[data-confirmation-action-param$="/restaurer"]');
        self::assertCount(1, $restaurer);

        $this->client->request('GET', $this->url('/version/'.$version->getId()));
        self::assertSelectorTextContains('article', 'Ancienne version');

        $this->client->request('POST', $restaurer->attr('data-confirmation-action-param'), ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $restaurer->attr('data-confirmation-action-param'), ['_token' => $restaurer->attr('data-confirmation-token-param')]);
        self::assertResponseRedirects($this->url());
        self::assertSame('<p>Ancienne version</p>', $this->reglement()->getContenu());

        // Version d'une autre saison passée dans l'adresse de cette saison : 404, rien n'est restauré
        $autre = ChampionnatDeTest::get('saison.precedente')->getId();
        $edition->enregistrer($em->find(Saison::class, $autre), '<p>Autre saison, ancienne</p>', null);
        $edition->enregistrer($em->find(Saison::class, $autre), '<p>Autre saison, actuelle</p>', null);
        $em->clear();
        $versionAutre = self::getContainer()->get(ReglementRepository::class)->findDeSaison($em->find(Saison::class, $autre))->getVersions()->first();
        $this->client->request('GET', $this->url('/version/'.$versionAutre->getId()));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->url('/version/'.$versionAutre->getId().'/restaurer'), ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('<p>Ancienne version</p>', $this->reglement()->getContenu());
    }

    #[TestDox('RGL-22 Saison en brouillon : « Enregistrer », pas de lien public ; « Voir la page publique » d\'un admin ouvre la saison demandée (jamais un brouillon), sans effet pour un visiteur')]
    public function test_brouillon_et_page_publique(): void
    {
        $brouillon = SaisonFactory::createOne(['nom' => 'Saison brouillon', 'brouillon' => true, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+2 years'), 'date_fin' => new \DateTimeImmutable('+3 years')])->getId();
        $precedente = ChampionnatDeTest::get('saison.precedente')->getId();
        $em = self::getContainer()->get('doctrine')->getManager();
        self::getContainer()->get(EditionReglement::class)->enregistrer($em->find(Saison::class, $precedente), '<p>Règlement précédent</p>', null);
        self::getContainer()->get(EditionReglement::class)->enregistrer($em->find(Saison::class, $brouillon), '<p>Règlement du brouillon</p>', null);

        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/'.$brouillon.'/reglement');
        self::assertSelectorTextContains('button[data-reglement-editeur-target="bouton"]', 'Enregistrer');
        self::assertSelectorTextNotContains('button[data-reglement-editeur-target="bouton"]', 'publier');
        self::assertSelectorTextContains('body', 'Brouillon : visible par le public quand la saison sera lancée');
        self::assertCount(0, $crawler->filter('a:contains("Voir la page publique")'));

        $this->client->request('GET', '/reglement?saison='.$precedente);
        self::assertSelectorTextContains('#article-reglement', 'Règlement précédent');
        $this->client->request('GET', '/reglement?saison='.$brouillon);
        self::assertSelectorTextNotContains('body', 'Règlement du brouillon');

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/reglement?saison='.$precedente);
        self::assertSelectorTextNotContains('body', 'Règlement précédent');
    }
}
