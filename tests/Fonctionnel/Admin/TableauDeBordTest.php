<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\ParametresSite;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\FermetureGymnaseFactory;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\DomCrawler\Crawler;

/**
 * TDB-01 à TDB-12 : tableau de bord de l'admin et page « Scores à saisir » (App\Service\TableauDeBord, D64).
 *
 * Championnat de référence : saison favorite en cours, phase 1 en place, 7 matchs dont 4 joués ; match de référence
 * joué hier sans score ; club voisin sans responsable ; A3, A4, B2, B3 sans capitaine ; pas de règlement, limitation
 * désactivée, pas d'adresse de contact.
 */
final class TableauDeBordTest extends TestFonctionnel
{
    private const URL = '/admin/dashboard';

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function tableauDeBord(): Crawler
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', self::URL);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return list<string> codes des lignes « À faire », dans l'ordre */
    private function lignes(Crawler $crawler): array
    {
        return $crawler->filter('[data-a-faire]')->each(fn (Crawler $l) => $l->attr('data-a-faire'));
    }

    private function ligne(Crawler $crawler, string $code): Crawler
    {
        return $crawler->filter('[data-a-faire="'.$code.'"]');
    }

    private function partie(string $etat): Partie
    {
        return $this->em()->getRepository(Partie::class)->find(ChampionnatDeTest::get($etat)->getId());
    }

    private function saison(): Saison
    {
        return $this->em()->getRepository(Saison::class)->find(ChampionnatDeTest::get('saison')->getId());
    }

    #[TestDox('TDB-01 : tableau de bord et scores à saisir réservés aux admins ; visiteur renvoyé à la connexion, capitaine refusé')]
    public function test_acces(): void
    {
        foreach ([self::URL, '/admin/scores'] as $url) {
            $this->client->request('GET', $url);
            $this->assertRedirigeVersConnexion($url);
        }
        $this->connecter('cap.a1');
        foreach ([self::URL, '/admin/scores'] as $url) {
            $this->client->request('GET', $url);
            $this->assertAccesRefuse($url);
        }
    }

    #[TestDox('TDB-02 : saison en cours : état, phase, matchs joués, prochain match, bouton « Suivre la saison » ; menu « Saison affichée »')]
    public function test_saison_en_cours(): void
    {
        $crawler = $this->tableauDeBord();
        $carte = $crawler->filter('section[aria-labelledby="carte-saison-'.$this->saison()->getId().'"]');

        self::assertStringContainsString('Saison affichée sur le site', $carte->text());
        self::assertStringContainsString('Phase 1 en cours', $carte->text());
        self::assertStringContainsString('Matchs joués : 4 sur 7', $carte->text());
        self::assertStringContainsString('Prochain match :', $carte->text());
        self::assertSame('/admin/saison/'.$this->saison()->getId(), $carte->selectLink('Suivre la saison Saison test')->attr('href'));
        self::assertStringContainsString('Saison affichée', $crawler->filter('nav[aria-label="Menu de l\'administration"]')->text());
        self::assertStringNotContainsString('Saison en cours', $crawler->filter('nav[aria-label="Menu de l\'administration"]')->text());
        self::assertStringNotContainsString('Outils techniques', $crawler->filter('main')->text(), 'retirés de la page, restent au menu');
    }

    #[TestDox('TDB-03 : « À faire » du championnat de référence : club sans responsable, équipes sans capitaine, règlement, limitation, contact')]
    public function test_a_faire_initial(): void
    {
        $crawler = $this->tableauDeBord();

        self::assertSame(['responsables', 'capitaines', 'reglement', 'connexion', 'contact'], $this->lignes($crawler));
        self::assertStringContainsString('1 club engagé n\'a pas de responsable pour la saison affichée.', $this->ligne($crawler, 'responsables')->text());
        self::assertStringContainsString('4 équipes engagées n\'ont pas de capitaine pour la saison affichée.', $this->ligne($crawler, 'capitaines')->text());
        self::assertStringContainsString('Saison test : le règlement n\'est pas encore rédigé.', $this->ligne($crawler, 'reglement')->text());
        self::assertSame('/admin/saison/'.$this->saison()->getId().'/reglement', $this->ligne($crawler, 'reglement')->filter('a')->attr('href'));
        self::assertSame('/admin/parametres#titre-contact', $this->ligne($crawler, 'contact')->filter('a')->attr('href'));
        self::assertCount(0, $crawler->filter('#titre-a-faire ~ details'), 'cinq lignes : rien de replié');
    }

    #[TestDox('TDB-04 : un score absent n\'est signalé qu\'au-delà de 2 jours ; la ligne mène aux scores à saisir')]
    public function test_scores_manquants(): void
    {
        self::assertNotContains('scores', $this->lignes($this->tableauDeBord()), 'match de référence joué hier');

        $this->partie('match.reference')->setDate(new \DateTimeImmutable('-3 days'));
        $this->em()->flush();
        $ligne = $this->ligne($this->tableauDeBord(), 'scores');

        self::assertStringContainsString('1 score manque depuis plus de 2 jours.', $ligne->text());
        self::assertSame('/admin/scores', $ligne->filter('a')->attr('href'));
    }

    #[TestDox('TDB-05 : un match sans score dans un gymnase fermé est signalé en tête, avec un lien vers le calendrier de sa poule ; un match joué ne l\'est pas')]
    public function test_gymnase_ferme(): void
    {
        $futur = $this->partie('match.futur');
        FermetureGymnaseFactory::createOne(['lieu' => $futur->getLieu(), 'dateDebut' => $futur->getDate()->setTime(0, 0), 'dateFin' => $futur->getDate()->setTime(0, 0)]);
        $crawler = $this->tableauDeBord();

        self::assertSame('gymnase_ferme', $this->lignes($crawler)[0]);
        $ligne = $this->ligne($crawler, 'gymnase_ferme');
        self::assertStringContainsString('Urgent : 1 match est programmé dans un gymnase fermé.', $ligne->text());
        self::assertSame('Afficher le match programmé dans un gymnase fermé', $ligne->filter('summary')->text());
        self::assertSame('/admin/saison/'.$this->saison()->getId().'?openPoule='.$futur->getPoule()->getId(), $ligne->filter('details a')->attr('href'));

        $futur = $this->partie('match.futur');
        $futur->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(0);
        $this->em()->flush();
        self::assertNotContains('gymnase_ferme', $this->lignes($this->tableauDeBord()));
    }

    #[TestDox('TDB-06 : phase dont tous les scores sont saisis : clôture proposée, lien vers la phase sur la page de la saison')]
    public function test_cloture(): void
    {
        foreach ($this->em()->getRepository(Partie::class)->findAll() as $partie) {
            $partie->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(1);
        }
        $this->em()->flush();
        $ligne = $this->ligne($this->tableauDeBord(), 'cloture');

        self::assertStringContainsString('Phase 1 : tous les scores sont saisis. Vous pouvez la clôturer.', $ligne->text());
        self::assertSame('/admin/saison/'.$this->saison()->getId().'#phase_'.ChampionnatDeTest::get('phase.1')->getId(), $ligne->filter('a')->attr('href'));
    }

    #[TestDox('TDB-07 : brouillon du wizard en carte réduite, étape et bouton « Reprendre la création » ; son règlement n\'est pas encore réclamé')]
    public function test_brouillon(): void
    {
        $brouillon = SaisonFactory::createOne(['nom' => 'Saison suivante', 'brouillon' => true, 'etapeWizard' => 3]);
        $crawler = $this->tableauDeBord();
        $carte = $crawler->filter('section[aria-labelledby="carte-saison-'.$brouillon->getId().'"]');

        self::assertStringContainsString('Saison en préparation', $carte->text());
        self::assertStringContainsString('Brouillon · étape 3 sur 6 : Phases', $carte->text());
        self::assertSame('/admin/saison/'.$brouillon->getId().'/wizard/3', $carte->filter('a')->first()->attr('href'));
        self::assertStringNotContainsString('Saison suivante : le règlement', $crawler->filter('main')->text(), 'pas pendant la préparation du brouillon');
        self::assertStringContainsString('Saison test : le règlement n\'est pas encore rédigé.', $crawler->filter('main')->text());
    }

    #[TestDox('TDB-08 : saison affichée terminée et une autre en préparation : la saison en préparation prend la grande carte')]
    public function test_saison_terminee(): void
    {
        $this->saison()->setDateFin(new \DateTimeImmutable('-1 day'));
        $this->em()->flush();
        $brouillon = SaisonFactory::createOne(['nom' => 'Saison suivante', 'brouillon' => true, 'etapeWizard' => 2]);
        $crawler = $this->tableauDeBord();

        $cartes = $crawler->filter('section[aria-labelledby^="carte-saison-"]');
        self::assertSame('carte-saison-'.$brouillon->getId(), $cartes->first()->attr('aria-labelledby'), 'grande carte');
        $terminee = $crawler->filter('section[aria-labelledby="carte-saison-'.$this->saison()->getId().'"]');
        self::assertStringContainsString('Saison terminée', $terminee->text());
        self::assertStringContainsString('Consulter la saison', $terminee->text());
        self::assertNotContains('responsables', $this->lignes($crawler), 'plus de désignation à faire pour une saison terminée');
    }

    #[TestDox('TDB-09 : aucune saison choisie pour le site : ligne « Choisir la saison »')]
    public function test_aucune_saison_affichee(): void
    {
        $this->saison()->setFavori(0);
        $this->em()->flush();
        $ligne = $this->ligne($this->tableauDeBord(), 'aucune_affichee');

        self::assertStringContainsString('Aucune saison n\'est affichée sur le site.', $ligne->text());
        self::assertSame('/admin/saison/saison', $ligne->filter('a')->attr('href'));
    }

    #[TestDox('TDB-10 : saison validée à mettre en place : carte, reprise à l\'étape incomplète, rappel des fermetures avant les matchs ; plus de cinq lignes repliées')]
    public function test_mise_en_place(): void
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison validée', 'date_fin' => new \DateTimeImmutable('+1 year')]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase A']);
        PouleFactory::createOne(['phase' => $phase, 'equipes' => [EquipeFactory::createOne(), EquipeFactory::createOne()]]);
        $crawler = $this->tableauDeBord();

        $carte = $crawler->filter('section[aria-labelledby="carte-saison-'.$saison->getId().'"]');
        self::assertStringContainsString('Saison validée · à mettre en place', $carte->text());
        self::assertStringContainsString('Phase A : poules faites · journées à faire · matchs à faire', $carte->text());
        $reprise = '/admin/saison/'.$saison->getId().'/mise-en-place/journees?phase='.$phase->getId();
        self::assertSame($reprise, $carte->selectLink('Continuer la mise en place de Saison validée')->attr('href'));
        self::assertStringContainsString('Saison validée, Phase A : la mise en place n\'est pas terminée.', $this->ligne($crawler, 'mise_en_place')->text());
        self::assertSame($reprise, $this->ligne($crawler, 'mise_en_place')->filter('a')->attr('href'));
        self::assertStringContainsString('Saison validée : avant de générer les matchs, demandez aux clubs leurs fermetures de gymnase.', $this->ligne($crawler, 'rappel_fermetures')->text());

        self::assertCount(8, $this->lignes($crawler));
        self::assertSame('Voir les 3 autres points', $crawler->filter('#titre-a-faire ~ details > summary')->text());
    }

    #[TestDox('TDB-11 : saison prête : « Afficher sur le site » avec confirmation, retour au tableau de bord')]
    public function test_saison_prete(): void
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison prête', 'date_fin' => new \DateTimeImmutable('+1 year')]);
        $phase = PhaseFactory::createOne(['saison' => $saison]);
        [$e1, $e2] = [EquipeFactory::createOne(), EquipeFactory::createOne()];
        $poule = PouleFactory::createOne(['phase' => $phase, 'equipes' => [$e1, $e2]]);
        $journee = JourneeFactory::createOne(['poule' => $poule]);
        PartieFactory::createOne(['journee' => $journee, 'poule' => $poule, 'idEquipeRecoit' => $e1, 'idEquipeDeplace' => $e2, 'date' => new \DateTimeImmutable('+1 month')]);
        $crawler = $this->tableauDeBord();

        $carte = $crawler->filter('section[aria-labelledby="carte-saison-'.$saison->getId().'"]');
        self::assertStringContainsString('Saison prête, pas encore affichée sur le site', $carte->text());
        $bouton = $carte->filter('[data-confirmation-action-param]');
        self::assertSame('/admin/saison/'.$saison->getId().'/favorite/', $bouton->attr('data-confirmation-action-param'));
        self::assertSame(['retour' => 'tableau_de_bord'], json_decode($bouton->attr('data-confirmation-champs-param'), true));

        $this->client->request('POST', '/admin/saison/'.$saison->getId().'/favorite/', ['_token' => $bouton->attr('data-confirmation-token-param'), 'retour' => 'tableau_de_bord']);
        self::assertResponseRedirects(self::URL);
    }

    #[TestDox('TDB-12 : scores à saisir : matchs passés sans score, saisie dans la fenêtre du calendrier ; ni match à venir ni match joué')]
    public function test_page_scores(): void
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/scores');
        self::assertResponseIsSuccessful();

        $reference = ChampionnatDeTest::get('match.reference');
        self::assertSame([(string) $reference->getId()], $crawler->filter('[data-partie]')->each(fn (Crawler $l) => $l->attr('data-partie')));
        $saisir = $crawler->filter('[data-partie] [data-score-action="saisir"]');
        self::assertSame('/front/partie/'.$reference->getId().'/api/update', $saisir->attr('data-url-score'));
        self::assertCount(1, $crawler->filter('[data-controller="score-modal"] [data-score-modal-target="modal"]'));
        self::assertNotEmpty($crawler->filter('meta[name="csrf-token"]')->attr('content'));
        self::assertCount(0, $crawler->filter('[data-partie] .badge'), 'joué hier : pas encore en retard');
    }

    #[TestDox('TDB-12 : accès rapides : chiffres de la saison affichée')]
    public function test_tuiles(): void
    {
        $texte = $this->tableauDeBord()->filter('section[aria-labelledby="titre-acces"]')->text();

        self::assertStringContainsString('7 engagées cette saison', $texte);
        self::assertStringContainsString('2 clubs · 1 sans responsable', $texte);
        self::assertStringContainsString('3 gymnases', $texte);
        self::assertStringContainsString('Connexion, contact, logo', $texte);
    }
}
