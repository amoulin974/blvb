<?php

namespace App\Tests\Fonctionnel\Front;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\DomCrawler\Crawler;

/**
 * SCO-18 et SCO-19 : annonce de la saisie avant le match (« Saisie après le match ») et saisie du score depuis la fiche
 * équipe, pour les seules personnes qui peuvent saisir le score du match (SaisieScoreService::peutSaisir).
 */
final class SaisieScoreInterfaceTest extends TestFonctionnel
{
    /** Texte de la case du score d'un match du calendrier (tableau ordinateur) */
    private function caseCalendrier(Crawler $crawler, string $match): string
    {
        $id = ChampionnatDeTest::get($match)->getId();

        return $crawler->filterXPath(sprintf('//span[contains(concat(" ", @class, " "), " score_partie-%d ")]/../..', $id))->first()->text();
    }

    private function boutons(Crawler $crawler, string $match, string $action): int
    {
        return $crawler->filter(sprintf('[data-partie-id="%d"][data-score-action="%s"]', ChampionnatDeTest::get($match)->getId(), $action))->count();
    }

    #[TestDox('SCO-18 Match à venir : « Saisie après le match » pour qui pourra saisir le score (capitaine de l\'équipe qui se déplace), ni pour un autre capitaine, ni pour un joueur, ni pour un visiteur')]
    public function test_annonce_avant_le_match(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/calendrier');
        self::assertStringContainsString('Saisie après le match', $this->caseCalendrier($crawler, 'match.A4-A1'), 'A1 se déplace');
        self::assertStringContainsString('Score à saisir après le match', $this->caseCalendrier($crawler, 'match.A4-A1'), 'lecteurs d\'écran');
        self::assertStringNotContainsString('Saisie après le match', $this->caseCalendrier($crawler, 'match.futur'), 'match sans A1');
        self::assertSame(0, $this->boutons($crawler, 'match.A4-A1', 'saisir'), 'pas de bouton avant le match');

        foreach (['joueur.a1', null] as $compte) {
            $compte ? $this->connecter($compte) : $this->client->restart();
            $crawler = $this->client->request('GET', '/calendrier');
            self::assertStringNotContainsString('Saisie après le match', $this->caseCalendrier($crawler, 'match.A4-A1'), $compte ?? 'visiteur');
        }
    }

    #[TestDox('SCO-19 Fiche équipe : « Saisir » à la place du tiret sur un match passé sans score, aussi dans la carte « Score à saisir » ; crayon sur un match joué, annonce sur un match à venir ; la page se recharge après enregistrement et confirme ; rien pour un joueur ni pour un visiteur')]
    public function test_fiche_equipe(): void
    {
        $url = '/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId();

        foreach (['cap.a1', 'cap.a2', 'resp.club', 'admin'] as $compte) {
            $this->connecter($compte);
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertGreaterThan(0, $this->boutons($crawler, 'match.reference', 'saisir'), $compte.' : match passé sans score');
            self::assertCount(1, $crawler->filter('[data-controller="score-modal"][data-score-modal-recharger-value="true"] [data-score-modal-target="modal"]'), $compte);
            self::assertCount(1, $crawler->filter('[data-score-modal-target="confirmation"]'), $compte.' : confirmation après rechargement');
            self::assertStringContainsString('Score à saisir', $crawler->filter('.grid')->first()->text(), $compte.' : carte de synthèse');
        }

        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $url);
        self::assertGreaterThan(0, $this->boutons($crawler, 'match.A2-A1', 'modifier'), 'match joué : crayon');
        self::assertGreaterThan(0, $crawler->filter('td:contains("Saisie après le match")')->count(), 'match à venir : annonce');
        $case = $crawler->filterXPath(sprintf('//td[.//*[@data-partie-id="%d"][@data-score-action="saisir"]]', ChampionnatDeTest::get('match.reference')->getId()));
        self::assertStringNotContainsString('Score non saisi', $case->text(), 'pas de tiret à côté du bouton « Saisir »');

        $this->connecter('cap.a2');
        $crawler = $this->client->request('GET', $url);
        self::assertSame(0, $crawler->filter('td:contains("Saisie après le match")')->count(), 'A4–A1 ne concerne pas A2');

        foreach (['joueur.a1', null] as $compte) {
            $compte ? $this->connecter($compte) : $this->client->restart();
            $crawler = $this->client->request('GET', $url);
            self::assertSame(0, $crawler->filter('[data-score-action]')->count(), $compte ?? 'visiteur');
            self::assertStringNotContainsString('Saisie après le match', $crawler->text(), $compte ?? 'visiteur');
            self::assertStringNotContainsString('Score à saisir', $crawler->text(), $compte ?? 'visiteur');
            self::assertStringContainsString('Score non saisi', $crawler->text(), $compte ?? 'visiteur'.' : tiret d\'un match passé');
        }
    }
}
