<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Partie;
use App\Enum\PhaseType;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** ICS-01 à ICS-08 : agenda .ics d'une équipe (abonnement depuis la fiche équipe). Équipe A1, saison courante. */
final class IcsTest extends TestFonctionnel
{
    private function url(): string
    {
        return sprintf('/calendar/saison/%d/equipe/%d.ics', ChampionnatDeTest::get('saison')->getId(), ChampionnatDeTest::get('equipe.A1')->getId());
    }

    private function agenda(array $entetes = []): string
    {
        $this->client->request('GET', $this->url(), server: $entetes);

        return (string) $this->client->getResponse()->getContent();
    }

    /** @return list<string> identifiants des événements */
    private function uids(string $ical): array
    {
        preg_match_all('/^UID:(.+)\r$/m', $ical, $m);

        return $m[1];
    }

    #[TestDox('ICS-01/08 Réponse : 200, text/calendar en UTF-8, fichier .ics ; l\'ancienne adresse par poule est toujours servie')]
    public function test_reponse(): void
    {
        $this->agenda();
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        self::assertMatchesRegularExpression('/filename="?[a-z0-9-]+\.ics"?/', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('GET', sprintf('/calendar/%d/%d.ics', ChampionnatDeTest::get('poule.A')->getId(), ChampionnatDeTest::get('equipe.A1')->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('BEGIN:VCALENDAR', (string) $this->client->getResponse()->getContent());
    }

    #[TestDox('ICS-02/03 Un événement par match de l\'équipe pour cette saison seulement ; identifiants stables d\'une génération à l\'autre')]
    public function test_evenements(): void
    {
        // Un match de A1 la saison précédente ne doit pas apparaître
        $phase = PhaseFactory::createOne(['saison' => ChampionnatDeTest::get('saison.precedente'), 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => new \DateTimeImmutable('-13 months'), 'datefin' => new \DateTimeImmutable('-10 months')]);
        $poule = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'equipes' => [ChampionnatDeTest::get('equipe.A1'), ChampionnatDeTest::get('equipe.A2')]]);
        $ancien = PartieFactory::createOne(['journee' => JourneeFactory::createOne(['poule' => $poule, 'numero' => 1]), 'poule' => $poule,
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'), 'date' => new \DateTimeImmutable('-12 months')]);

        $attendus = array_map(fn (string $m) => sprintf('match-%d@blvb', ChampionnatDeTest::get($m)->getId()), ['match.A2-A1', 'match.reference', 'match.A4-A1']);
        $premiere = $this->uids($this->agenda());
        sort($premiere);
        sort($attendus);
        self::assertSame($attendus, $premiere);
        self::assertNotContains(sprintf('match-%d@blvb', $ancien->getId()), $premiere);
        $seconde = $this->uids($this->agenda());
        sort($seconde);
        self::assertSame($premiere, $seconde, 'mêmes identifiants');
    }

    #[TestDox('ICS-04 Fuseau Europe/Paris déclaré ; heure locale juste en hiver comme en été')]
    public function test_fuseau(): void
    {
        foreach (['2027-01-13 20:30', '2027-06-16 20:30'] as $date) {
            PartieFactory::createOne(['journee' => ChampionnatDeTest::get('match.reference')->getJournee(), 'poule' => ChampionnatDeTest::get('poule.A'),
                'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A3'), 'date' => new \DateTimeImmutable($date)]);
        }
        $ical = $this->agenda();

        self::assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:Europe/Paris", $ical);
        self::assertStringContainsString('BEGIN:STANDARD', $ical);
        self::assertStringContainsString('BEGIN:DAYLIGHT', $ical);
        self::assertStringContainsString('DTSTART;TZID=Europe/Paris:20270113T203000', $ical, 'hiver');
        self::assertStringContainsString('DTSTART;TZID=Europe/Paris:20270616T203000', $ical, 'été');
    }

    #[TestDox('ICS-05 Description : phase, poule et journée ; résultat « 1–3 » quand le score est saisi')]
    public function test_description(): void
    {
        $ical = str_replace("\r\n ", '', $this->agenda()); // lignes dépliées (RFC 5545)
        $journee = ChampionnatDeTest::get('match.A2-A1')->getJournee()->getNumero();
        self::assertStringContainsString("Phase 1 · Poule A · Journée $journee", $ical);
        self::assertStringContainsString('Résultat : 1–3', $ical);
    }

    #[TestDox('ICS-06 Cache : même ETag sans changement, 304 si l\'agenda n\'a pas changé, nouvel ETag après une saisie de score')]
    public function test_cache(): void
    {
        $this->agenda();
        $etag = $this->client->getResponse()->headers->get('ETag');
        self::assertNotEmpty($etag);
        $this->agenda();
        self::assertSame($etag, $this->client->getResponse()->headers->get('ETag'));

        $this->agenda(['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->find(Partie::class, ChampionnatDeTest::get('match.reference')->getId())->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(0);
        $em->flush();
        $this->agenda(['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseIsSuccessful();
        self::assertNotSame($etag, $this->client->getResponse()->headers->get('ETag'));
    }

    #[TestDox('ICS-07 Lignes de 75 octets au plus, fins de ligne CRLF (RFC 5545)')]
    public function test_format(): void
    {
        $ical = $this->agenda();
        self::assertStringEndsWith("\r\n", $ical);
        self::assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $ical, 'aucun saut de ligne sans CR');
        foreach (explode("\r\n", rtrim($ical, "\r\n")) as $ligne) {
            self::assertLessThanOrEqual(75, \strlen($ligne), $ligne);
        }
    }
}
