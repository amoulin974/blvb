<?php

namespace App\Tests\Integration\Service;

use App\Entity\Partie;
use App\Entity\Phase;
use App\Factory\CreneauFactory;
use App\Factory\EquipeFactory;
use App\Factory\LieuFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\JourneeService;
use App\Service\PartieService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * MAT-05 : optimiseur du calendrier d'une phase (PartieService::creerCalendrierOptimise), sur 4 poules de 8 équipes
 * qui partagent 8 gymnases (une équipe de chaque poule par gymnase). Test lent : exécuté la nuit en CI.
 */
#[Group('lent')]
final class OptimiseurTest extends TestIntegration
{
    private function phase(int $capacite): int
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison optimiseur', 'favori' => 1]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'datedebut' => new \DateTimeImmutable('2026-09-07'), 'datefin' => new \DateTimeImmutable('2027-01-31')]);
        $gymnases = [];
        for ($g = 1; $g <= 8; ++$g) {
            $gymnases[$g] = LieuFactory::createOne(['nom' => "Gymnase $g"]);
            CreneauFactory::createOne(['lieu' => $gymnases[$g], 'jourSemaine' => 3, 'capacite' => $capacite, 'prioritaire' => 1, 'heureDebut' => new \DateTimeImmutable('20:30')]);
        }
        $em = self::service('doctrine')->getManager();
        foreach (['A', 'B', 'C', 'D'] as $n => $lettre) {
            $equipes = [];
            for ($i = 1; $i <= 8; ++$i) {
                $equipes[] = EquipeFactory::createOne(['nom' => "$lettre$i", 'lieu' => $gymnases[$i]]);
            }
            $poule = PouleFactory::createOne(['phase' => $phase, 'nom' => "Poule $lettre", 'niveau' => $n + 1, 'equipes' => $equipes]);
            self::service(JourneeService::class)->creerJournees($em->find(\App\Entity\Poule::class, $poule->getId()));
        }
        $em->clear();

        return $phase->getId();
    }

    private function generer(int $phase): array
    {
        $em = self::service('doctrine')->getManager();
        $rapport = self::service(PartieService::class)->creerCalendrierOptimise($em->find(Phase::class, $phase));
        $em->clear();
        $matchs = array_map(
            fn (Partie $m) => sprintf('%s>%s@%s', $m->getIdEquipeRecoit()->getNomSansClub(), $m->getIdEquipeDeplace()->getNomSansClub(), $m->getDate()->format('Y-m-d')),
            $em->getRepository(Partie::class)->findAll()
        );
        sort($matchs);

        return [$rapport, $matchs];
    }

    #[TestDox('MAT-05 Capacité suffisante : aucune surcharge ; résultat identique sur deux exécutions ; mémoire raisonnable')]
    public function test_capacite_suffisante(): void
    {
        $phase = $this->phase(4);
        [$rapport, $premier] = $this->generer($phase);
        self::assertCount(4 * 28, $premier);
        self::assertSame([], array_merge(...array_column($rapport, 'surcharges')));

        [, $second] = $this->generer($phase);
        self::assertSame($premier, $second, 'déterministe');
        self::assertLessThan(512 * 1024 * 1024, memory_get_peak_usage(true), 'moins de 512 Mo');
    }

    #[TestDox('MAT-05 Capacité insuffisante : les surcharges sont rapportées, sans exception')]
    public function test_capacite_insuffisante(): void
    {
        [$rapport, $matchs] = $this->generer($this->phase(1));
        self::assertCount(4 * 28, $matchs, 'tous les matchs sont créés');
        self::assertNotSame([], array_merge(...array_column($rapport, 'surcharges')), 'surcharges signalées');
    }
}
