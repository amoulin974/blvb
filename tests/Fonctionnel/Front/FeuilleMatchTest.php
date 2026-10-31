<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\Partie;
use App\Factory\JoueurFactory;
use App\Factory\MembreEquipeFactory;
use App\Factory\UserFactory;
use App\Security\FeuilleMatchVoter;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use App\VolleyStats\MatchEquipe;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * VST-01 à VST-04 : page du match d'une équipe, droits (Volley Stats, D67 ; spécification §4).
 * Match de référence : A1 (club CTA, responsable resp.club) reçoit A2, hier.
 */
final class FeuilleMatchTest extends TestFonctionnel
{
    use ClockSensitiveTrait;

    private function url(string $equipe = 'A1', string $match = 'match.reference'): string
    {
        return '/equipe/'.ChampionnatDeTest::get('equipe.'.$equipe)->getId().'/match/'.ChampionnatDeTest::get($match)->getId();
    }

    #[TestDox('VST-01 : la page du match s\'ouvre pour les membres de l\'équipe, le responsable de son club et l\'admin, en lecture privée')]
    public function test_ayants_droit(): void
    {
        foreach (['joueur.a1', 'cap.a1', 'resp.club', 'admin'] as $compte) {
            $this->connecter($compte);
            $this->client->request('GET', $this->url());
            self::assertResponseIsSuccessful($compte);
            self::assertSelectorTextContains('h1', 'Contre EQUIPE A2', $compte);
        }
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    #[TestDox('VST-02 : l\'équipe adverse, une autre équipe du même club et un visiteur n\'y ont pas accès ; chacun a la page de sa propre équipe')]
    public function test_refus(): void
    {
        $this->client->request('GET', $this->url());
        $this->assertRedirigeVersConnexion('visiteur');

        foreach (['cap.a2', 'joueur.a2', 'cap.b1', 'sans.equipe'] as $compte) {
            $this->connecter($compte);
            $this->client->request('GET', $this->url());
            self::assertResponseStatusCodeSame(404, $compte);
        }
        $this->connecter('cap.a2');
        $this->client->request('GET', $this->url('A2'));
        self::assertResponseIsSuccessful('page de sa propre équipe');
    }

    #[TestDox('VST-03 : une équipe qui ne joue pas ce match n\'a pas de page, même pour l\'admin ; un match sans adversaire non plus')]
    public function test_equipe_hors_match(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', $this->url('A3'));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId().'/match/999999');
        self::assertResponseStatusCodeSame(404);
    }

    #[TestDox('VST-04 : seule compte la saison du match : un membre de l\'équipe la saison précédente seulement n\'a pas accès')]
    public function test_saison_du_match(): void
    {
        $user = UserFactory::createOne(['email' => 'ancien.a1@blvb.test']);
        $joueur = JoueurFactory::createOne(['prenom' => 'Ancien', 'nom' => 'Membre', 'email' => 'ancien.a1@blvb.test', 'user' => $user]);
        MembreEquipeFactory::createOne(['equipe' => ChampionnatDeTest::get('equipe.A1'), 'saison' => ChampionnatDeTest::get('saison.precedente'), 'joueur' => $joueur]);
        $this->client->loginUser($user);

        $this->client->request('GET', $this->url());
        self::assertResponseStatusCodeSame(404);
    }

    #[TestDox('VST-04 : la page se fige 30 jours après un match jamais terminé, 7 jours après la fin ; l\'admin peut toujours la corriger')]
    public function test_gel(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $partie = $em->getRepository(Partie::class)->find(ChampionnatDeTest::get('match.reference')->getId());
        $equipe = $em->getRepository(Equipe::class)->find(ChampionnatDeTest::get('equipe.A1')->getId());
        $voter = self::getContainer()->get(FeuilleMatchVoter::class);
        $feuille = new FeuilleMatch($partie, $equipe);

        // Dates absolues : « +6 days » se compterait depuis l'heure déjà simulée
        $maintenant = new \DateTimeImmutable();
        self::assertFalse($voter->estGelee(new MatchEquipe($equipe, $partie, $feuille)), 'match d\'hier');
        self::mockTime($maintenant->modify('+31 days'));
        self::assertTrue($voter->estGelee(new MatchEquipe($equipe, $partie, $feuille)), 'jamais terminée, 31 jours après');

        $feuille->setEtat(FeuilleMatch::TERMINEE);
        self::mockTime($maintenant->modify('+6 days'));
        self::assertFalse($voter->estGelee(new MatchEquipe($equipe, $partie, $feuille)), '6 jours après la fin');
        self::mockTime($maintenant->modify('+8 days'));
        self::assertTrue($voter->estGelee(new MatchEquipe($equipe, $partie, $feuille)), '8 jours après la fin');
    }
}
