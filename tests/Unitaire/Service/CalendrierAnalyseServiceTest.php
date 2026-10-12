<?php

namespace App\Tests\Unitaire\Service;

use App\Entity\Creneau;
use App\Entity\Lieu;
use App\Service\CalendrierAnalyseService;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * GYM-01 à GYM-03 : analyse d'une date pour un gymnase (CalendrierAnalyseService), sans base de données.
 * Gymnase de test : mercredi 2 terrains, priorité 2 (créneau préféré) ; vendredi 1 terrain, priorité 1.
 */
final class CalendrierAnalyseServiceTest extends TestCase
{
    private Lieu $lieu;

    protected function setUp(): void
    {
        $this->lieu = (new Lieu())->setNom('Gymnase test')->setAdresse('Adresse');
        foreach ([[3, 2, 2], [5, 1, 1]] as [$jour, $capacite, $priorite]) {
            $this->lieu->addCreneau((new Creneau())->setJourSemaine($jour)->setCapacite($capacite)->setPrioritaire($priorite)
                ->setHeureDebut(new \DateTimeImmutable('20:30'))->setHeureFin(new \DateTimeImmutable('22:30')));
        }
    }

    private function analyser(string $date, int $nbMatchs): array
    {
        return (new CalendrierAnalyseService())->analyser($this->lieu, new \DateTimeImmutable($date), $nbMatchs);
    }

    #[TestDox('GYM-01 Match un jour sans créneau : alerte « hors créneau », capacité 0')]
    public function test_hors_creneau(): void
    {
        $a = $this->analyser('2026-10-13', 1); // mardi
        self::assertTrue($a['alerte_horscrenau']);
        self::assertSame(0, $a['capacite_max']);
    }

    #[TestDox('GYM-02 Capacité : plus de matchs que de terrains → surcharge ; autant ou moins → pas d\'alerte')]
    public function test_capacite(): void
    {
        self::assertTrue($this->analyser('2026-10-14', 3)['alerte_capacite'], '3 matchs, 2 terrains');
        foreach ([2, 1, 0] as $nb) {
            $a = $this->analyser('2026-10-14', $nb);
            self::assertFalse($a['alerte_capacite'], "$nb match(s)");
            self::assertFalse($a['alerte_horscrenau']);
            self::assertSame(2, $a['capacite_max']);
        }
    }

    #[TestDox('GYM-03 Créneau non prioritaire (vendredi) alors que le mercredi l\'est : alerte priorité, jour préféré nommé')]
    public function test_priorite(): void
    {
        $vendredi = $this->analyser('2026-10-16', 1);
        self::assertTrue($vendredi['alerte_priorite']);
        self::assertSame([1, 2], [$vendredi['priorite_actuelle'], $vendredi['priorite_max']]);
        self::assertNotSame('', $vendredi['jour_prefere']);
        self::assertSame((new Creneau())->setJourSemaine(3)->getJourLibelle(), $vendredi['jour_prefere'], 'le mercredi');

        self::assertFalse($this->analyser('2026-10-14', 1)['alerte_priorite'], 'mercredi : créneau préféré');
    }
}
