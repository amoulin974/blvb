<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Repository\ClubRepository;
use App\Repository\EquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** Gestion des clubs dans le back-office (App\Controller\Admin\ClubController). */
final class ClubAdminTest extends TestFonctionnel
{
    private function club(string $nom): ?Club
    {
        return self::getContainer()->get(ClubRepository::class)->findOneBy(['nom' => $nom]);
    }

    private function equipe(string $nom): Equipe
    {
        return self::getContainer()->get(EquipeRepository::class)->findOneBy(['nom' => $nom]);
    }

    #[TestDox('La liste des clubs est réservée aux administrateurs')]
    public function test_acces(): void
    {
        $this->client->request('GET', '/admin/club/');
        $this->assertRedirigeVersConnexion('visiteur');

        $this->connecter('resp.club');
        $this->client->request('GET', '/admin/club/');
        $this->assertAccesRefuse('responsable de club');

        $this->connecter('admin');
        $this->client->request('GET', '/admin/club/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Club test A1-B1');
        self::assertSelectorTextContains('table', 'Rene Responsable');
    }

    #[TestDox('L\'admin crée un club avec ses équipes, ses gymnases et son responsable')]
    public function test_creation(): void
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/club/new');
        $form = $crawler->selectButton('Créer le club')->form();
        $form['club[nom]'] = 'Club créé';
        $form['club[equipes]']->select([(string) $this->equipe('EQUIPE A2')->getId()]);
        $form['club[lieux]']->select([(string) ChampionnatDeTest::get('equipe.A2')->getLieu()->getId()]);
        $form['club[responsables]']->select([(string) ChampionnatDeTest::compte('cap.a2')->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/club/');
        $club = $this->club('Club créé');
        self::assertNotNull($club);
        self::assertSame('Club créé', $this->equipe('EQUIPE A2')->getClub()?->getNom());
        self::assertCount(1, $club->getLieux());
        self::assertContains('ROLE_RESPONSABLE_CLUB', ChampionnatDeTest::compte('cap.a2')->getRoles());
    }

    #[TestDox('Rattacher une équipe à un club la retire de son club précédent')]
    public function test_deplacer_une_equipe(): void
    {
        $this->connecter('admin');
        $voisin = $this->club('Club test voisin');
        $crawler = $this->client->request('GET', '/admin/club/'.$voisin->getId().'/edit');
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['club[equipes]']->select([(string) $this->equipe('EQUIPE A3')->getId(), (string) $this->equipe('EQUIPE A1')->getId()]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/club/');
        self::assertSame('Club test voisin', $this->equipe('EQUIPE A1')->getClub()?->getNom());
        self::assertSame(['EQUIPE B1'], $this->club('Club test A1-B1')->getEquipes()->map(fn (Equipe $e) => $e->getNom())->getValues());
    }

    #[TestDox('Supprimer un club garde ses équipes, sans club, et retire le rôle de responsable')]
    public function test_suppression(): void
    {
        $this->connecter('admin');
        $club = $this->club('Club test A1-B1');
        $crawler = $this->client->request('GET', '/admin/club/'.$club->getId().'/edit');
        // Jeton porté par le bouton qui ouvre la fenêtre de confirmation (admin/_confirmation.html.twig)
        $jeton = $crawler->filter('[data-confirmation-action-param$="/admin/club/'.$club->getId().'"]')->attr('data-confirmation-token-param');
        $this->client->request('POST', '/admin/club/'.$club->getId(), ['_token' => $jeton]);

        self::assertResponseRedirects('/admin/club/');
        self::assertNull($this->club('Club test A1-B1'));
        self::assertNull($this->equipe('EQUIPE A1')->getClub());
        self::assertNotContains('ROLE_RESPONSABLE_CLUB', ChampionnatDeTest::compte('resp.club')->getRoles());
    }

    #[TestDox('Les listes des équipes et des utilisateurs affichent le club et le rôle de responsable')]
    public function test_listes_equipes_et_utilisateurs(): void
    {
        $this->connecter('admin');

        $crawler = $this->client->request('GET', '/admin/equipe');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Club test A1-B1', $crawler->filter('tr:contains("EQUIPE A1")')->text());

        $crawler = $this->client->request('GET', '/admin/user');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Responsable de club', $crawler->filter('tr:contains("resp.club@blvb.test")')->text());

        $this->client->request('GET', '/admin/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="equipe[club]"] option[selected]');
    }
}
