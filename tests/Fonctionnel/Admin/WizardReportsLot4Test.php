<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * WIZ-18 à WIZ-21 : points reportés du lot 4 au lot 5 (relecture du wizard) : point de départ rappelé au récapitulatif,
 * heure d'enregistrement du brouillon, écarts d'effectif dans le schéma, aide des critères de départage, liens de
 * l'alerte d'erreur de l'étape 3 vers les champs.
 */
final class WizardReportsLot4Test extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /** Brouillon créé à l'étape 1 (modèle BLVB ou copie), étape 6 atteinte. */
    private function brouillon(array $choix): Saison
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        $this->client->submit($crawler->filter('#wizard-form')->form(), $choix);
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, (int) $m[1]);
        $saison->setEtapeWizard(6);
        $this->em()->flush();

        return $saison;
    }

    #[TestDox('WIZ-18 Point de départ enregistré (modèle ou copie), rappelé au récapitulatif ; « Brouillon enregistré à H h mm » dans l\'en-tête')]
    public function test_depart_et_heure(): void
    {
        $saison = $this->brouillon(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        self::assertStringStartsWith('Modèle « ', (string) $saison->getDepartLibelle());
        self::assertNotNull($saison->getModifieLe());

        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/6');
        self::assertSelectorTextContains('body', 'Point de départ');
        self::assertSelectorTextContains('body', (string) $saison->getDepartLibelle());
        self::assertSelectorTextContains('body', 'Brouillon enregistré à '.$saison->getModifieLe()->format('G \h i'));

        $source = $this->em()->find(Saison::class, ChampionnatDeTest::get('saison')->getId());
        $copie = $this->brouillon(['depart[depart]' => 'copie', 'depart[saison]' => (string) $source->getId()]);
        self::assertSame('Copie de la saison '.$source->getNom(), $copie->getDepartLibelle());
    }

    #[TestDox('WIZ-19 Étape 5 : une aide courte sous chaque critère de départage')]
    public function test_aide_des_criteres(): void
    {
        $saison = $this->brouillon(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/5');

        self::assertSelectorTextContains('#departage', 'Les points du barème de la saison.');
        self::assertSelectorTextContains('#departage', 'Sets gagnés moins sets perdus, sur toute la poule.');
        self::assertSelectorTextContains('#departage', 'Le vainqueur du dernier match joué entre deux équipes à égalité passe devant.');
    }

    #[TestDox('WIZ-20 Vérification : le schéma signale l\'écart entre les équipes reçues et le nombre prévu (« 9 / 8 équipes »)')]
    public function test_ecart_deffectif_dans_le_schema(): void
    {
        $saison = $this->brouillon(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/6');
        self::assertSelectorTextNotContains('body', 'écart à vérifier', 'le modèle BLVB est cohérent');

        $this->em()->clear();
        $phase2 = null;
        foreach ($this->em()->find(Saison::class, $saison->getId())->getPhases() as $phase) {
            if ($phase->getNom() === 'Phase 1') {
                // Les effectifs de la phase 1 sont connus : les équipes reçues par la phase 2 se calculent
                foreach ($phase->getPoules() as $poule) {
                    $poule->setNbEquipesPrevu(8);
                }
            }
            if ($phase->getNom() === 'Phase 2') {
                $phase2 = $phase;
            }
        }
        $poules = $phase2->getPoules()->toArray();
        $poules[0]->setNbEquipesPrevu(3);
        $this->em()->flush();

        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/6');
        self::assertSelectorTextContains('body', ' pour 3 prévues');
        self::assertSelectorTextContains('body', 'écart à vérifier');
    }

    #[TestDox('WIZ-21 Étape 3 : l\'alerte d\'erreur liste chaque point avec un lien vers le champ concerné')]
    public function test_liens_de_l_alerte_etape_3(): void
    {
        $saison = $this->brouillon(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $url = '/admin/saison/'.$saison->getId().'/wizard/3';
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('button[value="suivant"]')->form(), ['phases_wizard[phases][1][nom]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.admin-alerte', 'Des points sont à corriger');
        self::assertSelectorTextContains('.admin-alerte', 'Indiquez le nom de la phase.');
        self::assertSelectorExists('.admin-alerte a[href="#phases_wizard_phases_1_nom"]');
        self::assertSelectorExists('#phases_wizard_phases_1_nom');
    }
}
