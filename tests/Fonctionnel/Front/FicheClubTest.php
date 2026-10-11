<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Club;
use App\Repository\ClubRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Fiche publique d'un club et sa modification par ses responsables (App\Security\ClubVoter) :
 * nom long, abréviation, description et lien (ces deux derniers facultatifs).
 */
final class FicheClubTest extends TestFonctionnel
{
    /** Club relu en base (le gestionnaire d'entités est vidé : un formulaire refusé a pu modifier l'objet en mémoire). */
    private function club(string $cle = 'club'): Club
    {
        self::getContainer()->get('doctrine')->getManager()->clear();

        return self::getContainer()->get(ClubRepository::class)->find(ChampionnatDeTest::get($cle)->getId());
    }

    private function urlModifier(string $cle = 'club'): string
    {
        return '/club/'.ChampionnatDeTest::get($cle)->getId().'/modifier';
    }

    #[TestDox('La fiche du club est publique : nom long, abréviation, équipes, gymnases ; la fiche équipe y mène')]
    public function test_fiche_publique(): void
    {
        $crawler = $this->client->request('GET', '/club/'.ChampionnatDeTest::get('club')->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Club test A1-B1');
        self::assertSelectorTextContains('body', 'CTA');
        self::assertSelectorTextContains('body', 'Equipe b1 (CTA)');
        self::assertSelectorTextContains('body', 'Gymnase G2');
        self::assertSelectorNotExists('a[href$="/modifier"]', 'pas de bouton de modification pour un visiteur');

        $crawler = $this->client->request('GET', '/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId());
        self::assertCount(1, $crawler->filter('a[href="/club/'.ChampionnatDeTest::get('club')->getId().'"]'));
    }

    #[TestDox('Le responsable modifie la fiche de son club : nom long, abréviation, description, lien')]
    public function test_responsable_modifie(): void
    {
        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', '/club/'.ChampionnatDeTest::get('club')->getId());
        self::assertSelectorExists('a[href$="/modifier"]');

        $crawler = $this->client->request('GET', $this->urlModifier());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[nom]'] = 'Club test renommé';
        $form['club_fiche[nomCourt]'] = 'CTR';
        $form['club_fiche[description]'] = "Entraînements le mardi.\nTous niveaux.";
        $form['club_fiche[url]'] = 'https://club.example.org';
        $this->client->submit($form);

        self::assertResponseRedirects('/club/'.ChampionnatDeTest::get('club')->getId());
        $club = $this->club();
        self::assertSame('Club test renommé', $club->getNom());
        self::assertSame('CTR', $club->getNomCourt());
        self::assertSame("Entraînements le mardi.\nTous niveaux.", $club->getDescription());
        self::assertSame('https://club.example.org', $club->getUrl());
        self::assertSame('Equipe a1 (CTR)', $club->getEquipes()->first()->getNom());
    }

    #[TestDox('La description et le lien peuvent rester vides')]
    public function test_champs_facultatifs(): void
    {
        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', $this->urlModifier());
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[description]'] = '   ';
        $form['club_fiche[url]'] = '';
        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertNull($this->club()->getDescription());
        self::assertNull($this->club()->getUrl());
    }

    #[TestDox('Un lien qui n\'est pas une adresse web est refusé avec un message')]
    public function test_lien_invalide(): void
    {
        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', $this->urlModifier());
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[url]'] = 'pas une adresse';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Indiquez une adresse web complète');
        self::assertNull($this->club()->getUrl());
    }

    #[TestDox('Seuls les responsables du club et l\'admin peuvent modifier la fiche')]
    public function test_acces_modification(): void
    {
        $this->client->request('GET', $this->urlModifier());
        $this->assertRedirigeVersConnexion('visiteur');

        $this->connecter('cap.a1');
        $this->client->request('GET', $this->urlModifier());
        $this->assertAccesRefuse('capitaine d\'une équipe du club, non responsable');

        $this->connecter('resp.club');
        $this->client->request('GET', $this->urlModifier('club.voisin'));
        $this->assertAccesRefuse('responsable d\'un autre club');

        $this->connecter('admin');
        $this->client->request('GET', $this->urlModifier('club.voisin'));
        self::assertResponseIsSuccessful();
    }

    /** Image PNG minimale fabriquée pour le test. */
    private function imagePng(): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        $chemin = sys_get_temp_dir().'/logo-test-'.bin2hex(random_bytes(3)).'.png';
        $image = imagecreatetruecolor(8, 8);
        imagepng($image, $chemin);

        return new \Symfony\Component\HttpFoundation\File\UploadedFile($chemin, 'logo.png', 'image/png', null, true);
    }

    private function dossierImages(): string
    {
        return self::getContainer()->getParameter('app.dossier_images_clubs');
    }

    #[TestDox('Le responsable ajoute une image au club, affichée sur la fiche et la liste, puis la retire')]
    public function test_image(): void
    {
        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', $this->urlModifier());
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[imageFichier]']->upload($this->imagePng()->getPathname());
        $this->client->submit($form);

        self::assertResponseRedirects();
        $image = $this->club()->getImage();
        self::assertNotNull($image);
        self::assertStringStartsWith('cta-', $image);
        self::assertFileExists($this->dossierImages().'/'.$image);

        $this->client->request('GET', '/club/'.ChampionnatDeTest::get('club')->getId());
        self::assertSelectorExists('img[src$="/uploads/clubs/'.$image.'"]');
        $this->client->request('GET', '/clubs');
        self::assertSelectorExists('img[src$="/uploads/clubs/'.$image.'"]');

        $crawler = $this->client->request('GET', $this->urlModifier());
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[supprimerImage]']->tick();
        $this->client->submit($form);

        self::assertNull($this->club()->getImage());
        self::assertFileDoesNotExist($this->dossierImages().'/'.$image);
    }

    #[TestDox('Un fichier qui n\'est pas une image est refusé')]
    public function test_image_refusee(): void
    {
        $this->connecter('resp.club');
        $chemin = sys_get_temp_dir().'/pas-une-image.txt';
        file_put_contents($chemin, 'texte');
        $crawler = $this->client->request('GET', $this->urlModifier());
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club_fiche[imageFichier]']->upload($chemin);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisissez une image JPEG, PNG, WebP ou GIF.');
        self::assertNull($this->club()->getImage());
    }

    #[TestDox('La liste des clubs, triée par nom long, mène aux fiches ; le menu « Le championnat » y mène')]
    public function test_liste_des_clubs(): void
    {
        $crawler = $this->client->request('GET', '/clubs');

        self::assertResponseIsSuccessful();
        $noms = $crawler->filter('main ul li a span.text-lg')->each(fn ($n) => $n->text());
        self::assertSame(['Club test A1-B1', 'Club test voisin'], $noms);
        self::assertCount(1, $crawler->filter('a[href="/club/'.ChampionnatDeTest::get('club')->getId().'"]'));
        self::assertStringContainsString('2 équipes en Saison test', $crawler->filter('main')->text());

        $crawler = $this->client->request('GET', '/');
        self::assertGreaterThanOrEqual(1, $crawler->filter('a[href="/clubs"]')->count());
    }

    #[TestDox('La fiche du club ne liste que les équipes engagées dans la saison en cours, avec leur poule')]
    public function test_equipes_de_la_saison(): void
    {
        \App\Factory\EquipeFactory::createOne(['nom' => 'EQUIPE NON ENGAGEE', 'club' => ChampionnatDeTest::get('club')]);

        $crawler = $this->client->request('GET', '/club/'.ChampionnatDeTest::get('club')->getId());

        $equipes = $crawler->filter('#titre-equipes + ul li')->each(fn ($li) => preg_replace('/\s+/', ' ', trim($li->text())));
        self::assertCount(2, $equipes);
        self::assertStringStartsWith('Equipe a1 (CTA)', $equipes[0]);
        self::assertStringContainsString('Poule', $equipes[0]);
        self::assertStringStartsWith('Equipe b1 (CTA)', $equipes[1]);
        self::assertStringNotContainsString('Equipe non engagee', $crawler->filter('body')->text());
    }

    #[TestDox('La page « Qui sommes-nous ? » liste les clubs en lien vers leur fiche')]
    public function test_qui_sommes_nous(): void
    {
        $crawler = $this->client->request('GET', '/qui-sommes-nous');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('main a[href="/club/'.ChampionnatDeTest::get('club.voisin')->getId().'"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('main a[href="/clubs"]')->count());
    }
}
