<?php

namespace App\Tests\Integration\Service;

use App\Competition\MiseEnPlaceMatchs;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\CreneauFactory;
use App\Factory\EquipeFactory;
use App\Factory\FermetureGymnaseFactory;
use App\Factory\LieuFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Service\JourneeService;
use App\Service\PartieService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * FGY-12 à FGY-16 : le placement automatique des matchs évite les gymnases fermés (D60, lot 2).
 * Saison de test : première journée la semaine du lundi 7 septembre 2026, mercredi 9 pour le créneau du mercredi.
 */
final class PlacementFermeturesTest extends TestIntegration
{
    private const MERCREDI = '2026-09-09';
    private const VENDREDI = '2026-09-11';

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    /** Un gymnase, avec un créneau par [jour, priorité] */
    private function gymnase(string $nom, array $creneaux = [[3, 2]]): object
    {
        $lieu = LieuFactory::createOne(['nom' => $nom]);
        foreach ($creneaux as [$jour, $priorite]) {
            CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => $jour, 'capacite' => 4, 'prioritaire' => $priorite, 'heureDebut' => new \DateTimeImmutable('20:30')]);
        }

        return $lieu;
    }

    /** Poule de championnat de ces équipes, chacune dans le gymnase donné ; rend l'identifiant de la phase */
    private function phase(array $gymnases, array $attributs = []): int
    {
        $saison = SaisonFactory::createOne(['nom' => 'Saison fermetures', 'favori' => 1]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'datedebut' => new \DateTimeImmutable('2026-09-07'), 'datefin' => new \DateTimeImmutable('2027-01-31')] + $attributs);
        $equipes = [];
        foreach ($gymnases as $i => $lieu) {
            $equipes[] = EquipeFactory::createOne(['nom' => 'Equipe'.($i + 1), 'lieu' => $lieu]);
        }
        $poule = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'equipes' => $equipes]);
        self::service(JourneeService::class)->creerJournees($this->em()->find(Poule::class, $poule->getId()));

        return $phase->getId();
    }

    /** @return list<Partie> matchs de ce jour */
    private function matchsDu(string $jour): array
    {
        $this->em()->clear();

        return array_values(array_filter($this->em()->getRepository(Partie::class)->findAll(), fn (Partie $m) => $m->getDate()->format('Y-m-d') === $jour));
    }

    private function generer(int $phase): array
    {
        $this->em()->clear();

        return self::service(PartieService::class)->creerCalendrierOptimise($this->em()->find(Phase::class, $phase));
    }

    #[TestDox('FGY-12 : le placement reçoit chez l\'adversaire plutôt que dans un gymnase fermé, sans rien signaler')]
    public function test_recoit_ailleurs(): void
    {
        $ferme = $this->gymnase('Gymnase fermé');
        $ouvert = $this->gymnase('Gymnase ouvert');
        $phase = $this->phase([$ferme, $ouvert]);
        FermetureGymnaseFactory::createOne(['lieu' => $ferme, 'dateDebut' => new \DateTimeImmutable(self::MERCREDI), 'dateFin' => new \DateTimeImmutable(self::MERCREDI)]);

        $rapport = $this->generer($phase);

        $matchs = $this->matchsDu(self::MERCREDI);
        self::assertCount(1, $matchs);
        self::assertSame('Gymnase ouvert', $matchs[0]->getLieu()->getNom());
        self::assertSame([], $rapport[0]['surcharges']);
    }

    #[TestDox('FGY-13 : sans autre solution, le match passe au créneau suivant du gymnase, la même semaine')]
    public function test_creneau_suivant(): void
    {
        $partage = $this->gymnase('Gymnase partagé', [[3, 2], [5, 1]]);
        $phase = $this->phase([$partage, $partage]);
        FermetureGymnaseFactory::createOne(['lieu' => $partage, 'dateDebut' => new \DateTimeImmutable(self::MERCREDI), 'dateFin' => new \DateTimeImmutable(self::MERCREDI)]);

        $rapport = $this->generer($phase);

        self::assertSame([], $this->matchsDu(self::MERCREDI));
        self::assertCount(1, $this->matchsDu(self::VENDREDI));
        self::assertSame([], $rapport[0]['surcharges']);
    }

    #[TestDox('FGY-14 : sans aucune solution, le match reste placé et il est signalé, tant qu\'il n\'a pas de score')]
    public function test_sans_solution(): void
    {
        $partage = $this->gymnase('Gymnase partagé');
        $phase = $this->phase([$partage, $partage]);
        FermetureGymnaseFactory::createOne(['lieu' => $partage, 'dateDebut' => new \DateTimeImmutable(self::MERCREDI), 'dateFin' => new \DateTimeImmutable(self::MERCREDI)]);

        $rapport = $this->generer($phase);

        self::assertCount(1, $this->matchsDu(self::MERCREDI));
        self::assertStringContainsString('le gymnase Gymnase partagé est fermé le 09/09', $rapport[0]['surcharges'][0]);
        self::assertStringContainsString('à déplacer', $rapport[0]['surcharges'][0]);

        $textes = fn () => array_column(self::service(MiseEnPlaceMatchs::class)->etat($this->em()->find(Phase::class, $phase))['problemes'], 'texte');
        self::assertStringContainsString('à déplacer', implode(' ', $textes()));

        $match = $this->matchsDu(self::MERCREDI)[0];
        $match->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(0);
        $this->em()->flush();
        $this->em()->clear();
        self::assertStringNotContainsString('à déplacer', implode(' ', $textes()), 'match joué');
    }

    #[TestDox('FGY-15 : replacer les matchs à venir évite une fermeture ajoutée après la génération, sans toucher aux matchs joués')]
    public function test_replacement(): void
    {
        $premier = $this->gymnase('Gymnase 1');
        $deuxieme = $this->gymnase('Gymnase 2');
        $troisieme = $this->gymnase('Gymnase 3');
        $quatrieme = $this->gymnase('Gymnase 4');
        $phase = $this->phase([$premier, $deuxieme, $troisieme, $quatrieme]);
        $this->generer($phase);

        // Le premier match joué reste tel quel, même si son gymnase ferme ensuite
        $joue = $this->matchsDu(self::MERCREDI)[0];
        $joue->setNbSetGagnantReception(3)->setNbSetGagnantDeplacement(0);
        $this->em()->flush();
        $idJoue = $joue->getId();
        $lieuJoue = $joue->getLieu()->getNom();
        FermetureGymnaseFactory::createOne(['lieu' => $joue->getLieu(), 'dateDebut' => new \DateTimeImmutable(self::MERCREDI), 'dateFin' => new \DateTimeImmutable(self::MERCREDI)]);

        // Les gymnases qui reçoivent la journée 2 ferment ce jour-là
        $mercredi2 = '2026-09-16';
        $fermes = [];
        foreach ($this->matchsDu($mercredi2) as $match) {
            $fermes[] = $match->getLieu()->getNom();
            FermetureGymnaseFactory::createOne(['lieu' => $match->getLieu(), 'dateDebut' => new \DateTimeImmutable($mercredi2), 'dateFin' => new \DateTimeImmutable($mercredi2)]);
        }

        $this->em()->clear();
        self::service(PartieService::class)->replacerMatchsAVenir($this->em()->find(Phase::class, $phase));

        $this->em()->clear();
        self::assertSame($lieuJoue, $this->em()->find(Partie::class, $idJoue)->getLieu()->getNom());
        foreach ($this->matchsDu($mercredi2) as $match) {
            self::assertNotContains($match->getLieu()->getNom(), $fermes, 'match replacé hors des gymnases fermés');
        }
    }

    #[TestDox('FGY-16 : un barrage prend le créneau suivant ouvert')]
    public function test_barrage(): void
    {
        $partage = $this->gymnase('Gymnase du barrage', [[3, 2], [5, 1]]);
        $phase = $this->phase([$partage, $partage], ['type' => PhaseType::BARRAGE]);
        FermetureGymnaseFactory::createOne(['lieu' => $partage, 'dateDebut' => new \DateTimeImmutable(self::MERCREDI), 'dateFin' => new \DateTimeImmutable(self::MERCREDI)]);

        $this->generer($phase);

        self::assertSame([], $this->matchsDu(self::MERCREDI));
        self::assertCount(1, $this->matchsDu(self::VENDREDI));
    }
}
