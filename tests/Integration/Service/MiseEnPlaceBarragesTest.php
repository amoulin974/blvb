<?php

namespace App\Tests\Integration\Service;

use App\Competition\CompositionPoules;
use App\Competition\MiseEnPlaceJournees;
use App\Competition\MiseEnPlaceMatchs;
use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\SaisonFactory;
use App\Service\CompetitionCreator;
use App\Service\PhaseService;
use App\Tests\Support\TestIntegration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BAR-10 (6c bis) : mise en place d'une phase de barrages (Poules, Journées, Matchs) relue dans le navigateur : phases de
 * la saison dans leur ordre, origine des équipes, nombre de journées créées, nom des barrages dans les messages.
 */
final class MiseEnPlaceBarragesTest extends TestIntegration
{
    private function em(): EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    private function phase(int $saison, string $code): Phase
    {
        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $saison, 'code' => $code]);
    }

    #[TestDox('BAR-10 Phases de la saison dans leur ordre ; barrages : « Vient de la poule A », « 3 journées créées pour 3 barrages », « Le barrage A / B n\'a aucun match »')]
    public function test_mise_en_place_des_barrages(): void
    {
        $saison = SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('2026-09-01'), 'date_fin' => new \DateTimeImmutable('2027-07-31')])->getId();
        $em = $this->em();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($em->find(Saison::class, $saison), false, new ModeleBlvbAvecBarrages());
        $em->clear();
        foreach ($this->phase($saison, 'P1')->getPoules() as $poule) {
            foreach (range(1, 8) as $n) {
                $poule->addEquipe(EquipeFactory::createOne(['nom' => $poule->getCode().$n, 'lieu' => null]));
            }
        }
        $em->flush();
        $em->clear();

        // Les phases sont créées dans le désordre des identifiants (Barrages avant Phase 2) : la saison les rend par ordre
        self::assertSame(['P1', 'BAR', 'P2', 'F', 'BAR2'], array_map(fn (Phase $p) => $p->getCode(), $em->find(Saison::class, $saison)->getPhases()->toArray()));

        self::service(PhaseService::class)->cloturerEtBasculer($this->phase($saison, 'P1'));
        $em->clear();

        $etat = self::service(CompositionPoules::class)->etat($this->phase($saison, 'BAR'));
        $origines = [];
        foreach ($etat['poules'] as $ligne) {
            foreach ($ligne['equipes'] as $e) {
                $origines[$e['equipe']->getNom()] = $e['origine']['texte'];
            }
        }
        self::assertSame('Vient de la poule A', $origines['A7']);
        self::assertSame('Vient de la poule B', $origines['B2']);

        $resultat = self::service(MiseEnPlaceJournees::class)->appliquer($this->phase($saison, 'BAR'), 'generer-toutes');
        self::assertTrue($resultat['ok']);
        self::assertSame('3 journées créées pour 3 barrages.', $resultat['message']);
        $em->clear();

        $raison = self::service(MiseEnPlaceMatchs::class)->etat($this->phase($saison, 'BAR'))['raison'];
        self::assertSame('Le barrage A / B n\'a aucun match. Générez-les pour continuer.', $raison);
    }
}
