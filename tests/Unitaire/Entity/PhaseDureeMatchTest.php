<?php

namespace App\Tests\Unitaire\Entity;

use App\Entity\Phase;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** SET-05 : durée d'un match proposée selon le format de la phase, et durée enregistrée de la journée des finales (D49). */
final class PhaseDureeMatchTest extends TestCase
{
    private function phase(int $sets, ?int $duree = null): Phase
    {
        $phase = (new Phase())->setType(PhaseType::FINALE)->setSetsGagnants($sets);
        $phase->setOptions(array_merge($phase->getOptions(), ['journee_finales' => array_filter(['tours' => Phase::TOURS_FINALES, 'duree' => $duree])]));

        return $phase;
    }

    #[TestDox('SET-05 Durée proposée : 120 minutes en 3 sets gagnants, 75 minutes en 2 sets gagnants')]
    public function test_duree_par_defaut(): void
    {
        self::assertSame(120, (new Phase())->dureeMatchParDefaut());
        self::assertSame(120, $this->phase(3)->dureeMatchParDefaut());
        self::assertSame(75, $this->phase(2)->dureeMatchParDefaut());
    }

    #[TestDox('SET-05 Journée des finales sans durée enregistrée : la durée proposée du format')]
    public function test_journee_finales_sans_duree(): void
    {
        self::assertSame(120, $this->phase(3)->getJourneeFinales()['duree']);
        self::assertSame(75, $this->phase(2)->getJourneeFinales()['duree']);
    }

    #[TestDox('SET-05 Une durée enregistrée est conservée quel que soit le format')]
    public function test_duree_enregistree(): void
    {
        self::assertSame(45, $this->phase(2, 45)->getJourneeFinales()['duree']);
        self::assertSame(100, $this->phase(3, 100)->getJourneeFinales()['duree']);
    }
}
