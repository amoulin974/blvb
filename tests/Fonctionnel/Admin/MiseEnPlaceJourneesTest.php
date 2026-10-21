<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\IndisponibiliteFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * MEP-09 à MEP-14 : mise en place d'une saison validée, étape « Journées » (D35 à D39). Saison de l'an prochain, une
 * phase de championnat de 12 semaines : Poule A (4 équipes, 3 journées prévues) et Poule B (3 équipes, 3 journées).
 */
final class MiseEnPlaceJourneesTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * @return array{saison: int, phase: int, poules: array<string, int>, url: string}
     */
    private function saison(int $semaines = 12, bool $fermeture = false): array
    {
        $debut = (new \DateTimeImmutable('+1 year'))->modify('monday this week');
        $saison = SaisonFactory::createOne([
            'nom' => 'Saison suivante',
            'brouillon' => false,
            'favori' => 0,
            'date_debut' => $debut,
            'date_fin' => $debut->modify('+1 year'),
        ]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => $debut, 'datefin' => $debut->modify('+'.($semaines * 7 - 1).' days')]);
        if ($fermeture) {
            IndisponibiliteFactory::createOne(['saison' => $saison, 'nom' => 'Vacances de test',
                'dateDebut' => $debut->modify('+7 days'), 'dateFin' => $debut->modify('+20 days')]);
        }
        $e = fn (string $code) => ChampionnatDeTest::get('equipe.'.$code);
        $a = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'equipes' => [$e('A1'), $e('A2'), $e('A3'), $e('A4')]]);
        $b = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule B', 'niveau' => 2, 'equipes' => [$e('B1'), $e('B2'), $e('B3')]]);

        return [
            'saison' => $saison->getId(),
            'phase' => $phase->getId(),
            'poules' => ['A' => $a->getId(), 'B' => $b->getId()],
            'url' => '/admin/saison/'.$saison->getId().'/mise-en-place/journees',
        ];
    }

    /** Clique sur le bouton de valeur donnée et suit la redirection. */
    private function cliquer(string $url, string $valeur): void
    {
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('button[value="'.$valeur.'"]')->form());
    }

    /** @return list<int> numéros des journées de la poule */
    private function numeros(int $pouleId): array
    {
        $this->em()->clear();
        $numeros = array_map(fn (Journee $j) => $j->getNumero(), $this->em()->find(Poule::class, $pouleId)->getJournees()->toArray());
        sort($numeros);

        return $numeros;
    }

    #[TestDox('MEP-09 Écran sans journée : bilan, information, « Générer les journées manquantes », « Suivant : Matchs » désactivé avec sa raison')]
    public function test_affichage_sans_journee(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->client->request('GET', $s['url']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#titre-etape', 'Journées de Phase 1');
        self::assertSelectorTextContains('body', 'Une journée correspond à une semaine de matchs.');
        self::assertSelectorTextContains('body', '12 semaines');
        self::assertSelectorTextContains('body', 'Les journées ne sont pas encore créées.');
        self::assertSelectorExists('button[value="generer-toutes"]');
        self::assertSelectorExists('button[value="generer-'.$s['poules']['A'].'"]');
        self::assertSelectorExists('button[value="suivant"][aria-disabled="true"]');
        self::assertSelectorTextContains('#raison-validation', 'La poule A n\'a aucune journée. Générez-les pour continuer.');
    }

    #[TestDox('MEP-10 Générer les journées manquantes : une par semaine, 3 pour 4 équipes et 3 pour 3 équipes ; ensuite plus de bouton groupé et « Suivant » possible')]
    public function test_generer_toutes(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->cliquer($s['url'], 'generer-toutes');

        self::assertResponseRedirects($s['url'].'?phase='.$s['phase']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '6 journées créées pour 2 poules.');
        self::assertSelectorTextContains('#poule-'.$s['poules']['A'], '3 journées sur 3 prévues');
        self::assertSelectorExists('#poule-'.$s['poules']['A'].' a[href*="/getjourneecalendar"]');
        self::assertSelectorNotExists('button[value="generer-toutes"]');
        self::assertSelectorNotExists('button[value="suivant"][aria-disabled="true"]');
        self::assertSame([1, 2, 3], $this->numeros($s['poules']['A']));
        self::assertSame([1, 2, 3], $this->numeros($s['poules']['B']));

        $this->cliquer($s['url'], 'suivant');
        self::assertResponseRedirects('/admin/saison/'.$s['saison'].'/mise-en-place/matchs?phase='.$s['phase']);
    }

    #[TestDox('MEP-11 Régénérer les journées d\'une poule : les journées sont recréées, les matchs et scores de la poule supprimés, les équipes conservées')]
    public function test_regenerer(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->cliquer($s['url'], 'generer-toutes');
        $this->em()->clear();
        $poule = $this->em()->find(Poule::class, $s['poules']['A']);
        $journee = $poule->getJournees()->first();
        $equipes = $poule->getEquipes()->toArray();
        PartieFactory::createOne(['journee' => $journee, 'poule' => $poule, 'idEquipeRecoit' => $equipes[0], 'idEquipeDeplace' => $equipes[1],
            'date' => $journee->getDateDebut()]);
        $this->em()->clear();

        $crawler = $this->client->request('GET', $s['url']);
        self::assertSelectorTextContains('body', 'Régénérer les journées');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', $s['url'].'?phase='.$s['phase'], ['_token' => $token, 'action' => 'regenerer-'.$s['poules']['A']]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('.admin-alerte', '3 journées créées pour Poule A.');
        self::assertSame([1, 2, 3], $this->numeros($s['poules']['A']));
        self::assertSame(0, $this->em()->getRepository(Partie::class)->count(['poule' => $s['poules']['A']]));
        self::assertCount(4, $this->em()->find(Poule::class, $s['poules']['A'])->getEquipes());

        // Un « Générer » sur une poule qui a déjà ses journées est refusé
        $this->client->request('POST', $s['url'].'?phase='.$s['phase'], ['_token' => $token, 'action' => 'generer-'.$s['poules']['B']]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'a déjà ses journées');
    }

    #[TestDox('MEP-12 Phase trop courte : alerte chiffrée avec lien vers les phases ; la génération échoue sans laisser de journée partielle')]
    public function test_phase_trop_courte(): void
    {
        $s = $this->saison(2);
        $this->connecter('admin');
        $this->client->request('GET', $s['url']);
        self::assertSelectorTextContains('body', 'La phase est trop courte : il faut 3 semaines, il n\'y en a que 2 une fois les fermetures retirées.');
        self::assertSelectorExists('.admin-alerte a[href$="/wizard/3"]');

        $this->cliquer($s['url'], 'generer-toutes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'Poule A : la phase est trop courte');
        self::assertSame([], $this->numeros($s['poules']['A']));
        self::assertSame([], $this->numeros($s['poules']['B']));
    }

    #[TestDox('MEP-12b Régénération impossible (phase raccourcie depuis) : refusée avant toute suppression, journées et matchs conservés')]
    public function test_regeneration_impossible_conserve_les_journees(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->cliquer($s['url'], 'generer-toutes');
        $this->em()->clear();
        $phase = $this->em()->find(\App\Entity\Phase::class, $s['phase']);
        $phase->setDateFin($phase->getDateDebut()->modify('+13 days'));
        $this->em()->flush();

        $crawler = $this->client->request('GET', $s['url']);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', $s['url'].'?phase='.$s['phase'], ['_token' => $token, 'action' => 'regenerer-'.$s['poules']['A']]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.admin-alerte', 'Les journées actuelles sont conservées.');
        self::assertSame([1, 2, 3], $this->numeros($s['poules']['A']));
    }

    #[TestDox('MEP-13 Fermeture de la saison : semaines comptées à part et sautées par la génération (J2 après la fermeture), sans avertissement')]
    public function test_fermeture(): void
    {
        $s = $this->saison(12, true);
        $this->connecter('admin');
        $this->client->request('GET', $s['url']);
        self::assertSelectorTextContains('body', '12 semaines, dont 2 de fermeture (10 utilisables)');

        $this->cliquer($s['url'], 'generer-toutes');
        $this->em()->clear();
        $poule = $this->em()->find(Poule::class, $s['poules']['A']);
        $journees = $poule->getJournees()->toArray();
        usort($journees, fn (Journee $a, Journee $b) => $a->getNumero() <=> $b->getNumero());
        $phase = $poule->getPhase();
        self::assertEquals($phase->getDateDebut()->modify('+21 days')->format('Y-m-d'), $journees[1]->getDateDebut()->format('Y-m-d'));
        $this->client->request('GET', $s['url']);
        self::assertSelectorTextNotContains('body', 'tombe pendant la fermeture');
    }

    #[TestDox('MEP-15 « Enregistrer et quitter » sur les étapes Poules et Journées : retour à la saison avec message, rien d\'autre n\'est modifié')]
    public function test_enregistrer_et_quitter(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        foreach (['poules' => 'Étape Poules enregistrée', 'journees' => 'Étape Journées enregistrée'] as $etape => $message) {
            $this->cliquer('/admin/saison/'.$s['saison'].'/mise-en-place/'.$etape, 'quitter');
            self::assertResponseRedirects('/admin/saison/'.$s['saison']);
            $this->client->followRedirect();
            self::assertSelectorTextContains('.admin-alerte', $message);
        }
        self::assertSame([], $this->numeros($s['poules']['A']));
    }

    #[TestDox('MEP-14 Sécurité et verrou : jeton absent, poule d\'une autre phase, action inconnue, brouillon, phase avec scores saisis, non-administrateur')]
    public function test_securite(): void
    {
        $s = $this->saison();
        $autre = ChampionnatDeTest::get('poule.B')->getId();
        $this->connecter('admin');
        $crawler = $this->client->request('GET', $s['url']);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $avantAutre = $this->numeros($autre);

        foreach ([
            ['action' => 'generer-toutes'],                                         // sans jeton
            ['_token' => $token, 'action' => 'generer-'.$autre],                    // poule d'une autre phase
            ['_token' => $token, 'action' => 'regenerer-999999'],
            ['_token' => $token, 'action' => 'tout-supprimer'],
        ] as $envoi) {
            $this->client->request('POST', $s['url'], $envoi);
            self::assertResponseRedirects();
            $this->client->followRedirect(); // consomme le message
        }
        self::assertSame([], $this->numeros($s['poules']['A']));
        self::assertSame($avantAutre, $this->numeros($autre));

        // Phase avec des scores saisis : lecture seule, régénération refusée
        $saison = ChampionnatDeTest::get('saison');
        $phase = ChampionnatDeTest::get('phase.1');
        $url = '/admin/saison/'.$saison->getId().'/mise-en-place/journees?phase='.$phase->getId();
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorTextContains('.admin-alerte', 'Cette phase est lancée');
        self::assertSelectorNotExists('button[value="generer-toutes"]');
        $avant = $this->em()->getRepository(Journee::class)->count([]);
        $this->client->request('POST', $url, ['_token' => $crawler->filter('input[name="_token"]')->attr('value'), 'action' => 'regenerer-'.ChampionnatDeTest::get('poule.A')->getId()]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'ses journées ne peuvent plus être refaites ici');
        self::assertSame($avant, $this->em()->getRepository(Journee::class)->count([]));

        // Brouillon renvoyé vers le wizard
        $brouillon = SaisonFactory::createOne(['nom' => 'Brouillon', 'brouillon' => true, 'etapeWizard' => 3, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+3 years'), 'date_fin' => new \DateTimeImmutable('+4 years')]);
        $this->client->request('GET', '/admin/saison/'.$brouillon->getId().'/mise-en-place/journees');
        self::assertResponseRedirects('/admin/saison/'.$brouillon->getId().'/wizard/3');

        $this->connecter('cap.a1');
        $this->client->request('GET', $s['url']);
        $this->assertAccesRefuse('un capitaine ne crée pas les journées');
    }
}
