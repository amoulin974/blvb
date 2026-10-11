<?php

namespace App\Tests\Fonctionnel\Front;

use App\Repository\EquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Nom affiché d'une équipe : première lettre en majuscule, le reste en minuscules, suivi de l'abréviation du club
 * en majuscules (« Equipe a1 (CTA) ») ; en majuscules dans les titres. Le nom enregistré reste « EQUIPE A1 ».
 */
final class NomEquipeAfficheTest extends TestFonctionnel
{
    #[TestDox('Le calendrier affiche le nom de l\'équipe suivi du nom court de son club')]
    public function test_calendrier(): void
    {
        $crawler = $this->client->request('GET', '/calendrier');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Equipe a1 (CTA)', $crawler->filter('body')->text());
        self::assertStringContainsString('Equipe a3 (CTV)', $crawler->filter('body')->text());
        self::assertStringNotContainsString('Equipe a2 (', $crawler->filter('body')->text(), 'A2 n\'a pas de club');
    }

    #[TestDox('La fiche équipe affiche aussi le nom court du club dans la liste des matchs')]
    public function test_fiche_equipe(): void
    {
        $crawler = $this->client->request('GET', '/equipe/'.ChampionnatDeTest::get('equipe.A2')->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Equipe a1 (CTA)', $crawler->filter('body')->text());
    }

    #[TestDox('Le formulaire de l\'équipe modifie le nom sans le club, qui n\'est pas ajouté une seconde fois')]
    public function test_formulaire_admin(): void
    {
        $this->connecter('admin');
        $id = ChampionnatDeTest::get('equipe.A1')->getId();
        $crawler = $this->client->request('GET', '/admin/equipe/'.$id.'/edit');

        $form = $crawler->selectButton('Enregistrer')->form();
        self::assertSame('EQUIPE A1', $form['equipe[nomSansClub]']->getValue());
        $form['equipe[nomSansClub]'] = 'EQUIPE A1 RENOMMEE';
        $this->client->submit($form);

        $equipe = self::getContainer()->get(EquipeRepository::class)->find($id);
        self::assertSame('EQUIPE A1 RENOMMEE', $equipe->getNomSansClub());
        self::assertSame('Equipe a1 renommee (CTA)', $equipe->getNom());
    }

    #[TestDox('Mise en forme : première lettre en majuscule, abréviation du club en majuscules')]
    public function test_mise_en_forme(): void
    {
        self::assertSame('Les filous du filet (ASPTT)', \App\Entity\Equipe::formaterNom('LES FILOUS DU FILET', 'ASPTT'));
        self::assertSame('Titi et ses gros minets', \App\Entity\Equipe::formaterNom('Titi et ses gros minets'));
        self::assertSame('Leo lagrange (LÉO)', \App\Entity\Equipe::formaterNom(' LEO LAGRANGE', 'Léo'));
        self::assertSame('Équipe éclair', \App\Entity\Equipe::formaterNom('éQUIPE ÉCLAIR'));
    }

    #[TestDox('Dans les titres, le nom de l\'équipe est en majuscules')]
    public function test_titre_en_majuscules(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', '/admin/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId());

        self::assertSelectorTextSame('h1', 'EQUIPE A1 (CTA)');
    }

    #[TestDox('La page des équipes affiche l\'abréviation du club en lien vers sa fiche, avec le nom long en infobulle')]
    public function test_page_equipes(): void
    {
        $crawler = $this->client->request('GET', '/equipes');

        self::assertResponseIsSuccessful();
        $lien = $crawler->filter('a[href="/club/'.ChampionnatDeTest::get('club')->getId().'"]')->first();
        self::assertSame('Club test A1-B1', $lien->attr('title'));
        self::assertStringContainsString('(CTA)', $lien->text());
    }
}
