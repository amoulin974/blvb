<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Phase;
use App\Entity\Saison;
use App\Factory\LieuFactory;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** WIZ-28, WIZ-29 : journée des finales à l'étape Phases du wizard (lot 6e, D45, D46). */
final class WizardJourneeFinalesTest extends TestFonctionnel
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

    private function phaseFinale(Saison $saison): Phase
    {
        foreach ($saison->getPhases() as $phase) {
            if ($phase->getCode() === 'F') {
                return $phase;
            }
        }
        self::fail('Phase F absente');
    }

    /** Envoie l'étape 3 avec la journée des finales de la phase F réglée par $valeurs. */
    private function envoyer(Saison $saison, array $valeurs): void
    {
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';
        $crawler = $this->client->request('GET', $url);
        $donnees = $crawler->filter('#wizard-form')->form()->getPhpValues();
        foreach ($donnees['phases_wizard']['phases'] as $i => $phase) {
            if (($phase['code'] ?? '') === 'F') {
                // Une clé à null est retirée : c'est ainsi qu'un navigateur envoie une case décochée
                $donnees['phases_wizard']['phases'][$i]['journeeFinales'] = array_filter($valeurs + $phase['journeeFinales'], fn ($v) => $v !== null);
            }
        }
        $this->client->request('POST', $url, $donnees + ['aller' => 'suivant']);
    }

    #[TestDox('WIZ-28 Étape 3 : « Journée des finales » proposée pour le tableau seulement ; gymnase, date, heure, terrains et durée enregistrés dans les options de la phase')]
    public function test_enregistrement(): void
    {
        $gymnase = LieuFactory::createOne(['nom' => 'Gymnase des finales']);
        $saison = $this->brouillon();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/3');
        self::assertCount(1, $crawler->filter('legend:contains("Journée des finales")'), 'une seule phase à élimination');
        $date = $this->phaseFinale($saison)->getDateDebut()->modify('+20 days')->format('Y-m-d');

        $this->envoyer($saison, ['actif' => '1', 'tours' => Phase::TOURS_DEMI_FINALES, 'lieu' => (string) $gymnase->getId(),
            'date' => $date, 'heure' => '18:30', 'terrains' => '3', 'duree' => '75']);
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');

        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $saison->getId());
        $attendu = ['tours' => Phase::TOURS_DEMI_FINALES, 'lieu' => $gymnase->getId(), 'date' => $date, 'heure' => '18:30', 'terrains' => 3, 'duree' => 75];
        $enregistre = $this->phaseFinale($saison)->getOptions()['journee_finales'];
        ksort($attendu);
        ksort($enregistre); // MySQL ne garde pas l'ordre des clés d'un JSON
        self::assertSame($attendu, $enregistre);
        foreach ($saison->getPhases() as $phase) {
            if ($phase->getCode() !== 'F') {
                self::assertArrayNotHasKey('journee_finales', $phase->getOptions(), $phase->getNom());
            }
        }

        // Case décochée : l'option est retirée
        $this->envoyer($saison, ['actif' => null]);
        $this->em()->clear();
        self::assertNull($this->phaseFinale($this->em()->find(Saison::class, $saison->getId()))->getJourneeFinales());
    }

    #[TestDox('WIZ-29 Case décochée : les réglages masqués ne sont pas contrôlés (pas d\'erreur sur un champ invisible)')]
    public function test_case_decochee_sans_controle(): void
    {
        $saison = $this->brouillon();

        $this->envoyer($saison, ['actif' => null, 'terrains' => '40', 'duree' => '', 'heure' => '']);

        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/4');
        $this->em()->clear();
        self::assertNull($this->phaseFinale($this->em()->find(Saison::class, $saison->getId()))->getJourneeFinales());
    }

    #[TestDox('WIZ-29 Étape 3 : journée des finales cochée sans gymnase ni date, ou 0 terrain : messages sous les champs, rien d\'enregistré')]
    public function test_saisie_incomplete(): void
    {
        $saison = $this->brouillon();

        $this->envoyer($saison, ['actif' => '1', 'lieu' => '', 'date' => '', 'terrains' => '0']);

        self::assertResponseStatusCodeSame(422);
        $texte = $this->client->getCrawler()->filter('body')->text();
        foreach (['Choisissez le gymnase de la journée des finales.', 'Indiquez la date de la journée des finales.', 'Entre 1 et 12 terrains.'] as $message) {
            self::assertStringContainsString($message, $texte);
        }
        $this->em()->clear();
        self::assertNull($this->phaseFinale($this->em()->find(Saison::class, $saison->getId()))->getJourneeFinales());
    }
}
