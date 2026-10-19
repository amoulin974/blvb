<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Partie;
use App\Entity\Phase;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SET-03 : saisie du score d'un match dont la phase se joue en 2 sets gagnants (D48), sur le match de référence
 * (A1 reçoit A2, joué hier, sans score) avec le capitaine de A1. Le format à 3 sets est couvert par SaisieScoreTest.
 */
final class SaisieScoreFormatCourtTest extends TestFonctionnel
{
    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->find(Phase::class, ChampionnatDeTest::get('phase.1')->getId())->setSetsGagnants(2);
        $em->flush();
        $em->clear();
    }

    private function envoyer(mixed $reception, mixed $deplacement): array
    {
        $crawler = $this->client->request('GET', '/calendrier');
        $jeton = $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $this->client->request('PUT', '/front/partie/'.ChampionnatDeTest::get('match.reference')->getId().'/api/update', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
        ], content: json_encode(['scoreReception' => $reception, 'scoreDeplacement' => $deplacement]));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function score(): array
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $partie = $em->find(Partie::class, ChampionnatDeTest::get('match.reference')->getId());

        return [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];
    }

    public static function scoresValides(): iterable
    {
        yield '2-0' => ['2', '0', '2 - 0', [2, 0]];
        yield '2-1' => ['2', '1', '2 - 1', [2, 1]];
        yield '0-2' => ['0', '2', '0 - 2', [0, 2]];
        yield '1-2' => [1, 2, '1 - 2', [1, 2]];
        yield 'interrompu 1-1' => ['1', '1', '1 - 1', [1, 1]];
        yield 'interrompu 1-0' => ['1', '0', '1 - 0', [1, 0]];
    }

    #[DataProvider('scoresValides')]
    #[TestDox('SET-03 Score accepté en 2 sets gagnants : $_dataName')]
    public function test_score_valide(mixed $r, mixed $d, string $affiche, array $attendu): void
    {
        $this->connecter('cap.a1');
        $reponse = $this->envoyer($r, $d);

        self::assertResponseIsSuccessful();
        self::assertSame($affiche, $reponse['newScore']);
        self::assertSame($attendu, $this->score());
    }

    public static function scoresRefuses(): iterable
    {
        yield '3-0 (format long)' => ['3', '0'];
        yield '3-1' => ['3', '1'];
        yield '2-2 (interrompu du format long)' => ['2', '2'];
        yield '0-0' => ['0', '0'];
        yield '2-3' => ['2', '3'];
    }

    #[DataProvider('scoresRefuses')]
    #[TestDox('SET-03 Score refusé en 2 sets gagnants (400, rien d\'enregistré) : $_dataName')]
    public function test_score_refuse(mixed $r, mixed $d): void
    {
        $this->connecter('cap.a1');
        $reponse = $this->envoyer($r, $d);

        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('2 sets', $reponse['error']);
        self::assertSame([null, null], $this->score());
    }

    #[TestDox('SET-03 Forfait en 2 sets : -1 pour l\'équipe forfait, 2 pour l\'autre')]
    public function test_forfait(): void
    {
        $this->connecter('cap.a1');
        self::assertSame('F - 2', $this->envoyer('F', '')['newScore']);
        self::assertSame([-1, 2], $this->score());
        self::assertSame('2 - F', $this->envoyer('', 'F')['newScore']);
        self::assertSame([2, -1], $this->score());
    }

    #[TestDox('SET-03 Le bouton de saisie transmet le format de la phase à la fenêtre (data-sets-gagnants)')]
    public function test_bouton_porte_le_format(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/calendrier');
        $bouton = $crawler->filter('[data-partie-id="'.ChampionnatDeTest::get('match.reference')->getId().'"][data-score-action="saisir"]');

        self::assertGreaterThan(0, $bouton->count());
        self::assertSame('2', $bouton->first()->attr('data-sets-gagnants'));
    }
}
