<?php

namespace App\Tests\Unitaire\VolleyStats;

use App\Entity\Equipe;
use App\Entity\FeuilleMatch;
use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;
use App\Entity\Partie;
use App\VolleyStats\StatsFeuille;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/** VST-19 : statistiques d'un set noté « le score et les touches », calculées depuis le journal (App\VolleyStats\StatsFeuille, D68). */
final class StatsFeuilleTest extends TestCase
{
    private int $ordre = 0;

    /** Joueurs 1 à 6 aux postes 1 à 6 (2, 3, 4 devant) ; 7 sur le banc */
    private function set(string $service = 'eux'): FeuilleMatchSet
    {
        $set = new FeuilleMatchSet(new FeuilleMatch(new Partie(), new Equipe()), 1, 25);

        return $set->setPostes([1, 2, 3, 4, 5, 6])->setPremierService($service)->setSaisie(FeuilleMatchSet::SAISIE_TOUCHES);
    }

    /** @param array<string, mixed> $donnees */
    private function e(FeuilleMatchSet $set, string $type, array $donnees = []): string
    {
        $uuid = 'u'.++$this->ordre;
        $set->ajouterEvenement(new FeuilleMatchEvenement($set, $this->ordre, $uuid, $type, $donnees, null));

        return $uuid;
    }

    private function touche(FeuilleMatchSet $set, string $action, int $joueur): string
    {
        return $this->e($set, 'action', ['action' => $action, 'joueurs' => [$joueur]]);
    }

    #[TestDox('VST-19 : réception, passe décisive et attaque gagnante ; attaque contrée ; ace subi ; ace et faute de notre serveur (déduit de la rotation)')]
    public function test_echanges(): void
    {
        $set = $this->set();
        // Réception 5, passe 6, attaque 4 gagnante : nous gagnons le service et tournons (le joueur 2 sert)
        $this->touche($set, 'reception', 5);
        $this->touche($set, 'passe', 6);
        $a = $this->touche($set, 'attaque', 4);
        $this->e($set, 'point', ['pour' => 'nous', 'sur' => $a]);
        // Ace de notre serveur (2), puis faute de service
        $this->e($set, 'point', ['pour' => 'nous', 'sur' => 'service']);
        $this->e($set, 'point', ['pour' => 'eux', 'sur' => 'service']);
        // Ace subi sans contact
        $this->e($set, 'point', ['pour' => 'eux']);
        // Réception 6, passe 1, attaque 3 contrée
        $this->touche($set, 'reception', 6);
        $this->touche($set, 'passe', 1);
        $c = $this->touche($set, 'attaque', 3);
        $this->e($set, 'point', ['pour' => 'eux', 'sur' => $c, 'detail' => 'contree']);

        $s = StatsFeuille::calculer($set);
        self::assertSame([1, 1, 1, 1], [$s['joueurs'][5]['receptions'], $s['joueurs'][6]['passes'], $s['joueurs'][6]['passesDecisives'], $s['joueurs'][4]['attaquesGagnantes']]);
        self::assertSame([2, 1, 1], [$s['joueurs'][2]['services'], $s['joueurs'][2]['aces'], $s['joueurs'][2]['fautesService']], 'serveur déduit de la rotation');
        self::assertSame(1, $s['acesSubis']);
        self::assertSame([1, 0, 1], [$s['joueurs'][3]['attaques'], $s['joueurs'][3]['fautesAttaque'], $s['joueurs'][3]['attaquesContrees']]);
        self::assertSame(-100, StatsFeuille::efficaciteAttaque($s['joueurs'][3]));
        self::assertSame(100, StatsFeuille::efficaciteAttaque($s['joueurs'][4]));
        self::assertSame(2, $s['equipe']['pointsMarques'], 'attaque gagnante et ace');
        self::assertSame([5, 5], [$s['echanges'], $s['detailles']]);
    }

    #[TestDox('VST-19 : block gagnant à deux (les deux crédités), point marqué en défense, faute de règlement, faute de réception (% sans faute)')]
    public function test_block_defense_fautes(): void
    {
        $set = $this->set('nous');
        $b = $this->touche($set, 'block', 3);
        $this->e($set, 'point', ['pour' => 'nous', 'sur' => $b, 'avec' => 4]);
        $d = $this->touche($set, 'defense', 5);
        $this->e($set, 'point', ['pour' => 'nous', 'sur' => $d]);
        $this->e($set, 'point', ['pour' => 'eux', 'detail' => 'filet', 'fautif' => 2]);
        $r = $this->touche($set, 'reception', 6);
        $this->e($set, 'point', ['pour' => 'eux', 'sur' => $r]);
        $this->touche($set, 'reception', 6);
        $this->e($set, 'point', ['pour' => 'nous', 'sansDetail' => true]);

        $s = StatsFeuille::calculer($set);
        self::assertSame([1, 1], [$s['joueurs'][3]['blocksGagnants'], $s['joueurs'][4]['blocksGagnants']], 'deux bloqueurs crédités');
        self::assertSame(1, $s['joueurs'][5]['pointsMarques'], 'point marqué en défense');
        self::assertSame(0, $s['equipe']['attaquesGagnantes'], 'pas compté en attaque');
        self::assertSame(1, $s['joueurs'][2]['fautesReglement']);
        self::assertSame([1, 1], [$s['joueurs'][6]['receptions'], $s['joueurs'][6]['fautesReception']], 'la touche d\'un échange sans détail ne compte pas');
        self::assertSame(0, StatsFeuille::receptionSansFaute($s['joueurs'][6]));
        self::assertSame([5, 4], [$s['echanges'], $s['detailles']], 'un échange sans détail');
    }

    #[TestDox('VST-19 : balle à rejouer et touches annulées non comptées ; corrections appliquées (joueur, action, touche retirée) ; set « score seulement » sans stats')]
    public function test_rejouer_annulations_corrections(): void
    {
        $set = $this->set();
        $this->touche($set, 'reception', 5);
        $this->e($set, 'rejouer');
        $r = $this->touche($set, 'reception', 1);
        $p = $this->touche($set, 'passe', 6);
        $x = $this->touche($set, 'attaque', 2);
        $a = $this->touche($set, 'attaque', 4);
        $this->e($set, 'point', ['pour' => 'nous', 'sur' => $a]);
        $this->e($set, 'modification', ['cible' => $r, 'joueurs' => [5]]);
        $this->e($set, 'modification', ['cible' => $x, 'retirer' => true]);
        $this->e($set, 'modification', ['cible' => $p, 'action' => 'defense']);
        $annule = new FeuilleMatchEvenement($set, ++$this->ordre, 'annule', 'action', ['action' => 'passe', 'joueurs' => [3]], null);
        $annule->annuler();
        $set->ajouterEvenement($annule);

        $s = StatsFeuille::calculer($set);
        self::assertArrayNotHasKey(1, $s['joueurs'], 'réception corrigée : jouée par 5');
        self::assertSame(1, $s['joueurs'][5]['receptions'], 'la réception de la balle à rejouer ne compte pas');
        self::assertSame([1, 0, 0], [$s['joueurs'][6]['defenses'], $s['joueurs'][6]['passes'], $s['joueurs'][6]['passesDecisives']], 'passe devenue défense : plus de passe décisive');
        self::assertArrayNotHasKey(2, $s['joueurs'], 'touche retirée');
        self::assertArrayNotHasKey(3, $s['joueurs'], 'touche annulée');
        self::assertSame(1, $s['echanges'], 'la balle à rejouer n\'est pas un échange');

        $score = $this->set()->setSaisie(FeuilleMatchSet::SAISIE_SCORE);
        $this->e($score, 'point', ['pour' => 'nous']);
        self::assertSame(0, StatsFeuille::calculer($score)['echanges']);
        self::assertSame(2, StatsFeuille::cumuler([$s, $s])['joueurs'][5]['receptions']);
    }
}
