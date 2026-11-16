<?php

namespace App\Tests\Integration\Service;

use App\Competition\AjoutBarrages;
use App\Competition\Modele\ModeleBlvbActuel;
use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Entity\Phase;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\SaisonFactory;
use App\Service\CompetitionCreator;
use App\Tests\Support\TestIntegration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BAR-11 : barrages ajoutés à une saison créée sans eux (App\Competition\AjoutBarrages, commande
 * app:saison:ajouter-barrages) : même structure et mêmes passages que le modèle « BLVB avec barrages » (D44, D50).
 */
final class AjoutBarragesTest extends TestIntegration
{
    private function em(): EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    private function saison(object $modele): int
    {
        $id = SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('2026-09-01'), 'date_fin' => new \DateTimeImmutable('2027-07-31')])->getId();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($this->em()->find(Saison::class, $id), false, $modele);
        $this->em()->clear();

        return $id;
    }

    /** @return array<string, list<string>> code de phase → règles « source → poule (phase) », triées */
    private function passages(int $saison): array
    {
        $this->em()->clear();
        $passages = [];
        foreach ($this->em()->find(Saison::class, $saison)->getPhases() as $phase) {
            $regles = array_map(fn (RegleDePassage $r) => $r->getSource().' → '.$r->getPouleDestination()?->getCode().' ('.$r->getPouleDestination()?->getPhase()?->getCode().')',
                $phase->getReglesDePassage()->toArray());
            sort($regles);
            $passages[$phase->getCode()] = $regles;
        }

        return $passages;
    }

    #[TestDox('BAR-11 Saison au format actuel : barrages entre les phases et de fin de saison ajoutés comme dans le modèle ; une seconde fois : refusé')]
    public function test_comme_le_modele(): void
    {
        $id = $this->saison(new ModeleBlvbActuel());
        $resultat = self::service(AjoutBarrages::class)->ajouter($this->em()->find(Saison::class, $id), true, true);
        self::assertSame([], $resultat['refus']);
        self::assertCount(4, $resultat['actions']);
        $this->em()->flush();

        $this->em()->clear();
        $saison = $this->em()->find(Saison::class, $id);
        self::assertSame(['P1', 'BAR', 'P2', 'F', 'BAR2'], array_map(fn (Phase $p) => $p->getCode(), $saison->getPhases()->toArray()));
        self::assertSame(['Barrage A / B', 'Barrage B / C', 'Barrage C / D'], array_map(fn ($p) => $p->getNom(), $saison->getPhases()[1]->getPoules()->toArray()));

        $attendu = $this->passages($this->saison(new ModeleBlvbAvecBarrages()));
        $obtenu = $this->passages($id);
        self::assertSame($attendu['P1'], $obtenu['P1']);
        self::assertSame($attendu['BAR'], $obtenu['BAR']);
        // Phase 2 : les passages du modèle vers les barrages de fin de saison ; ceux vers la phase finale restent ceux de la saison
        $versFin = fn (array $regles) => array_values(array_filter($regles, fn (string $r) => str_ends_with($r, '(BAR2)')));
        self::assertSame($versFin($attendu['P2']), $versFin($obtenu['P2']));

        $encore = self::service(AjoutBarrages::class)->ajouter($this->em()->find(Saison::class, $id), true, true);
        self::assertSame([], $encore['actions']);
        self::assertCount(2, $encore['refus']);
    }

    #[TestDox('BAR-11 Phase 2 commencée (équipes placées) : barrages entre les phases refusés, barrages de fin de saison possibles')]
    public function test_phase_2_commencee(): void
    {
        $id = $this->saison(new ModeleBlvbActuel());
        $phase2 = $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $id, 'code' => 'P2']);
        $phase2->getPoules()->first()->addEquipe(EquipeFactory::createOne(['lieu' => null]));
        $this->em()->flush();
        $this->em()->clear();

        $resultat = self::service(AjoutBarrages::class)->ajouter($this->em()->find(Saison::class, $id), true, true);
        self::assertCount(1, $resultat['refus']);
        self::assertStringContainsString('a commencé', $resultat['refus'][0]);
        self::assertCount(2, $resultat['actions']);
        self::assertStringContainsString('Barrages de fin de saison', $resultat['actions'][0]);
    }
}
