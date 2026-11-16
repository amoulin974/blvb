<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Competition\ResultatsSaison;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;
use App\Factory\EquipeFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Repository\ClassementRepository;
use App\Service\PhaseService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BAR-01 à BAR-05 : phase de barrages (D42, D43) : un match sec par poule de deux équipes, reçu selon l'option de la phase,
 * dont le vainqueur et le perdant alimentent les règles de passage (« BAR.AB.vainqueur »).
 */
final class BarrageTest extends TestIntegration
{
    private object $saison;
    private object $barrage;
    private object $pouleAB;
    private object $pouleA;
    private object $pouleB;
    private object $p2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saison = SaisonFactory::createOne(['nom' => 'Saison barrages', 'favori' => 1]);
        $p1 = PhaseFactory::createOne(['saison' => $this->saison, 'nom' => 'Phase 1', 'code' => 'P1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT]);
        $this->barrage = PhaseFactory::createOne(['saison' => $this->saison, 'nom' => 'Barrages', 'code' => 'BAR', 'ordre' => 2, 'type' => PhaseType::BARRAGE,
            'datedebut' => new \DateTimeImmutable('2027-02-01'), 'datefin' => new \DateTimeImmutable('2027-02-07')]);
        $phase2 = PhaseFactory::createOne(['saison' => $this->saison, 'nom' => 'Phase 2', 'code' => 'P2', 'ordre' => 3, 'type' => PhaseType::CHAMPIONNAT]);

        $this->pouleA = PouleFactory::createOne(['phase' => $p1, 'nom' => 'Poule A', 'code' => 'A', 'niveau' => 1, 'equipes' => $this->equipes('A')]);
        $this->pouleB = PouleFactory::createOne(['phase' => $p1, 'nom' => 'Poule B', 'code' => 'B', 'niveau' => 2, 'equipes' => $this->equipes('B')]);
        $this->pouleAB = PouleFactory::createOne(['phase' => $this->barrage, 'nom' => 'Barrage AB', 'code' => 'AB', 'niveau' => 1, 'equipes' => []]);
        $this->p2 = PouleFactory::createOne(['phase' => $phase2, 'nom' => 'Poule A', 'code' => 'A', 'niveau' => 1, 'equipes' => []]);

        $this->regles($p1, [['P1.A#-2', $this->pouleAB], ['P1.B#2', $this->pouleAB]]);
        $this->regles($this->barrage, [['BAR.AB.vainqueur', $this->p2]]);
    }

    /** @return list<object> */
    private function equipes(string $lettre): array
    {
        return array_map(fn (string $n) => EquipeFactory::createOne(['nom' => "$lettre$n", 'lieu' => null]), ['1', '2', '3', '4']);
    }

    private function regles(object $phase, array $regles): void
    {
        $em = self::service('doctrine')->getManager();
        $p = $em->find(Phase::class, $phase->getId());
        foreach ($regles as $i => [$source, $destination]) {
            $p->addRegleDePassage((new RegleDePassage())->setOrdre($i + 1)->setSource($source)
                ->setPouleDestination($em->find(Poule::class, $destination->getId())));
        }
        $em->flush();
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    /** Clôture la phase 1 : l'avant-dernier de la poule A (A3) et le 2e de la poule B (B2) arrivent dans le barrage. */
    private function cloturerPhase1(): void
    {
        $em = $this->em();
        $em->clear();
        $phase1 = $em->getRepository(Phase::class)->findOneBy(['saison' => $this->saison->getId(), 'code' => 'P1']);
        self::service(PhaseService::class)->cloturerEtBasculer($phase1);
        $em->clear();
    }

    private function genererLeBarrage(): ?Partie
    {
        $em = $this->em();
        $poule = $em->find(Poule::class, $this->pouleAB->getId());
        $generateur = self::service(Formats::class)->pourPhase($poule->getPhase());
        self::assertNull($generateur->creerJournees($poule));
        $em->clear();
        $poule = $em->find(Poule::class, $this->pouleAB->getId());
        $generateur->genererMatchs($poule);
        $em->clear();

        return $em->find(Poule::class, $this->pouleAB->getId())->getParties()->first() ?: null;
    }

    #[TestDox('BAR-01 Les équipes des règles de passage arrivent dans le barrage ; une journée et un match sont créés')]
    public function test_generation(): void
    {
        $this->cloturerPhase1();

        $poule = $this->em()->find(Poule::class, $this->pouleAB->getId());
        $noms = array_map(fn ($e) => $e->getNomSansClub(), $poule->getEquipes()->toArray());
        sort($noms);
        self::assertSame(['A3', 'B2'], $noms);

        $partie = $this->genererLeBarrage();
        self::assertNotNull($partie);
        self::assertCount(1, $this->em()->find(Poule::class, $this->pouleAB->getId())->getJournees());
        self::assertSame(1, $this->em()->find(Poule::class, $this->pouleAB->getId())->getParties()->count());
    }

    #[TestDox('BAR-02 (D43) Le match est reçu par l\'équipe de la poule de niveau supérieur')]
    public function test_reception_niveau_superieur(): void
    {
        $this->cloturerPhase1();
        $partie = $this->genererLeBarrage();

        self::assertSame('A3', $partie->getIdEquipeRecoit()->getNomSansClub());
        self::assertSame('B2', $partie->getIdEquipeDeplace()->getNomSansClub());
    }

    #[TestDox('BAR-03 Un barrage de plus de deux équipes est refusé avec un message clair, sans journée créée')]
    public function test_refus_hors_deux_equipes(): void
    {
        $em = $this->em();
        $poule = $em->find(Poule::class, $this->pouleAB->getId());
        $generateur = self::service(Formats::class)->pourPhase($poule->getPhase());

        $message = $generateur->creerJournees($poule);

        self::assertStringContainsString('exactement deux équipes', (string) $message);
        self::assertSame(0, $generateur->nombreMatchs($poule));
    }

    #[TestDox('BAR-04 Le vainqueur n\'est connu qu\'une fois le score saisi ; un forfait donne la victoire à l\'autre équipe')]
    public function test_issue_du_match(): void
    {
        $this->cloturerPhase1();
        $partie = $this->genererLeBarrage();
        $em = $this->em();
        $resultats = fn () => new ResultatsSaison($em->find(\App\Entity\Saison::class, $this->saison->getId()), self::service(ClassementRepository::class));

        self::assertNull($resultats()->issue('BAR', 'AB', 'vainqueur'), 'avant le score');

        $partie = $em->find(Partie::class, $partie->getId());
        $partie->setNbSetGagnantReception(1)->setNbSetGagnantDeplacement(3);
        $em->flush();
        self::assertSame('B2', $resultats()->issue('BAR', 'AB', 'vainqueur')->getNomSansClub());
        self::assertSame('A3', $resultats()->issue('BAR', 'AB', 'perdant')->getNomSansClub());

        $partie->setNbSetGagnantReception(-1)->setNbSetGagnantDeplacement(3);
        $em->flush();
        self::assertSame('B2', $resultats()->issue('BAR', 'AB', 'vainqueur')->getNomSansClub(), 'forfait de l\'équipe qui reçoit');
    }

    #[TestDox('BAR-05 La clôture du barrage envoie le vainqueur dans la poule désignée par la règle')]
    public function test_cloture_du_barrage(): void
    {
        $this->cloturerPhase1();
        $partie = $this->genererLeBarrage();
        $em = $this->em();
        $em->find(Partie::class, $partie->getId())->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(0);
        $em->flush();
        $em->clear();

        $barrage = $em->getRepository(Phase::class)->findOneBy(['saison' => $this->saison->getId(), 'code' => 'BAR']);
        $avertissements = self::service(PhaseService::class)->cloturerEtBasculer($barrage);
        $em->clear();

        $noms = array_map(fn ($e) => $e->getNomSansClub(), $em->find(Poule::class, $this->p2->getId())->getEquipes()->toArray());
        self::assertSame(['A3'], $noms, 'le vainqueur (A3, qui recevait) joue la poule supérieure');
        self::assertSame(1, $em->find(Phase::class, $this->barrage->getId())->getClose());
        self::assertStringContainsString('B2', implode(' | ', $avertissements), 'le perdant n\'a pas de destination : il est signalé');
    }
}
