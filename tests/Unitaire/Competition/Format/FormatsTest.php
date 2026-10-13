<?php

namespace App\Tests\Unitaire\Competition\Format;

use App\Competition\Format\Championnat;
use App\Competition\Format\Elimination;
use App\Competition\Format\Formats;
use App\Entity\Phase;
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

        return new Formats([new Championnat($em), new Elimination($em)]);
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

    #[TestDox('Sans générateur pour la phase : aucun format')]
    public function test_aucun(): void
    {
        self::assertNull((new Formats([]))->pourPhase((new Phase())->setType(PhaseType::CHAMPIONNAT)));
    }
}
