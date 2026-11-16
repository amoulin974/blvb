<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Equipe;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\EquipeFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * MEP-01 à MEP-08 : mise en place d'une saison validée, étape « Poules » (D35 à D38). La saison de test est celle de
 * l'année suivante : ses équipes viennent de « Saison test » (A1 à A4 en niveau 1, B1 à B3 en niveau 2).
 */
final class MiseEnPlacePoulesTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /**
     * Saison validée de l'an prochain, une phase : Poule A (niveau 1, 4 prévues) avec A1, A2 et B1 ; Poule B (niveau 2)
     * avec A3 ; Poule C (niveau 3) vide. À placer : A4, B2, B3.
     *
     * @return array{saison: int, phase: int, poules: array<string, int>, url: string}
     */
    private function saison(bool $pouleCVide = true): array
    {
        $saison = SaisonFactory::createOne([
            'nom' => 'Saison suivante',
            'brouillon' => false,
            'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+1 year'),
            'date_fin' => new \DateTimeImmutable('+2 years'),
        ]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => new \DateTimeImmutable('+1 year'), 'datefin' => new \DateTimeImmutable('+18 months')]);
        $e = fn (string $code) => ChampionnatDeTest::get('equipe.'.$code);
        $a = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'nbEquipesPrevu' => 4, 'equipes' => [$e('A1'), $e('A2'), $e('B1')]]);
        $b = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule B', 'niveau' => 2, 'nbEquipesPrevu' => 1, 'equipes' => [$e('A3')]]);
        $c = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule C', 'niveau' => 3, 'equipes' => $pouleCVide ? [] : [$e('B2')]]);

        return [
            'saison' => $saison->getId(),
            'phase' => $phase->getId(),
            'poules' => ['A' => $a->getId(), 'B' => $b->getId(), 'C' => $c->getId()],
            'url' => '/admin/saison/'.$saison->getId().'/mise-en-place/poules',
        ];
    }

    /** Clique sur le bouton de valeur donnée, avec des valeurs de champs, et suit la redirection. */
    private function cliquer(string $url, string $valeur, array $champs = []): void
    {
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('button[value="'.$valeur.'"]')->form(), $champs);
    }

    private function idEquipe(string $code): int
    {
        return ChampionnatDeTest::get('equipe.'.$code)->getId();
    }

    /** @return list<string> codes des équipes de la poule, par identifiant de poule */
    private function codes(int $pouleId): array
    {
        $this->em()->clear();
        $noms = array_map(fn (Equipe $e) => (string) $e->getNomSansClub(), $this->em()->find(Poule::class, $pouleId)->getEquipes()->toArray());
        sort($noms);

        return array_map(fn (string $n) => str_replace('EQUIPE ', '', $n), $noms);
    }

    #[TestDox('MEP-01 Écran : bilan, origine de chaque équipe (montée, descente, reste), équipes à placer, avertissements, poule vide en erreur')]
    public function test_affichage(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->client->request('GET', $s['url']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#titre-etape', 'Poules de Phase 1');
        self::assertSelectorTextContains('body', '4 équipes placées sur 5 prévues.');
        self::assertSelectorTextContains('body', '3 équipes ne sont dans aucune poule.');
        self::assertSelectorTextContains('#poule-'.$s['poules']['A'], 'Montée depuis Poule B');
        self::assertSelectorTextContains('#poule-'.$s['poules']['A'], 'Reste en Poule A');
        self::assertSelectorTextContains('#poule-'.$s['poules']['B'], 'Descente depuis Poule A');
        self::assertSelectorTextContains('#a-placer', 'Équipes à placer (3)');
        self::assertSelectorTextContains('#a-placer', 'Une équipe laissée ici ne jouera pas cette phase.');
        self::assertSelectorTextContains('body', 'Poule A : 3 équipes pour 4 prévues.');
        self::assertSelectorTextContains('body', 'La poule C n\'a aucune équipe. Ajoutez-en une pour continuer.');
        self::assertSelectorTextContains('body', 'Vérifiez ce placement.');
        self::assertSelectorExists('button[value="suivant"][aria-disabled="true"]');
    }

    #[TestDox('MEP-02 Placer une équipe à placer dans une poule : enregistré, message de statut, l\'équipe quitte « Équipes à placer »')]
    public function test_placer(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $a4 = $this->idEquipe('A4');
        $this->cliquer($s['url'], 'placer-'.$a4, ['vers['.$a4.']' => (string) $s['poules']['C']]);

        self::assertResponseRedirects($s['url'].'?phase='.$s['phase'].'#poule-'.$s['poules']['C']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'est dans « Poule C »');
        self::assertSame(['A4'], $this->codes($s['poules']['C']));
        self::assertSelectorTextContains('#a-placer', 'Équipes à placer (2)');
    }

    #[TestDox('MEP-03 Déplacer une équipe retire l\'ancienne affectation (une seule poule par phase) ; Retirer la renvoie dans « Équipes à placer »')]
    public function test_deplacer_et_retirer(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $a1 = $this->idEquipe('A1');

        $this->cliquer($s['url'], 'deplacer-'.$a1, ['vers['.$a1.']' => (string) $s['poules']['B']]);
        self::assertSame(['A2', 'B1'], $this->codes($s['poules']['A']));
        self::assertSame(['A1', 'A3'], $this->codes($s['poules']['B']));

        $this->cliquer($s['url'], 'retirer-'.$a1);
        self::assertSame(['A3'], $this->codes($s['poules']['B']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('#a-placer', 'Équipes à placer (4)');
    }

    #[TestDox('MEP-04 Ajouter à une poule une équipe de la base qui ne jouait pas avant : proposée parmi « toutes les autres équipes », origine « Nouvelle équipe »')]
    public function test_ajouter(): void
    {
        $s = $this->saison();
        $nouvelle = EquipeFactory::createOne(['nom' => 'EQUIPE NOUVELLE']);
        $this->connecter('admin');
        $this->cliquer($s['url'], 'ajouter-'.$s['poules']['C'], ['ajout['.$s['poules']['C'].']' => (string) $nouvelle->getId()]);

        self::assertSame(['NOUVELLE'], $this->codes($s['poules']['C']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('#poule-'.$s['poules']['C'], 'Nouvelle équipe');
        self::assertSelectorTextContains('.admin-alerte', 'est dans « Poule C »');
    }

    #[TestDox('MEP-05 « Suivant : Journées » : refusé tant qu\'une poule est vide, accepté sinon (étape Journées)')]
    public function test_suivant(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->cliquer($s['url'], 'suivant');
        self::assertResponseRedirects($s['url'].'?phase='.$s['phase']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'La poule C n\'a aucune équipe');

        $this->em()->clear();
        $avec = $this->saison(false);
        $this->cliquer($avec['url'], 'suivant');
        self::assertResponseRedirects('/admin/saison/'.$avec['saison'].'/mise-en-place/journees?phase='.$avec['phase']);
    }

    #[TestDox('MEP-06 Phase lancée (matchs programmés) : composition en lecture seule, modification refusée sans rien changer')]
    public function test_phase_lancee(): void
    {
        $saison = ChampionnatDeTest::get('saison');
        $phase = ChampionnatDeTest::get('phase.1');
        $url = '/admin/saison/'.$saison->getId().'/mise-en-place/poules?phase='.$phase->getId();
        $this->connecter('admin');
        $this->client->request('GET', $url);

        self::assertSelectorTextContains('.admin-alerte', 'Cette phase est lancée');
        self::assertSelectorNotExists('button[value^="deplacer-"]');

        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $a1 = $this->idEquipe('A1');
        $this->client->request('POST', $url, ['_token' => $token, 'action' => 'retirer-'.$a1]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'sa composition ne se modifie plus');
        self::assertSame(['A1', 'A2', 'A3', 'A4'], $this->codes(ChampionnatDeTest::get('poule.A')->getId()));
    }

    #[TestDox('MEP-07 Sécurité : jeton CSRF absent, poule d\'une autre phase, équipe ou action inconnues → rien n\'est modifié ; non administrateur refusé')]
    public function test_securite(): void
    {
        $s = $this->saison();
        $autre = ChampionnatDeTest::get('poule.B')->getId();
        $a4 = $this->idEquipe('A4');
        $this->connecter('admin');
        $crawler = $this->client->request('GET', $s['url']);
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $envois = [
            ['action' => 'placer-'.$a4, 'vers' => [$a4 => (string) $s['poules']['C']]],              // sans jeton
            ['_token' => $token, 'action' => 'placer-'.$a4, 'vers' => [$a4 => (string) $autre]],      // poule d'une autre phase
            ['_token' => $token, 'action' => 'placer-999999', 'vers' => [999999 => (string) $s['poules']['C']]],
            ['_token' => $token, 'action' => 'supprimer-tout'],
        ];
        foreach ($envois as $envoi) {
            $this->client->request('POST', $s['url'], $envoi);
            self::assertResponseRedirects();
        }
        self::assertSame([], $this->codes($s['poules']['C']));
        self::assertSame(['A1', 'A2', 'B1'], $this->codes($s['poules']['A']));

        $this->connecter('cap.a1');
        $this->client->request('GET', $s['url']);
        $this->assertAccesRefuse('un capitaine ne compose pas les poules');
    }

    #[TestDox('MEP-08 Brouillon : renvoyé vers le wizard ; saison validée : « Composer les poules » sur la page de la saison')]
    public function test_brouillon_et_lien(): void
    {
        $brouillon = SaisonFactory::createOne(['nom' => 'Brouillon', 'brouillon' => true, 'etapeWizard' => 3, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+3 years'), 'date_fin' => new \DateTimeImmutable('+4 years')]);
        $this->connecter('admin');
        $this->client->request('GET', '/admin/saison/'.$brouillon->getId().'/mise-en-place/poules');
        self::assertResponseRedirects('/admin/saison/'.$brouillon->getId().'/wizard/3');

        $s = $this->saison();
        $this->client->request('GET', '/admin/saison/'.$s['saison']);
        self::assertSelectorExists('a[href="'.$s['url'].'"]');
    }
}
