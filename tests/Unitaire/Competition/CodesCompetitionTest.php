<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\CodesCompetition;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Codes des phases et des poules d'une saison (App\Competition\CodesCompetition, D20). */
final class CodesCompetitionTest extends TestCase
{
    /** Saison : phases [nom => [type, ordre, [nom de poule => niveau]]]. */
    private function saison(array $phases): Saison
    {
        $saison = new Saison();
        foreach ($phases as $nom => [$type, $ordre, $poules]) {
            $phase = (new Phase())->setNom($nom)->setType($type)->setOrdre($ordre);
            foreach ($poules as $nomPoule => $niveau) {
                $phase->addPoule((new Poule())->setNom($nomPoule)->setNiveau($niveau));
            }
            $saison->addPhase($phase);
        }

        return $saison;
    }

    /** @return array<string, array{0: string, 1: array<string, string>}> nom de phase => [code, [nom de poule => code]] */
    private static function codes(Saison $saison): array
    {
        $codes = [];
        foreach ($saison->getPhases() as $phase) {
            $poules = [];
            foreach ($phase->getPoules() as $poule) {
                $poules[$poule->getNom()] = $poule->getCode();
            }
            $codes[$phase->getNom()] = [$phase->getCode(), $poules];
        }

        return $codes;
    }

    #[TestDox('Saison BLVB : P1, P2, F ; poules A à D tirées des noms')]
    public function test_saison_blvb(): void
    {
        $poules = ['Poule A' => 1, 'Poule B' => 2, 'Poule C' => 3, 'Poule D' => 4];
        $saison = $this->saison([
            'Phase finale' => [PhaseType::FINALE, 2, $poules],
            'Phase 1' => [PhaseType::CHAMPIONNAT, 0, $poules],
            'Phase 2' => [PhaseType::CHAMPIONNAT, 1, $poules],
        ]);

        CodesCompetition::attribuer($saison);

        $abcd = ['Poule A' => 'A', 'Poule B' => 'B', 'Poule C' => 'C', 'Poule D' => 'D'];
        self::assertSame(['Phase finale' => ['F', $abcd], 'Phase 1' => ['P1', $abcd], 'Phase 2' => ['P2', $abcd]], self::codes($saison));
    }

    #[TestDox('Noms sans lettre finale : première lettre libre dans l\'ordre des niveaux, sans reprendre une lettre déjà tirée d\'un nom')]
    public function test_lettres_libres(): void
    {
        $saison = $this->saison(['Phase 1' => [PhaseType::CHAMPIONNAT, 0, ['Élite' => 1, 'Poule A' => 3, 'Loisir' => 2]]]);

        CodesCompetition::attribuer($saison);

        self::assertSame(['Phase 1' => ['P1', ['Élite' => 'B', 'Poule A' => 'A', 'Loisir' => 'C']]], self::codes($saison));
    }

    #[TestDox('Un code déjà saisi n\'est jamais changé ; les codes calculés évitent les codes pris')]
    public function test_codes_existants(): void
    {
        $saison = $this->saison([
            'Brassage' => [PhaseType::CHAMPIONNAT, 0, ['Poule A' => 1, 'Poule B' => 2]],
            'Niveaux' => [PhaseType::CHAMPIONNAT, 1, ['Poule A' => 1]],
        ]);
        $brassage = $saison->getPhases()->first();
        $brassage->setCode('P2');
        $brassage->getPoules()->first()->setCode('B');

        CodesCompetition::attribuer($saison);

        self::assertSame(['Brassage' => ['P2', ['Poule A' => 'B', 'Poule B' => 'A']], 'Niveaux' => ['P2_', ['Poule A' => 'A']]], self::codes($saison));
    }

    #[TestDox('Phases de barrages (D31, D42) : BAR, BAR2, sans changer les codes P et F')]
    public function test_phases_de_barrages(): void
    {
        $phases = [];
        foreach ([[0, 'champ', false], [1, 'bar1', true], [2, 'champ2', false], [3, 'bar2', true]] as [$ordre, $cle, $barrage]) {
            $phases[] = ['cle' => $cle, 'code' => null, 'finale' => false, 'barrage' => $barrage, 'ordre' => $ordre, 'poules' => []];
        }

        $codes = array_column(CodesCompetition::calculer($phases), 'code', 'cle');

        self::assertSame(['champ' => 'P1', 'bar1' => 'BAR', 'champ2' => 'P2', 'bar2' => 'BAR2'], $codes);
    }

    #[TestDox('Plusieurs phases finales : F, F2')]
    public function test_plusieurs_finales(): void
    {
        $saison = $this->saison([
            'Barrages' => [PhaseType::FINALE, 1, []],
            'Finales' => [PhaseType::FINALE, 2, []],
            'Championnat' => [PhaseType::CHAMPIONNAT, 0, []],
        ]);

        CodesCompetition::attribuer($saison);

        self::assertSame(['Barrages' => ['F', []], 'Finales' => ['F2', []], 'Championnat' => ['P1', []]], self::codes($saison));
    }
}
