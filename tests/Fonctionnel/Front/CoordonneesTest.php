<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Joueur;
use App\Entity\User;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * CON-01 à CON-05 : visibilité des coordonnées (RGPD). Règle : le capitaine d'une équipe est visible des capitaines
 * de sa poule (phase en cours), de ses coéquipiers et des administrateurs ; les autres joueurs, de leurs coéquipiers
 * et des administrateurs seulement.
 * Story : cap.a1 (06 11 11 11 11, cap.a1@blvb.test) capitaine de A1 ; joueur.a1 (06 10 10 10 10) simple joueur de A1.
 */
final class CoordonneesTest extends TestFonctionnel
{
    private const CAPITAINE_A1 = ['06 11 11 11 11', 'cap.a1@blvb.test'];
    private const JOUEUR_A1 = ['06 10 10 10 10', 'joueur.a1@blvb.test'];

    private function ficheA1(): string
    {
        $this->client->request('GET', '/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId());
        self::assertResponseIsSuccessful();

        // Seul le contenu principal compte (l'en-tête peut montrer l'adresse du compte connecté)
        return $this->client->getCrawler()->filter('main')->html();
    }

    /** @return array<string, array{?string, bool, bool}> spectateur, voit le capitaine, voit les joueurs */
    public static function spectateurs(): array
    {
        return [
            'visiteur' => [null, false, false],
            'joueur d\'une autre équipe' => ['joueur.a2', false, false],
            'capitaine adverse (même poule)' => ['cap.a2', true, false],
            'capitaine d\'une autre poule' => ['cap.b1', false, false],
            'coéquipier' => ['joueur.a1', true, true],
            'responsable du club de l\'équipe' => ['resp.club', false, false],
            'administrateur' => ['admin', true, true],
        ];
    }

    #[DataProvider('spectateurs')]
    #[TestDox('CON-01 Fiche équipe de A1 : coordonnées du capitaine et des joueurs selon le spectateur : $_dataName')]
    public function test_matrice(?string $compte, bool $voitCapitaine, bool $voitJoueurs): void
    {
        if ($compte !== null) {
            $this->connecter($compte);
        }
        $page = $this->ficheA1();

        foreach (self::CAPITAINE_A1 as $coordonnee) {
            self::assertSame($voitCapitaine, str_contains($page, $coordonnee), "capitaine : $coordonnee");
        }
        foreach (self::JOUEUR_A1 as $coordonnee) {
            self::assertSame($voitJoueurs, str_contains($page, $coordonnee), "joueur : $coordonnee");
        }
    }

    #[TestDox('CON-02 Accueil et calendrier : la ligne « Capitaine : … » n\'apparaît que pour le capitaine de l\'équipe adverse du match')]
    public function test_ligne_capitaine_adverse(): void
    {
        foreach (['cap.a2' => true, 'cap.b1' => false, 'joueur.a2' => false, 'resp.club' => false, 'admin' => false] as $compte => $voit) {
            $this->connecter($compte);
            foreach (['/', '/calendrier'] as $page) {
                $this->client->request('GET', $page);
                $html = $this->client->getCrawler()->filter('main')->html();
                self::assertSame($voit, str_contains($html, self::CAPITAINE_A1[0]), "$compte sur $page");
            }
        }
        $this->client->request('POST', '/logout');
        $this->client->request('GET', '/calendrier');
        self::assertStringNotContainsString(self::CAPITAINE_A1[0], $this->client->getCrawler()->filter('main')->html(), 'visiteur');
    }

    #[TestDox('CON-03 Coordonnées prises sur la fiche joueur, sinon sur le compte ; aucune → « Coordonnées non renseignées »')]
    public function test_repli_fiche_compte(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $fiche = $em->find(Joueur::class, ChampionnatDeTest::get('joueur.cap.a1')->getId());
        $fiche->setTelephone(null)->setEmail(null);
        $em->find(User::class, ChampionnatDeTest::compte('cap.a1')->getId())->setTelephone('06 99 88 77 66');
        $em->flush();

        $this->connecter('cap.a2');
        $page = $this->ficheA1();
        self::assertStringContainsString('06 99 88 77 66', $page, 'téléphone du compte');
        self::assertStringContainsString('cap.a1@blvb.test', $page, 'e-mail du compte');

        $compte = $em->find(User::class, ChampionnatDeTest::compte('cap.a1')->getId());
        $compte->setTelephone(null);
        $em->find(Joueur::class, $fiche->getId())->setUser(null);
        $em->flush();
        self::assertStringContainsString('Coordonnées non renseignées', $this->ficheA1());
    }

    #[TestDox('CON-04 Le capitaine de la saison précédente n\'est pas présenté comme capitaine de la saison courante')]
    public function test_capitaine_de_la_saison(): void
    {
        $this->connecter('admin');
        $page = $this->ficheA1();
        $bloc = $this->client->getCrawler()->filter('[aria-label="Capitaine"]')->text();

        self::assertStringContainsString('Camille Arrieta', $bloc, 'capitaine de la saison courante');
        self::assertStringNotContainsString('Pat Ancien', $bloc, 'capitaine de la saison précédente seulement');
    }

    #[TestDox('CON-05 L\'agenda .ics et l\'API de saisie ne contiennent aucune coordonnée personnelle')]
    public function test_pas_de_fuite_ics_api(): void
    {
        $routeur = self::getContainer()->get('router');
        $a1 = ChampionnatDeTest::get('equipe.A1')->getId();
        foreach ([
            $routeur->generate('calendar_ics_equipe', ['saison' => ChampionnatDeTest::get('saison')->getId(), 'equipe' => $a1]),
            $routeur->generate('calendar_ics', ['poule' => ChampionnatDeTest::get('poule.A')->getId(), 'equipe' => $a1]),
        ] as $agenda) {
            $this->client->request('GET', $agenda);
            $contenu = (string) $this->client->getResponse()->getContent();
            self::assertStringContainsString('BEGIN:VCALENDAR', $contenu);
            self::assertDoesNotMatchRegularExpression('/@blvb\.test|0\d( ?\d\d){4}/', $contenu, $agenda);
        }

        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', '/calendrier');
        $this->client->request('PUT', '/front/partie/'.ChampionnatDeTest::get('match.reference')->getId().'/api/update', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $crawler->filter('meta[name="csrf-token"]')->attr('content'),
        ], content: '{"scoreReception":"3","scoreDeplacement":"1"}');
        self::assertResponseIsSuccessful();
        self::assertDoesNotMatchRegularExpression('/@|0\d( ?\d\d){4}/', (string) $this->client->getResponse()->getContent());
    }
}
