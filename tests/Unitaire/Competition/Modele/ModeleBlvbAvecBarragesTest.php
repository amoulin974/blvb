<?php

namespace App\Tests\Unitaire\Competition\Modele;

use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Competition\VerificationPassages;
use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Modèle « Format BLVB avec barrages » (App\Competition\Modele\ModeleBlvbAvecBarrages, D44, proposition F2 du bureau). */
final class ModeleBlvbAvecBarragesTest extends TestCase
{
    private function saison(): Saison
    {
        $saison = (new Saison())->setDateDebut(new \DateTimeImmutable('2026-09-01'));
        (new ModeleBlvbAvecBarrages())->appliquer($saison);

        return $saison;
    }

    /** @return array<string, Phase> phases par code */
    private function phases(Saison $saison): array
    {
        $parCode = [];
        foreach ($saison->getPhases() as $phase) {
            $parCode[$phase->getCode()] = $phase;
        }

        return $parCode;
    }

    #[TestDox('Cinq phases : Phase 1, Barrages (une semaine), Phase 2, Phase finale, Barrages de fin de saison, codes P1, BAR, P2, F, BAR2')]
    public function test_phases(): void
    {
        $phases = array_map(fn ($p) => [$p->getNom(), $p->getType(), $p->getCode(), $p->getOrdre(),
            $p->getDateDebut()->format('Y-m-d'), $p->getDateFin()->format('Y-m-d')], $this->saison()->getPhases()->toArray());

        self::assertSame([
            ['Phase 1', PhaseType::CHAMPIONNAT, 'P1', 0, '2026-09-01', '2027-02-01'],
            ['Barrages', PhaseType::BARRAGE, 'BAR', 1, '2027-02-01', '2027-02-08'],
            ['Phase 2', PhaseType::CHAMPIONNAT, 'P2', 2, '2027-02-08', '2027-06-01'],
            ['Phase finale', PhaseType::FINALE, 'F', 3, '2027-06-01', '2027-07-31'],
            ['Barrages de fin de saison', PhaseType::BARRAGE, 'BAR2', 4, '2027-06-01', '2027-07-31'],
        ], $phases);
    }

    #[TestDox('Trois barrages entre poules voisines (AB, BC, CD), reçus par la poule supérieure ; quatre poules dans les autres phases')]
    public function test_poules(): void
    {
        $phases = $this->phases($this->saison());

        foreach (['BAR', 'BAR2'] as $code) {
            self::assertSame(['AB', 'BC', 'CD'], array_map(fn ($p) => $p->getCode(), $phases[$code]->getPoules()->toArray()), $code);
            self::assertSame(Phase::RECEPTION_NIVEAU_SUPERIEUR, $phases[$code]->getReception(), $code);
        }
        foreach (['P1', 'P2', 'F'] as $code) {
            self::assertSame(['A', 'B', 'C', 'D'], array_map(fn ($p) => $p->getCode(), $phases[$code]->getPoules()->toArray()), $code);
        }
    }

    #[TestDox('Phase 1 : le 1er monte, le dernier descend, l\'avant-dernier joue le barrage contre le 2e de la poule du dessous')]
    public function test_regles_phase_1(): void
    {
        $regles = [];
        foreach ($this->phases($this->saison())['P1']->getReglesDePassage() as $regle) {
            $poule = $regle->getPouleDestination();
            $regles[] = $regle->getSource().' → '.$poule->getPhase()->getCode().'.'.$poule->getCode();
        }

        self::assertSame([
            'P1.A#1 → P2.A', 'P1.A#2..-3 → P2.A', 'P1.A#-2 → BAR.AB', 'P1.A#-1 → P2.B',
            'P1.B#1 → P2.A', 'P1.B#2 → BAR.AB', 'P1.B#3..-3 → P2.B', 'P1.B#-2 → BAR.BC', 'P1.B#-1 → P2.C',
            'P1.C#1 → P2.B', 'P1.C#2 → BAR.BC', 'P1.C#3..-3 → P2.C', 'P1.C#-2 → BAR.CD', 'P1.C#-1 → P2.D',
            'P1.D#1 → P2.C', 'P1.D#2 → BAR.CD', 'P1.D#3..-2 → P2.D', 'P1.D#-1 → P2.D',
        ], $regles);
    }

    #[TestDox('Barrages : le vainqueur joue dans la poule supérieure, le perdant dans la poule inférieure')]
    public function test_regles_barrages(): void
    {
        $regles = [];
        foreach ($this->phases($this->saison())['BAR']->getReglesDePassage() as $regle) {
            $regles[] = $regle->getSource().' → P2.'.$regle->getPouleDestination()->getCode();
        }

        self::assertSame([
            'BAR.AB.vainqueur → P2.A', 'BAR.AB.perdant → P2.B',
            'BAR.BC.vainqueur → P2.B', 'BAR.BC.perdant → P2.C',
            'BAR.CD.vainqueur → P2.C', 'BAR.CD.perdant → P2.D',
        ], $regles);
    }

    #[TestDox('Phase 2 (D50) : toute la poule va au tableau de son niveau ; l\'avant-dernier et le 2e du dessous jouent en plus un barrage de fin de saison ; aucun passage vers la saison suivante')]
    public function test_regles_phase_2(): void
    {
        $regles = [];
        foreach ($this->phases($this->saison())['P2']->getReglesDePassage() as $regle) {
            $poule = $regle->getPouleDestination();
            self::assertNotNull($poule, 'aucun passage vers la saison suivante');
            $regles[] = $regle->getSource().' → '.$poule->getPhase()->getCode().'.'.$poule->getCode();
        }

        self::assertSame([
            'P2.A#1..-1 → F.A', 'P2.A#-2 → BAR2.AB',
            'P2.B#1..-1 → F.B', 'P2.B#2 → BAR2.AB', 'P2.B#-2 → BAR2.BC',
            'P2.C#1..-1 → F.C', 'P2.C#2 → BAR2.BC', 'P2.C#-2 → BAR2.CD',
            'P2.D#1..-1 → F.D', 'P2.D#2 → BAR2.CD',
        ], $regles);
    }

    #[TestDox('Vérification D5 : poules de 8 équipes en phase 1, barrages de deux équipes : aucune erreur')]
    public function test_verification_sans_erreur(): void
    {
        $phases = $this->phases($this->saison());
        foreach ($phases['P1']->getPoules() as $poule) {
            for ($i = 0; $i < 8; ++$i) {
                $poule->addEquipe((new Equipe())->setNom($poule->getCode().$i));
            }
        }
        foreach ($phases['BAR']->getPoules() as $poule) {
            $poule->addEquipe((new Equipe())->setNom('x'))->addEquipe((new Equipe())->setNom('y'));
        }
        foreach ($phases['P2']->getPoules() as $poule) {
            for ($i = 0; $i < 8; ++$i) {
                $poule->addEquipe((new Equipe())->setNom($poule->getCode().$i));
            }
        }

        self::assertSame([], VerificationPassages::verifier($phases['P1'], $phases['P1']->getReglesDePassage()));
        self::assertSame([], VerificationPassages::verifier($phases['BAR'], $phases['BAR']->getReglesDePassage()));
        self::assertSame([], VerificationPassages::verifier($phases['P2'], $phases['P2']->getReglesDePassage()), 'une équipe peut aller à la fois au tableau et au barrage (D50)');
    }

    #[TestDox('Le modèle n\'est pas encore proposé dans le wizard (écrans du lot 6c) mais a son code et son libellé')]
    public function test_identite(): void
    {
        $modele = new ModeleBlvbAvecBarrages();

        self::assertSame('blvb_avec_barrages', $modele->code());
        self::assertSame('Format BLVB avec barrages', $modele->libelle());
    }
}
