<?php

namespace App\Tests\Unitaire\VolleyStats;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\VolleyStats\EtatSet;
use App\VolleyStats\FeuilleMatchService;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** VST-08 : état d'un set recalculé depuis la composition et le journal (App\VolleyStats\EtatSet, D67). */
final class EtatSetTest extends TestCase
{
    private int $ordre = 0;

    /** @param list<?int> $postes */
    private function set(array $postes = [1, 2, 3, 4, 5, 6], string $service = 'eux', array $depart = [0, 0], int $pointsFin = 25): FeuilleMatchSet
    {
        $set = new FeuilleMatchSet(new FeuilleMatch(new Partie(), new Equipe()), 1, $pointsFin);

        return $set->setPostes($postes)->setPremierService($service)->setScoreDepart($depart);
    }

    /** @param array<string, mixed> $donnees */
    private function ajouter(FeuilleMatchSet $set, string $type, array $donnees = []): FeuilleMatchEvenement
    {
        $e = new FeuilleMatchEvenement($set, ++$this->ordre, 'u'.$this->ordre, $type, $donnees, null);
        $set->ajouterEvenement($e);

        return $e;
    }

    private function points(FeuilleMatchSet $set, string $suite): void
    {
        foreach (str_split($suite) as $p) {
            $this->ajouter($set, FeuilleMatchEvenement::POINT, ['pour' => $p === 'n' ? 'nous' : 'eux']);
        }
    }

    #[TestDox('VST-08 : un point gagné sur le service adverse fait tourner d\'un poste et donne le service ; sur son propre service, pas de rotation')]
    public function test_rotation(): void
    {
        $set = $this->set();
        $this->points($set, 'n');
        $etat = EtatSet::calculer($set);
        self::assertSame([1, 0], $etat->score);
        self::assertSame([2, 3, 4, 5, 6, 1], $etat->postes, 'le joueur du poste 2 passe au service');
        self::assertSame(2, $etat->serveur());

        $this->points($set, 'nn');
        self::assertSame([2, 3, 4, 5, 6, 1], EtatSet::calculer($set)->postes, 'service gardé : pas de rotation');
        $this->points($set, 'en');
        $etat = EtatSet::calculer($set);
        self::assertSame([4, 1], $etat->score);
        self::assertSame([3, 4, 5, 6, 1, 2], $etat->postes);
        self::assertNull(EtatSet::calculer($this->set(service: 'eux'))->serveur(), 'service adverse');
    }

    #[TestDox('VST-08 : la place libre d\'un set commencé à 5 tourne avec les autres ; un remplacement la remplit ou change un joueur')]
    public function test_place_libre_et_remplacements(): void
    {
        $set = $this->set([1, 2, 3, 4, 5, null], 'nous');
        $this->points($set, 'en');
        self::assertSame([2, 3, 4, 5, null, 1], EtatSet::calculer($set)->postes);
        $this->ajouter($set, FeuilleMatchEvenement::REMPLACEMENT, ['sort' => null, 'entre' => 7]);
        $this->ajouter($set, FeuilleMatchEvenement::REMPLACEMENT, ['sort' => 3, 'entre' => 8]);
        self::assertSame([2, 8, 4, 5, 7, 1], EtatSet::calculer($set)->postes);
    }

    #[TestDox('VST-08 : une correction fixe le score et le service ; « Tourner d\'un poste » fait tourner d\'un cran à chaque fois ; les événements annulés sont ignorés')]
    public function test_correction_rotation_annulation(): void
    {
        $set = $this->set();
        $this->points($set, 'nnn');
        $this->ajouter($set, FeuilleMatchEvenement::CORRECTION, ['nous' => 10, 'eux' => 8, 'service' => 'eux']);
        $this->ajouter($set, FeuilleMatchEvenement::ROTATION);
        $this->ajouter($set, FeuilleMatchEvenement::ROTATION);
        $etat = EtatSet::calculer($set);
        self::assertSame([10, 8], $etat->score);
        self::assertSame('eux', $etat->service);
        self::assertSame([4, 5, 6, 1, 2, 3], $etat->postes, 'une rotation par point gagné au service adverse, puis deux à la main');

        $this->ajouter($set, FeuilleMatchEvenement::POINT, ['pour' => 'nous'])->annuler();
        self::assertSame([10, 8], EtatSet::calculer($set)->score);
    }

    #[TestDox('VST-08 : fin de set à 25 points secs (sans deux points d\'écart), à 15 pour le set décisif ; départ à 0–8')]
    public function test_fin_de_set(): void
    {
        $set = $this->set(depart: [0, 8]);
        $this->points($set, str_repeat('n', 24));
        self::assertSame([24, 8], EtatSet::calculer($set)->score);
        self::assertFalse(EtatSet::calculer($set)->finAtteinte);
        $this->points($set, 'n');
        self::assertTrue(EtatSet::calculer($set)->finAtteinte);

        $court = $this->set(pointsFin: 15);
        $this->points($court, str_repeat('ne', 7).'e');
        self::assertSame([7, 8], EtatSet::calculer($court)->score);
        $this->points($court, str_repeat('e', 7));
        self::assertTrue(EtatSet::calculer($court)->finAtteinte, '15–7');

        $phase = (new Phase())->setOptions(['sets_gagnants' => 2]);
        $partie = (new Partie())->setPoule((new Poule())->setPhase($phase));
        $feuille = new FeuilleMatch($partie, new Equipe());
        self::assertSame([25, 25, 15], [FeuilleMatchService::pointsFin($feuille, 1), FeuilleMatchService::pointsFin($feuille, 2), FeuilleMatchService::pointsFin($feuille, 3)]);
        $phase->setOptions([]);
        self::assertSame([25, 15], [FeuilleMatchService::pointsFin($feuille, 4), FeuilleMatchService::pointsFin($feuille, 5)]);
    }
}
