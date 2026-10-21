<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Phase;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** ALR-07 : championnat aller-retour à l'étape Phases du wizard (lot 6f-2, A2, D47). */
final class WizardAllerRetourTest extends TestFonctionnel
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

    /** @return array<string, Phase> phases du brouillon par code */
    private function phases(Saison $saison): array
    {
        $parCode = [];
        foreach ($saison->getPhases() as $phase) {
            $parCode[$phase->getCode()] = $phase;
        }

        return $parCode;
    }

    /** Envoie l'étape 3 avec « Matchs aller et retour » coché (ou non) pour les phases de $codes. */
    private function envoyer(Saison $saison, array $codes, bool $coche): void
    {
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';
        $crawler = $this->client->request('GET', $url);
        $donnees = $crawler->filter('#wizard-form')->form()->getPhpValues();
        foreach ($donnees['phases_wizard']['phases'] as $i => $phase) {
            unset($donnees['phases_wizard']['phases'][$i]['allerRetour']); // case décochée : champ absent de la requête
            if (\in_array($phase['code'] ?? '', $codes, true) && $coche) {
                $donnees['phases_wizard']['phases'][$i]['allerRetour'] = '1';
            }
        }
        $this->client->request('POST', $url, $donnees + ['aller' => 'suivant']);
    }

    #[TestDox('ALR-07 Étape 3 : « Matchs aller et retour » proposé pour les championnats seulement, décoché par défaut')]
    public function test_proposition(): void
    {
        $saison = $this->brouillon();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/3');

        $nbChampionnats = \count(array_filter($saison->getPhases()->toArray(), fn (Phase $p) => $p->getType() === PhaseType::CHAMPIONNAT));
        self::assertGreaterThan(0, $nbChampionnats);
        self::assertCount($nbChampionnats, $crawler->filter('label:contains("Matchs aller et retour")'));
        self::assertCount(0, $crawler->filter('input[type=checkbox][name$="[allerRetour]"][checked]'));
    }

    #[TestDox('ALR-07 Aller-retour enregistré pour la phase choisie seulement, retiré en décochant')]
    public function test_enregistrement(): void
    {
        $saison = $this->brouillon();
        $this->envoyer($saison, ['P1'], true);
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');

        $this->em()->clear();
        $phases = $this->phases($this->em()->find(Saison::class, $saison->getId()));
        self::assertTrue($phases['P1']->estAllerRetour());
        self::assertTrue($phases['P1']->getOptions()['aller_retour']);
        foreach ($phases as $code => $phase) {
            if ($code !== 'P1') {
                self::assertFalse($phase->estAllerRetour(), $code);
            }
        }

        $this->envoyer($saison, ['P1'], false);
        $this->em()->clear();
        $phase = $this->phases($this->em()->find(Saison::class, $saison->getId()))['P1'];
        self::assertFalse($phase->estAllerRetour());
        self::assertArrayNotHasKey('aller_retour', $phase->getOptions());
    }

    #[TestDox('ALR-07 Une requête qui coche l\'aller-retour sur un tableau à élimination est ignorée')]
    public function test_ignore_hors_championnat(): void
    {
        $saison = $this->brouillon();
        $this->envoyer($saison, ['F'], true);

        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');
        $this->em()->clear();
        $finale = $this->phases($this->em()->find(Saison::class, $saison->getId()))['F'];
        self::assertFalse($finale->estAllerRetour());
        self::assertArrayNotHasKey('aller_retour', $finale->getOptions());
    }
}
