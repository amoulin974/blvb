<?php

namespace App\Tests\Integration\Service;

use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\SaisonFactory;
use App\Service\CompetitionCreator;
use App\Service\PhaseService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BAR-07 à BAR-09 : barrages de fin de saison du modèle « BLVB avec barrages » (D50). La phase 2 alimente le tableau
 * final comme avant et, en plus, les barrages placés après la phase finale ; la phase finale, terminale, se clôture sans
 * rien répartir ; rien n'est enregistré pour la saison suivante.
 */
final class BarragesFinSaisonTest extends TestIntegration
{
    private int $saisonId;

    protected function setUp(): void
    {
        parent::setUp();
        $saison = SaisonFactory::createOne(['nom' => 'Saison barrages de fin', 'favori' => 1, 'date_debut' => new \DateTimeImmutable('2026-09-01'),
            'date_fin' => new \DateTimeImmutable('2027-07-31')]);
        $this->saisonId = $saison->getId();
        $em = $this->em();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($em->find(Saison::class, $this->saisonId), false, new ModeleBlvbAvecBarrages());
        $em->clear();

        // 8 équipes par poule de la phase 2, classées par ordre alphabétique (aucun score saisi) : A1 … A8, B1 … B8, …
        foreach ($this->phase('P2')->getPoules() as $poule) {
            foreach (range(1, 8) as $n) {
                $poule->addEquipe(EquipeFactory::createOne(['nom' => $poule->getCode().$n, 'lieu' => null]));
            }
        }
        $em->flush();
        $em->clear();
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    private function phase(string $code): Phase
    {
        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $this->saisonId, 'code' => $code]);
    }

    /** @return list<string> noms des équipes de la poule, triés */
    private function equipes(string $phase, string $poule): array
    {
        $noms = [];
        foreach ($this->phase($phase)->getPoules() as $p) {
            if ($p->getCode() === $poule) {
                $noms = array_map(fn ($e) => $e->getNomSansClub(), $p->getEquipes()->toArray());
            }
        }
        sort($noms);

        return $noms;
    }

    #[TestDox('BAR-07 Clôture de la phase 2 : toute la poule va au tableau de son niveau, et l\'avant-dernier du haut et le 2e du bas dans un barrage de fin de saison')]
    public function test_cloture_phase_2(): void
    {
        $avertissements = self::service(PhaseService::class)->cloturerEtBasculer($this->phase('P2'));
        $this->em()->clear();

        self::assertSame([], $avertissements);
        self::assertSame(['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8'], $this->equipes('F', 'A'));
        self::assertSame(['D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8'], $this->equipes('F', 'D'));
        self::assertSame(['A7', 'B2'], $this->equipes('BAR2', 'AB'));
        self::assertSame(['B7', 'C2'], $this->equipes('BAR2', 'BC'));
        self::assertSame(['C7', 'D2'], $this->equipes('BAR2', 'CD'));
        self::assertSame(1, $this->phase('P2')->getClose());
    }

    #[TestDox('BAR-08 Une équipe de barrage reste dans son tableau final : les barrages ne changent pas la composition de la phase finale')]
    public function test_barrage_sans_effet_sur_la_finale(): void
    {
        self::service(PhaseService::class)->cloturerEtBasculer($this->phase('P2'));
        $this->em()->clear();

        foreach (['A7', 'B2'] as $nom) {
            $presentes = [];
            foreach ($this->phase('F')->getPoules() as $poule) {
                foreach ($poule->getEquipes() as $equipe) {
                    if ($equipe->getNomSansClub() === $nom) {
                        $presentes[] = $poule->getCode();
                    }
                }
            }
            self::assertSame([$nom[0]], $presentes, "$nom joue dans le tableau de sa poule d'origine");
        }
        $effectifs = array_map(fn ($p) => \count($p->getEquipes()), $this->phase('F')->getPoules()->toArray());
        self::assertSame([8, 8, 8, 8], $effectifs);
    }

    #[TestDox('BAR-09 La phase finale se clôture sans rien répartir ni avertir, même suivie des barrages de fin de saison ; les barrages restent intacts')]
    public function test_cloture_phase_finale_terminale(): void
    {
        $service = self::service(PhaseService::class);
        $service->cloturerEtBasculer($this->phase('P2'));
        $this->em()->clear();
        $finale = $this->phase('F');

        self::assertTrue($service->estTerminale($finale));
        self::assertSame([], $service->problemesAvantCloture($finale), 'rien à signaler avant la clôture');
        self::assertSame([], $service->cloturerEtBasculer($finale));
        $this->em()->clear();

        self::assertSame(1, $this->phase('F')->getClose());
        self::assertSame(['A7', 'B2'], $this->equipes('BAR2', 'AB'), 'barrages inchangés');
        self::assertSame(0, $this->phase('BAR2')->getClose());
        self::assertFalse($service->estTerminale($this->phase('P2')), 'seule une phase finale sans règle est terminale');
    }
}
