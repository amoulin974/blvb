<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\ReglesParDefaut;
use App\Competition\VerificationPassages;
use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Vérification des règles de passage d'une phase (App\Competition\VerificationPassages, D5 et D24). */
final class VerificationPassagesTest extends TestCase
{
    private Phase $phase;
    private Poule $destination;

    protected function setUp(): void
    {
        // Phase P1 : poule A de 4 équipes (2 montées, 2 descentes) ; phase suivante P2 : poule A
        $this->phase = (new Phase())->setNom('Phase 1')->setCode('P1')->setType(PhaseType::CHAMPIONNAT);
        $poule = (new Poule())->setNom('Poule A')->setCode('A')->setNiveau(1)->setNbMonteeDefaut(1)->setNbDescenteDefaut(1);
        foreach (['a', 'b', 'c', 'd'] as $nom) {
            $poule->addEquipe((new Equipe())->setNom($nom));
        }
        $this->phase->addPoule($poule);
        $this->destination = (new Poule())->setNom('Poule A')->setCode('A')->setNiveau(1);
    }

    /** @param list<array{0: string, 1?: ?int}> $regles [source, niveau de la saison suivante (sinon : poule de destination)] */
    private function verifier(array $regles): array
    {
        return VerificationPassages::verifier($this->phase, array_map(
            fn (array $r) => ($regle = (new RegleDePassage())->setSource($r[0])) && isset($r[1])
                ? $regle->setNiveauSaisonSuivante($r[1])
                : $regle->setPouleDestination($this->destination),
            $regles,
        ));
    }

    #[TestDox('Règles par défaut et règles complètes : aucune erreur')]
    public function test_regles_completes(): void
    {
        $suivante = (new Phase())->setCode('P2')->setType(PhaseType::CHAMPIONNAT);
        $suivante->addPoule($this->destination);

        self::assertSame([], VerificationPassages::verifier($this->phase, ReglesParDefaut::pourPhase($this->phase, $suivante)));
        self::assertSame([], $this->verifier([['P1.A#1..2'], ['P1.A#3..-1'], ['P1.A#1..-1', 1]]), 'avec la saison suivante');
    }

    #[TestDox('Rang sans destination, rang avec deux destinations')]
    public function test_couverture(): void
    {
        self::assertSame(
            ['Poule A : aucune poule prévue pour le 4e dans la phase suivante.', 'Poule A : les 2e et 3e sont prévus dans plusieurs poules ; seule la première règle compte.'],
            $this->verifier([['P1.A#1..3'], ['P1.A#2..3']]),
        );
        self::assertSame(['Poule A : aucune poule prévue pour les 2e, 3e et 4e dans la phase suivante.'], $this->verifier([['P1.A#1']]));
    }

    #[TestDox('Saison suivante : vérifiée seulement si la poule a des règles pour la saison suivante')]
    public function test_saison_suivante(): void
    {
        self::assertSame(['Poule A : aucun niveau prévu pour les 3e et 4e la saison prochaine.'], $this->verifier([['P1.A#1..-1'], ['P1.A#1..2', 1]]));
    }

    #[TestDox('Règle en clair, notation entre crochets ; rang hors de la poule expliqué sans rang négatif')]
    public function test_regle_en_clair(): void
    {
        $suivante = (new Phase())->setNom('Phase 2');
        $suivante->addPoule($this->destination);
        $regle = (new RegleDePassage())->setSource('P1.A#1..2')->setPouleDestination($this->destination);
        $this->phase->addRegleDePassage($regle);

        self::assertSame('1er et 2e de la Poule A (Phase 1) vers la Poule A (Phase 2) [P1.A#1..2]', (string) $regle);
        self::assertSame('avant-dernier de la Poule A (Phase 1) vers le niveau 3 la saison prochaine [P1.A#-2]', (string) (new RegleDePassage())->setSource('P1.A#-2')->setNiveauSaisonSuivante(3)->setPhase($this->phase));
        self::assertSame(
            // Règle non rattachée à sa phase : la poule de départ est nommée par son code
            ['Règle « 9e en partant de la fin de la poule A vers la Poule A (Phase 2) » ignorée : la poule n\'a que 4 équipes : il n\'y a pas de 9e en partant de la fin.'],
            array_slice($this->verifier([['P1.A#1..-1'], ['P1.A#-9']]), 0, 1),
        );
    }

    #[TestDox('Phase de barrages (D42) : vainqueur et perdant d\'un match sont les rangs 1 et 2 de la poule de deux équipes')]
    public function test_issues_de_match(): void
    {
        $barrage = (new Phase())->setNom('Barrages')->setCode('BAR')->setType(PhaseType::BARRAGE);
        $match = (new Poule())->setNom('Barrage AB')->setCode('AB')->setNiveau(1);
        $match->addEquipe((new Equipe())->setNom('a'))->addEquipe((new Equipe())->setNom('b'));
        $barrage->addPoule($match);
        $verifier = fn (array $sources) => VerificationPassages::verifier($barrage, array_map(
            fn (string $s) => (new RegleDePassage())->setSource($s)->setPouleDestination($this->destination),
            $sources,
        ));

        self::assertSame([], $verifier(['BAR.AB.vainqueur', 'BAR.AB.perdant']));
        self::assertSame(['Barrage AB : aucune poule prévue pour le 2e dans la phase suivante.'], $verifier(['BAR.AB.vainqueur']), 'le perdant n\'a pas de destination');
    }

    #[TestDox('Notation invalide, autre phase, poule inconnue, match inconnu, sans destination : erreurs nommant la règle')]
    public function test_regles_invalides(): void
    {
        $erreurs = $this->verifier([['P1.A#1..-1'], ['P1.A#0'], ['P2.A#1'], ['P1.Z#1'], ['P1.AB.vainqueur']]);
        $sansDestination = VerificationPassages::verifier($this->phase, [(new RegleDePassage())->setSource('P1.A#1..-1')]);

        self::assertCount(4, $erreurs);
        self::assertSame('Règle « [P1.A#0] » ignorée : elle est mal écrite.', $erreurs[0]);
        self::assertStringEndsWith('ignorée : elle concerne une autre phase.', $erreurs[1]);
        self::assertStringEndsWith('ignorée : il n\'y a pas de poule Z dans « Phase 1 ».', $erreurs[2]);
        self::assertStringContainsString('vainqueur du match AB', $erreurs[3]);
        self::assertStringEndsWith('ignorée : il n\'y a pas de match AB dans « Phase 1 ».', $erreurs[3]);
        self::assertStringEndsWith('ignorée : aucune poule d\'arrivée n\'est indiquée.', $sansDestination[0]);
    }

    #[TestDox('Plage vide pour une petite poule (plus de montées et descentes que d\'équipes) : pas une erreur')]
    public function test_plage_vide(): void
    {
        self::assertSame([], $this->verifier([['P1.A#1..2'], ['P1.A#3..-3'], ['P1.A#-2..-1']]));
    }
}
