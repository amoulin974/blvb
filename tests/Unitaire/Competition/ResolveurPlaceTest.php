<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\NotationInvalide;
use App\Competition\NotationPlace;
use App\Competition\ResolveurPlace;
use App\Competition\ResultatsPhases;
use App\Entity\Equipe;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Résolution d'une place en équipes (App\Competition\ResolveurPlace), sur des résultats en mémoire. */
final class ResolveurPlaceTest extends TestCase
{
    private ResolveurPlace $resolveur;

    /** @var array<string, Equipe> */
    private array $equipes = [];

    protected function setUp(): void
    {
        // Poule A de 8 et poule B de 9 en phase 1 ; barrage AB joué, barrage BC pas encore joué
        $classements = [
            'P1.A' => $this->poule('A', 8),
            'P1.B' => $this->poule('B', 9),
        ];
        $issues = [
            'BAR1.AB.vainqueur' => $this->equipes['B2'],
            'BAR1.AB.perdant' => $this->equipes['A7'],
            'BAR1.BC.vainqueur' => null,
        ];

        $this->resolveur = new ResolveurPlace(new class($classements, $issues) implements ResultatsPhases {
            public function __construct(private array $classements, private array $issues)
            {
            }

            public function classement(string $phase, string $poule): array
            {
                return $this->classements["$phase.$poule"] ?? throw new NotationInvalide("Poule $phase.$poule inconnue.");
            }

            public function issue(string $phase, string $match, string $issue): ?Equipe
            {
                if (!\array_key_exists("$phase.$match.$issue", $this->issues)) {
                    throw new NotationInvalide("Match $phase.$match inconnu.");
                }

                return $this->issues["$phase.$match.$issue"];
            }
        });
    }

    /** @return list<Equipe> classement de la poule : A1, A2… */
    private function poule(string $poule, int $nbEquipes): array
    {
        $classement = [];
        for ($rang = 1; $rang <= $nbEquipes; ++$rang) {
            $classement[] = $this->equipes[$poule.$rang] = (new Equipe())->setNom($poule.$rang);
        }

        return $classement;
    }

    /** @param list<Equipe> $equipes */
    private static function noms(array $equipes): array
    {
        return array_map(fn (Equipe $e) => $e->getNom(), $equipes);
    }

    #[TestDox('L\'avant-dernier est le 7e d\'une poule de 8 et le 8e d\'une poule de 9')]
    public function test_avant_dernier(): void
    {
        self::assertSame(['A7'], self::noms($this->resolveur->resoudre('P1.A#-2')));
        self::assertSame(['B8'], self::noms($this->resolveur->resoudre('P1.B#-2')));
    }

    #[TestDox('Une plage rend les équipes dans l\'ordre du classement')]
    public function test_plage(): void
    {
        self::assertSame(['A1', 'A2', 'A3', 'A4', 'A5', 'A6'], self::noms($this->resolveur->resoudre('P1.A#1..-3')));
    }

    #[TestDox('Le vainqueur et le perdant d\'un barrage joué')]
    public function test_issue(): void
    {
        self::assertSame(['B2'], self::noms($this->resolveur->resoudre('BAR1.AB.vainqueur')));
        self::assertSame(['A7'], self::noms($this->resolveur->resoudre(NotationPlace::analyser('BAR1.AB.perdant'))));
    }

    #[TestDox('Un barrage sans résultat ne peut pas être résolu')]
    public function test_match_non_joue(): void
    {
        $this->expectException(NotationInvalide::class);
        $this->expectExceptionMessage('pas encore de résultat');
        $this->resolveur->resoudre('BAR1.BC.vainqueur');
    }

    #[TestDox('Un rang hors de la poule ou une poule inconnue est refusé')]
    public function test_refus(): void
    {
        foreach (['P1.A#9', 'P1.Z#1', 'BAR1.ZZ.vainqueur', 'n importe quoi'] as $notation) {
            try {
                $this->resolveur->resoudre($notation);
                self::fail("« $notation » aurait dû être refusée");
            } catch (NotationInvalide) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[TestDox('Proposition du bureau (F2) : chaque équipe des poules A et B a exactement une destination')]
    public function test_regles_f2_sans_doublon(): void
    {
        $destinations = [
            'P1.A#1..-3', 'P1.A#-2', 'P1.A#-1', // A : maintien, barrage, descente
            'P1.B#1', 'P1.B#2', 'P1.B#3..-3', 'P1.B#-2', 'P1.B#-1', // B : montée, barrage, maintien, barrage, descente
        ];

        $equipes = array_merge(...array_map(fn (string $n) => self::noms($this->resolveur->resoudre($n)), $destinations));
        sort($equipes);
        $toutes = array_keys($this->equipes);
        sort($toutes);

        self::assertSame($toutes, $equipes);
    }
}
