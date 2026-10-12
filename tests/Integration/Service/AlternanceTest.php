<?php

namespace App\Tests\Integration\Service;

use App\Entity\Phase;
use App\Factory\EquipeFactory;
use App\Factory\JourneeFactory;
use App\Factory\LieuFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\OptimisationService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/** Tableau d'alternance de la page « Optimiser les matchs » (OptimisationService::getAlternanceParPoule). */
final class AlternanceTest extends TestIntegration
{
    #[TestDox('MAT-07 Statut de chaque match (Dom, Ext, Der) et break = deuxième déplacement de suite, un derby n\'en étant pas un')]
    public function test_statuts_et_breaks(): void
    {
        $phase = PhaseFactory::createOne(['saison' => SaisonFactory::createOne(['favori' => 1])]);
        $partage = LieuFactory::createOne(['nom' => 'Gymnase partagé']);
        $e = EquipeFactory::createOne(['nom' => 'E', 'lieu' => $partage]);
        $f = EquipeFactory::createOne(['nom' => 'F', 'lieu' => $partage]);
        $g = EquipeFactory::createOne(['nom' => 'G', 'lieu' => LieuFactory::createOne(['nom' => 'Gymnase G'])]);
        $h = EquipeFactory::createOne(['nom' => 'H', 'lieu' => LieuFactory::createOne(['nom' => 'Gymnase H'])]);
        $poule = PouleFactory::createOne(['phase' => $phase, 'equipes' => [$e, $f, $g, $h]]);
        $calendrier = [1 => [[$g, $e], [$h, $f]], 2 => [[$f, $e], [$h, $g]], 3 => [[$h, $e], [$f, $g]]];
        foreach ($calendrier as $numero => $matchs) {
            $journee = JourneeFactory::createOne(['poule' => $poule, 'numero' => $numero]);
            foreach ($matchs as [$recoit, $deplace]) {
                PartieFactory::createOne(['journee' => $journee, 'poule' => $poule, 'idEquipeRecoit' => $recoit, 'idEquipeDeplace' => $deplace,
                    'lieu' => $recoit->getLieu(), 'date' => new \DateTimeImmutable('2026-09-09 20:30')]);
            }
        }
        $em = self::service('doctrine')->getManager();
        $em->clear();

        [$alternance] = self::service(OptimisationService::class)->getAlternanceParPoule($em->find(Phase::class, $phase->getId()));
        $statuts = [];
        foreach ($alternance['rows'] as $row) {
            $statuts[$row['equipe']->getNomSansClub()] = implode(' ', array_map(
                fn ($c) => $c['label'].($c['break'] ? '!' : ''),
                array_values($row['cells'])
            ));
        }

        self::assertSame([
            'E' => 'Ext Der Ext',   // derby entre deux déplacements : pas de break
            'F' => 'Ext Der Dom',
            'G' => 'Dom Ext Ext!',  // deuxième déplacement de suite
            'H' => 'Dom Dom Dom',
        ], $statuts);
    }
}
