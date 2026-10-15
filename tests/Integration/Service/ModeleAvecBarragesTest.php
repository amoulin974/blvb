<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\SaisonFactory;
use App\Service\CompetitionCreator;
use App\Service\PhaseService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BAR-06 : parcours complet du modèle « BLVB avec barrages » (D44) : phase 1 de quatre poules de 8, clôture, barrages
 * joués, clôture des barrages : chaque poule de la phase 2 compte 8 équipes.
 */
final class ModeleAvecBarragesTest extends TestIntegration
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    private function phase(int $saisonId, string $code): Phase
    {
        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $saisonId, 'code' => $code]);
    }

    /** @return list<string> noms des équipes de la poule, triés */
    private function equipes(int $saisonId, string $phase, string $poule): array
    {
        $noms = [];
        foreach ($this->phase($saisonId, $phase)->getPoules() as $p) {
            if ($p->getCode() === $poule) {
                $noms = array_map(fn ($e) => $e->getNomSansClub(), $p->getEquipes()->toArray());
            }
        }
        sort($noms);

        return $noms;
    }

    #[TestDox('BAR-06 Phase 1, barrages joués, phase 2 : les poules se recomposent à 8 équipes selon les règles du modèle')]
    public function test_parcours_complet(): void
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison barrages', 'favori' => 1, 'date_debut' => new \DateTimeImmutable('2026-09-01'),
            'date_fin' => new \DateTimeImmutable('2027-07-31')]);
        $saisonId = $saison->getId();
        $em = $this->em();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($em->find(Saison::class, $saisonId), false, new ModeleBlvbAvecBarrages());
        $em->clear();

        // 8 équipes par poule de la phase 1, classées par ordre alphabétique (aucun score saisi) : A1 … A8, B1 … B8, …
        foreach ($this->phase($saisonId, 'P1')->getPoules() as $poule) {
            foreach (range(1, 8) as $n) {
                $poule->addEquipe(EquipeFactory::createOne(['nom' => $poule->getCode().$n, 'lieu' => null]));
            }
        }
        $em->flush();
        $em->clear();

        // Clôture de la phase 1 : les barrages reçoivent l'avant-dernier du haut et le 2e du bas
        self::service(PhaseService::class)->cloturerEtBasculer($this->phase($saisonId, 'P1'));
        $em->clear();
        self::assertSame(['A7', 'B2'], $this->equipes($saisonId, 'BAR', 'AB'));
        self::assertSame(['B7', 'C2'], $this->equipes($saisonId, 'BAR', 'BC'));
        self::assertSame(['C7', 'D2'], $this->equipes($saisonId, 'BAR', 'CD'));

        // Journées et matchs des barrages, puis scores : l'équipe qui reçoit (poule supérieure) gagne 3-0 à AB et BC,
        // celle qui se déplace gagne à CD
        $formats = self::service(Formats::class);
        foreach ($this->phase($saisonId, 'BAR')->getPoules() as $poule) {
            $generateur = $formats->pourPhase($poule->getPhase());
            self::assertNull($generateur->creerJournees($poule));
        }
        $em->clear();
        foreach ($this->phase($saisonId, 'BAR')->getPoules() as $poule) {
            $formats->pourPhase($poule->getPhase())->genererMatchs($poule);
        }
        $em->clear();
        foreach ($this->phase($saisonId, 'BAR')->getPoules() as $poule) {
            $partie = $poule->getParties()->first();
            self::assertNotFalse($partie, 'un match par barrage');
            $recoitGagne = $poule->getCode() !== 'CD';
            $partie->setNbSetGagnantReception($recoitGagne ? 3 : 1)->setNbSetGagnantDeplacement($recoitGagne ? 0 : 3);
        }
        $em->flush();
        $em->clear();

        // Clôture des barrages : vainqueur en haut, perdant en bas
        self::service(PhaseService::class)->cloturerEtBasculer($this->phase($saisonId, 'BAR'));
        $em->clear();

        $attendu = [
            'A' => ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'B1'],
            'B' => ['A8', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'C1'],
            'C' => ['B8', 'C2', 'C3', 'C4', 'C5', 'C6', 'D1', 'D2'], // D2 gagne à CD et monte
            'D' => ['C7', 'C8', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8'], // C7 perd à CD et descend
        ];
        foreach ($attendu as $poule => $equipes) {
            self::assertSame($equipes, $this->equipes($saisonId, 'P2', $poule), "Poule $poule de la phase 2");
        }
        self::assertSame(1, $this->phase($saisonId, 'BAR')->getClose());
    }
}
