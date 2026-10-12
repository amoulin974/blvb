<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Creneau;
use App\Entity\Equipe;
use App\Entity\Joueur;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\MembreEquipe;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\ResetPasswordRequest;
use App\Entity\Saison;
use App\Entity\User;
use App\Factory\CreneauFactory;
use App\Factory\LieuFactory;
use App\Factory\MembreEquipeFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SUP-01 à SUP-10 : suppressions dans l'admin, depuis la fenêtre de confirmation (jeton du bouton), message après
 * suppression ; un objet encore utilisé n'est pas supprimé (message, pas d'erreur 500).
 * Jetons manquants ou faux : SEC-08 (JetonsAdminTest). Saison affichée : ReglesAdminTest (A6).
 */
final class SuppressionTest extends TestFonctionnel
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter('admin');
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em;
    }

    /** Envoie la suppression telle que la fenêtre de confirmation de $page la ferait pour l'adresse $action. */
    private function supprimer(string $page, string $action): string
    {
        $crawler = $this->client->request('GET', $page);
        $bouton = $crawler->filter(sprintf('[data-confirmation-action-param="%s"]', $action));
        self::assertGreaterThan(0, $bouton->count(), "bouton de suppression de $action sur $page");
        $this->client->request('POST', $action, ['_token' => $bouton->first()->attr('data-confirmation-token-param')]);
        self::assertLessThan(500, $this->client->getResponse()->getStatusCode(), 'pas d\'erreur 500');
        $cible = (string) $this->client->getResponse()->headers->get('Location');
        $this->client->followRedirect();

        return $cible.' '.$this->client->getCrawler()->filter('main')->text();
    }

    #[TestDox('SUP-01 Équipe sans match : supprimée, sa composition aussi, les fiches joueurs restent ; retirée des poules')]
    public function test_equipe_sans_match(): void
    {
        $b3 = ChampionnatDeTest::get('equipe.B3')->getId();
        MembreEquipeFactory::createOne(['equipe' => ChampionnatDeTest::get('equipe.B3'), 'saison' => ChampionnatDeTest::get('saison'), 'joueur' => ChampionnatDeTest::get('joueur.joueur.a2')]);

        $retour = $this->supprimer("/admin/equipe/$b3/edit", "/admin/equipe/$b3");

        self::assertStringContainsString('a été supprimée', $retour);
        $em = $this->em();
        self::assertNull($em->find(Equipe::class, $b3));
        self::assertSame(0, $em->getRepository(MembreEquipe::class)->count(['equipe' => $b3]));
        self::assertNotNull($em->find(Joueur::class, ChampionnatDeTest::get('joueur.joueur.a2')->getId()), 'fiche joueur conservée');
        self::assertCount(2, $em->find(Poule::class, ChampionnatDeTest::get('poule.B')->getId())->getEquipes(), 'retirée de la poule B');
    }

    #[TestDox('SUP-02 Équipe qui a des matchs : blocage affiché ; suppression forcée → message, rien n\'est modifié')]
    public function test_equipe_avec_matchs(): void
    {
        $a1 = ChampionnatDeTest::get('equipe.A1')->getId();
        $crawler = $this->client->request('GET', "/admin/equipe/$a1/edit");
        self::assertNotSame('', (string) $crawler->filter("[data-confirmation-action-param=\"/admin/equipe/$a1\"]")->attr('data-confirmation-blocage-param'), 'blocage annoncé');

        $retour = $this->supprimer("/admin/equipe/$a1/edit", "/admin/equipe/$a1");

        self::assertStringContainsString('n\'a pas pu être supprimée', $retour);
        self::assertStringContainsString('Rien n\'a été modifié', $retour);
        $em = $this->em();
        self::assertNotNull($em->find(Equipe::class, $a1));
        self::assertNotNull($em->find(Partie::class, ChampionnatDeTest::get('match.reference')->getId()));
    }

    #[TestDox('SUP-03 Gymnase utilisé : refusé avec message ; gymnase libre : supprimé avec ses créneaux')]
    public function test_gymnase(): void
    {
        $g1 = ChampionnatDeTest::get('gymnase.G1')->getId();
        self::assertStringContainsString('n\'a pas pu être supprimé', $this->supprimer("/admin/lieu/$g1/edit", "/admin/lieu/$g1"));
        self::assertNotNull($this->em()->find(Lieu::class, $g1));

        $libre = LieuFactory::createOne(['nom' => 'Gymnase libre']);
        CreneauFactory::createOne(['lieu' => $libre, 'jourSemaine' => 2, 'capacite' => 1, 'prioritaire' => 1]);
        $id = $libre->getId();
        self::assertStringContainsString('a été supprimé', $this->supprimer("/admin/lieu/$id/edit", "/admin/lieu/$id"));
        $em = $this->em();
        self::assertNull($em->find(Lieu::class, $id));
        self::assertSame(0, $em->getRepository(Creneau::class)->count(['lieu' => $id]), 'créneaux supprimés');
    }

    #[TestDox('SUP-04 Fiche joueur membre de compositions : retirée des compositions puis supprimée ; le compte lié reste')]
    public function test_fiche_joueur(): void
    {
        $fiche = ChampionnatDeTest::get('joueur.joueur.a1')->getId();
        $compte = ChampionnatDeTest::compte('joueur.a1')->getId();

        self::assertStringContainsString('a été supprimée', $this->supprimer("/admin/joueur/$fiche/edit", "/admin/joueur/$fiche"));
        $em = $this->em();
        self::assertNull($em->find(Joueur::class, $fiche));
        self::assertSame(0, $em->getRepository(MembreEquipe::class)->count(['joueur' => $fiche]));
        self::assertNotNull($em->find(User::class, $compte), 'compte conservé');
    }

    #[TestDox('SUP-05 Compte lié à une fiche : compte supprimé, fiche conservée sans compte, demandes de mot de passe supprimées')]
    public function test_compte(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $compte = $em->find(User::class, ChampionnatDeTest::compte('joueur.a2')->getId());
        $em->persist(new ResetPasswordRequest($compte, new \DateTimeImmutable('+1 hour'), 'selecteur'.bin2hex(random_bytes(4)), 'jeton-hache'));
        $em->flush();
        $id = $compte->getId();

        self::assertStringContainsString('a été supprimé', $this->supprimer("/admin/user/$id/edit", "/admin/user/$id"));
        $em = $this->em();
        self::assertNull($em->find(User::class, $id));
        self::assertNull($em->find(Joueur::class, ChampionnatDeTest::get('joueur.joueur.a2')->getId())->getUser(), 'fiche conservée, sans compte');
        self::assertSame(0, $em->getRepository(ResetPasswordRequest::class)->count(['user' => $id]));
    }

    #[TestDox('SUP-06 Supprimer son propre compte : refusé côté serveur, compte intact')]
    public function test_son_propre_compte(): void
    {
        $id = ChampionnatDeTest::compte('admin')->getId();
        $crawler = $this->client->request('GET', "/admin/user/$id/edit");
        $jeton = $crawler->filter("[data-confirmation-action-param=\"/admin/user/$id\"]")->count()
            ? $crawler->filter("[data-confirmation-action-param=\"/admin/user/$id\"]")->attr('data-confirmation-token-param') : 'faux';
        $this->client->request('POST', "/admin/user/$id", ['_token' => $jeton]);
        $this->client->followRedirect();

        self::assertStringContainsString('Vous ne pouvez pas supprimer votre propre compte', $this->client->getCrawler()->filter('main')->text());
        self::assertNotNull($this->em()->find(User::class, $id));
    }

    #[TestDox('SUP-07 Saison non affichée : supprimée avec son contenu')]
    public function test_saison(): void
    {
        $id = ChampionnatDeTest::get('saison.precedente')->getId();
        self::assertStringContainsString('a été supprimée', $this->supprimer("/admin/saison/$id/edit", "/admin/saison/$id"));
        self::assertNull($this->em()->find(Saison::class, $id));
    }

    #[TestDox('SUP-08/09 Poule, phase et match : supprimés avec leur contenu, retour sur la page de la saison (poule ouverte pour un match)')]
    public function test_poule_phase_match(): void
    {
        $saison = ChampionnatDeTest::get('saison')->getId();
        $page = "/admin/saison/$saison";

        $match = ChampionnatDeTest::get('match.A3-A4')->getId();
        $retour = $this->supprimer($page, "/admin/partie/$match/delete");
        self::assertStringContainsString("/admin/saison/$saison?openPoule=".ChampionnatDeTest::get('poule.A')->getId(), $retour);
        self::assertNull($this->em()->find(Partie::class, $match));

        $pouleB = ChampionnatDeTest::get('poule.B')->getId();
        $matchB = ChampionnatDeTest::get('match.B1-B2')->getId();
        self::assertStringContainsString("/admin/saison/$saison", $this->supprimer($page, "/admin/poule/$pouleB"));
        $em = $this->em();
        self::assertNull($em->find(Poule::class, $pouleB));
        self::assertNull($em->find(Partie::class, $matchB), 'matchs de la poule supprimés');

        $phase = ChampionnatDeTest::get('phase.1')->getId();
        self::assertStringContainsString("/admin/saison/$saison", $this->supprimer($page, "/admin/phase/$phase"));
        $em = $this->em();
        self::assertNull($em->find(Phase::class, $phase));
        self::assertNull($em->find(Poule::class, ChampionnatDeTest::get('poule.A')->getId()));
        self::assertNull($em->find(Partie::class, ChampionnatDeTest::get('match.reference')->getId()));
    }

    #[TestDox('SUP-10 Suppressions en masse : seules les données visées disparaissent')]
    public function test_suppressions_en_masse(): void
    {
        $saison = ChampionnatDeTest::get('saison')->getId();
        $pouleA = ChampionnatDeTest::get('poule.A')->getId();
        $journee = ChampionnatDeTest::get('match.reference')->getJournee()->getId();
        $compter = fn (string $classe, string $poule) => $this->em()->getRepository($classe)->count(['poule' => ChampionnatDeTest::get($poule)->getId()]);
        $matchsB = $compter(Partie::class, 'poule.B');
        $journeesB = $compter(Journee::class, 'poule.B');

        // Les matchs d'une journée de la poule A
        $this->client->request('GET', "/admin/saison/$saison");
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter("form[action=\"/admin/partie/$pouleA/deleteallbyjournee/$journee\"]");
        $bouton = $crawler->filter("[data-confirmation-action-param=\"/admin/partie/$pouleA/deleteallbyjournee/$journee\"]");
        $jeton = $bouton->count() ? $bouton->attr('data-confirmation-token-param') : $form->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', "/admin/partie/$pouleA/deleteallbyjournee/$journee", ['_token' => $jeton]);
        $em = $this->em();
        self::assertSame(0, $em->getRepository(Partie::class)->count(['journee' => $journee]), 'matchs de la journée supprimés');
        self::assertGreaterThan(0, $em->getRepository(Partie::class)->count(['poule' => $pouleA]), 'les autres journées de la poule A gardent leurs matchs');
        self::assertSame($matchsB, $compter(Partie::class, 'poule.B'), 'poule B intacte');

        // Tous les matchs, puis toutes les journées de la poule A
        $this->supprimer("/admin/saison/$saison?openPoule=$pouleA", "/admin/partie/$pouleA/deleteall");
        self::assertSame(0, $compter(Partie::class, 'poule.A'));
        $this->supprimer("/admin/saison/$saison?openPoule=$pouleA", "/admin/journee/$pouleA/deleteall");
        self::assertSame(0, $compter(Journee::class, 'poule.A'));
        self::assertSame($matchsB, $compter(Partie::class, 'poule.B'), 'poule B intacte');
        self::assertSame($journeesB, $compter(Journee::class, 'poule.B'));
    }
}
