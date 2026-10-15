<?php

namespace App\Tests\Unitaire\Competition\Modele;

use App\Competition\Modele\ModeleBlvbActuel;
use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Competition\Modele\Modeles;
use App\Entity\Saison;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Modèle « Format BLVB actuel » (App\Competition\Modele\ModeleBlvbActuel, D6 et D10), ancien config/app/saison.yaml. */
final class ModeleBlvbActuelTest extends TestCase
{
    private function saison(): Saison
    {
        $saison = (new Saison())->setDateDebut(new \DateTimeImmutable('2026-09-01'));
        (new ModeleBlvbActuel())->appliquer($saison);

        return $saison;
    }

    #[TestDox('Trois phases (Phase 1, Phase 2 en championnat, Phase Finale), dates relatives à l\'année de début, codes P1, P2, F')]
    public function test_phases(): void
    {
        $phases = array_map(fn ($p) => [$p->getNom(), $p->getType(), $p->getCode(), $p->getOrdre(), $p->getClose(),
            $p->getDateDebut()->format('Y-m-d'), $p->getDateFin()->format('Y-m-d')], $this->saison()->getPhases()->toArray());

        self::assertSame([
            ['Phase 1', PhaseType::CHAMPIONNAT, 'P1', 0, 0, '2026-09-01', '2027-02-01'],
            ['Phase 2', PhaseType::CHAMPIONNAT, 'P2', 1, 0, '2027-02-01', '2027-06-01'],
            ['Phase Finale', PhaseType::FINALE, 'F', 2, 0, '2027-06-01', '2027-07-31'],
        ], $phases);
    }

    #[TestDox('Chaque phase : 4 poules de niveau A à D, 2 montées et 2 descentes')]
    public function test_poules(): void
    {
        foreach ($this->saison()->getPhases() as $phase) {
            $poules = array_map(fn ($p) => [$p->getNom(), $p->getCode(), $p->getNiveau(), $p->getNbMonteeDefaut(), $p->getNbDescenteDefaut()], $phase->getPoules()->toArray());
            self::assertSame([['Poule A', 'A', 1, 2, 2], ['Poule B', 'B', 2, 2, 2], ['Poule C', 'C', 3, 2, 2], ['Poule D', 'D', 4, 2, 2]], $poules, $phase->getNom());
        }
    }

    #[TestDox('Règles de passage du schéma 2025 : 12 de la phase 1 vers la phase 2, 16 de la phase 2 vers la finale, aucune après')]
    public function test_regles(): void
    {
        self::assertSame([12, 16, 0], array_map(fn ($p) => $p->getReglesDePassage()->count(), $this->saison()->getPhases()->toArray()));
    }

    #[TestDox('Préfixe des noms (jeux de données de test) ; le modèle est proposé par le registre des modèles')]
    public function test_prefixe_et_registre(): void
    {
        $saison = (new Saison())->setDateDebut(new \DateTimeImmutable('2026-09-01'));
        (new ModeleBlvbActuel('test_'))->appliquer($saison);

        self::assertSame('test_Phase 1', $saison->getPhases()->first()->getNom());
        self::assertSame('test_Poule A', $saison->getPhases()->first()->getPoules()->first()->getNom());
        self::assertInstanceOf(ModeleBlvbActuel::class, (new Modeles())->parCode(ModeleBlvbActuel::CODE));
        self::assertSame([ModeleBlvbActuel::CODE, ModeleBlvbAvecBarrages::CODE], array_keys((new Modeles())->tous()));
    }
}
