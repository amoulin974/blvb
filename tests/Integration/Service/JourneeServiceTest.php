<?php

namespace App\Tests\Integration\Service;

use App\Entity\Journee;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\EquipeFactory;
use App\Factory\IndisponibiliteFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\JourneeService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/** JOU-01 à JOU-03 : création des journées d'une poule (JourneeService::creerJournees). */
final class JourneeServiceTest extends TestIntegration
{
    private object $saison;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saison = SaisonFactory::createOne(['nom' => 'Saison journées', 'favori' => 1]);
    }

    /** Phase qui commence le lundi 7 septembre 2026, sur $semaines semaines ; une poule de $nbEquipes équipes. */
    private function poule(int $nbEquipes, int $semaines = 20, PhaseType $type = PhaseType::CHAMPIONNAT, ?object $phase = null): Poule
    {
        $phase ??= PhaseFactory::createOne(['saison' => $this->saison, 'type' => $type, 'datedebut' => new \DateTimeImmutable('2026-09-07'),
            'datefin' => new \DateTimeImmutable('2026-09-07')->modify('+'.($semaines * 7 - 1).' days')]);
        $equipes = [];
        for ($i = 1; $i <= $nbEquipes; ++$i) {
            $equipes[] = EquipeFactory::createOne(['lieu' => null]);
        }

        return PouleFactory::createOne(['phase' => $phase, 'equipes' => $equipes]);
    }

    /** @return list<Journee> triées par numéro */
    private function journees(Poule $poule): array
    {
        $em = self::service('doctrine')->getManager();
        $em->clear();
        $journees = $em->getRepository(Journee::class)->findBy(['poule' => $poule->getId()], ['numero' => 'ASC']);

        return $journees;
    }

    public static function tailles(): iterable
    {
        yield '4 équipes : 3 journées' => [4, 3];
        yield '5 équipes (impair) : 5 journées' => [5, 5];
        yield '8 équipes : 7 journées' => [8, 7];
    }

    #[DataProvider('tailles')]
    #[TestDox('JOU-01 Nombre de journées et numéros consécutifs, une semaine par journée : $_dataName')]
    public function test_nombre(int $nbEquipes, int $attendu): void
    {
        $poule = $this->poule($nbEquipes);
        self::assertNull(self::service(JourneeService::class)->creerJournees($poule));

        $journees = $this->journees($poule);
        self::assertSame(range(1, $attendu), array_map(fn (Journee $j) => $j->getNumero(), $journees));
        self::assertSame('2026-09-07', $journees[0]->getDateDebut()->format('Y-m-d'), 'première journée : lundi de début de phase');
        self::assertSame('2026-09-13', $journees[0]->getDateFin()->format('Y-m-d'), 'jusqu\'au dimanche');
        self::assertSame('2026-09-14', $journees[1]->getDateDebut()->format('Y-m-d'), 'semaine suivante');
    }

    #[TestDox('JOU-02 Les semaines de vacances (indisponibilités de la saison) sont sautées')]
    public function test_vacances(): void
    {
        IndisponibiliteFactory::createOne(['saison' => $this->saison, 'dateDebut' => new \DateTimeImmutable('2026-09-16'), 'dateFin' => new \DateTimeImmutable('2026-09-22')]);
        $poule = $this->poule(4);
        self::service(JourneeService::class)->creerJournees($poule);

        $debuts = array_map(fn (Journee $j) => $j->getDateDebut()->format('Y-m-d'), $this->journees($poule));
        self::assertSame(['2026-09-07', '2026-09-28', '2026-10-05'], $debuts, 'les semaines du 14 et du 21 touchent les vacances');
    }

    #[TestDox('JOU-02 Phase trop courte, ou trop courte une fois les vacances retirées : message d\'erreur, aucune journée créée')]
    public function test_phase_trop_courte(): void
    {
        $courte = $this->poule(8, 4); // 7 journées pour 4 semaines
        self::assertSame('La phase est trop courte pour contenir toutes les journées.', self::service(JourneeService::class)->creerJournees($courte));
        self::assertSame([], $this->journees($courte));

        IndisponibiliteFactory::createOne(['saison' => $this->saison, 'dateDebut' => new \DateTimeImmutable('2026-09-07'), 'dateFin' => new \DateTimeImmutable('2026-09-20')]);
        $justeAssez = $this->poule(4, 3); // 3 journées pour 3 semaines, dont 2 de vacances
        self::assertStringContainsString('trop courte pour placer les 3 journées', (string) self::service(JourneeService::class)->creerJournees($justeAssez));
    }

    public static function finales(): iterable
    {
        yield '4 équipes' => [4, ['Demi-finale', 'Finale']];
        yield '8 équipes' => [8, ['Quart de finale', 'Demi-finale', 'Finale']];
        yield '6 équipes : barrages' => [6, ['Barrages', 'Demi-finale', 'Finale']];
    }

    #[DataProvider('finales')]
    #[TestDox('JOU-03 Phase finale : journées nommées selon le tour : $_dataName')]
    public function test_finales(int $nbEquipes, array $noms): void
    {
        $poule = $this->poule($nbEquipes, 10, PhaseType::FINALE);
        self::assertNull(self::service(JourneeService::class)->creerJournees($poule));
        self::assertSame($noms, array_map(fn (Journee $j) => $j->getNom(), $this->journees($poule)));
    }

    #[TestDox('JOU-01 Une deuxième poule de même taille reprend les dates de la première ; recréer les journées remplace les anciennes')]
    public function test_recopie_et_recreation(): void
    {
        $premiere = $this->poule(4);
        self::service(JourneeService::class)->creerJournees($premiere);
        $seconde = $this->poule(4, phase: $premiere->getPhase());
        $em = self::service('doctrine')->getManager();
        self::service(JourneeService::class)->creerJournees($em->find(Poule::class, $seconde->getId()));

        $dates = fn (Poule $p) => array_map(fn (Journee $j) => [$j->getNumero(), $j->getDateDebut()->format('Y-m-d')], $this->journees($p));
        self::assertSame($dates($premiere), $dates($seconde));

        self::service(JourneeService::class)->creerJournees(self::service('doctrine')->getManager()->find(Poule::class, $premiere->getId()));
        self::assertCount(3, $this->journees($premiere), 'pas de doublon après recréation');
    }
}
