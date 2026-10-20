<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Reglement;
use App\Entity\Saison;
use App\Factory\SaisonFactory;
use App\Reglement\EditionReglement;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** RGL-12 à RGL-15 : page publique du règlement de la saison affichée (/reglement, D57). */
final class ReglementPageTest extends TestFonctionnel
{
    private function enregistrer(int $saisonId, string $contenu): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        self::getContainer()->get(EditionReglement::class)->enregistrer($em->find(Saison::class, $saisonId), $contenu, null);
    }

    #[TestDox('RGL-12 Règlement de la saison affichée : titre, date de mise à jour, sommaire, ancres, éléments calculés ; rien d\'un code inapplicable pour un visiteur')]
    public function test_page_publique(): void
    {
        $this->enregistrer(ChampionnatDeTest::get('saison')->getId(),
            '<h2>Classement</h2><p>[departage]</p><h2>Rencontres</h2><h3>RÈGLE 1R : Partie interrompue</h3><p>Texte.</p><p>[bareme phase=ZZ]</p>');

        $crawler = $this->client->request('GET', '/reglement');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Règlement de la saison test');
        self::assertSelectorTextContains('#article-reglement', 'Mis à jour le '.(new \IntlDateFormatter('fr_FR', 0, 0, null, null, 'd MMMM yyyy'))->format(new \DateTimeImmutable()));
        self::assertCount(1, $crawler->filter('nav[aria-label="Sommaire du règlement"]'));
        self::assertCount(1, $crawler->filter('h2#classement'));
        self::assertCount(1, $crawler->filter('h3#regle-1r'));
        self::assertCount(1, $crawler->filter('section[data-bloc="departage"]'));
        self::assertSelectorTextNotContains('#article-reglement', 'Élément calculé non affiché');
        self::assertSelectorTextNotContains('#article-reglement', '[bareme');
    }

    #[TestDox('RGL-13 Un admin voit l\'encart d\'un code inapplicable ; un texte enregistré sans passer par l\'éditeur est nettoyé à l\'affichage')]
    public function test_admin_et_nettoyage(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist((new Reglement())->setSaison($em->find(Saison::class, ChampionnatDeTest::get('saison')->getId()))
            ->setContenu('<p onclick="alert(1)">Texte</p><script>alert(1)</script><p>[bareme phase=ZZ]</p>'));
        $em->flush();

        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/reglement');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#article-reglement', 'Élément calculé non affiché au public');
        self::assertSelectorTextContains('#article-reglement', '[bareme phase=ZZ]');
        $article = $crawler->filter('#article-reglement')->html();
        self::assertStringNotContainsString('<script', $article);
        self::assertStringNotContainsString('onclick', $article);
    }

    #[TestDox('RGL-14 Saison sans règlement : message, sans sommaire ; le règlement d\'un brouillon n\'est jamais affiché')]
    public function test_sans_reglement_et_brouillon(): void
    {
        $brouillon = SaisonFactory::createOne(['nom' => 'Saison brouillon', 'brouillon' => true, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+2 years'), 'date_fin' => new \DateTimeImmutable('+3 years')])->getId();
        $this->enregistrer($brouillon, '<p>Règlement du brouillon</p>');

        $this->client->request('POST', '/set-saison', ['idSaisonSelected' => $brouillon]);
        $crawler = $this->client->request('GET', '/reglement');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#article-reglement', 'Le règlement de cette saison n\'est pas encore publié.');
        self::assertSelectorTextNotContains('body', 'Règlement du brouillon');
        self::assertCount(0, $crawler->filter('nav[aria-label="Sommaire du règlement"]'));
    }

    #[TestDox('RGL-15 Changer de saison depuis une page y revient (ex. le règlement) ; une adresse de retour externe ramène à l\'accueil ; une saison passée est signalée')]
    public function test_retour_du_selecteur(): void
    {
        $precedente = ChampionnatDeTest::get('saison.precedente')->getId();
        $this->enregistrer($precedente, '<p>Règlement de la saison précédente</p>');

        $this->client->request('POST', '/set-saison', ['idSaisonSelected' => $precedente, 'retour' => '/reglement']);
        self::assertResponseRedirects('/reglement');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Règlement de la saison test précédente');
        self::assertSelectorTextContains('#article-reglement', 'Ce règlement n\'est pas celui de la saison en cours.');

        foreach (['//exemple.org/reglement', '/\\exemple.org', 'https://exemple.org', 'reglement', '/set-saison', "/reglement\n", '/a/../..//exemple.org'] as $retour) {
            $this->client->request('POST', '/set-saison', ['idSaisonSelected' => $precedente, 'retour' => $retour]);
            self::assertResponseRedirects('/', message: $retour);
        }
    }
}
