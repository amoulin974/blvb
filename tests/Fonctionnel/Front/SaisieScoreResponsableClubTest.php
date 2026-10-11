<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Club;
use App\Entity\Partie;
use App\Entity\User;
use App\Enum\PhaseType;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\UserFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Saisie du score par un responsable de club : tous les matchs des équipes de son club, à domicile ou à
 * l'extérieur, quelle que soit la poule. Le capitaine garde son droit sur les matchs que son équipe reçoit
 * (App\Security\ScoreVoter).
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
        yield 'équipe du club qui se déplace' => ['match.A4-A1'];
    }

    #[DataProvider('matchsAutorises')]
    #[TestDox('Le responsable de club saisit le score d\'un match de son club : $_dataName')]
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
        yield 'match entre une équipe d\'un autre club et une équipe sans club' => ['match.A3-A4'];
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

    #[TestDox('Le capitaine de l\'équipe qui se déplace ne peut pas saisir le score')]
    public function test_capitaine_qui_se_deplace(): void
    {
        $this->connecter('cap.a2');

        $this->saisir(ChampionnatDeTest::get('match.reference'));

        self::assertResponseStatusCodeSame(400);
    }

    #[TestDox('Un simple joueur de l\'équipe qui reçoit ne peut pas saisir le score')]
    public function test_joueur_ne_saisit_pas(): void
    {
        $this->connecter('joueur.a1');

        $this->saisir(ChampionnatDeTest::get('match.reference'));

        self::assertResponseStatusCodeSame(400);
    }

    #[TestDox('Le calendrier propose « Saisir » au responsable sur les matchs de son club, pas sur les autres')]
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

    /** Match de la saison précédente : A1 (club CTA) reçoit A2, il y a un an, sans score. */
    private function matchSaisonPrecedente(): Partie
    {
        $precedente = ChampionnatDeTest::get('saison.precedente');
        $phase = PhaseFactory::createOne(['saison' => $precedente, 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => new \DateTimeImmutable('-13 months'), 'datefin' => new \DateTimeImmutable('-10 months')]);
        $poule = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1,
            'equipes' => [ChampionnatDeTest::get('equipe.A1'), ChampionnatDeTest::get('equipe.A2')]]);
        $journee = JourneeFactory::createOne(['poule' => $poule, 'numero' => 1,
            'date_debut' => new \DateTimeImmutable('-12 months -3 days'), 'date_fin' => new \DateTimeImmutable('-12 months +3 days')]);

        return PartieFactory::createOne(['journee' => $journee, 'poule' => $poule, 'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'),
            'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'), 'date' => new \DateTimeImmutable('-12 months')]);
    }

    private function club(): Club
    {
        return self::getContainer()->get('doctrine')->getManager()->find(Club::class, ChampionnatDeTest::get('club')->getId());
    }

    #[TestDox('Les droits du responsable valent pour la saison de sa désignation : pas de saisie d\'un match d\'une saison précédente')]
    public function test_responsable_saison_courante_pas_de_saisie_ancienne_saison(): void
    {
        $ancien = $this->matchSaisonPrecedente();
        $this->connecter('resp.club');

        $this->saisir($ancien);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Modification interdite', json_decode($this->client->getResponse()->getContent(), true)['error']);
    }

    #[TestDox('Un responsable désigné pour la saison précédente seulement : saisie de cette saison-là, mais ni saisie ni fiche du club ni rôle pour la saison en cours')]
    public function test_responsable_saison_precedente(): void
    {
        $ancien = $this->matchSaisonPrecedente();
        $em = self::getContainer()->get('doctrine')->getManager();
        $ancienResponsable = UserFactory::createOne(['email' => 'resp.ancien@blvb.test', 'prenom' => 'Ancien', 'nom' => 'Responsable']);
        $club = $this->club();
        $club->setResponsables($em->find(\App\Entity\Saison::class, ChampionnatDeTest::get('saison.precedente')->getId()), [$em->find(User::class, $ancienResponsable->getId())]);
        $em->flush();

        $user = $em->find(User::class, $ancienResponsable->getId());
        self::assertNotContains('ROLE_RESPONSABLE_CLUB', $user->getRoles(), 'pas de rôle hors de la saison en cours');
        $this->client->loginUser($user);

        $this->saisir(ChampionnatDeTest::get('match.reference'));
        self::assertResponseStatusCodeSame(400, 'match de la saison en cours refusé');

        $this->saisir($ancien);
        self::assertResponseIsSuccessful('match de la saison de sa désignation accepté');

        $this->client->request('GET', '/club/'.$club->getId().'/modifier');
        self::assertResponseStatusCodeSame(403, 'fiche du club refusée');
    }

    #[TestDox('Un responsable retiré perd ses droits immédiatement, sans se reconnecter')]
    public function test_retrait_immediat(): void
    {
        $this->connecter('resp.club');
        $crawler = $this->client->request('GET', '/calendrier');
        $jeton = $crawler->filter('meta[name="csrf-token"]')->attr('content');
        $match = ChampionnatDeTest::get('match.B1-B2');
        $avant = [$match->getNbSetGagnantReception(), $match->getNbSetGagnantDeplacement()];

        $em = self::getContainer()->get('doctrine')->getManager();
        $club = $this->club();
        $club->setResponsables($em->find(\App\Entity\Saison::class, ChampionnatDeTest::get('saison')->getId()), []);
        $em->flush();

        // Requête préparée avant le retrait (même jeton) : refusée, score inchangé
        $this->client->request('PUT', '/front/partie/'.$match->getId().'/api/update', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $jeton, 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], content: json_encode(['scoreReception' => '0', 'scoreDeplacement' => '3']));
        self::assertContains($this->client->getResponse()->getStatusCode(), [400, 403], 'après retrait : saisie refusée');
        $em->clear();
        $match = $em->find(Partie::class, $match->getId());
        self::assertSame($avant, [$match->getNbSetGagnantReception(), $match->getNbSetGagnantDeplacement()]);

        $this->client->request('GET', '/club/'.$club->getId().'/modifier');
        self::assertNotSame(200, $this->client->getResponse()->getStatusCode(), 'après retrait : fiche du club refusée');
    }
}
