<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\MembreEquipe;
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
 * VST-01 à VST-07, VST-09 à VST-11 : page du match d'une équipe, droits, joueurs présents, composition et journal des sets
 * (Volley Stats, D67 ; spécification §3 à §5). VST-08 : Unitaire/VolleyStats/EtatSetTest.
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

    private function feuille(string $equipe = 'A1', string $match = 'match.reference'): ?FeuilleMatch
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(FeuilleMatch::class)->findOneBy(['partie' => ChampionnatDeTest::get($match)->getId(), 'equipe' => ChampionnatDeTest::get('equipe.'.$equipe)->getId()]);
    }

    private function membre(string $compte, string $equipe = 'A1'): MembreEquipe
    {
        return self::getContainer()->get('doctrine')->getManager()->getRepository(MembreEquipe::class)->findOneBy([
            'joueur' => ChampionnatDeTest::get('joueur.'.$compte)->getId(),
            'equipe' => ChampionnatDeTest::get('equipe.'.$equipe)->getId(),
            'saison' => ChampionnatDeTest::get('saison')->getId(),
        ]);
    }

    /** @return list<string> noms des joueurs cochés sur la page */
    private function coches(): array
    {
        return $this->client->getCrawler()->filter('input[name="presents[]"]:checked')->each(fn ($c) => trim($c->closest('label')->text()));
    }

    #[TestDox('VST-05 : une visite ne crée rien ; « Préparer le match » crée la page avec toute l\'équipe cochée, puis les présents se modifient')]
    public function test_presents(): void
    {
        $this->connecter('joueur.a1');
        $crawler = $this->client->request('GET', $this->url());
        self::assertNull($this->feuille(), 'rien n\'est créé par une visite');
        self::assertSame(['Camille Ar.', 'Jean Pr.', 'Pat An.'], $this->coches(), 'toute l\'équipe cochée d\'office');

        $form = $crawler->selectButton('Préparer le match')->form();
        $form['presents'][2]->untick();
        $this->client->submit($form);
        self::assertResponseRedirects($this->url());
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Joueurs présents enregistrés.');
        self::assertSame(['Camille Ar.', 'Jean Pr.'], $this->coches());
        self::assertCount(2, $this->feuille()->getJoueurs());
    }

    #[TestDox('VST-06 : au match suivant, les présents du match précédent sont cochés d\'office')]
    public function test_presents_repris(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $this->url('A1', 'match.A2-A1'));
        $form = $crawler->selectButton('Préparer le match')->form();
        $form['presents'][1]->untick();
        $this->client->submit($form);

        $crawler = $this->client->request('GET', $this->url());
        self::assertSame(['Camille Ar.', 'Pat An.'], $this->coches(), 'repris du match du '.ChampionnatDeTest::get('match.A2-A1')->getDate()->format('d/m'));
        $this->client->request('POST', $this->url().'/preparer', ['_token' => $crawler->filter('input[name="_token"]')->attr('value')]);
        self::assertResponseRedirects($this->url());
        self::assertCount(2, $this->feuille()->getJoueurs());
    }

    #[TestDox('VST-07 : présents refusés : membre d\'une autre équipe, jeton absent, équipe adverse (404) ; rien n\'est enregistré')]
    public function test_presents_refuses(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $this->url());
        $jeton = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->url().'/presents', ['_token' => $jeton, 'presents' => [(string) $this->membre('cap.a2', 'A2')->getId()]]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'ne fait pas partie de l\'équipe');
        self::assertSelectorTextNotContains('[role="alert"]', 'Claude', 'aucun nom repris');

        $this->client->request('POST', $this->url().'/presents', ['presents' => [(string) $this->membre('cap.a1')->getId()]]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'La page a expiré');

        $this->connecter('cap.a2');
        $this->client->request('POST', $this->url().'/presents', ['_token' => $jeton, 'presents' => []]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->url().'/preparer', ['_token' => $jeton]);
        self::assertResponseStatusCodeSame(404);
    }

    private string $jeton = '';

    /** Prépare la page de A1 avec six joueurs présents (trois membres ajoutés) ; renvoie les identifiants des présents */
    private function preparerSix(): array
    {
        foreach (['Ana Ur', 'Beñat Lo', 'Maite Ze'] as $i => $nom) {
            [$prenom, $famille] = explode(' ', $nom);
            $joueur = JoueurFactory::createOne(['prenom' => $prenom, 'nom' => $famille, 'email' => 'extra'.$i.'@blvb.test']);
            MembreEquipeFactory::createOne(['equipe' => ChampionnatDeTest::get('equipe.A1'), 'saison' => ChampionnatDeTest::get('saison'), 'joueur' => $joueur]);
        }
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $this->url());
        $this->jeton = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->submit($crawler->selectButton('Préparer le match')->form());

        return array_map(fn ($j) => $j->getId(), $this->feuille()->getJoueurs()->toArray());
    }

    /** @param array<string, mixed> $donnees @return array<string, mixed> */
    private function envoyer(int $set, string $action, array $donnees = [], ?string $jeton = null): array
    {
        $this->client->request('POST', $this->url().'/set/'.$set.'/'.$action, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton], json_encode($donnees));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    private function point(string $pour, ?int $dernierOrdre, ?string $uuid = null): array
    {
        return $this->envoyer(1, 'evenement', ['uuid' => $uuid ?? self::uuid(), 'type' => 'point', 'donnees' => ['pour' => $pour], 'dernierOrdre' => $dernierOrdre]);
    }

    private static function uuid(): string
    {
        $o = random_bytes(16);
        $o[6] = \chr(\ord($o[6]) & 0x0f | 0x40);
        $o[8] = \chr(\ord($o[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($o), 4));
    }

    #[TestDox('VST-09 : composition du set 1 validée côté serveur (six joueurs de cette page, distincts, une place libre au plus) ; commencer exige le premier service')]
    public function test_composition(): void
    {
        $ids = $this->preparerSix();
        $refus = [
            'doublon' => [$ids[0], $ids[0], $ids[2], $ids[3], $ids[4], $ids[5]],
            'sept places' => [...$ids, null],
            'deux places libres' => [$ids[0], $ids[1], $ids[2], $ids[3], null, null],
            'joueur inconnu' => [999999, $ids[1], $ids[2], $ids[3], $ids[4], $ids[5]],
            'texte' => ['1', $ids[1], $ids[2], $ids[3], $ids[4], $ids[5]],
        ];
        foreach ($refus as $cas => $postes) {
            $reponse = $this->envoyer(1, 'composition', ['composition' => ['postes' => $postes, 'premierService' => null, 'scoreDepart' => [0, 0]]]);
            self::assertResponseStatusCodeSame(400, $cas);
            self::assertStringNotContainsString('Camille', $reponse['message'], $cas);
        }
        $this->envoyer(1, 'composition', ['composition' => ['postes' => $ids, 'premierService' => null, 'scoreDepart' => [0, 8], 'inconnu' => 1]]);
        self::assertResponseStatusCodeSame(400, 'clé inconnue');

        $this->envoyer(1, 'composition', ['composition' => ['postes' => $ids, 'premierService' => null, 'scoreDepart' => [0, 8]]]);
        self::assertResponseIsSuccessful();
        $reponse = $this->envoyer(1, 'commencer');
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Choisissez le premier service.', $reponse['message']);
        $this->envoyer(1, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'eux', 'scoreDepart' => [0, 8]]]);
        $reponse = $this->envoyer(1, 'commencer');
        self::assertResponseIsSuccessful();
        self::assertSame('en_cours', $reponse['feuille']['sets'][0]['etat']);
        self::assertSame([0, 8], $reponse['feuille']['sets'][0]['calcul']['score']);
        $this->envoyer(1, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'nous', 'scoreDepart' => [0, 0]]]);
        self::assertResponseStatusCodeSame(400, 'set commencé : composition figée');
        $this->envoyer(2, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'nous', 'scoreDepart' => [0, 0]]]);
        self::assertResponseStatusCodeSame(400, 'set 2 avant la fin du set 1');
    }

    #[TestDox('VST-10 : points et rotation, envoi rejoué sans effet, saisie concurrente refusée (409), « Précédent » et « Suivant », historique')]
    public function test_journal(): void
    {
        $ids = $this->preparerSix();
        $this->envoyer(1, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'eux', 'scoreDepart' => [0, 0]]]);
        $this->envoyer(1, 'commencer');

        $uuid = self::uuid();
        $reponse = $this->point('nous', null, $uuid);
        self::assertSame([1, 0], $reponse['feuille']['sets'][0]['calcul']['score']);
        self::assertSame([$ids[1], $ids[2], $ids[3], $ids[4], $ids[5], $ids[0]], $reponse['feuille']['sets'][0]['calcul']['postes'], 'rotation');
        $reponse = $this->point('nous', null, $uuid);
        self::assertSame([1, 0], $reponse['feuille']['sets'][0]['calcul']['score'], 'même uuid : sans effet');

        $this->point('eux', null);
        self::assertResponseStatusCodeSame(409, 'un autre téléphone a saisi');
        $reponse = $this->point('eux', 1);
        self::assertSame([1, 1], $reponse['feuille']['sets'][0]['calcul']['score']);
        $historique = $reponse['feuille']['sets'][0]['historique'];
        self::assertSame(['Point pour EQUIPE A1 (CTA)', 'Point pour EQUIPE A2'], array_column($historique, 'texte'));

        $reponse = $this->envoyer(1, 'precedent', ['uuid' => $historique[1]['uuid']]);
        self::assertSame([1, 0], $reponse['feuille']['sets'][0]['calcul']['score']);
        self::assertTrue($reponse['feuille']['sets'][0]['historique'][1]['annule'], 'gardé dans l\'historique, barré');
        $reponse = $this->envoyer(1, 'suivant', ['uuid' => $historique[1]['uuid']]);
        self::assertSame([1, 1], $reponse['feuille']['sets'][0]['calcul']['score']);

        $this->envoyer(1, 'precedent', ['uuid' => $historique[1]['uuid']]);
        $this->point('nous', 2);
        $this->envoyer(1, 'suivant', ['uuid' => $historique[1]['uuid']]);
        self::assertResponseStatusCodeSame(400, 'nouvel événement depuis : plus rétablissable');

        $reponse = $this->envoyer(1, 'evenement', ['uuid' => self::uuid(), 'type' => 'remplacement', 'donnees' => ['sort' => $ids[0], 'entre' => $ids[1]], 'dernierOrdre' => 3]);
        self::assertResponseStatusCodeSame(400, 'l\'entrant est déjà sur le terrain');
        $this->envoyer(1, 'evenement', ['uuid' => self::uuid(), 'type' => 'point', 'donnees' => ['pour' => 'nous', 'motif' => 'blessure'], 'dernierOrdre' => 3]);
        self::assertResponseStatusCodeSame(400, 'clé inconnue');
    }

    #[TestDox('VST-11 : fin de set refusée avant le score de fin, puis set suivant ; match gagné : page terminée ; rien n\'est écrit dans le match ni le classement')]
    public function test_fin_de_set(): void
    {
        $ids = $this->preparerSix();
        $match = ChampionnatDeTest::get('match.reference');
        for ($n = 1; $n <= 3; ++$n) {
            $this->envoyer($n, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'nous', 'scoreDepart' => [8, 0]]]);
            $this->envoyer($n, 'commencer');
            $ordre = null;
            for ($p = 0; $p < 17; ++$p) {
                $this->client->request('POST', $this->url().'/set/'.$n.'/evenement', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->jeton],
                    json_encode(['uuid' => self::uuid(), 'type' => 'point', 'donnees' => ['pour' => 'nous'], 'dernierOrdre' => $ordre]));
                $ordre = ($ordre ?? 0) + 1;
                if ($p === 15) {
                    $this->envoyer($n, 'terminer');
                    self::assertResponseStatusCodeSame(400, '24 points');
                }
            }
            $reponse = $this->envoyer($n, 'terminer');
            self::assertResponseIsSuccessful();
        }
        self::assertSame([3, 0], $reponse['feuille']['setsGagnes']);
        self::assertSame('terminee', $reponse['feuille']['etat']);
        $this->envoyer(4, 'composition', ['composition' => ['postes' => $ids, 'premierService' => 'nous', 'scoreDepart' => [0, 0]]]);
        self::assertResponseStatusCodeSame(400, 'match gagné : pas de 4ᵉ set');

        $partie = self::getContainer()->get('doctrine')->getManager()->getRepository(Partie::class)->find($match->getId());
        self::assertNull($partie->getNbSetGagnantReception(), 'score officiel intact (D66)');
    }
}
