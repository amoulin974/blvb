<?php

namespace App\Tests\Fonctionnel\Front;

use App\Repository\EquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le nom court du club est ajouté entre parenthèses au nom affiché de l'équipe (« EQUIPE A1 (CTA) »),
 * alors que le nom enregistré et modifié dans l'admin reste « EQUIPE A1 ».
 */
final class NomEquipeAfficheTest extends TestFonctionnel
{
    #[TestDox('Le calendrier affiche le nom de l\'équipe suivi du nom court de son club')]
    public function test_calendrier(): void
    {
        $crawler = $this->client->request('GET', '/calendrier');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('EQUIPE A1 (CTA)', $crawler->filter('body')->text());
        self::assertStringContainsString('EQUIPE A3 (CTV)', $crawler->filter('body')->text());
        self::assertStringNotContainsString('EQUIPE A2 (', $crawler->filter('body')->text(), 'A2 n\'a pas de club');
    }

    #[TestDox('La fiche équipe affiche aussi le nom court du club dans la liste des matchs')]
    public function test_fiche_equipe(): void
    {
        $crawler = $this->client->request('GET', '/equipe/'.ChampionnatDeTest::get('equipe.A2')->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('EQUIPE A1 (CTA)', $crawler->filter('body')->text());
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
        self::assertSame('EQUIPE A1 RENOMMEE (CTA)', $equipe->getNom());
    }
}
