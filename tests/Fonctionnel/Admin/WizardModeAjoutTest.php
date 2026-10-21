<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * ADJ-01 à ADJ-06 : wizard en mode ajout d'une saison lancée (D51). Saison de la story : la phase 1 a des matchs
 * (commencée, figée), la phase 2 n'a ni poule ni match (libre).
 */
final class WizardModeAjoutTest extends TestFonctionnel
{
    private int $id;

    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
        $this->id = ChampionnatDeTest::get('saison')->getId();
        $this->connecter('admin');
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function url(int $etape): string
    {
        return '/admin/saison/'.$this->id.'/wizard/'.$etape;
    }

    /** Envoie l'étape 3 telle qu'affichée, avec des valeurs modifiées et une action. */
    private function envoyerPhases(array $modifs = [], string $action = '', string $aller = ''): void
    {
        $valeurs = $this->client->request('GET', $this->url(3))->filter('#wizard-form')->form()->getPhpValues();
        $valeurs = array_replace_recursive($valeurs, $modifs);
        $this->client->request('POST', $this->url(3), $valeurs + array_filter(['action' => $action, 'aller' => $aller]));
    }

    /** @return list<string> noms des phases de la saison dans l'ordre */
    private function phases(): array
    {
        $this->em()->clear();
        $phases = $this->em()->find(Saison::class, $this->id)->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return array_map(fn (Phase $p) => $p->getNom(), $phases);
    }

    private function phase(string $nom): Phase
    {
        $this->em()->clear();

        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $this->id, 'nom' => $nom]);
    }

    #[TestDox('ADJ-01 Saison lancée : l\'étape Phases s\'ouvre en mode ajout, la phase commencée en lecture seule hors du formulaire ; étapes proposées : Phases, Passages, Vérification')]
    public function test_affichage(): void
    {
        $crawler = $this->client->request('GET', $this->url(3));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'les phases déjà commencées ne se modifient plus');
        self::assertSelectorTextContains('body', 'Commencée · ne se modifie plus');
        self::assertCount(1, $crawler->filter('input[name$="[nom]"][name^="phases_wizard[phases]"]'), 'seule la phase 2 est modifiable');
        self::assertSame('Phase 2', $crawler->filter('input[name="phases_wizard[phases][0][nom]"]')->attr('value'));
        foreach (['Phases', 'Passages', 'Vérification'] as $etape) {
            self::assertStringContainsString($etape, $crawler->filter('body')->text(), $etape);
        }
        self::assertSelectorNotExists('a[href$="/wizard/5"]', 'pas d\'étape Classement');
        self::assertSelectorNotExists('a[href$="/wizard/2"]', 'pas d\'étape Saison');
    }

    #[TestDox('ADJ-02 Une phase de barrages s\'ajoute à la fin puis se remonte entre la phase commencée et la phase 2, jamais avant la phase commencée')]
    public function test_ajout_et_ordre(): void
    {
        $this->envoyerPhases(action: 'ajouter-phase');
        self::assertSame(['Phase 1', 'Phase 2', 'Phase 3'], $this->phases());

        // La nouvelle phase (2e du formulaire) devient des barrages, puis remonte d'un cran
        $this->envoyerPhases(['phases_wizard' => ['phases' => [1 => ['nom' => 'Barrages', 'type' => (string) PhaseType::BARRAGE->value]]]], 'monter-phase-1');
        self::assertSame(['Phase 1', 'Barrages', 'Phase 2'], $this->phases());
        self::assertSame(PhaseType::BARRAGE, $this->phase('Barrages')->getType());

        // Première phase libre : « Monter » ne la place pas avant la phase commencée
        $this->envoyerPhases(action: 'monter-phase-0');
        self::assertSame(['Phase 1', 'Barrages', 'Phase 2'], $this->phases());
    }

    #[TestDox('ADJ-03 La phase commencée ne peut être ni supprimée ni modifiée : les actions ne visent que les phases libres, ses matchs restent')]
    public function test_phase_commencee_protegee(): void
    {
        $matchs = \count($this->em()->getRepository(Partie::class)->findAll());

        $this->envoyerPhases(action: 'supprimer-phase-0'); // vise la phase 2, première phase libre
        self::assertSame(['Phase 1'], $this->phases());

        $this->client->request('POST', $this->url(3), ['action' => 'supprimer-phase-5']);
        self::assertSame(['Phase 1'], $this->phases());
        self::assertSame($matchs, \count($this->em()->getRepository(Partie::class)->findAll()), 'aucun match supprimé');
    }

    #[TestDox('ADJ-04 Le code d\'une phase libre ne peut pas reprendre celui d\'une phase commencée')]
    public function test_code_reserve(): void
    {
        $this->envoyerPhases(); // attribue les codes (P1, P2)
        $code = $this->phase('Phase 1')->getCode();
        self::assertNotEmpty($code);

        $this->envoyerPhases(['phases_wizard' => ['phases' => [0 => ['code' => $code]]]], aller: 'suivant');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'déjà utilisé par une autre phase');
        self::assertNotSame($code, $this->phase('Phase 2')->getCode());
    }

    #[TestDox('ADJ-05 Passages : ceux d\'une phase clôturée ne s\'affichent plus et ne changent pas, même envoyés à la main')]
    public function test_passages_phase_cloturee(): void
    {
        $this->envoyerPhases(); // codes
        $phase1 = $this->phase('Phase 1');
        $phase1->setClose(1);
        $this->em()->flush();
        $avant = array_map(fn ($r) => $r->getSource(), $phase1->getReglesDePassage()->toArray());
        $pouleA = $phase1->getPoules()->first()->getId();

        $crawler = $this->client->request('GET', $this->url(4));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Les passages de la phase « Phase 1 » ne se modifient plus');
        self::assertCount(0, $crawler->filter('h3:contains("Phase 1 →")'));

        $jeton = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', $this->url(4), ['_token' => $jeton, 'p' => [$pouleA => ['mode' => 'detail', 'r' => [['du' => '1', 'au' => '-1', 'dest' => 'n:1']]]], 'aller' => 'suivant']);
        self::assertResponseRedirects($this->url(6));
        self::assertSame($avant, array_map(fn ($r) => $r->getSource(), $this->phase('Phase 1')->getReglesDePassage()->toArray()));
    }

    /** Une poule dans la phase 2 (libre), créée par le wizard ; renvoie la poule. */
    private function pouleEnPhase2(): \App\Entity\Poule
    {
        $this->envoyerPhases(action: 'ajouter-poule-0');

        return $this->phase('Phase 2')->getPoules()->first();
    }

    #[TestDox('ADJ-07 Une phase ou une poule libre visée par les passages d\'une phase commencée ne se supprime pas : message, passages conservés')]
    public function test_suppression_refusee_si_visee(): void
    {
        $poule = $this->pouleEnPhase2();
        $phase1 = $this->phase('Phase 1');
        $phase1->addRegleDePassage((new \App\Entity\RegleDePassage())->setOrdre(99)->setSource($phase1->getCode().'.A#1..-1')
            ->setPouleDestination($this->em()->find(\App\Entity\Poule::class, $poule->getId())));
        $this->em()->flush();

        $this->envoyerPhases(action: 'supprimer-poule-0-0');
        self::assertCount(1, $this->phase('Phase 2')->getPoules(), 'poule conservée');
        $this->envoyerPhases(action: 'supprimer-phase-0');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Phase 2 n\'est pas supprimée : les passages de « Phase 1 », déjà commencée, y envoient des équipes.');
        self::assertSame(['Phase 1', 'Phase 2'], $this->phases());
        self::assertCount(1, array_filter($this->phase('Phase 1')->getReglesDePassage()->toArray(), fn ($r) => $r->getOrdre() === 99), 'passage conservé');
    }

    #[TestDox('ADJ-08 Une phase dont une poule a des lignes de classement, même à zéro, est commencée : figée, et les passages qui y mènent ne se modifient plus')]
    public function test_classement_a_zero(): void
    {
        $poule = $this->pouleEnPhase2();
        \App\Factory\ClassementFactory::createOne(['poule' => $this->em()->find(\App\Entity\Poule::class, $poule->getId()),
            'equipe' => ChampionnatDeTest::get('equipe.A1'), 'points' => 0, 'setGagnes' => 0, 'setPerdus' => 0, 'position' => 1]);

        $crawler = $this->client->request('GET', $this->url(3));
        self::assertCount(0, $crawler->filter('input[name$="[nom]"][name^="phases_wizard[phases]"]'), 'aucune phase modifiable');
        self::assertCount(2, $crawler->filter('li:contains("ne se modifie plus")'));

        $crawler = $this->client->request('GET', $this->url(4));
        self::assertSelectorTextContains('body', 'Les passages de la phase « Phase 1 » ne se modifient plus');
        self::assertCount(0, $crawler->filter('h3:contains("Phase 1 →")'));
    }

    #[TestDox('ADJ-09 Phase 1 commencée mais pas clôturée : ses passages restent modifiables, vers les matchs d\'un barrage ajouté entre la phase 1 et la phase 2')]
    public function test_passages_phase_en_cours_vers_barrage(): void
    {
        $this->envoyerPhases(action: 'ajouter-phase');
        $this->envoyerPhases(['phases_wizard' => ['phases' => [1 => ['nom' => 'Barrages', 'type' => (string) PhaseType::BARRAGE->value]]]], 'monter-phase-1');
        $this->envoyerPhases(action: 'ajouter-poule-0'); // un match dans les barrages, désormais première phase libre
        $match = $this->phase('Barrages')->getPoules()->first();

        $crawler = $this->client->request('GET', $this->url(4));
        self::assertCount(1, $crawler->filter('h3:contains("Phase 1 → Barrages")'), 'passages de la phase 1 affichés');
        $pouleA = $this->phase('Phase 1')->getPoules()->filter(fn ($p) => $p->getNom() === 'Poule A')->first();

        // Le 3e de la poule A va dans le match de barrage
        $valeurs = $crawler->filter('#wizard-form')->form()->getPhpValues();
        $valeurs['p'][$pouleA->getId()] = ['mode' => 'detail', 'r' => [['du' => '3', 'au' => '3', 'dest' => 'p:'.$match->getId()]]];
        $this->client->request('POST', $this->url(4), $valeurs + ['aller' => 'suivant']);
        self::assertResponseRedirects($this->url(6));

        $destinations = array_map(fn ($r) => $r->getPouleDestination()?->getNom(), $this->phase('Phase 1')->getReglesDePassage()->toArray());
        self::assertContains($match->getNom(), $destinations);
    }

    #[TestDox('ADJ-06 Navigation du mode ajout : Passages → Vérification (sans Classement), Vérification → Passages')]
    public function test_navigation(): void
    {
        $jeton = $this->client->request('GET', $this->url(4))->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', $this->url(4), ['_token' => $jeton, 'aller' => 'suivant']);
        self::assertResponseRedirects($this->url(6));

        $this->client->request('POST', $this->url(6), ['_token' => $jeton, 'aller' => 'precedent']);
        self::assertResponseRedirects($this->url(4));
    }
}
