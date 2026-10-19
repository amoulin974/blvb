<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Partie;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SCO-17 : l'API de saisie du score répond 400 (et non 500) à un corps forgé, sans rien enregistrer (relecture
 * sécurité du lot 6, octobre 2026).
 */
final class SaisieScoreRobustesseTest extends TestFonctionnel
{
    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
    }

    private function envoyer(int $partieId, array $corps): array
    {
        $crawler = $this->client->request('GET', '/calendrier');
        $jeton = $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $this->client->request('PUT', '/front/partie/'.$partieId.'/api/update', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
        ], content: json_encode($corps));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function score(int $partieId): array
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $partie = $em->find(Partie::class, $partieId);

        return [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];
    }

    #[TestDox('SCO-17 Un tableau, un objet ou un booléen à la place d\'un score : 400, rien d\'enregistré')]
    public function test_corps_forge(): void
    {
        $this->connecter('cap.a1');
        $id = ChampionnatDeTest::get('match.reference')->getId();

        foreach ([[[1], '2'], ['3', ['a' => 1]], [true, '1'], ['3', null]] as [$reception, $deplacement]) {
            $this->envoyer($id, ['scoreReception' => $reception, 'scoreDeplacement' => $deplacement]);
            self::assertResponseStatusCodeSame(400);
        }
        self::assertSame([null, null], $this->score($id));
    }
}
