<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\FermetureGymnase;
use App\Entity\ParametresSite;
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

/**
 * FGY-01 à FGY-10 : saisie des fermetures de gymnase par les responsables de club et les administrateurs (D60).
 */
final class FermetureGymnaseTest extends TestFonctionnel
{
    private function url(string $club = 'club'): string
    {
        return '/club/'.ChampionnatDeTest::get($club)->getId().'/fermetures';
    }

    private function urlAdmin(string $gymnase = 'G1'): string
    {
        return '/admin/lieu/'.ChampionnatDeTest::get('gymnase.'.$gymnase)->getId().'/fermetures';
    }

    private function nombreFermetures(): int
    {
        return self::getContainer()->get('doctrine')->getManager()->getRepository(FermetureGymnase::class)->count([]);
    }

    /** Période loin dans le futur, où le championnat de référence n'a aucun match. */
    private function saisir(string $debut = '+3 years', string $fin = '+3 years +2 days', string $motif = 'Travaux', string $gymnase = 'G1'): void
    {
        $crawler = $this->client->request('GET', $this->url());
        $form = $crawler->selectButton('Enregistrer la fermeture')->form();
        $form['fermeture_gymnase[lieu]'] = (string) ChampionnatDeTest::get('gymnase.'.$gymnase)->getId();
        $form['fermeture_gymnase[motif]'] = $motif;
        $form['fermeture_gymnase[dateDebut]'] = (new \DateTimeImmutable($debut))->format('Y-m-d');
        $form['fermeture_gymnase[dateFin]'] = (new \DateTimeImmutable($fin))->format('Y-m-d');
        $this->client->submit($form);
    }

    private function matchLe(string $date, string $gymnase = 'G1'): void
    {
        PartieFactory::createOne(['journee' => ChampionnatDeTest::get('match.reference')->getJournee(), 'poule' => ChampionnatDeTest::get('poule.A'),
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'),
            'lieu' => ChampionnatDeTest::get('gymnase.'.$gymnase), 'date' => new \DateTimeImmutable($date)]);
    }

    #[TestDox('FGY-01 : le menu d\'un responsable mène à son club par « Mon club », la fiche aux fermetures ; rien pour un autre compte')]
    public function test_acces(): void
    {
        $crawler = $this->client->request('GET', '/clubs');
        self::assertCount(0, $crawler->filter('a:contains("Mon club")'), 'visiteur');

        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/clubs');
        self::assertCount(0, $crawler->filter('a:contains("Mon club")'), 'capitaine');

        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', '/clubs');
        $fiche = '/club/'.ChampionnatDeTest::get('club')->getId();
        self::assertGreaterThan(0, $crawler->filter('a[href="'.$fiche.'"]:contains("Mon club")')->count());

        $this->client->request('GET', $fiche);
        self::assertSelectorExists('a[href="'.$this->url().'"]');
    }

    #[TestDox('FGY-02 : seuls l\'admin et les responsables du club ouvrent la page ; un visiteur va à la connexion')]
    public function test_droits(): void
    {
        $this->client->request('GET', $this->url());
        $this->assertRedirigeVersConnexion();

        $this->connecter('cap.a1');
        $this->client->request('GET', $this->url());
        $this->assertAccesRefuse();

        $this->connecter('resp.club');
        $this->client->request('GET', $this->url('club.voisin'));
        $this->assertAccesRefuse('responsable d\'un autre club');
        $this->client->request('GET', $this->url());
        self::assertResponseIsSuccessful();

        $this->connecter('admin');
        $this->client->request('GET', $this->url());
        self::assertResponseIsSuccessful();
    }

    #[TestDox('FGY-03 : sans match pendant la période, la fermeture est enregistrée et listée')]
    public function test_enregistrement(): void
    {
        $this->connecter('resp.club');
        $this->saisir();

        self::assertResponseRedirects($this->url());
        self::assertSame(1, $this->nombreFermetures());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Gymnase G1');
        self::assertSelectorTextContains('body', 'Travaux');
    }

    #[TestDox('FGY-04 : un match programmé (premier ou dernier jour compris) empêche le responsable d\'enregistrer ; il lui est cité avec un e-mail prérempli aux admins')]
    public function test_refus_si_match(): void
    {
        foreach (['+3 years', '+3 years +2 days'] as $dateMatch) {
            $this->matchLe($dateMatch.' 20:30');
            $this->connecter('resp.club');
            $this->saisir(motif: 'Travaux&bcc=pirate@exemple.org');

            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->nombreFermetures());
            self::assertSelectorTextContains('[role="alert"]', 'n\'est pas enregistrée');
            self::assertSelectorTextContains('[role="alert"]', 'contre');
            self::assertSelectorTextContains('[role="alert"]', 'Contactez le championnat');
            $lien = $this->client->getCrawler()->filter('a[href^="mailto:"]');
            self::assertCount(1, $lien);
            self::assertStringStartsWith('mailto:admin@blvb.test?subject=', $lien->attr('href'));
            self::assertStringContainsString(rawurlencode('Gymnase G1'), $lien->attr('href'));
            self::assertStringNotContainsString('&bcc=', $lien->attr('href'), 'le motif reste dans le corps du message');
        }
    }

    #[TestDox('FGY-05 : ne bloquent pas la saisie : un match joué, un match d\'un autre gymnase, un match hors de la période')]
    public function test_matchs_sans_effet(): void
    {
        $this->matchLe('+3 years', 'G2');
        $this->matchLe('+3 years +3 days');
        PartieFactory::createOne(['journee' => ChampionnatDeTest::get('match.reference')->getJournee(), 'poule' => ChampionnatDeTest::get('poule.A'),
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'),
            'lieu' => ChampionnatDeTest::get('gymnase.G1'), 'date' => new \DateTimeImmutable('+3 years +1 day'),
            'nb_set_gagnant_reception' => 3, 'nb_set_gagnant_deplacement' => 0]);

        $this->connecter('resp.club');
        $this->saisir();

        self::assertResponseRedirects($this->url());
        self::assertSame(1, $this->nombreFermetures());
    }

    #[TestDox('FGY-06 : l\'admin enregistre malgré un match programmé, et ce match lui est signalé (page du club et page du gymnase)')]
    public function test_admin_enregistre_et_averti(): void
    {
        $this->matchLe('+3 years +1 day');
        $this->connecter('admin');
        $this->saisir();
        self::assertResponseRedirects($this->url());
        self::assertSame(1, $this->nombreFermetures());
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', '1 match est programmé');

        $crawler = $this->client->request('GET', $this->urlAdmin());
        $form = $crawler->selectButton('Enregistrer la fermeture')->form();
        $form['fermeture_gymnase[motif]'] = 'Peinture';
        $form['fermeture_gymnase[dateDebut]'] = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');
        $form['fermeture_gymnase[dateFin]'] = (new \DateTimeImmutable('+3 years +2 days'))->format('Y-m-d');
        $this->client->submit($form);
        self::assertResponseRedirects($this->urlAdmin());
        self::assertSame(2, $this->nombreFermetures());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', '1 match est programmé');
    }

    #[TestDox('FGY-07 : saisie invalide refusée avec un message : champs vides, dates inversées, période terminée, gymnase d\'un autre club')]
    public function test_saisies_invalides(): void
    {
        $this->connecter('resp.club');

        $this->saisir(motif: '');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Indiquez le motif');

        $this->saisir('+3 years +2 days', '+3 years');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'ne peut pas précéder');

        $this->saisir('-10 days', '-5 days');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'déjà terminée');

        $this->saisir('+1 year', '+3 years +2 days');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'dépasser 2 ans');

        $this->saisir('+6 years', '+6 years');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'trop lointaine');

        $this->saisir(motif: "Travaux\r\nsuite");
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'caractères de contrôle');

        $this->saisir(motif: "Travaux\u{202E}suite");
        self::assertResponseStatusCodeSame(422, 'caractère invisible qui inverse le texte');

        // Gymnase G3 hors du club : absent de la liste, refusé même en forçant la valeur
        $crawler = $this->client->request('GET', $this->url());
        $form = $crawler->selectButton('Enregistrer la fermeture')->form();
        $valeurs = $form->getPhpValues();
        $valeurs['fermeture_gymnase']['lieu'] = (string) ChampionnatDeTest::get('gymnase.G3')->getId();
        $valeurs['fermeture_gymnase']['motif'] = 'Travaux';
        $valeurs['fermeture_gymnase']['dateDebut'] = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');
        $valeurs['fermeture_gymnase']['dateFin'] = (new \DateTimeImmutable('+3 years'))->format('Y-m-d');
        $this->client->request('POST', $this->url(), $valeurs);
        self::assertResponseStatusCodeSame(422);

        // Sans jeton CSRF
        $valeurs['fermeture_gymnase']['lieu'] = (string) ChampionnatDeTest::get('gymnase.G1')->getId();
        unset($valeurs['fermeture_gymnase']['_token']);
        $this->client->getHistory()->clear();
        $this->client->request('POST', $this->url(), $valeurs);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(0, $this->nombreFermetures());
    }

    #[TestDox('FGY-08 : tout responsable d\'un club utilisant le gymnase retire une fermeture ; jeton requis ; refus pour un gymnase hors du club')]
    public function test_retrait(): void
    {
        $fermeture = FermetureGymnaseFactory::createOne(['lieu' => ChampionnatDeTest::get('gymnase.G1'), 'dateDebut' => new \DateTimeImmutable('+3 years'), 'dateFin' => new \DateTimeImmutable('+3 years')]);
        $autre = FermetureGymnaseFactory::createOne(['lieu' => ChampionnatDeTest::get('gymnase.G3'), 'dateDebut' => new \DateTimeImmutable('+3 years'), 'dateFin' => new \DateTimeImmutable('+3 years')]);
        $this->connecter('resp.club');

        $this->client->request('POST', $this->url().'/'.$autre->getId().'/supprimer');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->url('club.voisin').'/'.$fermeture->getId().'/supprimer');
        $this->assertAccesRefuse('URL d\'un autre club');

        $this->client->request('POST', $this->url().'/'.$fermeture->getId().'/supprimer', ['_token' => 'faux']);
        self::assertResponseRedirects($this->url());
        self::assertSame(2, $this->nombreFermetures(), 'jeton invalide : rien de retiré');

        $crawler = $this->client->request('GET', $this->url());
        $this->client->submit($crawler->selectButton('Retirer')->form());
        self::assertResponseRedirects($this->url());
        self::assertSame(1, $this->nombreFermetures());
    }

    #[TestDox('FGY-09 : un match d\'une saison en préparation bloque le responsable sans dévoiler ses équipes, ni dans la page ni dans l\'e-mail')]
    public function test_saison_en_preparation(): void
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison en préparation', 'favori' => 0, 'brouillon' => true]);
        $poule = PouleFactory::createOne(['phase' => PhaseFactory::createOne(['saison' => $saison]), 'nom' => 'Poule prépa']);
        PartieFactory::createOne(['journee' => JourneeFactory::createOne(['poule' => $poule]), 'poule' => $poule,
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'),
            'lieu' => ChampionnatDeTest::get('gymnase.G1'), 'date' => new \DateTimeImmutable('+3 years +1 day')]);

        $this->connecter('resp.club');
        $this->saisir();

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->nombreFermetures());
        $alerte = $this->client->getCrawler()->filter('[role="alert"]')->text();
        self::assertStringContainsString('saison en préparation', $alerte);
        self::assertStringNotContainsString('A1', $alerte);
        $lien = rawurldecode((string) $this->client->getCrawler()->filter('a[href^="mailto:"]')->attr('href'));
        self::assertStringContainsString('saison en préparation', $lien);
        self::assertStringNotContainsString('A1', $lien);
    }

    #[TestDox('FGY-10 : l\'adresse de contact des paramètres (D62) remplace celles des admins dans le message de refus')]
    public function test_adresse_de_contact(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($em->getRepository(ParametresSite::class)->courants()->setAdresseContact('contact@blvb.test'));
        $em->flush();
        $this->matchLe('+3 years 20:30');
        $this->connecter('resp.club');
        $this->saisir();

        self::assertResponseStatusCodeSame(422);
        $alerte = $this->client->getCrawler()->filter('[role="alert"]');
        self::assertStringStartsWith('mailto:contact@blvb.test?subject=', $alerte->filter('a[href^="mailto:"]')->attr('href'));
        self::assertStringContainsString('écrivez à : contact@blvb.test', $alerte->text());
        self::assertStringNotContainsString('admin@blvb.test', $alerte->html());
    }
}
