<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\VerificationPassages;
use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** VER-02 : une équipe peut avoir une destination dans chaque phase d'arrivée, jamais deux dans la même (D50). */
final class VerificationPassagesPhasesTest extends TestCase
{
    private Phase $source;
    private Poule $tableau;
    private Poule $autreTableau;
    private Poule $barrage;

    protected function setUp(): void
    {
        $this->source = (new Phase())->setNom('Phase 2')->setCode('P2')->setType(PhaseType::CHAMPIONNAT);
        $poule = (new Poule())->setNom('Poule A')->setCode('A');
        $this->source->addPoule($poule);
        for ($i = 1; $i <= 4; ++$i) {
            $poule->addEquipe((new Equipe())->setNom("A$i"));
        }

        $finale = (new Phase())->setNom('Finale')->setCode('F')->setType(PhaseType::FINALE);
        $this->tableau = (new Poule())->setNom('Tableau A')->setCode('A');
        $this->autreTableau = (new Poule())->setNom('Tableau B')->setCode('B');
        $finale->addPoule($this->tableau)->addPoule($this->autreTableau);

        $barrages = (new Phase())->setNom('Barrages')->setCode('BAR2')->setType(PhaseType::BARRAGE);
        $this->barrage = (new Poule())->setNom('Barrage A / B')->setCode('AB');
        $barrages->addPoule($this->barrage);
    }

    private function regle(string $source, Poule $destination): RegleDePassage
    {
        return (new RegleDePassage())->setSource($source)->setPouleDestination($destination);
    }

    #[TestDox('VER-02 Un rang dans le tableau final et dans un barrage : aucune erreur')]
    public function test_deux_phases_differentes(): void
    {
        $regles = [$this->regle('P2.A#1..-1', $this->tableau), $this->regle('P2.A#-2', $this->barrage)];

        self::assertSame([], VerificationPassages::verifier($this->source, $regles));
    }

    #[TestDox('VER-02 Un rang dans deux poules de la même phase : signalé')]
    public function test_meme_phase_deux_fois(): void
    {
        $regles = [$this->regle('P2.A#1..-1', $this->tableau), $this->regle('P2.A#-2', $this->autreTableau)];

        $erreurs = VerificationPassages::verifier($this->source, $regles);

        self::assertCount(1, $erreurs);
        self::assertStringContainsString('plusieurs poules', $erreurs[0]);
        self::assertStringContainsString('le 3e', $erreurs[0], 'avec 4 équipes, l\'avant-dernier est le 3e');
    }

    #[TestDox('VER-02 Un rang sans aucune destination : toujours signalé, même si un barrage existe pour un autre rang')]
    public function test_rang_sans_destination(): void
    {
        $regles = [$this->regle('P2.A#1..2', $this->tableau), $this->regle('P2.A#-2', $this->barrage)];

        $erreurs = VerificationPassages::verifier($this->source, $regles);

        self::assertCount(1, $erreurs);
        self::assertStringContainsString('aucune poule prévue', $erreurs[0]);
    }
}
