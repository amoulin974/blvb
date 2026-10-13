<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Saison;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** WIZ-02 à WIZ-05 : wizard de création d'une saison, étapes 1 (départ) et 2 (saison) et navigation (D29). */
final class WizardSaisonTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /** Étape 1 : envoie le choix et renvoie le brouillon créé. */
    private function demarrer(array $choix): Saison
    {
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('#wizard-form')->form(), $choix);
        self::assertResponseRedirects();
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        self::assertNotEmpty($m, 'redirigé vers l\'étape 2 du brouillon');
        $this->em()->clear();

        return $this->em()->find(Saison::class, (int) $m[1]);
    }

    #[TestDox('WIZ-02 Partir d\'un modèle : brouillon créé (non visible), 3 phases du format BLVB, nom proposé « Saison AAAA-AAAA+1 »')]
    public function test_depart_modele(): void
    {
        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);

        self::assertTrue($saison->isBrouillon());
        self::assertSame(2, $saison->getEtapeWizard());
        self::assertSame(0, (int) $saison->getFavori());
        self::assertMatchesRegularExpression('/^Saison (\d{4})-\d{4}$/', $saison->getNom());
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn ($a, $b) => $a->getOrdre() <=> $b->getOrdre());
        self::assertSame(['Phase 1', 'Phase 2', 'Phase Finale'], array_map(fn ($p) => $p->getNom(), $phases));
        self::assertSame(28, array_sum(array_map(fn ($p) => $p->getReglesDePassage()->count(), $saison->getPhases()->toArray())));
    }

    #[TestDox('WIZ-03 Copier une saison : structure recopiée, dates décalées d\'un an, avertissement si la saison copiée n\'est pas terminée')]
    public function test_depart_copie(): void
    {
        $source = $this->em()->find(Saison::class, ChampionnatDeTest::get('saison')->getId());
        $saison = $this->demarrer(['depart[depart]' => 'copie', 'depart[saison]' => (string) $source->getId()]);

        self::assertTrue($saison->isBrouillon());
        self::assertSame($source->getDateDebut()->modify('+1 year')->format('Y-m-d'), $saison->getDateDebut()->format('Y-m-d'));
        self::assertSame($source->getPhases()->count(), $saison->getPhases()->count());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'sont à composer');
    }

    #[TestDox('WIZ-04 Étape 2 : erreurs (dates, nom déjà pris) sans enregistrement ; « Suivant » enregistre et avance, « Enregistrer et quitter » revient à la liste')]
    public function test_etape_saison(): void
    {
        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $url = '/admin/saison/'.$saison->getId().'/wizard/2';
        $crawler = $this->client->request('GET', $url);
        $form = $crawler->filter('#wizard-form')->form();

        $this->client->submit($form, ['saison_wizard[date_debut]' => '2030-09-01', 'saison_wizard[date_fin]' => '2030-08-01']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La date de fin doit être après la date de début.');

        $this->client->submit($form, ['saison_wizard[nom]' => ChampionnatDeTest::get('saison')->getNom()]);
        self::assertSelectorTextContains('body', 'Une saison porte déjà ce nom.');

        $form = $this->client->request('GET', $url)->selectButton('Suivant →')->form();
        $this->client->submit($form, ['saison_wizard[nom]' => 'Saison du wizard']);
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/3');
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $saison->getId());
        self::assertSame(['Saison du wizard', 3], [$saison->getNom(), $saison->getEtapeWizard()]);

        $this->client->submit($this->client->request('GET', $url)->selectButton('Enregistrer et quitter')->form());
        self::assertResponseRedirects('/admin/saison/saison');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Brouillon « Saison du wizard » enregistré');
        self::assertSelectorTextContains('body', 'Brouillon · étape 3 sur 6');
    }

    #[TestDox('WIZ-05 Garde-fous : une saison déjà créée n\'ouvre pas le wizard ; une étape pas encore atteinte renvoie à la dernière atteinte')]
    public function test_garde_fous(): void
    {
        $this->connecter('admin');
        $id = ChampionnatDeTest::get('saison')->getId();
        $this->client->request('GET', '/admin/saison/'.$id.'/wizard/2');
        self::assertResponseRedirects('/admin/saison/'.$id);

        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/5');
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/2');
    }
}
