<?php

namespace App\Tests\Integration\Service;

use App\Competition\Classement\CritereDepartage;
use App\Competition\CopieSaison;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Factory\EquipeFactory;
use App\Factory\IndisponibiliteFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Repository\ClassementRepository;
use App\Service\ClassementService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/** CPS-01 à CPS-03 : nouvelle saison par copie d'une saison existante (App\Competition\CopieSaison, D10, D22). */
final class CopieSaisonTest extends TestIntegration
{
    private object $source;
    private object $phase1;
    private object $pouleA;
    private object $pouleB;
    private object $tableauA;

    protected function setUp(): void
    {
        parent::setUp();
        // Saison 2025-2026 : P1 (championnat, poules A et B de 4 équipes), F (tableaux A et B)
        $this->source = SaisonFactory::createOne(['nom' => 'Saison source', 'date_debut' => new \DateTimeImmutable('2025-09-01'),
            'date_fin' => new \DateTimeImmutable('2026-07-31'), 'points_victoire_forte' => 7]);
        $this->phase1 = PhaseFactory::createOne(['saison' => $this->source, 'nom' => 'Phase 1', 'code' => 'P1', 'ordre' => 1,
            'datedebut' => new \DateTimeImmutable('2025-09-15'), 'datefin' => new \DateTimeImmutable('2026-02-01')]);
        $finale = PhaseFactory::createOne(['saison' => $this->source, 'nom' => 'Finale', 'code' => 'F', 'ordre' => 2, 'type' => PhaseType::FINALE,
            'datedebut' => new \DateTimeImmutable('2026-06-01'), 'datefin' => new \DateTimeImmutable('2026-06-30'), 'bareme' => ['victoire_forte' => 0]]);
        foreach (['A' => 1, 'B' => 2] as $code => $niveau) {
            $equipes = array_map(fn ($n) => EquipeFactory::createOne(['nom' => "$code$n", 'lieu' => null]), [1, 2, 3, 4]);
            $this->{'poule'.$code} = PouleFactory::createOne(['phase' => $this->phase1, 'nom' => "Poule $code", 'code' => $code, 'niveau' => $niveau, 'equipes' => $equipes]);
            $tableau = PouleFactory::createOne(['phase' => $finale, 'nom' => "Tableau $code", 'code' => $code, 'niveau' => $niveau]);
            if ($code === 'A') {
                $this->tableauA = $tableau;
            }
        }
        IndisponibiliteFactory::createOne(['saison' => $this->source, 'nom' => 'Noël', 'dateDebut' => new \DateTimeImmutable('2025-12-20'), 'dateFin' => new \DateTimeImmutable('2026-01-04')]);

        $em = self::service('doctrine')->getManager();
        $em->find(Saison::class, $this->source->getId())->setDepartage([CritereDepartage::POINTS, CritereDepartage::QUOTIENT_SETS]);
        $p1 = $em->find(Phase::class, $this->phase1->getId());
        $regles = [
            ['P1.A#1..-1', $this->tableauA, null], // vers le tableau A
            ['P1.A#1..3', null, 1], ['P1.A#-1', null, 2], // A : 3 restent, le dernier descend
            ['P1.B#1', null, 1], ['P1.B#2..-1', null, 2],  // B : le premier monte
        ];
        foreach ($regles as $i => [$source, $poule, $niveau]) {
            $p1->addRegleDePassage((new RegleDePassage())->setOrdre($i + 1)->setSource($source)
                ->setPouleDestination($poule ? $em->find(Poule::class, $poule->getId()) : null)->setNiveauSaisonSuivante($niveau));
        }
        $em->flush();
    }

    /** Clôt la phase 1 de la saison source, classements calculés (sans score : ordre alphabétique). */
    private function cloturerPhase1(): void
    {
        $em = self::service('doctrine')->getManager();
        foreach ([$this->pouleA, $this->pouleB] as $poule) {
            self::service(ClassementService::class)->mettreAJourClassementPoule($em->find(Poule::class, $poule->getId()));
        }
        $em->find(Phase::class, $this->phase1->getId())->setClose(1);
        $em->flush();
    }

    private function copier(): array
    {
        $em = self::service('doctrine')->getManager();
        $em->clear();
        // Construit à la main tant qu'aucun écran ne l'utilise (service retiré du conteneur de test)
        $copie = new CopieSaison($em, self::service(ClassementRepository::class));
        $resultat = $copie->copier($em->find(Saison::class, $this->source->getId()), 'Saison copie',
            new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2027-07-31'));
        $em->clear();

        return [$em->find(Saison::class, $resultat['saison']->getId()), $resultat['avertissements']];
    }

    /** @return list<string> noms des équipes de la poule, triés */
    private static function noms(Poule $poule): array
    {
        $noms = array_map(fn ($e) => $e->getNomSansClub(), $poule->getEquipes()->toArray());
        sort($noms);

        return $noms;
    }

    #[TestDox('CPS-01 Structure recopiée : sport, barème, départage, phases (dates décalées d\'un an, non closes), poules, codes, barème de phase, périodes sans match décalées')]
    public function test_structure(): void
    {
        $this->cloturerPhase1();
        [$copie] = $this->copier();

        self::assertSame(['volley', 7], [$copie->getSport(), $copie->getPointsVictoireForte()]);
        self::assertSame([CritereDepartage::POINTS, CritereDepartage::QUOTIENT_SETS, CritereDepartage::ALPHABETIQUE], $copie->getDepartage());
        $phases = $copie->getPhases()->toArray();
        usort($phases, fn ($a, $b) => $a->getOrdre() <=> $b->getOrdre());
        self::assertSame([['Phase 1', 'P1', 0, '2026-09-15'], ['Finale', 'F', 0, '2027-06-01']],
            array_map(fn (Phase $p) => [$p->getNom(), $p->getCode(), $p->getClose(), $p->getDateDebut()->format('Y-m-d')], $phases));
        self::assertSame(['victoire_forte' => 0], $phases[1]->getBareme());
        self::assertSame(['A' => 1, 'B' => 2], array_column(array_map(fn (Poule $p) => [$p->getCode(), $p->getNiveau()], $phases[0]->getPoules()->toArray()), 1, 0));
        $noel = $copie->getIndisponibilites()->first();
        self::assertSame(['Noël', '2026-12-20', '2027-01-04'], [$noel->getNom(), $noel->getDateDebut()->format('Y-m-d'), $noel->getDateFin()->format('Y-m-d')]);
        self::assertNotSame($this->source->getId(), $copie->getId());
    }

    #[TestDox('CPS-02 Règles de passage recopiées, rattachées aux poules de la nouvelle saison ; poules de la phase 1 pré-remplies par les montées et descentes de la saison copiée')]
    public function test_regles_et_pre_remplissage(): void
    {
        $this->cloturerPhase1();
        [$copie, $avertissements] = $this->copier();

        self::assertSame([], $avertissements);
        $phase1 = $copie->getPhases()->filter(fn (Phase $p) => $p->getCode() === 'P1')->first();
        $regles = $phase1->getReglesDePassage()->toArray();
        self::assertSame(['P1.A#1..-1', 'P1.A#1..3', 'P1.A#-1', 'P1.B#1', 'P1.B#2..-1'], array_map(fn ($r) => $r->getSource(), $regles));
        self::assertSame($copie->getId(), $regles[0]->getPouleDestination()->getPhase()->getSaison()->getId(), 'destination dans la nouvelle saison');
        self::assertSame('Tableau A', $regles[0]->getPouleDestination()->getNom());

        $poules = [];
        foreach ($phase1->getPoules() as $poule) {
            $poules[$poule->getCode()] = self::noms($poule);
        }
        self::assertSame(['A' => ['A1', 'A2', 'A3', 'B1'], 'B' => ['A4', 'B2', 'B3', 'B4']], $poules);
    }

    #[TestDox('CPS-03 Saison copiée pas encore terminée : structure recopiée, poules vides, avertissement')]
    public function test_saison_non_terminee(): void
    {
        [$copie, $avertissements] = $this->copier();

        self::assertCount(1, $avertissements);
        self::assertStringContainsString('« Phase 1 » de la saison « Saison source » n\'est pas clôturée', $avertissements[0]);
        foreach ($copie->getPhases() as $phase) {
            foreach ($phase->getPoules() as $poule) {
                self::assertSame([], self::noms($poule), $phase->getNom().' / '.$poule->getNom());
            }
        }
    }
}
