<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\FeuilleMatch;
use App\Factory\JoueurFactory;
use App\Factory\MembreEquipeFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * VST-14 à VST-18 : saisie des actions en mode intermédiaire, validation côté serveur (Volley Stats, D68 ;
 * specification-actions.md §3 et §3 bis). Page de A1 sur le match de référence, sept joueurs présents (un sur le banc).
 */
final class SaisieActionsTest extends TestFonctionnel
{
    private string $jeton = '';
    /** @var list<int> */
    private array $ids = [];
    private ?int $ordre = null;

    private function url(): string
    {
        return '/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId().'/match/'.ChampionnatDeTest::get('match.reference')->getId();
    }

    private static function uuid(): string
    {
        $o = random_bytes(16);
        $o[6] = \chr(\ord($o[6]) & 0x0f | 0x40);
        $o[8] = \chr(\ord($o[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($o), 4));
    }

    /** @param array<string, mixed> $donnees @return array<string, mixed> */
    private function envoyer(int $set, string $action, array $donnees = []): array
    {
        $this->client->request('POST', $this->url().'/set/'.$set.'/'.$action, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->jeton], json_encode($donnees));
        $reponse = json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
        if ($this->client->getResponse()->isSuccessful() && $action === 'evenement') {
            $h = $reponse['feuille']['sets'][$set - 1]['historique'];
            $this->ordre = $h === [] ? null : $h[array_key_last($h)]['ordre'];
        }

        return $reponse;
    }

    /** @param array<string, mixed> $donnees @return array<string, mixed> renvoie la réponse ; l'uuid est dans $donnees['uuid'] */
    private function evenement(string $type, array $donnees, ?string $uuid = null, int $set = 1): array
    {
        return $this->envoyer($set, 'evenement', ['uuid' => $uuid ?? self::uuid(), 'type' => $type, 'donnees' => $donnees, 'dernierOrdre' => $this->ordre]);
    }

    /** Action d'un joueur au poste donné (1 à 6, ordre officiel du départ) ; renvoie son uuid */
    private function action(string $action, int $poste, int $set = 1): string
    {
        $uuid = self::uuid();
        $this->evenement('action', ['action' => $action, 'joueurs' => [$this->ids[$poste - 1]]], $uuid, $set);
        self::assertResponseIsSuccessful($action.' au poste '.$poste);

        return $uuid;
    }

    /** Sept présents, set 1 composé (postes = les six premiers), commencé en notant les touches ; nous servons ou recevons */
    private function commencer(string $saisie = 'touches', string $service = 'eux'): void
    {
        foreach (['Ana Ur', 'Beñat Lo', 'Maite Ze', 'Unai Ba'] as $i => $nom) {
            [$prenom, $famille] = explode(' ', $nom);
            $joueur = JoueurFactory::createOne(['prenom' => $prenom, 'nom' => $famille, 'email' => 'act'.$i.'@blvb.test']);
            MembreEquipeFactory::createOne(['equipe' => ChampionnatDeTest::get('equipe.A1'), 'saison' => ChampionnatDeTest::get('saison'), 'joueur' => $joueur]);
        }
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $this->url());
        $this->jeton = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->submit($crawler->selectButton('Préparer le match')->form());
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $feuille = $em->getRepository(FeuilleMatch::class)->findOneBy(['partie' => ChampionnatDeTest::get('match.reference')->getId(), 'equipe' => ChampionnatDeTest::get('equipe.A1')->getId()]);
        $this->ids = array_map(fn ($j) => $j->getId(), $feuille->getJoueurs()->toArray());
        self::assertCount(7, $this->ids);
        $this->envoyer(1, 'composition', ['composition' => ['postes' => \array_slice($this->ids, 0, 6), 'premierService' => $service, 'scoreDepart' => [0, 0]]]);
        $this->envoyer(1, 'commencer', ['saisie' => $saisie]);
        self::assertResponseIsSuccessful();
    }

    #[TestDox('VST-14 : le mode se choisit au début du set ; en « score seulement », touches, balle à rejouer et détail du point sont refusés')]
    public function test_mode_score(): void
    {
        $this->commencer('score');
        $this->evenement('action', ['action' => 'reception', 'joueurs' => [$this->ids[0]]]);
        self::assertResponseStatusCodeSame(400);
        $this->evenement('rejouer', []);
        self::assertResponseStatusCodeSame(400);
        $this->evenement('point', ['pour' => 'nous', 'sur' => 'service']);
        self::assertResponseStatusCodeSame(400);
        $reponse = $this->evenement('point', ['pour' => 'nous']);
        self::assertSame('score', $reponse['feuille']['sets'][0]['saisie']);
        $this->client->request('POST', $this->url().'/set/1/commencer', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->jeton], json_encode(['saisie' => 'tout']));
        self::assertResponseStatusCodeSame(400, 'mode inconnu');
    }

    #[TestDox('VST-15 : une touche désigne un seul joueur de cette page, sur le terrain ; au block, un avant seulement ; action inconnue refusée')]
    public function test_actions_refusees(): void
    {
        $this->commencer();
        $refus = [
            'joueur sur le banc' => ['action' => 'reception', 'joueurs' => [$this->ids[6]]],
            'arrière au block' => ['action' => 'block', 'joueurs' => [$this->ids[0]]],
            'deux joueurs' => ['action' => 'block', 'joueurs' => [$this->ids[1], $this->ids[2]]],
            'action inconnue' => ['action' => 'smash', 'joueurs' => [$this->ids[1]]],
            'joueur inconnu' => ['action' => 'passe', 'joueurs' => [999999]],
            'clé inconnue' => ['action' => 'passe', 'joueurs' => [$this->ids[1]], 'qualite' => '#'],
        ];
        foreach ($refus as $cas => $donnees) {
            $this->evenement('action', $donnees);
            self::assertResponseStatusCodeSame(400, $cas);
        }
        $this->action('block', 3);
    }

    #[TestDox('VST-16 : le point porte sur la dernière touche de l\'échange ou sur notre service ; contrée, faute, deuxième bloqueur, fautif et « sans détail » contrôlés')]
    public function test_points(): void
    {
        $this->commencer();
        $reception = $this->action('reception', 5);
        $passe = $this->action('passe', 6);
        $this->evenement('point', ['pour' => 'nous', 'sur' => $reception]);
        self::assertResponseStatusCodeSame(400, 'pas la dernière touche');
        $this->evenement('point', ['pour' => 'eux', 'sur' => $passe, 'detail' => 'contree']);
        self::assertResponseStatusCodeSame(400, 'contrée sur une passe');
        $this->evenement('point', ['pour' => 'nous', 'sansDetail' => true]);
        self::assertResponseStatusCodeSame(400, 'sans détail avec des touches');
        $this->evenement('remplacement', ['sort' => $this->ids[0], 'entre' => $this->ids[6]]);
        self::assertResponseStatusCodeSame(400, 'remplacement pendant un échange');
        $attaque = $this->action('attaque', 4);
        $this->evenement('point', ['pour' => 'nous', 'sur' => $attaque, 'detail' => 'faute']);
        self::assertResponseStatusCodeSame(400, 'détail sur un point gagné');
        $reponse = $this->evenement('point', ['pour' => 'eux', 'sur' => $attaque, 'detail' => 'contree']);
        self::assertResponseIsSuccessful();
        self::assertSame([0, 1], $reponse['feuille']['sets'][0]['calcul']['score']);

        // Service adverse : pas de point « sur le service » ; après rotation… l'adversaire sert encore
        $this->evenement('point', ['pour' => 'eux', 'sur' => 'service']);
        self::assertResponseStatusCodeSame(400, 'service adverse');
        $this->evenement('point', ['pour' => 'eux', 'sansDetail' => true]);
        self::assertResponseIsSuccessful('sans détail');
        // Faute de filet : joueur fautif sur le terrain
        $this->evenement('point', ['pour' => 'eux', 'detail' => 'filet']);
        self::assertResponseStatusCodeSame(400, 'fautif manquant');
        $this->evenement('point', ['pour' => 'eux', 'detail' => 'filet', 'fautif' => $this->ids[2]]);
        self::assertResponseIsSuccessful();
        $this->evenement('point', ['pour' => 'eux', 'detail' => 'quatre_touches']);
        self::assertResponseIsSuccessful('faute de l\'équipe');
        // Block gagnant à deux
        $block = $this->action('block', 2);
        $this->evenement('point', ['pour' => 'nous', 'sur' => $block, 'avec' => $this->ids[1]]);
        self::assertResponseStatusCodeSame(400, 'même joueur deux fois');
        $reponse = $this->evenement('point', ['pour' => 'nous', 'sur' => $block, 'avec' => $this->ids[2]]);
        self::assertResponseIsSuccessful();
        self::assertSame('nous', $reponse['feuille']['sets'][0]['calcul']['service'], 'point gagné en réception : nous servons');
        $reponse = $this->evenement('point', ['pour' => 'nous', 'sur' => 'service']);
        self::assertResponseIsSuccessful('ace');
    }

    #[TestDox('VST-17 : la balle à rejouer clôt l\'échange sans point ni changement de service')]
    public function test_rejouer(): void
    {
        $this->commencer();
        $this->action('reception', 5);
        $reponse = $this->evenement('rejouer', []);
        self::assertSame([0, 0], $reponse['feuille']['sets'][0]['calcul']['score']);
        self::assertSame('eux', $reponse['feuille']['sets'][0]['calcul']['service']);
        $this->evenement('remplacement', ['sort' => $this->ids[0], 'entre' => $this->ids[6]]);
        self::assertResponseIsSuccessful('échange clos : remplacement possible');
    }

    #[TestDox('VST-18 : correction d\'une touche d\'un échange terminé de ce set ; refusée sur l\'échange en cours, sur la touche du point, sur un joueur entré après ; acceptée sur un set terminé')]
    public function test_modifications(): void
    {
        $this->commencer();
        $reception = $this->action('reception', 5);
        $attaque = $this->action('attaque', 4);
        $this->evenement('modification', ['cible' => $reception, 'action' => 'defense']);
        self::assertResponseStatusCodeSame(400, 'échange en cours');
        $this->evenement('point', ['pour' => 'nous', 'sur' => $attaque]);
        $this->evenement('modification', ['cible' => $attaque, 'retirer' => true]);
        self::assertResponseStatusCodeSame(400, 'touche qui a fait le point');
        $this->evenement('remplacement', ['sort' => $this->ids[0], 'entre' => $this->ids[6]]);
        $this->evenement('modification', ['cible' => $reception, 'joueurs' => [$this->ids[6]]]);
        self::assertResponseStatusCodeSame(400, 'joueur entré après la touche');
        $this->evenement('modification', ['cible' => $reception, 'action' => 'defense', 'retirer' => true]);
        self::assertResponseStatusCodeSame(400, 'deux corrections à la fois');
        $reponse = $this->evenement('modification', ['cible' => $reception, 'joueurs' => [$this->ids[3]]]);
        self::assertResponseIsSuccessful();
        self::assertSame('Touche corrigée', end($reponse['feuille']['sets'][0]['historique'])['texte']);

        // Set terminé : correction acceptée, point refusé
        for ($p = 1; $p < 25; ++$p) {
            $this->evenement('point', ['pour' => 'nous', 'sansDetail' => true]);
        }
        $this->envoyer(1, 'terminer');
        self::assertResponseIsSuccessful();
        $this->evenement('point', ['pour' => 'nous', 'sansDetail' => true]);
        self::assertResponseStatusCodeSame(400, 'point sur un set terminé');
        $this->evenement('modification', ['cible' => $reception, 'action' => 'defense']);
        self::assertResponseIsSuccessful('correction sur un set terminé');
    }
}
