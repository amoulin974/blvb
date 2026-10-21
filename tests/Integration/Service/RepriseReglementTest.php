<?php

namespace App\Tests\Integration\Service;

use App\Entity\Saison;
use App\Reglement\EditionReglement;
use App\Reglement\RenduReglement;
use App\Repository\ReglementRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/** RGL-23 : reprise du règlement 2025 dans la saison en cours (app:reglement:importer, étape R5, D57). */
final class RepriseReglementTest extends TestIntegration
{
    private function lancer(array $options = []): CommandTester
    {
        $saisonId = (string) ChampionnatDeTest::get('saison')->getId();
        $tester = new CommandTester((new Application(self::$kernel))->find('app:reglement:importer'));
        $tester->execute($options + ['--saison' => $saisonId]);

        return $tester;
    }

    private function contenu(): ?string
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return self::getContainer()->get(ReglementRepository::class)
            ->findDeSaison($em->find(Saison::class, ChampionnatDeTest::get('saison')->getId()))?->getContenu();
    }

    #[TestDox('RGL-23 Reprise du règlement 2025 : simulation sans écriture ; --force verse le texte nettoyé (titres, tableaux, images du site centrées, ancre #regle-1r) ; un règlement déjà rédigé n\'est remplacé qu\'avec --remplacer, l\'ancien restant dans l\'historique')]
    public function test_reprise(): void
    {
        $simulation = $this->lancer();
        self::assertSame(0, $simulation->getStatusCode());
        self::assertStringContainsString('Simulation', $simulation->getDisplay());
        self::assertStringContainsString('présente', $simulation->getDisplay(), 'ancre #regle-1r');
        self::assertNull($this->contenu());

        $this->lancer(['--force' => true]);
        $contenu = (string) $this->contenu();
        self::assertMatchesRegularExpression('#<h3>RÈGLE 1R : Partie interrompue\s*</h3>#u', $contenu);
        self::assertStringContainsString('<img class="aligne-centre" src="/images/reglement/schema-2025.jpg" alt="Schéma du fonctionnement du championnat 2025-2026" />', $contenu);
        self::assertStringContainsString('<th>Score</th><th>Point gagnant</th>', preg_replace('/>\s+</', '><', $contenu));
        self::assertStringNotContainsString('{{', $contenu);
        $saison = self::getContainer()->get('doctrine')->getManager()->find(Saison::class, ChampionnatDeTest::get('saison')->getId());
        self::assertStringContainsString('id="regle-1r"', self::getContainer()->get(RenduReglement::class)->rendre($saison, $contenu)['html']);

        // Déjà rédigé : refus sans --remplacer ; avec, l'ancien texte devient une version
        self::getContainer()->get(EditionReglement::class)->enregistrer($saison, '<p>Texte du bureau</p>', null);
        self::assertSame(1, $this->lancer(['--force' => true])->getStatusCode());
        self::assertSame('<p>Texte du bureau</p>', $this->contenu());
        self::assertSame(0, $this->lancer(['--force' => true, '--remplacer' => true])->getStatusCode());
        self::assertStringContainsString('RÈGLE 1R', (string) $this->contenu());
        $versions = self::getContainer()->get(ReglementRepository::class)->findDeSaison($saison)->getVersions();
        self::assertContains('<p>Texte du bureau</p>', $versions->map(fn ($v) => $v->getContenu())->toArray());
    }
}
