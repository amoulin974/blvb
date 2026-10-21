<?php

namespace App\Tests\Unitaire\Competition\Format;

use App\Competition\Format\Championnat;
use App\Competition\Format\Elimination;
use App\Competition\Format\Formats;
use App\Competition\Format\PlanningFinales;
use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Choix du générateur de calendrier selon la phase (App\Competition\Format\Formats). */
final class FormatsTest extends TestCase
{
    private function formats(): Formats
    {
        $em = $this->createStub(EntityManagerInterface::class);

        return new Formats([new Championnat($em), new Elimination($em, new PlanningFinales($em))]);
    }

    #[TestDox('Une phase de championnat est générée par le championnat, optimisé')]
    public function test_championnat(): void
    {
        $generateur = $this->formats()->pourPhase((new Phase())->setType(PhaseType::CHAMPIONNAT));

        self::assertInstanceOf(Championnat::class, $generateur);
        self::assertTrue($generateur->estOptimisable());
    }

    #[TestDox('Une phase finale est générée par l\'élimination directe, hors optimiseur')]
    public function test_finale(): void
    {
        $generateur = $this->formats()->pourPhase((new Phase())->setType(PhaseType::FINALE));

        self::assertInstanceOf(Elimination::class, $generateur);
        self::assertFalse($generateur->estOptimisable());
    }

    #[TestDox('Nombre de journées créées : championnat n-1 (n pair) ou n (n impair), élimination avec un tour de barrages hors puissance de 2, 0 sous deux équipes')]
    public function test_nombre_journees(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $poule = function (int $n): Poule {
            $poule = new Poule();
            for ($i = 0; $i < $n; ++$i) {
                $poule->addEquipe(new Equipe());
            }

            return $poule;
        };
        $championnat = new Championnat($em);
        $elimination = new Elimination($em, new PlanningFinales($em));

        foreach ([0 => 0, 1 => 0, 2 => 1, 4 => 3, 8 => 7, 9 => 9] as $equipes => $attendu) {
            self::assertSame($attendu, $championnat->nombreJournees($poule($equipes)), "championnat à $equipes équipes");
            self::assertSame(intdiv($equipes * ($equipes - 1), 2), $championnat->nombreMatchs($poule($equipes)), "matchs du championnat à $equipes équipes");
        }
        foreach ([0 => 0, 1 => 0, 2 => 1, 4 => 2, 8 => 3, 9 => 4, 12 => 4] as $equipes => $attendu) {
            self::assertSame($attendu, $elimination->nombreJournees($poule($equipes)), "élimination à $equipes équipes");
            self::assertSame(max(0, $equipes - 1), $elimination->nombreMatchs($poule($equipes)), "matchs de l'élimination à $equipes équipes");
        }
    }

    #[TestDox('Sans générateur pour la phase : aucun format')]
    public function test_aucun(): void
    {
        self::assertNull((new Formats([]))->pourPhase((new Phase())->setType(PhaseType::CHAMPIONNAT)));
    }
}
