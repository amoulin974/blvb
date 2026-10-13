<?php

namespace App\Tests\Unitaire\Competition;

use App\Competition\PassagesSimples;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** Saisie simple des passages d'une poule (App\Competition\PassagesSimples, wizard étape 4, D29). */
final class PassagesSimplesTest extends TestCase
{
    private static function regle(string $source, ?Poule $poule = null, ?int $niveau = null): RegleDePassage
    {
        return (new RegleDePassage())->setSource($source)->setPouleDestination($poule)->setNiveauSaisonSuivante($niveau);
    }

    #[TestDox('Championnat : « 2 premiers montent, 2 derniers descendent, les autres restent » est reconnu, avec ses destinations')]
    public function test_lire_championnat(): void
    {
        [$a, $b, $c] = [(new Poule())->setNom('A'), (new Poule())->setNom('B'), (new Poule())->setNom('C')];
        $valeurs = PassagesSimples::lire('P1.B#', [self::regle('P1.B#1..2', $a), self::regle('P1.B#3..-3', $b), self::regle('P1.B#-2..-1', $c)], false);

        self::assertSame([2, 2], [$valeurs['montee'], $valeurs['descente']]);
        self::assertSame([$a, $b, $c], [$valeurs['versMontee'], $valeurs['versAutres'], $valeurs['versDescente']]);
    }

    #[TestDox('Sans montée (poule du haut) ou sans descente : reconnu ; une seule règle « toute la poule » aussi')]
    public function test_lire_cas_partiels(): void
    {
        $a = (new Poule())->setNom('A');

        self::assertSame([0, 1], array_values(array_intersect_key(PassagesSimples::lire('P1.A#', [self::regle('P1.A#1..-2', $a), self::regle('P1.A#-1..-1', $a)], false), ['montee' => 0, 'descente' => 0])));
        self::assertSame(0, PassagesSimples::lire('P1.A#', [self::regle('P1.A#1..-1', $a)], false)['montee']);
    }

    #[TestDox('Règles d\'une autre forme (rang isolé, ordre différent) : mode détaillé (null)')]
    public function test_lire_autre_forme(): void
    {
        $a = (new Poule())->setNom('A');

        self::assertNull(PassagesSimples::lire('P1.A#', [self::regle('P1.A#1', $a), self::regle('P1.A#2..-1', $a)], false));
        self::assertNull(PassagesSimples::lire('P1.A#', [self::regle('P1.A#3..-3', $a), self::regle('P1.A#1..2', $a), self::regle('P1.A#-2..-1', $a)], false));
        self::assertNull(PassagesSimples::lire('P1.A#', [self::regle('P1.A#1..-1', $a), self::regle('P1.A#1..2', null, 1)], false), 'saison suivante hors phase finale');
    }

    #[TestDox('Vers une phase finale : tableau, puis montées et descentes pour la saison suivante vers des niveaux')]
    public function test_lire_et_ecrire_finale(): void
    {
        $tableau = (new Poule())->setNom('Tableau B');
        $regles = [self::regle('P2.B#1..-1', $tableau), self::regle('P2.B#1..2', null, 1), self::regle('P2.B#3..-3', null, 2), self::regle('P2.B#-2..-1', null, 3)];

        $valeurs = PassagesSimples::lire('P2.B#', $regles, true);
        self::assertTrue($valeurs['saisonSuivante']);
        self::assertSame([$tableau, 2, 2, 1, 2, 3], [$valeurs['versAutres'], $valeurs['montee'], $valeurs['descente'], $valeurs['niveauMontee'], $valeurs['niveauAutres'], $valeurs['niveauDescente']]);

        $reecrites = PassagesSimples::regles('P2.B#', $valeurs, true);
        self::assertSame(['P2.B#1..-1', 'P2.B#1..2', 'P2.B#3..-3', 'P2.B#-2..-1'], array_map(fn ($r) => $r->getSource(), $reecrites));
        self::assertSame([null, 1, 2, 3], array_map(fn ($r) => $r->getNiveauSaisonSuivante(), $reecrites));

        $sansSaison = PassagesSimples::regles('P2.B#', ['saisonSuivante' => false] + $valeurs, true);
        self::assertSame(['P2.B#1..-1'], array_map(fn ($r) => $r->getSource(), $sansSaison));
    }

    #[TestDox('Écriture en championnat : 1 montée, 0 descente → deux règles')]
    public function test_ecrire_championnat(): void
    {
        [$a, $b] = [(new Poule())->setNom('A'), (new Poule())->setNom('B')];
        $regles = PassagesSimples::regles('P1.B#', ['montee' => 1, 'descente' => 0, 'versMontee' => $a, 'versAutres' => $b, 'versDescente' => null], false);

        self::assertSame(['P1.B#1..1', 'P1.B#2..-1'], array_map(fn ($r) => $r->getSource(), $regles));
        self::assertSame([$a, $b], array_map(fn ($r) => $r->getPouleDestination(), $regles));
    }
}
