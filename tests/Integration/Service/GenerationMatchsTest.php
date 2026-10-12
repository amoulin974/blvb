<?php

namespace App\Tests\Integration\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
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
 * MAT-01 à MAT-04, MAT-06 : génération des matchs d'une poule de championnat par la version optimisée qu'utilise
 * l'application (PartieService::creerCalendrierOptimisePourPoule, bouton « Générer les matchs »).
 * Chaque équipe a son gymnase : mercredi 20:30 (priorité 1) et vendredi 21:00 (priorité 2, le créneau préféré).
 */
final class GenerationMatchsTest extends TestIntegration
{
    private object $phase;

    protected function setUp(): void
    {
        parent::setUp();
        $saison = SaisonFactory::createOne(['nom' => 'Saison matchs', 'favori' => 1]);
        $this->phase = PhaseFactory::createOne(['saison' => $saison, 'datedebut' => new \DateTimeImmutable('2026-09-07'), 'datefin' => new \DateTimeImmutable('2027-01-31')]);
    }

    private function equipe(string $nom, bool $avecGymnase = true): object
    {
        $lieu = null;
        if ($avecGymnase) {
            $lieu = LieuFactory::createOne(['nom' => 'Gymnase '.$nom]);
            CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 3, 'capacite' => 2, 'prioritaire' => 1, 'heureDebut' => new \DateTimeImmutable('20:30')]);
            CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 5, 'capacite' => 2, 'prioritaire' => 2, 'heureDebut' => new \DateTimeImmutable('21:00')]);
        }

        return EquipeFactory::createOne(['nom' => $nom, 'lieu' => $lieu]);
    }

    /** Poule de $nb équipes, journées créées, matchs générés ; renvoie [poule, rapport]. */
    private function generer(int $nb, string $prefixe = 'E', array $sansGymnase = []): array
    {
        $equipes = [];
        for ($i = 1; $i <= $nb; ++$i) {
            $equipes[] = $this->equipe("$prefixe$i", !\in_array($i, $sansGymnase, true));
        }
        $poule = PouleFactory::createOne(['phase' => $this->phase, 'nom' => "Poule $prefixe", 'niveau' => \count($this->phase->getPoules()) + 1, 'equipes' => $equipes]);
        $em = self::service('doctrine')->getManager();
        self::assertNull(self::service(JourneeService::class)->creerJournees($em->find(Poule::class, $poule->getId())));
        $rapport = self::service(PartieService::class)->creerCalendrierOptimisePourPoule($em->find(Poule::class, $poule->getId()));
        $em->clear();

        return [$em->find(Poule::class, $poule->getId()), $rapport];
    }

    /** @return list<Partie> */
    private function matchs(Poule $poule): array
    {
        return self::service('doctrine')->getManager()->getRepository(Partie::class)->findBy(['poule' => $poule->getId()]);
    }

    public static function tailles(): iterable
    {
        yield '4 équipes' => [4];
        yield '5 équipes (impair)' => [5];
        yield '6 équipes' => [6];
    }

    #[DataProvider('tailles')]
    #[TestDox('MAT-01/02 Chaque paire se rencontre une fois, une équipe joue au plus une fois par journée, exemption unique, domicile/extérieur équilibrés : $_dataName')]
    public function test_round_robin(int $nb): void
    {
        [$poule, $rapport] = $this->generer($nb);
        $matchs = $this->matchs($poule);
        self::assertCount($nb * ($nb - 1) / 2, $matchs);

        $paires = $parJournee = $domicile = $exterieur = [];
        foreach ($matchs as $m) {
            $ids = [$m->getIdEquipeRecoit()->getId(), $m->getIdEquipeDeplace()->getId()];
            sort($ids);
            $paires[] = implode('-', $ids);
            foreach ($ids as $id) {
                $parJournee[$m->getJournee()->getId()][] = $id;
            }
            $domicile[$m->getIdEquipeRecoit()->getId()] = ($domicile[$m->getIdEquipeRecoit()->getId()] ?? 0) + 1;
            $exterieur[$m->getIdEquipeDeplace()->getId()] = ($exterieur[$m->getIdEquipeDeplace()->getId()] ?? 0) + 1;
        }
        self::assertSame(\count($paires), \count(array_unique($paires)), 'aucune paire en double');
        foreach ($parJournee as $journee => $equipes) {
            self::assertSame(\count($equipes), \count(array_unique($equipes)), "journée $journee : personne ne joue deux fois");
        }
        if ($nb % 2 === 1) {
            $exemptions = [];
            foreach ($poule->getEquipes() as $e) {
                foreach ($parJournee as $equipes) {
                    if (!\in_array($e->getId(), $equipes, true)) {
                        $exemptions[$e->getId()] = ($exemptions[$e->getId()] ?? 0) + 1;
                    }
                }
            }
            self::assertSame(array_fill_keys(array_keys($exemptions), 1), $exemptions, 'chaque équipe exempte une seule fois');
            self::assertCount($nb, $exemptions);
        }
        foreach ($poule->getEquipes() as $e) {
            self::assertLessThanOrEqual(1, abs(($domicile[$e->getId()] ?? 0) - ($exterieur[$e->getId()] ?? 0)), $e->getNom().' : domicile/extérieur');
        }
        self::assertSame([], $rapport['surcharges'], 'capacité suffisante : aucune surcharge');
        if ($nb % 2 === 0) {
            self::assertLessThanOrEqual($nb - 2, $rapport['nbBreaks'], 'breaks : au plus n-2 (minimum théorique pour n pair)');
        }
    }

    public static function derbys(): iterable
    {
        yield '3 équipes du même gymnase sur 5' => [3, 2];
        yield '4 équipes du même gymnase sur 6' => [4, 2];
        yield '4 équipes du même gymnase sur 7' => [4, 3];
        yield '5 équipes du même gymnase sur 7' => [5, 2];
        yield '2 équipes du même gymnase sur 6' => [2, 4];
    }

    #[DataProvider('derbys')]
    #[TestDox('MAT-02 Derbys (équipes du même gymnase) : comptés ni à domicile ni à l\'extérieur ; écart d\'au plus un match sur les autres : $_dataName')]
    public function test_derbys_ignores(int $nbPartage, int $nbAutres): void
    {
        $partage = LieuFactory::createOne(['nom' => 'Gymnase partagé']);
        CreneauFactory::createOne(['lieu' => $partage, 'jourSemaine' => 3, 'capacite' => 8, 'prioritaire' => 1, 'heureDebut' => new \DateTimeImmutable('20:30')]);
        $equipes = [];
        for ($i = 1; $i <= $nbPartage; ++$i) {
            $equipes[] = EquipeFactory::createOne(['nom' => "D$i", 'lieu' => $partage]);
        }
        for ($i = 1; $i <= $nbAutres; ++$i) {
            $equipes[] = $this->equipe("X$i");
        }
        $poule = PouleFactory::createOne(['phase' => $this->phase, 'nom' => 'Poule D', 'equipes' => $equipes]);
        $em = self::service('doctrine')->getManager();
        self::service(JourneeService::class)->creerJournees($em->find(Poule::class, $poule->getId()));
        self::service(PartieService::class)->creerCalendrierOptimisePourPoule($em->find(Poule::class, $poule->getId()));
        $em->clear();

        $solde = $derbys = [];
        foreach ($this->matchs($em->find(Poule::class, $poule->getId())) as $m) {
            $recoit = $m->getIdEquipeRecoit();
            $deplace = $m->getIdEquipeDeplace();
            if ($recoit->getLieu()?->getId() === $deplace->getLieu()?->getId()) {
                $derbys[] = $m;
                continue;
            }
            $solde[$recoit->getNomSansClub()] = ($solde[$recoit->getNomSansClub()] ?? 0) + 1;
            $solde[$deplace->getNomSansClub()] = ($solde[$deplace->getNomSansClub()] ?? 0) - 1;
        }
        self::assertCount($nbPartage * ($nbPartage - 1) / 2, $derbys);
        foreach ($solde as $nom => $ecart) {
            self::assertLessThanOrEqual(1, abs($ecart), "$nom : écart domicile/extérieur hors derbys");
        }
    }

    #[TestDox('MAT-03 Le match a lieu au gymnase de l\'équipe qui reçoit, au jour et à l\'heure de son créneau préféré, dans la semaine de la journée')]
    public function test_date_et_lieu(): void
    {
        [$poule] = $this->generer(4);
        foreach ($this->matchs($poule) as $m) {
            self::assertSame($m->getIdEquipeRecoit()->getLieu()->getId(), $m->getLieu()?->getId());
            self::assertSame('5', $m->getDate()->format('N'), 'vendredi (priorité 2)');
            self::assertSame('21:00', $m->getDate()->format('H:i'));
            self::assertGreaterThanOrEqual($m->getJournee()->getDateDebut()->format('Y-m-d'), $m->getDate()->format('Y-m-d'));
            self::assertLessThanOrEqual($m->getJournee()->getDateFin()->format('Y-m-d'), $m->getDate()->format('Y-m-d'));
        }
    }

    #[TestDox('MAT-03 calculerDateMatch : jour de la semaine de la journée, heure du créneau ; le jour même si la journée commence ce jour-là')]
    public function test_calculer_date(): void
    {
        $service = self::service(PartieService::class);
        $heure = new \DateTimeImmutable('20:30');
        self::assertSame('2026-09-16 20:30', $service->calculerDateMatch(new \DateTimeImmutable('2026-09-14'), 'mercredi', $heure)->format('Y-m-d H:i'));
        self::assertSame('2026-09-16 20:30', $service->calculerDateMatch(new \DateTimeImmutable('2026-09-14'), '3', $heure)->format('Y-m-d H:i'));
        self::assertSame('2026-09-14 20:30', $service->calculerDateMatch(new \DateTimeImmutable('2026-09-14'), 'lundi', $heure)->format('Y-m-d H:i'));
        $this->expectException(\InvalidArgumentException::class);
        $service->calculerDateMatch(new \DateTimeImmutable('2026-09-14'), 'jourdi', $heure);
    }

    #[TestDox('MAT-04 Équipe sans gymnase : pas d\'erreur ; ses matchs à domicile se jouent au gymnase par défaut, à une date')]
    public function test_equipe_sans_gymnase(): void
    {
        [$poule] = $this->generer(4, 'S', [2]);
        $matchs = $this->matchs($poule);
        self::assertCount(6, $matchs);
        foreach ($matchs as $m) {
            self::assertNotNull($m->getDate());
            self::assertNotNull($m->getLieu(), 'gymnase par défaut (LieuRepository::getLieuByDefaut)');
        }
    }

    #[TestDox('MAT-06 Régénérer une poule recrée ses matchs sans toucher à ceux des autres poules')]
    public function test_regenerer_une_poule(): void
    {
        [$a] = $this->generer(4, 'A');
        [$b] = $this->generer(4, 'B');
        $matchsB = array_map(fn (Partie $m) => $m->getId(), $this->matchs($b));
        $anciensA = array_map(fn (Partie $m) => $m->getId(), $this->matchs($a));

        $em = self::service('doctrine')->getManager();
        self::service(PartieService::class)->creerCalendrierOptimisePourPoule($em->find(Poule::class, $a->getId()));
        $em->clear();

        self::assertSame($matchsB, array_map(fn (Partie $m) => $m->getId(), $this->matchs($b)), 'poule B intacte');
        $nouveauxA = array_map(fn (Partie $m) => $m->getId(), $this->matchs($a));
        self::assertCount(6, $nouveauxA);
        self::assertSame([], array_intersect($anciensA, $nouveauxA), 'matchs de la poule A recréés');
    }
}
