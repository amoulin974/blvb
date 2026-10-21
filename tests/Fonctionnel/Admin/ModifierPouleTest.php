<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Factory\PouleFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * POU-01 à POU-05 : page « Modifier la poule » (D52). Story : poules A et B de la phase 1 (matchs : phase commencée) ;
 * une poule « Poule libre » est créée dans la phase 2, vide.
 */
final class ModifierPouleTest extends TestFonctionnel
{
    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
        $this->connecter('admin');
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function pouleLibre(): Poule
    {
        $phase2 = $this->em()->getRepository(Phase::class)->findOneBy(['saison' => ChampionnatDeTest::get('saison')->getId(), 'ordre' => 2]);

        return PouleFactory::createOne(['phase' => $phase2, 'nom' => 'Poule libre', 'niveau' => 3]);
    }

    private function recharger(int $id): Poule
    {
        $this->em()->clear();

        return $this->em()->find(Poule::class, $id);
    }

    #[TestDox('POU-01 Page : nom et niveau modifiables, phase en lecture seule, équipes listées avec un lien vers la mise en place ; ni liste de phases, ni sélection d\'équipes, ni journées')]
    public function test_page(): void
    {
        $poule = $this->pouleLibre();
        $crawler = $this->client->request('GET', '/admin/poule/'.$poule->getId().'/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="poule_edit[nom]"]'));
        self::assertCount(1, $crawler->filter('input[name="poule_edit[niveau]"]:not([disabled])'));
        self::assertCount(0, $crawler->filter('select'), 'ni phase ni équipes à choisir');
        self::assertCount(0, $crawler->filter('[name*="journees"]'), 'pas de journées dans le formulaire');
        self::assertSelectorTextContains('body', 'Aucune équipe.');
        self::assertCount(1, $crawler->filter('a[href*="/mise-en-place/poules"]:contains("Composer les poules")'));
        $consequences = json_decode((string) $crawler->filter('[data-confirmation-consequences-param]')->attr('data-confirmation-consequences-param'), true);
        self::assertContains('Aucune journée ni aucun match à supprimer.', $consequences, 'confirmation de suppression d\'une poule vide');
        self::assertSelectorTextContains('body', 'Niveau (1 = le plus fort)');
    }

    #[TestDox('POU-02 Enregistrer : nom et niveau enregistrés, retour à la saison sur la poule ; niveau hors bornes refusé (422)')]
    public function test_enregistrement(): void
    {
        $poule = $this->pouleLibre();
        $url = '/admin/poule/'.$poule->getId().'/edit';

        $this->client->submit($this->client->request('GET', $url)->selectButton('Enregistrer')->form(), ['poule_edit[nom]' => 'Poule Z', 'poule_edit[niveau]' => '4']);
        self::assertResponseRedirects('/admin/saison/'.ChampionnatDeTest::get('saison')->getId().'?openPoule='.$poule->getId());
        self::assertSame(['Poule Z', 4], [$this->recharger($poule->getId())->getNom(), $this->recharger($poule->getId())->getNiveau()]);

        $this->client->submit($this->client->request('GET', $url)->selectButton('Enregistrer')->form(), ['poule_edit[niveau]' => '0']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le niveau est un nombre de 1 à 20.');
        self::assertSame(4, $this->recharger($poule->getId())->getNiveau());
    }

    #[TestDox('POU-03 Phase commencée : seul le nom se modifie ; un niveau envoyé à la main est ignoré ; ni suppression ni lien de composition')]
    public function test_phase_commencee(): void
    {
        $poule = ChampionnatDeTest::get('poule.A');
        $url = '/admin/poule/'.$poule->getId().'/edit';
        $crawler = $this->client->request('GET', $url);

        self::assertSelectorTextContains('body', 'Seul le nom se modifie ici.');
        self::assertCount(0, $crawler->filter('input[name="poule_edit[niveau]"]:not([disabled])'));
        self::assertCount(0, $crawler->filter('a[href*="/mise-en-place/poules"]'));
        self::assertSelectorNotExists('[data-confirmation-action-param="/admin/poule/'.$poule->getId().'"]');

        $jeton = $crawler->filter('input[name="poule_edit[_token]"]')->attr('value');
        $this->client->request('POST', $url, ['poule_edit' => ['nom' => 'Poule A renommée', 'niveau' => '7', '_token' => $jeton]]);
        self::assertResponseRedirects();
        $poule = $this->recharger($poule->getId());
        self::assertSame('Poule A renommée', $poule->getNom());
        self::assertSame(1, $poule->getNiveau(), 'niveau inchangé');
    }

    #[TestDox('POU-04 Phase clôturée : page en lecture seule, rien n\'est enregistré même envoyé à la main')]
    public function test_phase_cloturee(): void
    {
        $poule = ChampionnatDeTest::get('poule.B');
        $phase = $this->em()->find(Phase::class, ChampionnatDeTest::get('phase.1')->getId());
        $phase->setClose(1);
        $this->em()->flush();
        $url = '/admin/poule/'.$poule->getId().'/edit';

        $crawler = $this->client->request('GET', $url);
        self::assertSelectorTextContains('body', 'est clôturée : cette poule ne se modifie plus.');
        self::assertCount(0, $crawler->filter('button[type="submit"]:contains("Enregistrer")'));
        self::assertCount(0, $crawler->filter('input[name="poule_edit[nom]"]'), 'nom affiché en texte');
        self::assertSelectorTextContains('h1', 'Poule B');

        $this->client->request('POST', $url, ['poule_edit' => ['nom' => 'Piratée']]);
        self::assertSame('Poule B', $this->recharger($poule->getId())->getNom());
    }

    #[TestDox('POU-05 Champs ajoutés à la main (phase, équipes, journées) : refusés, rien n\'est déplacé ni modifié')]
    public function test_champs_forges(): void
    {
        $poule = $this->pouleLibre();
        $url = '/admin/poule/'.$poule->getId().'/edit';
        $jeton = $this->client->request('GET', $url)->filter('input[name="poule_edit[_token]"]')->attr('value');
        $phaseAvant = $poule->getPhase()->getId();

        $this->client->request('POST', $url, ['poule_edit' => ['nom' => 'Poule libre', 'niveau' => '3', '_token' => $jeton,
            'phase' => (string) ChampionnatDeTest::get('phase.1')->getId(), 'equipes' => [(string) ChampionnatDeTest::get('equipe.A1')->getId()]]]);

        self::assertResponseStatusCodeSame(422);
        $poule = $this->recharger($poule->getId());
        self::assertSame($phaseAvant, $poule->getPhase()->getId());
        self::assertCount(0, $poule->getEquipes());
    }
}
