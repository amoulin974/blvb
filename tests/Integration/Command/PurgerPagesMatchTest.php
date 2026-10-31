<?php

namespace App\Tests\Integration\Command;

use App\Command\PurgerPagesMatchCommand;
use App\Entity\FeuilleMatch;
use App\Factory\EquipeFactory;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** VST-24 : conservation des pages du match (D66) : supprimées une fois la saison suivante terminée (app:volley-stats:purger). */
final class PurgerPagesMatchTest extends TestIntegration
{
    private function feuille(object $saison): void
    {
        $phase = PhaseFactory::createOne(['saison' => $saison]);
        $poule = PouleFactory::createOne(['phase' => $phase]);
        [$a, $b] = [EquipeFactory::createOne(), EquipeFactory::createOne()];
        $journee = JourneeFactory::createOne(['poule' => $poule]);
        $partie = PartieFactory::createOne(['journee' => $journee, 'poule' => $poule, 'idEquipeRecoit' => $a, 'idEquipeDeplace' => $b, 'date' => $saison->getDateDebut()]);
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist(new FeuilleMatch($partie, $a));
        $em->flush();
    }

    #[TestDox('VST-24 : les pages d\'une saison sont supprimées quand la saison suivante est terminée, pas avant ; la simulation ne supprime rien')]
    public function test_purge(): void
    {
        $ancienne = SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('-3 years'), 'date_fin' => new \DateTimeImmutable('-2 years -6 months')]);
        SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('-2 years -5 months'), 'date_fin' => new \DateTimeImmutable('-1 year -6 months')]);
        $precedente = SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('-1 year -5 months'), 'date_fin' => new \DateTimeImmutable('-1 month')]);
        SaisonFactory::createOne(['date_debut' => new \DateTimeImmutable('-1 week'), 'date_fin' => new \DateTimeImmutable('+10 months')]);
        $this->feuille($ancienne);
        $this->feuille($precedente);
        $compter = fn () => self::getContainer()->get('doctrine')->getManager()->getRepository(FeuilleMatch::class)->count([]);

        $commande = new CommandTester((new Application(self::$kernel))->find('app:volley-stats:purger'));
        $commande->execute(['--simulation' => true]);
        self::assertSame(2, $compter(), 'simulation');
        $commande->execute([]);
        $commande->assertCommandIsSuccessful();
        self::assertSame(1, $compter(), 'seule la page de la saison dont la suivante est terminée');
        self::assertStringContainsString('1 page du match supprimée', $commande->getDisplay());
    }
}
