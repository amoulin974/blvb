<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\ClassementFactory;
use App\Factory\EquipeFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * ELI-02 à ELI-04 : tableau à élimination (B1, B2) : têtes de série selon le classement de la phase précédente,
 * placement standard, exemptions quand le nombre d'équipes n'est pas une puissance de 2, petite finale.
 */
final class EliminationTableauTest extends TestIntegration
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    /**
     * Phase 1 (classement E01 … En) puis tableau à $n équipes ; les équipes sont ajoutées au tableau dans le désordre pour
     * vérifier que les têtes de série viennent du classement et non de l'ordre d'insertion. Génère journées et matchs.
     *
     * @return list<Partie> matchs du tableau
     */
    private function tableau(int $n, array $options = []): array
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison tableau', 'favori' => 1]);
        $p1 = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 1', 'code' => 'P1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT]);
        $finale = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Finale', 'code' => 'F', 'ordre' => 2, 'type' => PhaseType::FINALE,
            'options' => $options ?: null, 'datedebut' => new \DateTimeImmutable('2027-06-01'), 'datefin' => new \DateTimeImmutable('2027-07-31')]);

        $equipes = [];
        foreach (range(1, $n) as $rang) {
            $equipes[$rang] = EquipeFactory::createOne(['nom' => sprintf('E%02d', $rang), 'lieu' => null]);
        }
        $source = PouleFactory::createOne(['phase' => $p1, 'nom' => 'Poule A', 'code' => 'A', 'niveau' => 1, 'equipes' => array_values($equipes)]);
        foreach ($equipes as $rang => $equipe) {
            ClassementFactory::createOne(['poule' => $source, 'equipe' => $equipe, 'position' => $rang, 'points' => 100 - $rang, 'set_gagnes' => 0, 'set_perdus' => 0]);
        }
        $ordre = array_values($equipes);
        shuffle($ordre);
        $tableau = PouleFactory::createOne(['phase' => $finale, 'nom' => 'Tableau A', 'code' => 'A', 'niveau' => 1, 'equipes' => $ordre]);

        $em = $this->em();
        $em->clear();
        $poule = $em->find(Poule::class, $tableau->getId());
        $generateur = self::service(Formats::class)->pourPhase($poule->getPhase());
        self::assertNull($generateur->creerJournees($poule));
        $em->clear();
        $poule = $em->find(Poule::class, $tableau->getId());
        $generateur->genererMatchs($poule);
        $em->clear();

        return array_values($em->find(Poule::class, $tableau->getId())->getParties()->toArray());
    }

    /** Équipes d'un match sous la forme « E05-E12 » (réception - déplacement), « ? » si elle n'est pas connue. */
    private static function affiche(Partie $match): string
    {
        return ($match->getIdEquipeRecoit()?->getNom() ?? '?').'-'.($match->getIdEquipeDeplace()?->getNom() ?? '?');
    }

    /** @param list<Partie> $matchs @return list<string> */
    private static function premierTour(array $matchs, string $journee): array
    {
        $liste = array_map(fn (Partie $m) => self::affiche($m), array_filter($matchs, fn (Partie $m) => $m->getJournee()->getNom() === $journee));
        sort($liste);

        return array_values($liste);
    }

    #[TestDox('ELI-02 8 équipes : quarts de finale 1-8, 4-5, 2-7, 3-6 selon le classement, 7 matchs, pas de barrage')]
    public function test_huit_equipes(): void
    {
        $matchs = $this->tableau(8);

        self::assertCount(7, $matchs);
        self::assertSame(['E01-E08', 'E02-E07', 'E03-E06', 'E04-E05'], self::premierTour($matchs, 'Quart de finale'));
        // 1er et 2e dans des moitiés opposées : la finale relie deux demi-finales, chacune reliée à deux quarts
        $finale = array_values(array_filter($matchs, fn (Partie $m) => $m->getJournee()->getNom() === 'Finale'))[0];
        self::assertNotNull($finale->getParentMatch1());
        self::assertNotNull($finale->getParentMatch2());
        $parentsDemi1 = [$finale->getParentMatch1()->getParentMatch1(), $finale->getParentMatch1()->getParentMatch2()];
        $equipesHaut = array_map(fn (Partie $m) => self::affiche($m), $parentsDemi1);
        sort($equipesHaut);
        self::assertSame(['E01-E08', 'E04-E05'], $equipesHaut, 'le 1er et le 4e dans la même moitié, le 2e dans l\'autre');
    }

    #[TestDox('ELI-03 12 équipes : barrages 5-12, 6-11, 7-10, 8-9 ; les 4 premières exemptées et opposées aux vainqueurs (1 contre 8-9…)')]
    public function test_douze_equipes_avec_exemptions(): void
    {
        $matchs = $this->tableau(12);

        self::assertCount(11, $matchs, 'n - 1 matchs');
        self::assertSame(['E05-E12', 'E06-E11', 'E07-E10', 'E08-E09'], self::premierTour($matchs, 'Barrages'));

        // Quarts : une tête de série exemptée contre le vainqueur d'un barrage
        $quarts = array_values(array_filter($matchs, fn (Partie $m) => $m->getJournee()->getNom() === 'Quart de finale'));
        self::assertCount(4, $quarts);
        $paires = [];
        foreach ($quarts as $quart) {
            self::assertNotNull($quart->getIdEquipeRecoit(), 'tête de série exemptée');
            self::assertNull($quart->getIdEquipeDeplace());
            $paires[$quart->getIdEquipeRecoit()->getNom()] = self::affiche($quart->getParentMatch2());
        }
        ksort($paires);
        self::assertSame(['E01' => 'E08-E09', 'E02' => 'E07-E10', 'E03' => 'E06-E11', 'E04' => 'E05-E12'], $paires);
    }

    #[TestDox('ELI-03 9 équipes : un seul barrage 8-9 ; le 1er l\'attend, les têtes de série 2 à 8 restent opposées 4-5, 2-7, 3-6')]
    public function test_neuf_equipes(): void
    {
        $matchs = $this->tableau(9);

        self::assertCount(8, $matchs);
        self::assertSame(['E08-E09'], self::premierTour($matchs, 'Barrages'));
        $quarts = self::premierTour($matchs, 'Quart de finale');
        self::assertContains('E04-E05', $quarts);
        self::assertContains('E02-E07', $quarts);
        self::assertContains('E03-E06', $quarts);
        self::assertContains('E01-?', $quarts, 'le 1er attend le vainqueur du barrage');
    }

    #[TestDox('ELI-04 Petite finale (option de la phase) : perdants des deux demi-finales, jouée avec la finale ; pas de petite finale sous 4 équipes')]
    public function test_petite_finale(): void
    {
        $matchs = $this->tableau(8, ['petite_finale' => true]);

        self::assertCount(8, $matchs);
        $petite = array_values(array_filter($matchs, fn (Partie $m) => $m->getNom() === 'Petite finale'));
        self::assertCount(1, $petite);
        self::assertTrue($petite[0]->isParentPerdants());
        self::assertSame('Finale', $petite[0]->getJournee()->getNom());
        self::assertStringStartsWith('Perdant', $petite[0]->getNomReception());
        $finale = array_values(array_filter($matchs, fn (Partie $m) => $m->getNom() === 'Finale - Match 1'))[0];
        self::assertSame($finale->getParentMatch1()->getId(), $petite[0]->getParentMatch1()->getId(), 'mêmes demi-finales que la finale');
        self::assertFalse($finale->isParentPerdants());

        self::assertCount(1, $this->tableau(2, ['petite_finale' => true]), 'deux équipes : la finale seule');
    }
}
