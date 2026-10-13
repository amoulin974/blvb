<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\ReglesParDefaut;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Règles de passage par défaut, schéma 2025 (App\Competition\ReglesParDefaut, D21 et D22). */
final class ReglesParDefautTest extends TestCase
{
    /** Phase [code, finale, poules] ; poules A à D aux niveaux 1 à 4, 2 montées et 2 descentes, clés = codes. */
    private static function phase(string $code, bool $finale = false, string $poules = 'ABCD', int $montee = 2, int $descente = 2): array
    {
        $liste = [];
        foreach (str_split($poules) as $i => $lettre) {
            $liste[] = ['cle' => $lettre, 'code' => $lettre, 'niveau' => $i + 1, 'montee' => $montee, 'descente' => $descente];
        }

        return ['code' => $code, 'finale' => $finale, 'poules' => $liste];
    }

    /** Règles lisibles : « source → poule » ou « source → saison suivante, niveau n ». */
    private static function lisibles(array $regles): array
    {
        return array_map(fn (array $r) => $r['source'].' → '.($r['poule'] ?? 'saison suivante, niveau '.$r['niveauSaisonSuivante']), $regles);
    }

    #[TestDox('Championnat vers championnat : 2 montées et 2 descentes, pas de montée au niveau le plus haut ni de descente au plus bas')]
    public function test_championnat_vers_championnat(): void
    {
        self::assertSame([
            'P1.A#1..2 → A', 'P1.A#3..-3 → A', 'P1.A#-2..-1 → B',
            'P1.B#1..2 → A', 'P1.B#3..-3 → B', 'P1.B#-2..-1 → C',
            'P1.C#1..2 → B', 'P1.C#3..-3 → C', 'P1.C#-2..-1 → D',
            'P1.D#1..2 → C', 'P1.D#3..-3 → D', 'P1.D#-2..-1 → D',
        ], self::lisibles(ReglesParDefaut::calculer(self::phase('P1'), self::phase('P2'))));
    }

    #[TestDox('Vers la phase finale : chaque poule vers le tableau de son niveau ; montées et descentes pour la saison suivante')]
    public function test_vers_phase_finale(): void
    {
        $regles = self::lisibles(ReglesParDefaut::calculer(self::phase('P2', poules: 'AB'), self::phase('F', true, 'AB')));

        self::assertSame([
            'P2.A#1..-1 → A', 'P2.A#1..2 → saison suivante, niveau 1', 'P2.A#3..-3 → saison suivante, niveau 1', 'P2.A#-2..-1 → saison suivante, niveau 2',
            'P2.B#1..-1 → B', 'P2.B#1..2 → saison suivante, niveau 1', 'P2.B#3..-3 → saison suivante, niveau 2', 'P2.B#-2..-1 → saison suivante, niveau 2',
        ], $regles);
    }

    #[TestDox('Sans montée ni descente : toute la poule au même niveau')]
    public function test_sans_montee_ni_descente(): void
    {
        $regles = ReglesParDefaut::calculer(self::phase('P1', poules: 'A', montee: 0, descente: 0), self::phase('P2', poules: 'A'));

        self::assertSame(['P1.A#1..-1 → A'], self::lisibles($regles));
        self::assertSame([1], array_column($regles, 'ordre'));
    }

    #[TestDox('Petite poule (3 équipes, 2 montées, 2 descentes) : la montée l\'emporte, pas de chevauchement')]
    public function test_petite_poule(): void
    {
        $source = self::phase('P1', poules: 'AB');
        $source['poules'][1]['nbEquipes'] = 3;

        self::assertSame(
            ['P1.A#1..2 → A', 'P1.A#3..-3 → A', 'P1.A#-2..-1 → B', 'P1.B#1..2 → A', 'P1.B#3..-2 → B', 'P1.B#-1..-1 → B'],
            self::lisibles(ReglesParDefaut::calculer($source, self::phase('P2', poules: 'AB'))),
        );
    }

    #[TestDox('Niveau absent de la phase suivante : première poule ; pas de phase suivante : aucune règle')]
    public function test_cas_limites(): void
    {
        $suivante = ['code' => 'P2', 'finale' => false, 'poules' => [['cle' => 'X', 'code' => 'X', 'niveau' => 7, 'montee' => 0, 'descente' => 0]]];

        self::assertSame(['P1.A#1..-1 → X'], self::lisibles(ReglesParDefaut::calculer(self::phase('P1', poules: 'A', montee: 0, descente: 0), $suivante)));
        self::assertSame([], ReglesParDefaut::calculer(self::phase('P1'), null));
    }

    #[TestDox('Entités : règles ordonnées, destination = poule de la phase suivante ou niveau de la saison suivante')]
    public function test_entites(): void
    {
        $p1 = (new Phase())->setCode('P1')->setType(PhaseType::CHAMPIONNAT);
        $p1->addPoule((new Poule())->setNom('Poule A')->setCode('A')->setNiveau(1)->setNbMonteeDefaut(0)->setNbDescenteDefaut(1));
        $p1->addPoule((new Poule())->setNom('Poule B')->setCode('B')->setNiveau(2)->setNbMonteeDefaut(1)->setNbDescenteDefaut(0));
        $finale = (new Phase())->setCode('F')->setType(PhaseType::FINALE);
        $finale->addPoule($tableauA = (new Poule())->setNom('Tableau A')->setCode('A')->setNiveau(1));
        $finale->addPoule((new Poule())->setNom('Tableau B')->setCode('B')->setNiveau(2));

        $regles = ReglesParDefaut::pourPhase($p1, $finale);

        self::assertSame(['P1.A#1..-1', 'P1.A#1..-2', 'P1.A#-1..-1', 'P1.B#1..-1', 'P1.B#1..1', 'P1.B#2..-1'], array_map(fn ($r) => $r->getSource(), $regles));
        self::assertSame($tableauA, $regles[0]->getPouleDestination());
        self::assertSame([null, 1, 2, null, 1, 2], array_map(fn ($r) => $r->getNiveauSaisonSuivante(), $regles));
        self::assertSame([1, 2, 3, 4, 5, 6], array_map(fn ($r) => $r->getOrdre(), $regles));
    }
}
