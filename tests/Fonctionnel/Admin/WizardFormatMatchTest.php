<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Phase;
use App\Entity\Saison;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** SET-04 : format de match (3 ou 2 sets gagnants) à l'étape Phases du wizard (lot 6f, D48). */
final class WizardFormatMatchTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /** Brouillon créé depuis le modèle « Format BLVB actuel », toutes étapes atteintes. */
    private function brouillon(): Saison
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        $this->client->submit($crawler->filter('#wizard-form')->form(), ['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        self::assertResponseRedirects();
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        $this->em()->clear();
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
        self::fail('Phase '.$code.' absente');
    }

    /** Envoie l'étape 3 avec le format de match de la phase $code réglé sur $sets. */
    private function envoyer(Saison $saison, string $code, string $sets): void
    {
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';
        $crawler = $this->client->request('GET', $url);
        $donnees = $crawler->filter('#wizard-form')->form()->getPhpValues();
        foreach ($donnees['phases_wizard']['phases'] as $i => $phase) {
            if (($phase['code'] ?? '') === $code) {
                $donnees['phases_wizard']['phases'][$i]['setsGagnants'] = $sets;
            }
        }
        $this->client->request('POST', $url, $donnees + ['aller' => 'suivant']);
    }

    #[TestDox('SET-04 Étape 3 : le format des matchs est proposé pour chaque phase, 3 sets gagnants par défaut')]
    public function test_proposition(): void
    {
        $saison = $this->brouillon();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/3');

        self::assertCount(count($saison->getPhases()), $crawler->filter('legend:contains("Format des matchs")'));
        self::assertCount(count($saison->getPhases()), $crawler->filter('input[type=radio][name$="[setsGagnants]"][value="3"][checked]'));
    }

    #[TestDox('SET-04 Deux sets gagnants enregistré dans les options de la phase choisie seulement, puis retiré en revenant à trois')]
    public function test_enregistrement(): void
    {
        $saison = $this->brouillon();
        $this->envoyer($saison, 'F', '2');
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');

        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $saison->getId());
        self::assertSame(2, $this->phase($saison, 'F')->getSetsGagnants());
        self::assertSame(2, $this->phase($saison, 'F')->getOptions()['sets_gagnants']);
        foreach ($saison->getPhases() as $phase) {
            if ($phase->getCode() !== 'F') {
                self::assertSame(3, $phase->getSetsGagnants(), $phase->getNom());
                self::assertArrayNotHasKey('sets_gagnants', $phase->getOptions(), $phase->getNom());
            }
        }

        $this->envoyer($saison, 'F', '3');
        $this->em()->clear();
        $phase = $this->phase($this->em()->find(Saison::class, $saison->getId()), 'F');
        self::assertSame(3, $phase->getSetsGagnants());
        self::assertArrayNotHasKey('sets_gagnants', $phase->getOptions());
    }

    #[TestDox('SET-05 Durée d\'un match de la journée des finales proposée selon le format : 120 minutes en 3 sets gagnants, 75 en 2 sets')]
    public function test_duree_proposee_selon_le_format(): void
    {
        $saison = $this->brouillon();
        $duree = fn () => $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/3')
            ->filter('input[name$="[journeeFinales][duree]"]')->last()->attr('value'); // la phase finale est la dernière

        self::assertSame('120', $duree());

        $this->envoyer($saison, 'F', '2');
        self::assertSame('75', $duree());
    }

    #[TestDox('WIZ-30 Étape 3 : la légende des poules et le bouton d\'ajout ont une variante par type de phase, seule celle du type enregistré est active ; colonne « Nombre d\'équipes prévues »')]
    public function test_libelles_selon_le_type(): void
    {
        $saison = $this->brouillon();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/3');

        $phase = $crawler->filter('li.phase-wizard')->first(); // Phase 1, championnat
        self::assertSame(['Poules', 'Ajouter une poule'], $phase->filter('.selon-type > span:not(.type-inactif)')->each(fn ($s) => $s->text()));
        self::assertSame(['Tableaux', 'Matchs de barrage', 'Ajouter un tableau', 'Ajouter un match'], $phase->filter('.selon-type > span.type-inactif')->each(fn ($s) => $s->text()));
        self::assertSame(['0', '0'], $phase->filter('.selon-type > span:not(.type-inactif)')->each(fn ($s) => $s->attr('data-type')));
        self::assertGreaterThan(0, $crawler->filter('th:contains("Nombre d\'équipes prévues")')->count());
        self::assertSelectorNotExists('th:contains("Équipes prévues")');
    }

    #[TestDox('SET-04 Une valeur inconnue envoyée pour le format est refusée, rien n\'est enregistré')]
    public function test_valeur_inconnue(): void
    {
        $saison = $this->brouillon();
        $this->envoyer($saison, 'F', '5');

        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        self::assertSame(3, $this->phase($this->em()->find(Saison::class, $saison->getId()), 'F')->getSetsGagnants());
    }
}
