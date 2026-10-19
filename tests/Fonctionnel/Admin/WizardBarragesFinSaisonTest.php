<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Competition\VerificationSaison;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * FIN-01 à FIN-06 : section « Phase 2 → Barrages de fin de saison » de l'étape Passages (D53), sur un brouillon du modèle
 * « Format BLVB avec barrages » (phase 2 : toute la poule vers son tableau, avant-dernier et 2e vers les barrages).
 */
final class WizardBarragesFinSaisonTest extends TestFonctionnel
{
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/saison/wizard/nouvelle');
        $this->client->submit($crawler->filter('#wizard-form')->form(), ['depart[depart]' => 'modele', 'depart[modele]' => 'blvb_avec_barrages']);
        preg_match('#/admin/saison/(\d+)/wizard/2$#', $this->client->getResponse()->headers->get('Location'), $m);
        $this->id = (int) $m[1];
        $this->em()->clear();
        $this->em()->find(Saison::class, $this->id)->setEtapeWizard(6);
        $this->em()->flush();
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function url(): string
    {
        return '/admin/saison/'.$this->id.'/wizard/4';
    }

    private function phase(string $code): Phase
    {
        $this->em()->clear();

        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $this->id, 'code' => $code]);
    }

    /** @return list<string> règles de la phase 2 « source → phase.poule », dans l'ordre */
    private function reglesP2(): array
    {
        return array_map(fn ($r) => $r->getSource().' → '.$r->getPouleDestination()?->getPhase()->getCode().'.'.$r->getPouleDestination()?->getCode(),
            $this->phase('P2')->getReglesDePassage()->toArray());
    }

    /** Envoie l'étape 4 telle qu'affichée, avec les choix des barrages de fin de saison modifiés ([code du match => [équipe 1, équipe 2]]). */
    private function envoyer(array $choix = [], string $aller = 'enregistrer'): void
    {
        $crawler = $this->client->request('GET', $this->url());
        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $barrage = $this->phase('BAR2');
        foreach ($barrage->getPoules() as $match) {
            if (isset($choix[$match->getCode()])) {
                $valeurs['fin'][$barrage->getId()][$match->getId()] = $choix[$match->getCode()];
            }
        }
        $this->client->request('POST', $this->url(), $valeurs + ['aller' => $aller]);
    }

    #[TestDox('FIN-01 Étape Passages : section « Phase 2 → Barrages de fin de saison », une carte par match avec les équipes du modèle ; la phase 2 reste en mode simple, sans barrages ni case « saison suivante »')]
    public function test_affichage(): void
    {
        $crawler = $this->client->request('GET', $this->url());

        self::assertCount(1, $crawler->filter('h3:contains("Phase 2 → Barrages de fin de saison")'));
        $barrage = $this->phase('BAR2');
        $selection = [];
        foreach ($barrage->getPoules() as $match) {
            foreach ([0, 1] as $k) {
                $selection[$match->getCode()][] = $crawler->filter(sprintf('select[name="fin[%d][%d][%d]"] option[selected]', $barrage->getId(), $match->getId(), $k))->attr('value');
            }
        }
        self::assertSame(['AB' => ['A#-2', 'B#2'], 'BC' => ['B#-2', 'C#2'], 'CD' => ['C#-2', 'D#2']], self::trier($selection));
        self::assertSame('Avant-dernier – Poule A', $crawler->filter('option[value="A#-2"]')->first()->text());

        foreach ($this->phase('P2')->getPoules() as $poule) {
            self::assertSame('simple', $crawler->filter('input[name="p['.$poule->getId().'][mode]"]')->attr('value'), $poule->getNom().' en mode simple');
        }
        $sectionP2 = $crawler->filter('section')->reduce(fn ($s) => str_contains($s->filter('h3')->text(''), 'Phase 2 → Phase Finale'));
        self::assertStringNotContainsString('Montées et descentes pour la saison suivante', $sectionP2->text());
        self::assertCount(0, $sectionP2->filter('optgroup[label="Barrages de fin de saison"]'));
        self::assertSelectorNotExists('body:contains("Proposition selon le schéma du bureau")');
    }

    /** Tri des deux équipes de chaque match (l'ordre d'enregistrement ne compte pas). */
    private static function trier(array $selection): array
    {
        return array_map(function (array $equipes) {
            sort($equipes);

            return $equipes;
        }, $selection);
    }

    #[TestDox('FIN-02 Changer une équipe : seules les règles vers les barrages de fin de saison changent, celles vers les tableaux restent')]
    public function test_modification(): void
    {
        $tableaux = array_values(array_filter($this->reglesP2(), fn ($r) => str_contains($r, '→ F.')));

        $this->envoyer(['AB' => ['A#-2', 'B#3']]);
        self::assertResponseRedirects($this->url());

        $regles = $this->reglesP2();
        self::assertContains('P2.B#3 → BAR2.AB', $regles);
        self::assertNotContains('P2.B#2 → BAR2.AB', $regles);
        self::assertContains('P2.A#-2 → BAR2.AB', $regles);
        self::assertSame($tableaux, array_values(array_filter($regles, fn ($r) => str_contains($r, '→ F.'))), 'règles vers les tableaux inchangées');
        self::assertCount(\count($tableaux) + 6, $regles, '6 équipes de barrage');
    }

    #[TestDox('FIN-03 Contrôles : match incomplet, deux fois la même équipe, une équipe dans deux barrages : message, choix ignoré')]
    public function test_controles(): void
    {
        $this->envoyer(['AB' => ['A#-2', ''], 'BC' => ['B#-2', 'B#-2'], 'CD' => ['A#-2', 'D#2']]);
        $this->client->followRedirect();
        $texte = $this->client->getCrawler()->filter('body')->text();

        self::assertStringContainsString('Barrage de fin de saison A / B : choisissez les deux équipes.', $texte);
        self::assertStringContainsString('Barrage de fin de saison B / C : choisissez deux équipes différentes.', $texte);
        self::assertStringContainsString('L\'avant-dernier de Poule A est déjà dans Barrage de fin de saison A / B : une équipe ne joue qu\'un barrage.', $texte);
        $regles = $this->reglesP2();
        self::assertCount(1, array_filter($regles, fn ($r) => str_starts_with($r, 'P2.A#-2 →')), 'avant-dernier de A dans un seul barrage');
    }

    #[TestDox('FIN-07 Poules de 8 : le 7e et l\'avant-dernier sont la même équipe ; une place qui n\'est pas proposée (rang 50) est refusée ; aucune autre phase ne peut envoyer vers ces barrages')]
    public function test_meme_equipe_et_places_forgees(): void
    {
        $em = $this->em();
        foreach ($this->phase('P2')->getPoules() as $poule) {
            $poule->setNbEquipesPrevu(8);
        }
        $em->flush();

        $this->envoyer(['AB' => ['A#7', 'A#-2'], 'BC' => ['B#50', 'C#2']]);
        $this->client->followRedirect();
        $texte = $this->client->getCrawler()->filter('body')->text();
        self::assertStringContainsString('Barrage de fin de saison A / B : choisissez deux équipes différentes.', $texte);
        self::assertStringContainsString('Barrage de fin de saison B / C : choisissez les deux équipes.', $texte);
        self::assertStringContainsString('À vérifier : Barrages de fin de saison', $texte);
        self::assertNotContains('P2.B#50 → BAR2.BC', $this->reglesP2());

        // La phase 1 ne propose pas les matchs de ces barrages comme destination
        $crawler = $this->client->request('GET', $this->url());
        self::assertCount(0, $crawler->filter('optgroup[label="Barrages de fin de saison"]'));
    }

    #[TestDox('FIN-04 Rétablir les règles par défaut de la phase 2 garde les barrages de fin de saison, sans règle « saison suivante »')]
    public function test_retablir(): void
    {
        $crawler = $this->client->request('GET', $this->url());
        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $this->client->request('POST', $this->url(), $valeurs + ['action' => 'retablir-'.$this->phase('P2')->getId()]);

        $regles = $this->phase('P2')->getReglesDePassage()->toArray();
        self::assertCount(6, array_filter($regles, fn ($r) => $r->getPouleDestination()?->getPhase()->getCode() === 'BAR2'));
        self::assertCount(0, array_filter($regles, fn ($r) => $r->versSaisonSuivante()));
    }

    #[TestDox('FIN-05 Sans aucune équipe choisie : proposition du schéma du bureau (avant-dernier de la poule N contre 2e de la poule N+1), enregistrée seulement à l\'envoi')]
    public function test_proposition(): void
    {
        $p2 = $this->phase('P2');
        foreach ($p2->getReglesDePassage()->toArray() as $regle) {
            if ($regle->getPouleDestination()?->getPhase()->getCode() === 'BAR2') {
                $p2->removeRegleDePassage($regle);
            }
        }
        $this->em()->flush();

        $crawler = $this->client->request('GET', $this->url());
        self::assertSelectorTextContains('body', 'Proposition selon le schéma du bureau');
        $barrage = $this->phase('BAR2');
        $ab = $barrage->getPoules()->filter(fn ($p) => $p->getCode() === 'AB')->first();
        self::assertSame('A#-2', $crawler->filter(sprintf('select[name="fin[%d][%d][0]"] option[selected]', $barrage->getId(), $ab->getId()))->attr('value'));
        self::assertSame('B#2', $crawler->filter(sprintf('select[name="fin[%d][%d][1]"] option[selected]', $barrage->getId(), $ab->getId()))->attr('value'));
        self::assertCount(0, array_filter($this->reglesP2(), fn ($r) => str_contains($r, 'BAR2')), 'rien d\'enregistré à l\'affichage');

        $this->envoyer();
        self::assertCount(6, array_filter($this->reglesP2(), fn ($r) => str_contains($r, 'BAR2')));
    }

    #[TestDox('FIN-06 Vérification : un match de barrage de fin de saison sans ses deux équipes est une erreur de l\'étape Passages')]
    public function test_verification(): void
    {
        self::assertSame([], array_filter(VerificationSaison::verifier($this->em()->find(Saison::class, $this->id))['erreurs'], fn ($e) => str_contains($e['texte'], 'choisissez les deux équipes')));

        $this->envoyer(['AB' => ['A#-2', '']]);
        $erreurs = VerificationSaison::verifier($this->phase('P2')->getSaison())['erreurs'];
        self::assertContains(['texte' => 'Barrage de fin de saison A / B (Barrages de fin de saison) : choisissez les deux équipes.', 'etape' => 4], $erreurs);
    }
}
