<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** WIZ-31 : bouton « Enregistrer » des étapes 2 à 5 du wizard, qui enregistre sans changer d'étape. */
final class WizardEnregistrerTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /** Brouillon créé depuis le modèle « Format BLVB actuel », étape 3 atteinte. */
    private function brouillon(): Saison
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        $this->client->submit($crawler->filter('#wizard-form')->form(), ['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, (int) $m[1]);
        $saison->setEtapeWizard(3);
        $this->em()->flush();
        $this->em()->clear();

        return $this->em()->find(Saison::class, (int) $m[1]);
    }

    #[TestDox('WIZ-31 « Enregistrer » aux étapes 2 à 5 : la saisie est enregistrée, la même étape se réaffiche (alertes recalculées), l\'étape atteinte ne change pas ; pas de bouton à la vérification')]
    public function test_enregistrer_sans_changer_d_etape(): void
    {
        $saison = $this->brouillon();
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';

        $crawler = $this->client->request('GET', $url);
        self::assertCount(2, $crawler->filter('.btn[name="aller"][value="enregistrer"]'), 'actions au-dessus de la barre et dans la barre');
        // Touche Entrée : le premier bouton d'envoi du formulaire dans le document est « Enregistrer » (pas une étape du stepper)
        $premier = $crawler->filter('button[type="submit"][form="wizard-form"], #wizard-form button[type="submit"]')->first();
        self::assertSame('enregistrer', $premier->attr('value'));
        // Position de défilement gardée par les actions de l'étape (position_defilement_controller.js)
        self::assertCount(1, $crawler->filter('[data-controller~="position-defilement"][data-action*="submit->position-defilement#memoriser"] #wizard-form'));
        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $valeurs['phases_wizard']['phases'][0]['nom'] = 'Phase 1 renommée';
        $this->client->request('POST', $url, $valeurs + ['aller' => 'enregistrer']);

        self::assertResponseRedirects($url);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Modifications enregistrées. Les alertes ci-dessous sont à jour.');
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $saison->getId());
        self::assertSame(3, $saison->getEtapeWizard(), 'étape atteinte inchangée');
        self::assertContains('Phase 1 renommée', array_map(fn ($p) => $p->getNom(), $saison->getPhases()->toArray()));

        $saison->setEtapeWizard(6);
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/6');
        self::assertCount(0, $crawler->filter('button[value="enregistrer"]'));
    }
}
