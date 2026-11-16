<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Factory\CreneauFactory;
use App\Factory\EquipeFactory;
use App\Factory\LieuFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\JourneeService;
use App\Service\PartieService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * ALR-01 à ALR-05 : championnat aller-retour (lot 6f-2, A2, D47). Le retour reprend les journées de l'aller dans le même
 * ordre, domicile et extérieur inversés ; l'optimiseur place l'aller et en déduit le retour. Chaque équipe a son gymnase
 * (mercredi 20:30, vendredi 21:00), comme dans GenerationMatchsTest.
 */
final class AllerRetourTest extends TestIntegration
{
    private object $phase;

    protected function setUp(): void
    {
        parent::setUp();
        $saison = SaisonFactory::createOne(['nom' => 'Saison aller-retour', 'favori' => 1]);
        $this->phase = PhaseFactory::createOne(['saison' => $saison, 'datedebut' => new \DateTimeImmutable('2026-09-07'), 'datefin' => new \DateTimeImmutable('2027-03-31'),
            'options' => ['aller_retour' => true]]);
    }

    private function equipe(string $nom): object
    {
        $lieu = LieuFactory::createOne(['nom' => 'Gymnase '.$nom]);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 3, 'capacite' => 2, 'prioritaire' => 1, 'heureDebut' => new \DateTimeImmutable('20:30')]);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 5, 'capacite' => 2, 'prioritaire' => 2, 'heureDebut' => new \DateTimeImmutable('21:00')]);

        return EquipeFactory::createOne(['nom' => $nom, 'lieu' => $lieu]);
    }

    /** Poule de $nb équipes dans la phase donnée (ou celle du test), journées créées ; renvoie [poule, message d'erreur éventuel]. */
    private function poule(int $nb, ?object $phase = null): array
    {
        $equipes = [];
        for ($i = 1; $i <= $nb; ++$i) {
            $equipes[] = $this->equipe("E$i");
        }
        $poule = PouleFactory::createOne(['phase' => $phase ?? $this->phase, 'nom' => 'Poule AR', 'niveau' => 1, 'equipes' => $equipes]);
        $em = self::service('doctrine')->getManager();
        $erreur = self::service(JourneeService::class)->creerJournees($em->find(Poule::class, $poule->getId()));

        return [$poule, $erreur];
    }

    private function generer(int $nb): Poule
    {
        [$poule] = $this->poule($nb);
        $em = self::service('doctrine')->getManager();
        self::service(PartieService::class)->creerCalendrierOptimisePourPoule($em->find(Poule::class, $poule->getId()));
        $em->clear();

        return $em->find(Poule::class, $poule->getId());
    }

    /** @return array<int, list<string>> numéro de journée => rencontres « domicile>extérieur », triées */
    private function rencontresParJournee(Poule $poule): array
    {
        $parJournee = [];
        foreach (self::service('doctrine')->getManager()->getRepository(Partie::class)->findBy(['poule' => $poule->getId()]) as $m) {
            $parJournee[$m->getJournee()->getNumero()][] = $m->getIdEquipeRecoit()->getNom().'>'.$m->getIdEquipeDeplace()->getNom();
        }
        foreach ($parJournee as &$rencontres) {
            sort($rencontres);
        }
        ksort($parJournee);

        return $parJournee;
    }

    /** Vérifie que la journée $k + $nbAller reprend la journée $k, domicile et extérieur inversés. */
    private function assertMiroir(array $parJournee, int $nbAller): void
    {
        for ($k = 1; $k <= $nbAller; ++$k) {
            $attendu = array_map(fn (string $r) => implode('>', array_reverse(explode('>', $r))), $parJournee[$k] ?? []);
            sort($attendu);
            self::assertSame($attendu, $parJournee[$k + $nbAller] ?? [], "journée ".($k + $nbAller)." = journée $k inversée");
        }
    }

    public static function tailles(): iterable
    {
        yield '4 équipes' => [4, 6];
        yield '5 équipes (impair)' => [5, 10];
        yield '6 équipes' => [6, 10];
    }

    #[DataProvider('tailles')]
    #[TestDox('ALR-01 Deux fois plus de journées et de matchs ; chaque équipe reçoit chaque autre une fois, personne ne joue deux fois par journée : $_dataName')]
    public function test_double_round_robin(int $nb, int $journeesAttendues): void
    {
        $poule = $this->generer($nb);

        self::assertCount($journeesAttendues, $poule->getJournees(), 'journées : 2(n-1), ou 2n si n est impair');
        $parJournee = $this->rencontresParJournee($poule);
        $toutes = array_merge(...array_values($parJournee));
        self::assertCount($nb * ($nb - 1), $toutes, 'n(n-1) matchs');
        self::assertSame(\count($toutes), \count(array_unique($toutes)), 'chaque rencontre ordonnée (domicile>extérieur) apparaît une seule fois');

        foreach ($parJournee as $numero => $rencontres) {
            $equipes = array_merge(...array_map(fn (string $r) => explode('>', $r), $rencontres));
            self::assertSame(\count($equipes), \count(array_unique($equipes)), "journée $numero : personne ne joue deux fois");
        }
    }

    #[DataProvider('tailles')]
    #[TestDox('ALR-02 Le retour reprend les journées de l\'aller dans le même ordre, domicile et extérieur inversés : $_dataName')]
    public function test_retour_en_miroir(int $nb, int $journeesAttendues): void
    {
        $this->assertMiroir($this->rencontresParJournee($this->generer($nb)), intdiv($journeesAttendues, 2));
    }

    #[TestDox('ALR-03 Chaque équipe reçoit autant qu\'elle se déplace, et les dates du retour suivent celles de l\'aller')]
    public function test_equilibre_et_dates(): void
    {
        $poule = $this->generer(4);
        $matchs = self::service('doctrine')->getManager()->getRepository(Partie::class)->findBy(['poule' => $poule->getId()]);

        $solde = [];
        foreach ($matchs as $m) {
            $solde[$m->getIdEquipeRecoit()->getNom()] = ($solde[$m->getIdEquipeRecoit()->getNom()] ?? 0) + 1;
            $solde[$m->getIdEquipeDeplace()->getNom()] = ($solde[$m->getIdEquipeDeplace()->getNom()] ?? 0) - 1;
            self::assertGreaterThanOrEqual($m->getJournee()->getDateDebut()->format('Y-m-d'), $m->getDate()->format('Y-m-d'));
            self::assertLessThanOrEqual($m->getJournee()->getDateFin()->format('Y-m-d'), $m->getDate()->format('Y-m-d'));
        }
        self::assertSame(array_fill_keys(array_keys($solde), 0), $solde, 'autant de réceptions que de déplacements');
    }

    #[TestDox('ALR-04 Le générateur annonce le double de journées et de matchs')]
    public function test_nombres_annonces(): void
    {
        [$poule] = $this->poule(5);
        $em = self::service('doctrine')->getManager();
        $generateur = self::service(Formats::class)->pourPhase($em->find(Phase::class, $this->phase->getId()));
        $poule = $em->find(Poule::class, $poule->getId());

        self::assertSame(20, $generateur->nombreMatchs($poule));
        self::assertSame(10, $generateur->nombreJournees($poule));
    }

    #[TestDox('ALR-05 Phase trop courte pour les journées de l\'aller et du retour : message, aucune journée créée')]
    public function test_phase_trop_courte(): void
    {
        $courte = PhaseFactory::createOne(['saison' => $this->phase->getSaison(), 'nom' => 'Phase courte', 'datedebut' => new \DateTimeImmutable('2026-09-07'),
            'datefin' => new \DateTimeImmutable('2026-10-04'), 'options' => ['aller_retour' => true]]);

        [$poule, $erreur] = $this->poule(4, $courte);

        self::assertNotNull($erreur);
        self::assertStringContainsString('trop courte', $erreur);
        self::assertCount(0, self::service('doctrine')->getManager()->find(Poule::class, $poule->getId())->getJournees());
    }

    #[TestDox('ALR-06 Replacer les matchs à venir conserve le match joué et garde le retour en miroir')]
    public function test_replacement_garde_le_miroir(): void
    {
        $poule = $this->generer(4);
        $em = self::service('doctrine')->getManager();
        $joue = $em->getRepository(Partie::class)->findOneBy(['poule' => $poule->getId()], ['id' => 'ASC']);
        $joue->setNbSetGagnantReception(3);
        $joue->setNbSetGagnantDeplacement(1);
        $em->flush();
        $identifiant = $joue->getId();
        $rencontre = $joue->getIdEquipeRecoit()->getNom().'>'.$joue->getIdEquipeDeplace()->getNom();
        $numero = $joue->getJournee()->getNumero();

        self::service(PartieService::class)->replacerMatchsAVenir($em->find(Phase::class, $this->phase->getId()), $em->find(Poule::class, $poule->getId()));
        $em->clear();

        $poule = $em->find(Poule::class, $poule->getId());
        $parJournee = $this->rencontresParJournee($poule);
        self::assertCount(12, array_merge(...array_values($parJournee)), 'les 12 matchs existent toujours');
        self::assertNotNull($em->find(Partie::class, $identifiant), 'le match joué est conservé');
        self::assertContains($rencontre, $parJournee[$numero], 'dans la même journée et le même sens');
        $this->assertMiroir($parJournee, 3);
    }
}
