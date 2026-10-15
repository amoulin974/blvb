<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Phase;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * WIZ-22 à WIZ-25 : phase de barrages dans le wizard (lot 6c, D32, D42, D43) : modèle « BLVB avec barrages » proposé au
 * départ, matchs et réception à l'étape 3, une carte par match à l'étape 4, vérification de l'étape 6.
 */
final class WizardBarragesTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function brouillon(): Saison
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#wizard-form', 'Format BLVB avec barrages');
        $this->client->submit($crawler->filter('#wizard-form')->form(), ['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_avec_barrages']);
        self::assertResponseRedirects();
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        $this->em()->clear();

        // Toutes les étapes sont atteintes (le wizard ne saute pas d'étape)
        $saison = $this->em()->find(Saison::class, (int) $m[1]);
        $saison->setEtapeWizard(6);
        $this->em()->flush();
        $this->em()->clear();

        return $this->em()->find(Saison::class, (int) $m[1]);
    }

    private function phase(Saison $saison, string $code): Phase
    {
        foreach ($saison->getPhases() as $phase) {
            if ($phase->getCode() === $code) {
                return $phase;
            }
        }
        self::fail("Phase $code absente");
    }

    #[TestDox('WIZ-22 Le modèle « BLVB avec barrages » est proposé au départ : 4 phases dont des barrages (3 matchs), réception par la poule supérieure')]
    public function test_depart_modele_barrages(): void
    {
        $saison = $this->brouillon();

        $codes = array_map(fn ($p) => $p->getCode(), $saison->getPhases()->toArray());
        sort($codes);
        self::assertSame(['BAR', 'F', 'P1', 'P2'], $codes);
        $barrage = $this->phase($saison, 'BAR');
        self::assertSame(PhaseType::BARRAGE, $barrage->getType());
        self::assertCount(3, $barrage->getPoules());
        self::assertSame(Phase::RECEPTION_NIVEAU_SUPERIEUR, $barrage->getReception());
    }

    #[TestDox('WIZ-23 Étape 3 : la phase de barrages affiche ses matchs et « Qui reçoit le match ? » ; le choix est enregistré')]
    public function test_etape_phases(): void
    {
        $saison = $this->brouillon();
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Barrages');
        self::assertSelectorTextContains('body', 'Qui reçoit le match ?');
        self::assertSelectorTextContains('body', 'Ajouter un match');

        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $index = null;
        foreach ($valeurs['phases_wizard']['phases'] ?? [] as $i => $phase) {
            if (($phase['nom'] ?? '') === 'Barrages') {
                $index = $i;
            }
        }
        self::assertNotNull($index, 'phase Barrages dans le formulaire');
        $valeurs['phases_wizard']['phases'][$index]['reception'] = Phase::RECEPTION_NEUTRE;
        $this->client->request('POST', $url, $valeurs + ['aller' => 'suivant']);
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');

        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $saison->getId());
        self::assertSame(Phase::RECEPTION_NEUTRE, $this->phase($saison, 'BAR')->getReception());
        self::assertSame(Phase::RECEPTION_NIVEAU_SUPERIEUR, $this->phase($saison, 'P1')->getReception(), 'autres phases : inchangées');
        self::assertArrayNotHasKey('reception', $this->phase($saison, 'P1')->getOptions());
    }

    #[TestDox('WIZ-24 Étape 4 : une carte par match de barrage (vainqueur, perdant) ; les passages vers les barrages restent en mode détaillé')]
    public function test_etape_passages(): void
    {
        $saison = $this->brouillon();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/4');
        self::assertResponseIsSuccessful();

        // 3 matchs : une liste « vainqueur » et une « perdant » chacun
        self::assertCount(3, $crawler->filter('select[name$="[vainqueur]"]'));
        self::assertCount(3, $crawler->filter('select[name$="[perdant]"]'));
        self::assertSelectorTextContains('body', 'Le vainqueur joue ensuite dans');
        // Phase 1 → barrages : pas de mode simple, ni de règles « par défaut » à rétablir
        foreach ($this->phase($saison, 'P1')->getPoules() as $poule) {
            self::assertSelectorNotExists('button[value="detail-'.$poule->getId().'"]');
            self::assertSelectorNotExists('button[value="simple-'.$poule->getId().'"]');
        }
        self::assertSelectorNotExists('button[value="retablir-'.$this->phase($saison, 'P1')->getId().'"]');
        self::assertSelectorNotExists('button[value="retablir-'.$this->phase($saison, 'BAR')->getId().'"]');
        // Valeurs du modèle sélectionnées : le vainqueur du barrage AB va en poule A de la phase 2
        $poule = $this->phase($saison, 'BAR')->getPoules()->toArray()[0];
        $choisi = $crawler->filter('select[name="p['.$poule->getId().'][vainqueur]"] option[selected]');
        self::assertCount(1, $choisi);
        self::assertStringContainsString('Poule A (Phase 2)', $choisi->text());
    }

    #[TestDox('WIZ-25 Étape 4 : changer le vainqueur d\'un match enregistre la règle ; un vainqueur laissé vide est signalé et sa règle retirée')]
    public function test_enregistrer_un_match(): void
    {
        $saison = $this->brouillon();
        $url = '/admin/saison/'.$saison->getId().'/wizard/4';
        $crawler = $this->client->request('GET', $url);
        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $barrage = $this->phase($saison, 'BAR');
        $match = $barrage->getPoules()->toArray()[0];
        $phase2 = $this->phase($saison, 'P2');
        $pouleC = array_values(array_filter($phase2->getPoules()->toArray(), fn ($p) => $p->getCode() === 'C'))[0];

        $valeurs['p'][$match->getId()]['vainqueur'] = 'p:'.$pouleC->getId();
        $valeurs['p'][$match->getId()]['perdant'] = ['x']; // saisie malformée (tableau) : traitée comme absente, sans erreur 500
        $this->client->request('POST', $url, $valeurs);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'indiquez où va le perdant');

        $this->em()->clear();
        $barrage = $this->em()->find(Phase::class, $barrage->getId());
        $sources = [];
        foreach ($barrage->getReglesDePassage() as $regle) {
            $sources[$regle->getSource()] = $regle->getPouleDestination()?->getCode();
        }
        self::assertSame('C', $sources['BAR.AB.vainqueur'] ?? null);
        self::assertArrayNotHasKey('BAR.AB.perdant', $sources);
        self::assertSame('B', $sources['BAR.BC.vainqueur'] ?? null, 'les autres matchs sont inchangés');
    }

    #[TestDox('WIZ-26 Étape 6 : le modèle « BLVB avec barrages » passe la vérification sans erreur et le schéma affiche les barrages')]
    public function test_verification(): void
    {
        $saison = $this->brouillon();
        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/6');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-verification="erreur"]');
        self::assertSelectorTextContains('body', '3 matchs');
        self::assertSelectorTextNotContains('body', 'Aucun passage');
    }
}
