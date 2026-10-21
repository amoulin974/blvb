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
        self::assertSame(['Phase 1', 'Phase 2', 'Phase finale'], array_map(fn ($p) => $p->getNom(), $phases));
        self::assertSame(28, array_sum(array_map(fn ($p) => $p->getReglesDePassage()->count(), $saison->getPhases()->toArray())));
    }

    #[TestDox('WIZ-03 Copier une saison : avertissement dès le choix si elle n\'est pas terminée ; structure recopiée, dates décalées d\'un an')]
    public function test_depart_copie(): void
    {
        $source = $this->em()->find(Saison::class, ChampionnatDeTest::get('saison')->getId());
        $this->connecter('admin');
        $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        self::assertSelectorTextContains('[data-saison="'.$source->getId().'"]', 'seront à composer');
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

        $form = $this->client->request('GET', $url)->filter('button[value="suivant"]')->form();
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

    #[TestDox('WIZ-16 Étape 2 : une période de fermeture ajoutée mais vide, ou dont les dates sont inversées, est refusée (422) sans erreur 500 ni enregistrement')]
    public function test_periode_de_fermeture_vide(): void
    {
        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $url = '/admin/saison/'.$saison->getId().'/wizard/2';
        $valeurs = $this->client->request('GET', $url)->filter('#wizard-form')->form()->getPhpValues();

        $valeurs['saison_wizard']['indisponibilites'] = [['nom' => '', 'dateDebut' => '', 'dateFin' => '']];
        $this->client->request('POST', $url, $valeurs + ['aller' => 'suivant']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-erreur', 'Indiquez le motif de la fermeture.');
        self::assertSelectorTextNotContains('body', 'vérifiez les vacances scolaires', 'le rappel de copie ne masque pas les erreurs');
        self::assertSelectorTextContains('body', 'Indiquez le premier jour de la fermeture.');
        self::assertSelectorTextContains('body', 'Indiquez le dernier jour de la fermeture.');

        $valeurs['saison_wizard']['indisponibilites'] = [['nom' => 'Noël', 'dateDebut' => '2030-12-31', 'dateFin' => '2030-12-20']];
        $this->client->request('POST', $url, $valeurs + ['aller' => 'suivant']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le dernier jour ne peut pas précéder le premier.');
        $this->em()->clear();
        self::assertCount(0, $this->em()->find(Saison::class, $saison->getId())->getIndisponibilites(), 'rien enregistré');
    }

    /** Brouillon du modèle BLVB, toutes les étapes déjà vues ; renvoie l'URL de l'étape demandée. */
    private function brouillonA(int $etape): array
    {
        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $this->em()->find(Saison::class, $saison->getId())->setEtapeWizard(6);
        $this->em()->flush();

        return [$saison->getId(), '/admin/saison/'.$saison->getId().'/wizard/'.$etape];
    }

    /** Phases du brouillon dans l'ordre : [nom, code, nombre de poules]. */
    private function phases(int $id): array
    {
        $this->em()->clear();
        $phases = $this->em()->find(Saison::class, $id)->getPhases()->toArray();
        usort($phases, fn ($a, $b) => $a->getOrdre() <=> $b->getOrdre());

        return array_map(fn ($p) => [$p->getNom(), $p->getCode(), $p->getPoules()->count()], $phases);
    }

    /** Clique sur le bouton de l'étape dont la valeur est donnée (action ou navigation), avec des valeurs de champs. */
    private function cliquer(string $url, string $valeur, array $champs = []): void
    {
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('button[value="'.$valeur.'"]')->form(), $champs);
    }

    #[TestDox('WIZ-06 Étape 3 : ajouter et supprimer une poule, monter une phase, ajouter et supprimer une phase (codes attribués)')]
    public function test_phases_structure(): void
    {
        [$id, $url] = $this->brouillonA(3);

        $this->cliquer($url, 'ajouter-poule-0');
        self::assertResponseRedirects();
        self::assertSame(5, $this->phases($id)[0][2], 'une poule de plus en Phase 1');
        $this->cliquer($url, 'supprimer-poule-0-4');
        self::assertSame(4, $this->phases($id)[0][2]);

        $this->cliquer($url, 'monter-phase-1');
        self::assertSame(['Phase 2', 'Phase 1', 'Phase finale'], array_column($this->phases($id), 0));

        $this->cliquer($url, 'ajouter-phase');
        $phases = $this->phases($id);
        self::assertSame(['Phase 4', 'P3', 1], $phases[3], 'nouvelle phase en dernier, une poule, code attribué');
        $this->cliquer($url, 'supprimer-phase-3');
        self::assertCount(3, $this->phases($id));
    }

    #[TestDox('WIZ-07 Étape 3 : modifications enregistrées par « Suivant » ; nom vide ou code en double refusés sans rien enregistrer')]
    public function test_phases_saisie(): void
    {
        [$id, $url] = $this->brouillonA(3);

        $this->cliquer($url, 'suivant', ['phases_wizard[phases][0][nom]' => 'Brassage', 'phases_wizard[phases][0][poules][0][nbEquipesPrevu]' => '8']);
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/4');
        self::assertSame('Brassage', $this->phases($id)[0][0]);

        $this->cliquer($url, 'suivant', ['phases_wizard[phases][1][nom]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Indiquez le nom de la phase.');

        $this->cliquer($url, 'suivant', ['phases_wizard[phases][0][poules][1][code]' => 'A']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le code A est déjà utilisé par une autre poule de cette phase.');
        self::assertSame(['Brassage', 'Phase 2', 'Phase finale'], array_column($this->phases($id), 0), 'rien enregistré');
    }

    /** Règles de la phase d'ordre donné : sources dans l'ordre. */
    private function sources(int $id, int $ordre): array
    {
        $this->em()->clear();
        $phase = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === $ordre)->first();

        return array_map(fn ($r) => $r->getSource(), $phase->getReglesDePassage()->toArray());
    }

    /** Identifiant d'une poule par phase (ordre) et code. */
    private function poule(int $id, int $ordre, string $code): int
    {
        $this->em()->clear();
        $phase = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === $ordre)->first();

        return $phase->getPoules()->filter(fn ($p) => $p->getCode() === $code)->first()->getId();
    }

    #[TestDox('WIZ-08 Étape 4 : passages du modèle en mode simple ; « Suivant » sans changement les conserve ; 1 montée au lieu de 2 réécrit les règles de la poule')]
    public function test_passages_simple(): void
    {
        [$id, $url] = $this->brouillonA(4);
        $avant = $this->sources($id, 0);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Phase 1 → Phase 2');
        self::assertSelectorTextContains('body', 'Phase 2 → Phase finale');
        self::assertSelectorTextContains('body', 'Montées et descentes pour la saison suivante');

        $this->client->submit($crawler->filter('button[value="suivant"]')->form());
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/5');
        self::assertSame($avant, $this->sources($id, 0), 'règles inchangées');
        self::assertCount(16, $this->sources($id, 1));

        $b = $this->poule($id, 0, 'B');
        $this->cliquer($url, 'suivant', ["p[$b][montee]" => '1']);
        self::assertSame(['P1.B#1..1', 'P1.B#2..-3', 'P1.B#-2..-1'], array_values(array_filter($this->sources($id, 0), fn ($s) => str_starts_with($s, 'P1.B#'))));
    }

    #[TestDox('WIZ-09 Étape 4 : mode détaillé (ajouter une règle), retour au mode simple, « Rétablir les règles par défaut »')]
    public function test_passages_detail(): void
    {
        [$id, $url] = $this->brouillonA(4);
        $a = $this->poule($id, 0, 'A');

        $this->cliquer($url, 'detail-'.$a);
        self::assertResponseRedirects();
        $crawler = $this->client->request('GET', $url);
        self::assertCount(1, $crawler->filter('button[value="simple-'.$a.'"]'), 'Poule A en mode détaillé');
        self::assertSelectorTextContains('body', 'Règle 1 :');

        $this->cliquer($url, 'ajouter-regle-'.$a);
        self::assertSame(['P1.A#1..2', 'P1.A#3..-3', 'P1.A#-2..-1', 'P1.A#1..-1'], array_values(array_filter($this->sources($id, 0), fn ($s) => str_starts_with($s, 'P1.A#'))), 'une règle de plus pour la Poule A (toute la poule)');

        $this->cliquer($url, 'simple-'.$a);
        self::assertSame(['P1.A#1..2', 'P1.A#3..-3', 'P1.A#-2..-1'], array_values(array_filter($this->sources($id, 0), fn ($s) => str_starts_with($s, 'P1.A#'))), 'règles par défaut de la poule');

        $this->cliquer($url, 'detail-'.$a);
        $this->cliquer($url, 'supprimer-regle-'.$a.'-0');
        $phase1 = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === 0)->first();
        $this->cliquer($url, 'retablir-'.$phase1->getId());
        self::assertCount(12, $this->sources($id, 0), 'règles par défaut du modèle (3 par poule)');
    }

    #[TestDox('WIZ-10 Étape 5 : barème de la saison, phase finale sans points, départage réordonné et complété ; valeur non numérique refusée')]
    public function test_classement(): void
    {
        [$id, $url] = $this->brouillonA(5);
        $this->em()->clear();
        $finale = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === 2)->first()->getId();

        $this->cliquer($url, 'suivant', ['bareme[victoire_forte]' => '4', "phase[$finale][propre]" => '1', "phase[$finale][mode]" => 'aucun']);
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/6');
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $id);
        self::assertSame(4, $saison->getPointsVictoireForte());
        // (MySQL réordonne les clés d'une colonne JSON)
        self::assertEqualsCanonicalizing(array_fill_keys(['victoire_forte', 'victoire_faible', 'defaite_forte', 'defaite_faible', 'forfait'], 0),
            $saison->getPhases()->filter(fn ($p) => $p->getOrdre() === 2)->first()->getBareme());

        $this->cliquer($url, 'descendre-critere-0');
        $this->cliquer($url, 'ajouter-critere', ['nouveau_critere' => 'mini_classement']);
        $this->cliquer($url, 'retirer-critere-2');
        $this->em()->clear();
        self::assertSame(['difference_sets', 'points', 'mini_classement', 'alphabetique'],
            array_map(fn ($c) => $c->value, $this->em()->find(Saison::class, $id)->getDepartage()));

        $this->cliquer($url, 'suivant', ['bareme[forfait]' => 'beaucoup']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Équipe forfait : indiquez un nombre de points');
    }

    #[TestDox('WIZ-11 Étape 6 : saison du modèle prête (avertissement : nombres d\'équipes non indiqués), schéma, passages en clair ; « Valider la saison » la publie dans l\'admin')]
    public function test_verification_et_validation(): void
    {
        [$id, $url] = $this->brouillonA(6);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'La saison est prête à être validée.');
        self::assertSelectorTextContains('body', 'Nombre d\'équipes prévu non indiqué pour 12 poules');
        self::assertSelectorTextContains('table caption', 'Lignes : niveaux');
        self::assertSelectorTextContains('body', '1er et 2e de la Poule B (Phase 1) vers la Poule A (Phase 2)');

        $this->client->submit($crawler->filter('button[value="valider"]')->form());
        self::assertResponseRedirects('/admin/saison/'.$id.'/mise-en-place/poules');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Prochaine étape : composer les poules de « Phase 1 ».');
        self::assertSelectorTextContains('#titre-etape', 'Poules de Phase 1');
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $id);
        self::assertFalse($saison->isBrouillon());
        self::assertNull($saison->getEtapeWizard());
    }

    #[TestDox('WIZ-12 Étape 6 : une erreur (dates de phase incohérentes) bloque la validation, avec un lien « Corriger » et l\'étape signalée dans le stepper')]
    public function test_validation_bloquee(): void
    {
        [$id, $url] = $this->brouillonA(6);
        $phase2 = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === 1)->first();
        $phase2->setDatefin($phase2->getDateDebut()->modify('-1 day'));
        $this->em()->flush();

        $crawler = $this->client->request('GET', $url);
        self::assertSelectorTextContains('body', 'Phase 2 : la date de fin doit être après la date de début.');
        self::assertSame('true', $crawler->filter('button[value="valider"]')->attr('aria-disabled'));
        self::assertSame('!', $crawler->filter('.steps li')->eq(2)->attr('data-content'), 'étape « Phases » à corriger');

        $this->client->submit($crawler->filter('button[value="valider"]')->form());
        self::assertResponseStatusCodeSame(422);
        $this->em()->clear();
        self::assertTrue($this->em()->find(Saison::class, $id)->isBrouillon(), 'toujours un brouillon');

        $this->client->submit($this->client->getCrawler()->filter('button[value="3"]')->first()->form());
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/3');
    }

    #[TestDox('WIZ-17 Étape 6 : une phase placée avant une phase qui commence plus tôt (finale montée en premier) bloque la validation ; une phase finale n\'a pas à être la dernière si les dates le permettent')]
    public function test_ordre_des_phases_selon_les_dates(): void
    {
        [$id, $url] = $this->brouillonA(3);
        $this->cliquer($url, 'monter-phase-2');
        $this->cliquer($url, 'monter-phase-1');
        self::assertSame(['Phase finale', 'Phase 1', 'Phase 2'], array_column($this->phases($id), 0));

        $this->client->request('GET', '/admin/saison/'.$id.'/wizard/6');
        self::assertSelectorTextContains('body', 'Phase finale est placée avant Phase 1 mais commence plus tard');
        self::assertSame('true', $this->client->getCrawler()->filter('button[value="valider"]')->attr('aria-disabled'));

        // Les dates suivent l'ordre choisi : plus d'erreur
        $this->em()->clear();
        $phases = $this->em()->find(Saison::class, $id)->getPhases()->toArray();
        usort($phases, fn ($a, $b) => $a->getOrdre() <=> $b->getOrdre());
        $debut = $phases[1]->getDateDebut();
        $phases[0]->setDateDebut($debut->modify('-30 days'))->setDateFin($debut->modify('-1 day'));
        $this->em()->flush();
        $this->client->request('GET', '/admin/saison/'.$id.'/wizard/6');
        self::assertSelectorTextNotContains('body', 'commence plus tard');
    }

    #[TestDox('WIZ-13Saison créée, pas encore lancée : « Modifier la saison » propose le wizard (étapes 2 à 6, sans le départ), « Terminer » ; nom, dates et fermetures modifiables sur la page')]
    public function test_modification_saison_creee(): void
    {
        [$id, $url] = $this->brouillonA(6);
        $this->client->submit($this->client->request('GET', $url)->filter('button[value="valider"]')->form());

        $crawler = $this->client->request('GET', '/admin/saison/'.$id.'/edit');
        self::assertSelectorTextContains('body', 'Aucun match n\'est encore programmé');
        $crawler = $this->client->click($crawler->selectLink('Modifier l\'organisation de la saison')->link());
        self::assertSelectorTextContains('h1', 'Modifier la saison :');
        self::assertCount(5, $crawler->filter('.steps li'), 'pas d\'étape « Départ »');

        $this->client->request('GET', '/admin/saison/'.$id.'/wizard/1');
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/2');

        $this->client->submit($this->client->request('GET', '/admin/saison/'.$id.'/wizard/6')->selectButton('Terminer')->form());
        self::assertResponseRedirects('/admin/saison/'.$id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'sont enregistrées');
        $this->em()->clear();
        self::assertFalse($this->em()->find(Saison::class, $id)->isBrouillon());

        $form = $this->client->request('GET', '/admin/saison/'.$id.'/edit')->selectButton('Enregistrer')->form();
        $this->client->submit($form, ['saison_wizard[nom]' => 'Saison renommée']);
        self::assertResponseRedirects('/admin/saison/'.$id);
        $this->em()->clear();
        self::assertSame('Saison renommée', $this->em()->find(Saison::class, $id)->getNom());
    }

    #[TestDox('WIZ-14 Saison créée : « Enregistrer et quitter » avec une erreur renvoie à la vérification ; une phase clôturée ferme le wizard')]
    public function test_modification_garde_fous(): void
    {
        [$id, $url] = $this->brouillonA(6);
        $this->client->submit($this->client->request('GET', $url)->filter('button[value="valider"]')->form());
        $phase2 = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === 1)->first();
        $phase2->setDatefin($phase2->getDateDebut()->modify('-1 day'));
        $this->em()->flush();

        $this->cliquer('/admin/saison/'.$id.'/wizard/5', 'quitter');
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/6');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'corrigez-les avant de quitter');

        $this->em()->clear();
        $this->em()->find(Saison::class, $id)->getPhases()->first()->setClose(1);
        $this->em()->flush();
        // Une phase clôturée : la saison est lancée, le wizard passe en mode ajout (D51)
        $this->client->request('GET', '/admin/saison/'.$id.'/wizard/3');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Clôturée · ne se modifie plus');
    }

    #[TestDox('WIZ-15 Sécurité : sans jeton, les étapes 4 à 6 n\'enregistrent rien ; une poule d\'une autre saison n\'est pas une destination ; champs mal formés et niveau démesuré refusés sans erreur 500')]
    public function test_entrees_refusees(): void
    {
        [$id, $url] = $this->brouillonA(4);
        $avant = $this->sources($id, 0);
        $phase1 = $this->em()->find(Saison::class, $id)->getPhases()->filter(fn ($p) => $p->getOrdre() === 0)->first()->getId();

        $this->client->request('POST', $url, ['action' => 'retablir-'.$phase1, 'p' => []]);
        self::assertResponseRedirects($url);
        $this->client->request('POST', '/admin/saison/'.$id.'/wizard/5', ['bareme' => ['victoire_forte' => '9'], 'aller' => 'suivant']);
        $this->client->request('POST', '/admin/saison/'.$id.'/wizard/6', ['aller' => 'valider']);
        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $id);
        self::assertTrue($saison->isBrouillon(), 'pas validée sans jeton');
        self::assertNotSame(9, $saison->getPointsVictoireForte());
        self::assertSame($avant, $this->sources($id, 0));

        $a = $this->poule($id, 0, 'A');
        $etrangere = ChampionnatDeTest::get('poule.A')->getId();
        $this->cliquer($url, 'detail-'.$a);
        // Requête forgée : la liste ne propose pas cette poule
        $valeurs = $this->client->request('GET', $url)->filter('#wizard-form')->form()->getPhpValues();
        $valeurs['p'][$a]['r'][0]['dest'] = 'p:'.$etrangere;
        $this->client->request('POST', $url, $valeurs + ['aller' => 'suivant']);
        self::assertNotContains('P1.A#1..2', $this->sources($id, 0), 'règle vers une poule d\'une autre saison ignorée');

        $valeurs = $this->client->request('GET', $url)->filter('#wizard-form')->form()->getPhpValues();
        $valeurs['p'][$a] = 'x';
        $this->client->request('POST', $url, $valeurs);
        self::assertResponseRedirects();
        $valeurs['p'][$a] = ['mode' => 'detail', 'r' => 'x'];
        $this->client->request('POST', $url, $valeurs);
        self::assertResponseRedirects();

        $this->cliquer('/admin/saison/'.$id.'/wizard/3', 'suivant', ['phases_wizard[phases][0][poules][0][niveau]' => '2000000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le niveau est un nombre de 1 à 20.');
    }

    #[TestDox('WIZ-05 Garde-fous : une saison lancée (matchs programmés) ouvre le wizard en mode ajout (D51), sans les étapes Saison et Classement, ni en lecture ni en envoi ; une étape pas encore atteinte renvoie à la dernière atteinte')]
    public function test_garde_fous(): void
    {
        $this->connecter('admin');
        $id = ChampionnatDeTest::get('saison')->getId();
        $crawler = $this->client->request('GET', '/admin/saison/'.$id.'/edit');
        self::assertSelectorTextContains('body', 'seuls le nom, les dates et les périodes de fermeture se modifient');
        self::assertCount(1, $crawler->filter('a[href$="/wizard/3"]:contains("Ajouter des phases")'), 'accès au mode ajout');
        foreach ([2, 5] as $etape) {
            $this->client->request('GET', '/admin/saison/'.$id.'/wizard/'.$etape);
            self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/3');
        }
        $this->client->request('POST', '/admin/saison/'.$id.'/wizard/5', ['bareme' => ['victoire_forte' => '9'], 'aller' => 'suivant']);
        self::assertResponseRedirects('/admin/saison/'.$id.'/wizard/3');
        $this->em()->clear();
        self::assertNotSame(9, $this->em()->find(Saison::class, $id)->getPointsVictoireForte());

        $saison = $this->demarrer(['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_actuel']);
        $this->client->request('GET', '/admin/saison/'.$saison->getId().'/wizard/5');
        self::assertResponseRedirects('/admin/saison/'.$saison->getId().'/wizard/2');
    }
}
