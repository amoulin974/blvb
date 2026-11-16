<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Competition\MiseEnPlaceJournees;
use App\Competition\MiseEnPlaceMatchs;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Service\CalendrierAnalyseService;
use App\Service\OptimisationService;
use App\Service\PartieService;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\LoggerInterface;

/**
 * MEP-24 : un calcul de matchs qui échoue en cours de route ne fait perdre aucun match (transaction annulée), et
 * l'échec est journalisé sans fuite vers l'utilisateur. Le calcul de la poule est remplacé par un double qui supprime les
 * matchs puis lève une exception, comme le ferait une erreur en plein calcul.
 */
final class MiseEnPlaceMatchsEchecTest extends TestIntegration
{
    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
    }

    #[TestDox('MEP-24 Calcul interrompu par une erreur : transaction annulée, tous les matchs conservés, message neutre, erreur journalisée')]
    public function test_echec_du_calcul_conserve_les_matchs(): void
    {
        $em = self::service('doctrine')->getManager();
        $phase = $em->find(\App\Entity\Phase::class, ChampionnatDeTest::get('phase.1')->getId());
        // Phase sans score : sinon la génération complète est refusée avant le calcul. On retire les scores du jeu de test.
        foreach ($em->getRepository(Partie::class)->findAll() as $partie) {
            $partie->setNbSetGagnantReception(null);
            $partie->setNbSetGagnantDeplacement(null);
        }
        $em->flush();
        $em->getConnection()->executeStatement('DELETE FROM classement');
        $em->clear();
        $phase = $em->find(\App\Entity\Phase::class, $phase->getId());

        $avant = $em->getRepository(Partie::class)->count([]);
        self::assertGreaterThan(0, $avant);

        $pouleA = ChampionnatDeTest::get('poule.A');
        $parties = $this->createStub(PartieService::class);
        $parties->method('creerCalendrierOptimisePourPoule')->willReturnCallback(function () use ($em, $pouleA): void {
            foreach ($em->getRepository(Partie::class)->findAll() as $partie) {
                $em->remove($partie);
            }
            $em->flush();
            throw new \RuntimeException('Erreur simulée en plein calcul : '.$pouleA->getNom());
        });
        $journaux = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string $message, array $contexte = []) use (&$journaux): void {
            $journaux[] = [$message, $contexte];
        });

        $service = new MiseEnPlaceMatchs(
            $em,
            self::service(Formats::class),
            $parties,
            self::service(OptimisationService::class),
            self::service(CalendrierAnalyseService::class),
            self::service(MiseEnPlaceJournees::class),
            $logger,
        );

        $resultat = $service->appliquer($phase, 'regenerer-'.$pouleA->getId());

        self::assertFalse($resultat['ok']);
        self::assertStringContainsString('Aucun match n\'a été modifié', $resultat['message']);
        self::assertStringNotContainsString('simulée', $resultat['message'], 'le message technique ne fuit pas');
        self::assertCount(1, $journaux);
        self::assertStringContainsString('simulée', $journaux[0][1]['message']);

        $em->clear();
        self::assertSame($avant, $em->getRepository(Partie::class)->count([]), 'tous les matchs sont conservés');
        self::assertNotNull($em->find(Poule::class, $pouleA->getId()));
    }
}
