<?php

namespace App\Tests\Integration\Service;

use App\Competition\Format\Formats;
use App\Competition\Format\PlanningFinales;
use App\Competition\VerificationSaison;
use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\EquipeFactory;
use App\Factory\LieuFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * JFI-01 à JFI-05 : journée des finales (C3, D45, D46) : derniers tours de tous les tableaux le même jour, dans un
 * gymnase, par créneaux d'autant de matchs que de terrains ; finale du niveau 1 en dernier.
 */
final class JourneeFinalesTest extends TestIntegration
{
    private object $saison;
    private object $gymnase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saison = SaisonFactory::createOne(['nom' => 'Saison finales', 'favori' => 1,
            'date_debut' => new \DateTimeImmutable('2026-09-01'), 'date_fin' => new \DateTimeImmutable('2027-07-31')]);
        $this->gymnase = LieuFactory::createOne(['nom' => 'Gymnase des finales']);
    }

    /** Phase finale du 01/06/2027 au 31/07/2027, journée des finales le vendredi 25/06/2027 à 19 h. */
    private function phase(array $finales, array $autres = []): object
    {
        return PhaseFactory::createOne(['saison' => $this->saison, 'nom' => 'Phase finale', 'code' => 'F', 'ordre' => 1, 'type' => PhaseType::FINALE,
            'datedebut' => new \DateTimeImmutable('2027-06-01'), 'datefin' => new \DateTimeImmutable('2027-07-31'),
            'options' => $autres + ['journee_finales' => $finales + ['tours' => Phase::TOURS_FINALES, 'lieu' => $this->gymnase->getId(),
                'date' => '2027-06-25', 'heure' => '19:00', 'terrains' => 2, 'duree' => 90]]]);
    }

    private function tableau(object $phase, int $niveau, int $nbEquipes): object
    {
        $equipes = [];
        for ($i = 1; $i <= $nbEquipes; ++$i) {
            $equipes[] = EquipeFactory::createOne(['nom' => "N$niveau-$i", 'lieu' => null]);
        }

        return PouleFactory::createOne(['phase' => $phase, 'nom' => "Tableau $niveau", 'code' => "T$niveau", 'niveau' => $niveau, 'equipes' => $equipes]);
    }

    /** Journées puis matchs de chaque tableau, comme l'étape Matchs ; renvoie les messages d'erreur des journées. */
    private function generer(object $phase): array
    {
        $em = self::service('doctrine')->getManager();
        $messages = [];
        foreach ([true, false] as $journees) {
            $em->clear();
            foreach ($em->find(Phase::class, $phase->getId())->getPoules() as $poule) {
                $generateur = self::service(Formats::class)->pourPhase($poule->getPhase());
                if ($journees) {
                    $messages[] = $generateur->creerJournees($poule);
                } else {
                    $generateur->genererMatchs($poule);
                }
            }
        }
        // Comme la génération de la phase (PartieService::creerCalendrierOptimise) : planning de toute la journée à la fin
        self::service(PlanningFinales::class)->planifier($em->find(Phase::class, $phase->getId()));
        $em->clear();

        return array_values(array_filter($messages));
    }

    /** @return list<string> matchs du jour des finales, dans l'ordre des heures : « 19:00 Tableau 2 Petite finale » */
    private function planning(): array
    {
        $matchs = array_filter(
            self::service('doctrine')->getManager()->getRepository(Partie::class)->findAll(),
            fn (Partie $m) => $m->getDate()->format('Y-m-d') === '2027-06-25'
        );
        usort($matchs, fn (Partie $a, Partie $b) => [$a->getDate(), $a->getPoule()->getNiveau() * -1, $a->isParentPerdants() ? 0 : 1, $a->getNom()] <=> [$b->getDate(), $b->getPoule()->getNiveau() * -1, $b->isParentPerdants() ? 0 : 1, $b->getNom()]);

        return array_map(fn (Partie $m) => sprintf('%s %s %s @%s', $m->getDate()->format('H:i'), $m->getPoule()->getNom(),
            $m->isParentPerdants() ? 'Petite finale' : $m->getJournee()->getNom(), $m->getLieu()?->getNom()), $matchs);
    }

    #[TestDox('JFI-01 Finales seules : petites finales et finales de tous les tableaux le même jour au gymnase choisi, du niveau le plus bas au niveau 1, 2 terrains')]
    public function test_finales_seules(): void
    {
        $phase = $this->phase([], ['petite_finale' => true]);
        $this->tableau($phase, 1, 8);
        $this->tableau($phase, 2, 4);

        self::assertSame([], $this->generer($phase));

        self::assertSame([
            '19:00 Tableau 2 Petite finale @Gymnase des finales',
            '19:00 Tableau 2 Finale @Gymnase des finales',
            '20:30 Tableau 1 Petite finale @Gymnase des finales',
            '20:30 Tableau 1 Finale @Gymnase des finales',
        ], $this->planning());

        // Les tours précédents restent un par semaine, depuis le début de la phase
        $journees = self::service('doctrine')->getManager()->getRepository(Journee::class)->findBy([], ['numero' => 'ASC']);
        $tableau1 = array_values(array_filter($journees, fn (Journee $j) => $j->getPoule()->getNiveau() === 1));
        self::assertSame(['Quart de finale 2027-06-01', 'Demi-finale 2027-06-08', 'Finale 2027-06-25'],
            array_map(fn (Journee $j) => $j->getNom().' '.$j->getDateDebut()->format('Y-m-d'), $tableau1));
    }

    #[TestDox('JFI-02 Un seul terrain : un match par créneau de 90 minutes, la finale du niveau 1 en dernier')]
    public function test_un_terrain(): void
    {
        $phase = $this->phase(['terrains' => 1]);
        $this->tableau($phase, 1, 4);
        $this->tableau($phase, 2, 2);
        $this->tableau($phase, 3, 4);

        $this->generer($phase);

        self::assertSame([
            '19:00 Tableau 3 Finale @Gymnase des finales',
            '20:30 Tableau 2 Finale @Gymnase des finales',
            '22:00 Tableau 1 Finale @Gymnase des finales',
        ], $this->planning());
    }

    #[TestDox('JFI-03 Demi-finales et finales : toutes les demi-finales d\'abord, les finales à un créneau suivant, même avec des terrains libres')]
    public function test_demi_finales_et_finales(): void
    {
        $phase = $this->phase(['tours' => Phase::TOURS_DEMI_FINALES, 'terrains' => 4]);
        $this->tableau($phase, 1, 4);
        $this->tableau($phase, 2, 2); // pas de demi-finale : sa finale se joue avec les autres finales

        $this->generer($phase);

        self::assertSame([
            '19:00 Tableau 1 Demi-finale @Gymnase des finales',
            '19:00 Tableau 1 Demi-finale @Gymnase des finales',
            '20:30 Tableau 2 Finale @Gymnase des finales',
            '20:30 Tableau 1 Finale @Gymnase des finales',
        ], $this->planning());
    }

    #[TestDox('JFI-03 Régénérer un tableau ne déplace pas la finale d\'un autre tableau retouchée à la main')]
    public function test_regenerer_un_tableau(): void
    {
        $phase = $this->phase([]);
        $this->tableau($phase, 1, 2);
        $t2 = $this->tableau($phase, 2, 2);
        $this->generer($phase);
        $em = self::service('doctrine')->getManager();
        $finale1 = $em->getRepository(Partie::class)->findOneBy(['poule' => $em->getRepository(Poule::class)->findOneBy(['nom' => 'Tableau 1'])]);
        $finale1->setDate(new \DateTimeImmutable('2027-06-25 21:45'));
        $em->flush();
        $em->clear();

        $poule = $em->find(Poule::class, $t2->getId());
        foreach ($poule->getParties() as $match) {
            $em->remove($match);
        }
        $em->flush();
        self::service(Formats::class)->pourPhase($poule->getPhase())->genererMatchs($poule);
        $em->clear();

        self::assertSame(['19:00 Tableau 2 Finale @Gymnase des finales', '21:45 Tableau 1 Finale @Gymnase des finales'], $this->planning());
    }

    #[TestDox('JFI-04 Date trop proche du début de la phase pour les tours précédents : message, aucune journée créée')]
    public function test_date_trop_tot(): void
    {
        $phase = $this->phase(['date' => '2027-06-10']);
        $this->tableau($phase, 1, 8); // quarts et demi-finales : deux semaines, jusqu'au 14/06

        $messages = $this->generer($phase);

        self::assertSame(['Journée des finales trop tôt (10/06/2027) : les 2 tours précédents se jouent une semaine chacun, du 01/06/2027 au 14/06/2027. Choisissez une date après le 14/06/2027.'], $messages);
        self::assertSame(0, self::service('doctrine')->getManager()->getRepository(Journee::class)->count([]));
    }

    #[TestDox('JFI-04 Un seul tour avant la journée des finales : message au singulier ; aussi signalé par la vérification de la saison')]
    public function test_date_trop_tot_un_tour(): void
    {
        $phase = $this->phase(['date' => '2027-06-04']);
        $this->tableau($phase, 1, 4); // demi-finales la semaine du 01/06 au 07/06

        $attendu = 'Journée des finales trop tôt (04/06/2027) : le tour précédent se joue la semaine du 01/06/2027 au 07/06/2027. Choisissez une date après le 07/06/2027.';
        $em = self::service('doctrine')->getManager();
        $em->clear();
        $erreurs = array_column(VerificationSaison::verifier($em->find(Phase::class, $phase->getId())->getSaison())['erreurs'], 'texte');
        self::assertContains('Phase finale : '.$attendu, $erreurs);
        self::assertSame([$attendu], $this->generer($phase));
    }

    #[TestDox('JFI-05 Vérification de la saison : gymnase manquant, date hors de la phase')]
    public function test_verification(): void
    {
        $phase = $this->phase(['lieu' => null, 'date' => '2027-08-15']);
        $this->tableau($phase, 1, 4);
        $em = self::service('doctrine')->getManager();
        $em->clear();

        $erreurs = array_column(VerificationSaison::verifier($em->find(Phase::class, $phase->getId())->getSaison())['erreurs'], 'texte');

        self::assertContains('Phase finale : choisissez le gymnase de la journée des finales.', $erreurs);
        self::assertContains('Phase finale : la journée des finales (15/08/2027) doit avoir lieu pendant la phase (du 01/06/2027 au 31/07/2027).', $erreurs);
    }
}
