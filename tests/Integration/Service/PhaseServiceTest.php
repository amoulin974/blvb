<?php

namespace App\Tests\Integration\Service;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Factory\EquipeFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\PhaseService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * PHA-01, PHA-02, PHA-05 : clôture d'une phase et répartition des équipes dans la phase suivante
 * (PhaseService::cloturerEtBasculer). Sans score saisi, le classement suit l'ordre alphabétique (départage).
 */
final class PhaseServiceTest extends TestIntegration
{
    private object $saison;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saison = SaisonFactory::createOne(['nom' => 'Saison phases', 'favori' => 1]);
    }

    private function phase(int $ordre): object
    {
        return PhaseFactory::createOne(['saison' => $this->saison, 'nom' => "Phase $ordre", 'ordre' => $ordre]);
    }

    /** Poule de niveau $niveau avec les équipes « N<niveau> a » à « N<niveau> d » (1 montée, 1 descente). */
    private function poule(object $phase, int $niveau, bool $avecEquipes = true): object
    {
        $equipes = [];
        foreach ($avecEquipes ? ['a', 'b', 'c', 'd'] : [] as $lettre) {
            $equipes[] = EquipeFactory::createOne(['nom' => "N$niveau $lettre", 'lieu' => null]);
        }

        return PouleFactory::createOne(['phase' => $phase, 'nom' => "Niveau $niveau", 'niveau' => $niveau,
            'nb_montee_defaut' => 1, 'nb_descente_defaut' => 1, 'equipes' => $equipes]);
    }

    private function cloturer(object $phase): void
    {
        $em = self::service('doctrine')->getManager();
        $em->clear(); // collections inverses (phases, poules, classements) relues depuis la base
        self::service(PhaseService::class)->cloturerEtBasculer($em->find(Phase::class, $phase->getId()));
        $em->clear();
    }

    /** @return list<string> noms des équipes de la poule, triés */
    private function equipes(object $poule): array
    {
        $noms = array_map(fn ($e) => $e->getNomSansClub(), self::service('doctrine')->getManager()->find(Poule::class, $poule->getId())->getEquipes()->toArray());
        sort($noms);

        return $noms;
    }

    #[TestDox('PHA-01 PHA-02 Le premier monte, le dernier descend ; pas de montée au niveau le plus haut ni de descente au plus bas')]
    public function test_montees_descentes(): void
    {
        $phase1 = $this->phase(1);
        foreach ([1, 2, 3] as $niveau) {
            $this->poule($phase1, $niveau);
        }
        $phase2 = $this->phase(2);
        $dest = [];
        foreach ([1, 2, 3] as $niveau) {
            $dest[$niveau] = $this->poule($phase2, $niveau, false);
        }

        $this->cloturer($phase1);

        self::assertSame(['N1 a', 'N1 b', 'N1 c', 'N2 a'], $this->equipes($dest[1]));
        self::assertSame(['N1 d', 'N2 b', 'N2 c', 'N3 a'], $this->equipes($dest[2]));
        self::assertSame(['N2 d', 'N3 b', 'N3 c', 'N3 d'], $this->equipes($dest[3]));
        self::assertSame(1, self::service('doctrine')->getManager()->find(Phase::class, $phase1->getId())->getClose(), 'phase close');
    }

    #[TestDox('PHA-01 Niveau absent dans la phase suivante : l\'équipe va dans la première poule disponible')]
    public function test_repli_premiere_poule(): void
    {
        $phase1 = $this->phase(1);
        $this->poule($phase1, 1);
        $unique = $this->poule($this->phase(2), 5, false);

        $this->cloturer($phase1);

        self::assertSame(['N1 a', 'N1 b', 'N1 c', 'N1 d'], $this->equipes($unique));
    }

    #[TestDox('PHA-01 La phase suivante est celle d\'ordre immédiatement supérieur, quel que soit l\'ordre de création')]
    public function test_phase_suivante(): void
    {
        $phase1 = $this->phase(1);
        $phase3 = $this->phase(3);
        $phase2 = $this->phase(2);
        $service = self::service(PhaseService::class);
        $em = self::service('doctrine')->getManager();

        self::assertSame($phase2->getId(), $service->phaseSuivante($em->find(Phase::class, $phase1->getId()))->getId());
        self::assertNull($service->phaseSuivante($em->find(Phase::class, $phase3->getId())), 'dernière phase');
    }

    #[TestDox('PHA-05 Clôturer une phase déjà close est sans effet : les équipes ne sont pas redistribuées')]
    public function test_double_cloture_sans_effet(): void
    {
        $phase1 = $this->phase(1);
        $this->poule($phase1, 1);
        $dest = $this->poule($this->phase(2), 1, false);
        $this->cloturer($phase1);
        $avant = $this->equipes($dest);

        $em = self::service('doctrine')->getManager();
        $poule = $em->find(Poule::class, $dest->getId());
        foreach ($poule->getEquipes()->toArray() as $equipe) {
            $poule->removeEquipe($equipe);
        }
        $em->flush();
        $em->clear();
        $this->cloturer($phase1);

        self::assertNotSame([], $avant);
        self::assertSame([], $this->equipes($dest), 'aucune équipe remise dans la phase suivante');
    }
}
