<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Factory\SaisonFactory;
use App\Repository\SaisonRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** WIZ-01 : une saison en brouillon (wizard de création) est invisible sur le site et ne peut pas y être affichée (D29). */
final class BrouillonSaisonTest extends TestFonctionnel
{
    private const NOM = 'Saison brouillon du wizard';

    private function brouillon(): int
    {
        // La plus récente de toutes : sans la règle des brouillons, elle serait choisie par défaut
        return SaisonFactory::createOne(['nom' => self::NOM, 'brouillon' => true, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+2 years'), 'date_fin' => new \DateTimeImmutable('+3 years')])->getId();
    }

    #[TestDox('WIZ-01 Le site ne propose pas le brouillon, même demandé explicitement ; il ne devient jamais la saison par défaut')]
    public function test_invisible_sur_le_site(): void
    {
        $id = $this->brouillon();

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', self::NOM);

        $this->client->request('POST', '/set-saison', ['idSaisonSelected' => $id]);
        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('body', self::NOM);

        $repo = self::getContainer()->get(SaisonRepository::class);
        self::assertNull($repo->findPubliee($id));
        self::assertNotSame($id, $repo->findPlusRecentePubliee()?->getId());
    }

    #[TestDox('WIZ-01 L\'agenda .ics d\'une équipe n\'existe pas pour un brouillon (adresse devinée en changeant l\'identifiant)')]
    public function test_agenda_ics(): void
    {
        $id = $this->brouillon();
        $equipe = ChampionnatDeTest::get('equipe.A1')->getId();

        $this->client->request('GET', '/calendar/saison/'.$id.'/equipe/'.$equipe.'.ics');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/calendar/saison/'.ChampionnatDeTest::get('saison')->getId().'/equipe/'.$equipe.'.ics');
        self::assertResponseIsSuccessful();
    }

    #[TestDox('WIZ-01 Un brouillon ne peut pas être « affiché sur le site », même par une requête directe avec un jeton valide')]
    public function test_pas_affichable(): void
    {
        $id = $this->brouillon();
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/saison');
        self::assertCount(0, $crawler->filter('form[action$="/admin/saison/'.$id.'/favorite/"]'), 'le bouton « Afficher sur le site » n\'est pas proposé');
        self::assertSelectorTextContains('body', 'Brouillon · étape');
        // Requête forgée par un administrateur : jeton valide de la session
        $requete = $this->client->getRequest();
        $pile = $this->client->getContainer()->get('request_stack');
        $pile->push($requete);
        $jeton = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('favorite'.$id)->getValue();
        $requete->getSession()->save();
        $pile->pop();

        $this->client->request('POST', '/admin/saison/'.$id.'/favorite/', ['_token' => $jeton]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'est un brouillon : terminez sa création');

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertSame(0, (int) $em->find(Saison::class, $id)->getFavori());
    }
}
