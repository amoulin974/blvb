<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Partie;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Saisie du score par un responsable de club : matchs reçus par une équipe de son club, quelle que soit
 * la poule. Le capitaine de l'équipe qui reçoit garde ce droit (App\Security\ScoreVoter).
 *
 * Championnat de référence : « Club test A1-B1 » (A1 et B1, responsable resp.club) ;
 * « Club test voisin » (A3) partage le gymnase G1.
 */
final class SaisieScoreResponsableClubTest extends TestFonctionnel
{
    /** Envoie un score comme le fait score-modal-controller.js, avec le jeton CSRF de la page. */
    private function saisir(Partie $partie, string $reception = '3', string $deplacement = '1'): void
    {
        $crawler = $this->client->request('GET', '/calendrier');
        $jeton = $crawler->filter('meta[name="csrf-token"]')->attr('content');

        $this->client->request('PUT', '/front/partie/'.$partie->getId().'/api/update', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], content: json_encode(['scoreReception' => $reception, 'scoreDeplacement' => $deplacement]));
    }

    public static function matchsAutorises(): iterable
    {
        yield 'équipe du club qui reçoit (poule A)' => ['match.reference'];
        yield 'autre équipe du club qui reçoit (poule B)' => ['match.B1-B2'];
    }

    #[DataProvider('matchsAutorises')]
    #[TestDox('Le responsable de club saisit le score d\'un match reçu par une équipe de son club : $_dataName')]
    public function test_responsable_saisit_les_matchs_recus_par_son_club(string $match): void
    {
        $this->connecter('resp.club');
        $partie = ChampionnatDeTest::get($match);

        $this->saisir($partie);

        self::assertResponseIsSuccessful();
        self::assertSame('3 - 1', json_decode($this->client->getResponse()->getContent(), true)['newScore']);
    }

    public static function matchsRefuses(): iterable
    {
        yield 'match reçu par une équipe d\'un autre club' => ['match.A3-A4'];
        yield 'match où l\'équipe du club se déplace' => ['match.A4-A1'];
    }

    #[DataProvider('matchsRefuses')]
    #[TestDox('Le responsable de club ne saisit pas : $_dataName')]
    public function test_responsable_ne_saisit_pas_hors_de_son_club(string $match): void
    {
        $this->connecter('resp.club');
        $partie = ChampionnatDeTest::get($match);
        $avant = [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];

        $this->saisir($partie, '3', '0');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Modification interdite', json_decode($this->client->getResponse()->getContent(), true)['error']);
        $partie = ChampionnatDeTest::get($match);
        self::assertSame($avant, [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()]);
    }

    #[TestDox('Le capitaine de l\'équipe qui reçoit peut toujours saisir le score')]
    public function test_capitaine_saisit_toujours(): void
    {
        $this->connecter('cap.a1');

        $this->saisir(ChampionnatDeTest::get('match.reference'));

        self::assertResponseIsSuccessful();
    }

    #[TestDox('Un simple joueur de l\'équipe qui reçoit ne peut pas saisir le score')]
    public function test_joueur_ne_saisit_pas(): void
    {
        $this->connecter('joueur.a1');

        $this->saisir(ChampionnatDeTest::get('match.reference'));

        self::assertResponseStatusCodeSame(400);
    }

    #[TestDox('Le calendrier propose « Saisir » au responsable sur les matchs reçus par son club, pas sur les autres')]
    public function test_calendrier_du_responsable(): void
    {
        $this->connecter('resp.club');

        $crawler = $this->client->request('GET', '/calendrier');

        self::assertResponseIsSuccessful();
        $bouton = static fn (string $match) => $crawler->filter(sprintf('[data-partie-id="%d"]', ChampionnatDeTest::get($match)->getId()))->count();
        self::assertGreaterThan(0, $bouton('match.reference'), 'A1 reçoit A2 : bouton attendu');
        self::assertGreaterThan(0, $bouton('match.B1-B2'), 'B1 reçoit B2 : bouton attendu');
        self::assertSame(0, $bouton('match.A3-A4'), 'A3 (autre club) reçoit A4 : pas de bouton');
    }

    #[TestDox('Le rôle ROLE_RESPONSABLE_CLUB vient de la désignation comme responsable d\'un club')]
    public function test_role_responsable(): void
    {
        self::assertContains('ROLE_RESPONSABLE_CLUB', ChampionnatDeTest::compte('resp.club')->getRoles());
        self::assertNotContains('ROLE_RESPONSABLE_CLUB', ChampionnatDeTest::compte('cap.a1')->getRoles());
    }
}
