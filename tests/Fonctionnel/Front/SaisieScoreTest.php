<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Classement;
use App\Entity\Partie;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SCO-01 à SCO-11, SCO-16 : API de saisie du score (PUT /front/partie/{id}/api/update), sur le match de référence
 * (A1 reçoit A2, joué hier, sans score) avec le capitaine de A1. Les droits par profil sont dans
 * SaisieScoreResponsableClubTest et CompositionAccesTest.
 */
final class SaisieScoreTest extends TestFonctionnel
{
    private function envoyer(string $match, mixed $reception, mixed $deplacement, ?string $jeton = 'page', ?array $corps = null): array
    {
        if ($jeton === 'page') {
            $crawler = $this->client->request('GET', '/calendrier');
            $jeton = $crawler->filter('meta[name="csrf-token"]')->count() ? $crawler->filter('meta[name="csrf-token"]')->attr('content') : 'aucun';
        }
        $this->client->request('PUT', '/front/partie/'.ChampionnatDeTest::get($match)->getId().'/api/update', server: array_filter([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
        ]), content: json_encode($corps ?? ['scoreReception' => $reception, 'scoreDeplacement' => $deplacement]));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    /** Score enregistré en base : [sets de l'équipe qui reçoit, sets de l'équipe qui se déplace]. */
    private function score(string $match): array
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $partie = $em->find(Partie::class, ChampionnatDeTest::get($match)->getId());

        return [$partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()];
    }

    private function points(string $equipe): ?int
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(Classement::class)->findOneBy(['equipe' => ChampionnatDeTest::get('equipe.'.$equipe)->getId(), 'poule' => ChampionnatDeTest::get('poule.A')->getId()])?->getPoints();
    }

    #[TestDox('SCO-01 Sans jeton de sécurité ou avec un jeton faux : 403, score inchangé')]
    public function test_jeton(): void
    {
        $this->connecter('cap.a1');
        foreach ([null, 'faux'] as $jeton) {
            $this->envoyer('match.reference', '3', '0', $jeton);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame([null, null], $this->score('match.reference'));
    }

    #[TestDox('SCO-02 Visiteur : refus (403), score inchangé')]
    public function test_visiteur(): void
    {
        $this->envoyer('match.reference', '3', '0');
        self::assertResponseStatusCodeSame(403);
        self::assertSame([null, null], $this->score('match.reference'));
    }

    #[TestDox('SCO-03/04 (A4) Refus de droits (capitaine d\'une autre poule) : 403 « Modification interdite » (et non 400)')]
    public function test_refus_403(): void
    {
        $this->connecter('cap.b1');
        $reponse = $this->envoyer('match.reference', '3', '0');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Modification interdite', $reponse['error']);
    }

    public static function scoresValides(): iterable
    {
        foreach ([[3, 0], [3, 1], [3, 2], [2, 3], [1, 3], [0, 3]] as [$r, $d]) {
            yield "$r-$d" => [(string) $r, (string) $d, "$r - $d", [$r, $d]];
        }
        yield 'nombres JSON' => [3, 1, '3 - 1', [3, 1]];
        yield 'espaces autour' => [' 3 ', '2', '3 - 2', [3, 2]];
    }

    #[DataProvider('scoresValides')]
    #[TestDox('SCO-04 Score valide enregistré : $_dataName')]
    public function test_score_valide(mixed $r, mixed $d, string $affiche, array $attendu): void
    {
        $this->connecter('cap.a1');
        $reponse = $this->envoyer('match.reference', $r, $d);

        self::assertResponseIsSuccessful();
        self::assertSame($affiche, $reponse['newScore']);
        self::assertSame($attendu, $this->score('match.reference'));
    }

    public static function scoresRefuses(): iterable
    {
        yield 'plus de 3 sets' => ['4', '0'];
        yield 'sets négatifs' => ['-1', '3'];
        yield 'lettres' => ['a', '3'];
        yield 'décimal' => ['2.5', '3'];
        yield 'décimal JSON' => [2.5, 3];
        yield 'SCO-06 (A2) 3-3' => ['3', '3'];
        yield 'SCO-06 (A2) 0-0' => ['0', '0'];
        yield 'SCO-06 (A2) 2-0, ni complet ni règle 1R' => ['2', '0'];
        yield 'deux forfaits' => ['F', 'F'];
    }

    #[DataProvider('scoresRefuses')]
    #[TestDox('SCO-05/06 Score refusé (400), rien n\'est enregistré : $_dataName')]
    public function test_score_refuse(mixed $r, mixed $d): void
    {
        $this->connecter('cap.a1');
        $reponse = $this->envoyer('match.reference', $r, $d);

        self::assertResponseStatusCodeSame(400);
        self::assertNotEmpty($reponse['error']);
        self::assertSame([null, null], $this->score('match.reference'));
    }

    #[TestDox('SCO-05 Champ absent : 400')]
    public function test_champ_absent(): void
    {
        $this->connecter('cap.a1');
        $this->envoyer('match.reference', null, null, corps: ['scoreReception' => '3']);
        self::assertResponseStatusCodeSame(400);
    }

    #[TestDox('SCO-07 Forfait : « F » pour l\'équipe forfait, enregistré -1 contre 3 sets')]
    public function test_forfait(): void
    {
        $this->connecter('cap.a1');
        self::assertSame('F - 3', $this->envoyer('match.reference', 'F', '')['newScore']);
        self::assertSame([-1, 3], $this->score('match.reference'));
        self::assertSame('3 - F', $this->envoyer('match.reference', '', 'F')['newScore']);
        self::assertSame([3, -1], $this->score('match.reference'));
    }

    #[TestDox('SCO-08/09 Le classement est recalculé à chaque saisie, et l\'effacement retire les points du match')]
    public function test_classement_et_effacement(): void
    {
        $this->connecter('cap.a1');
        $this->envoyer('match.reference', '3', '1');
        $avecMatch = $this->points('A1');

        $reponse = $this->envoyer('match.reference', '', '');
        self::assertResponseIsSuccessful();
        self::assertNull($reponse['newScore']);
        self::assertSame([null, null], $this->score('match.reference'));
        self::assertSame($avecMatch - 5, $this->points('A1'), 'la victoire 3-1 (5 points) est retirée');
    }

    #[TestDox('SCO-10 (A5) Match à venir : refusé (403) au capitaine et au responsable, accepté pour l\'admin')]
    public function test_match_a_venir(): void
    {
        foreach (['cap.a2', 'resp.club'] as $compte) { // A2 reçoit A3 la semaine prochaine ; A4-A1 pour le club de A1
            $this->connecter($compte);
            $match = $compte === 'cap.a2' ? 'match.futur' : 'match.A4-A1';
            $reponse = $this->envoyer($match, '3', '0');
            self::assertResponseStatusCodeSame(403, $compte);
            self::assertStringContainsString('pas encore eu lieu', $reponse['error']);
            self::assertSame([null, null], $this->score($match));
        }

        $this->connecter('admin');
        $this->envoyer('match.futur', '3', '0');
        self::assertResponseIsSuccessful();
        self::assertSame([3, 0], $this->score('match.futur'));
    }

    #[TestDox('SCO-11 Match inexistant : 404')]
    public function test_match_inexistant(): void
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/calendrier');
        $this->client->request('PUT', '/front/partie/999999/api/update', server: ['CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $crawler->filter('meta[name="csrf-token"]')->attr('content')], content: '{"scoreReception":"3","scoreDeplacement":"0"}');
        self::assertResponseStatusCodeSame(404);
    }

    public static function scoresInterrompus(): iterable
    {
        yield '2-1 : 2 points / 1 point' => ['2', '1', 2, 1];
        yield '1-2 : 1 point / 2 points' => ['1', '2', 1, 2];
        yield '2-2 : 1 point chacun' => ['2', '2', 1, 1];
        yield '1-1 : aucun point' => ['1', '1', 0, 0];
        yield '1-0 : aucun point' => ['1', '0', 0, 0];
    }

    #[DataProvider('scoresInterrompus')]
    #[TestDox('SCO-16 Match interrompu (règle 1R) saisi par le capitaine, points du règlement : $_dataName')]
    public function test_match_interrompu(string $r, string $d, int $pointsA1, int $pointsA2): void
    {
        $this->connecter('cap.a1');
        // Le classement de la story n'est pas encore calculé : une première saisie puis un effacement le calculent
        $this->envoyer('match.reference', '', '');
        $baseA1 = $this->points('A1');
        $baseA2 = $this->points('A2');

        $reponse = $this->envoyer('match.reference', $r, $d);
        self::assertResponseIsSuccessful();
        self::assertSame("$r - $d", $reponse['newScore']);
        self::assertSame([(int) $r, (int) $d], $this->score('match.reference'));
        self::assertSame($baseA1 + $pointsA1, $this->points('A1'));
        self::assertSame($baseA2 + $pointsA2, $this->points('A2'));
    }

    #[TestDox('La fenêtre de saisie renvoie vers la règle 1R du règlement (nouvel onglet), et l\'ancre existe')]
    public function test_lien_regle_1r(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/calendrier');
        $lien = $crawler->filter('[data-score-modal-target="aideInterrompu"] a');
        self::assertSame('/reglement#regle-1r', $lien->attr('href'));
        self::assertSame('_blank', $lien->attr('target'));

        $crawler = $this->client->request('GET', '/reglement');
        self::assertStringContainsString('RÈGLE 1R', $crawler->filter('#regle-1r')->text());
    }
}
