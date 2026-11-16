<?php

namespace App\Tests\Integration\Service;

use App\Entity\Classement;
use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Factory\EquipeFactory;
use App\Factory\JourneeFactory;
use App\Factory\PartieFactory;
use App\Factory\PouleFactory;
use App\Repository\ClassementRepository;
use App\Service\ClassementService;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SET-02 : le classement d'une poule suit le format de match de sa phase (D48). Barème de la saison de test :
 * victoire forte 5, victoire faible 4, défaite forte 2, défaite faible 1, forfait -3.
 */
final class FormatMatchClassementTest extends TestIntegration
{
    /** @var array<string, Equipe> */
    private array $equipes = [];
    private object $journee;

    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
    }

    private function poule(int $setsGagnants, string ...$noms): Poule
    {
        $em = self::service('doctrine')->getManager();
        $em->find(Phase::class, ChampionnatDeTest::get('phase.1')->getId())->setSetsGagnants($setsGagnants);
        $em->flush();

        foreach ($noms as $nom) {
            $this->equipes[$nom] = EquipeFactory::createOne(['nom' => $nom, 'lieu' => null]);
        }
        $poule = PouleFactory::createOne(['phase' => ChampionnatDeTest::get('phase.1'), 'nom' => 'Poule format court', 'niveau' => 9, 'equipes' => array_values($this->equipes)]);
        $this->journee = JourneeFactory::createOne(['poule' => $poule, 'numero' => 1,
            'date_debut' => new \DateTimeImmutable('-20 days'), 'date_fin' => new \DateTimeImmutable('-1 day')]);

        return $poule;
    }

    private function match(Poule $poule, string $recoit, string $deplace, ?int $setsR, ?int $setsD): void
    {
        PartieFactory::createOne(['journee' => $this->journee, 'poule' => $poule, 'idEquipeRecoit' => $this->equipes[$recoit],
            'idEquipeDeplace' => $this->equipes[$deplace], 'lieu' => null, 'date' => new \DateTimeImmutable('-10 days'),
            'nbSetGagnantReception' => $setsR, 'nbSetGagnantDeplacement' => $setsD]);
    }

    /** nom => [points, sets gagnés, sets perdus] */
    private function classement(Poule $poule): array
    {
        $em = self::service('doctrine')->getManager();
        $em->clear();
        self::service(ClassementService::class)->mettreAJourClassementPoule($em->find(Poule::class, $poule->getId()));
        $em->clear();

        $lignes = [];
        foreach (self::service(ClassementRepository::class)->findBy(['poule' => $poule->getId()], ['position' => 'ASC']) as $c) {
            /** @var Classement $c */
            $lignes[$c->getEquipe()->getNomSansClub()] = [$c->getPoints(), $c->getSetGagnes(), $c->getSetPerdus()];
        }

        return $lignes;
    }

    #[TestDox('SET-02 En 2 sets gagnants : 2-0 victoire forte, 2-1 victoire faible, 1-2 défaite forte, 0-2 défaite faible')]
    public function test_points_en_deux_sets(): void
    {
        $poule = $this->poule(2, 'Alpha', 'Beta', 'Gamma', 'Delta');
        $this->match($poule, 'Alpha', 'Beta', 2, 0);
        $this->match($poule, 'Gamma', 'Delta', 2, 1);

        $c = $this->classement($poule);
        self::assertSame([5, 2, 0], $c['Alpha'], 'victoire forte');
        self::assertSame([1, 0, 2], $c['Beta'], 'défaite faible');
        self::assertSame([4, 2, 1], $c['Gamma'], 'victoire faible');
        self::assertSame([2, 1, 2], $c['Delta'], 'défaite forte');
    }

    #[TestDox('SET-02 Forfait en 2 sets : la victoire par forfait vaut une victoire forte, l\'équipe forfait les points de forfait')]
    public function test_forfait_en_deux_sets(): void
    {
        $poule = $this->poule(2, 'Alpha', 'Beta');
        $this->match($poule, 'Alpha', 'Beta', 2, -1);

        $c = $this->classement($poule);
        self::assertSame([5, 2, 0], $c['Alpha']);
        self::assertSame([-3, 0, 2], $c['Beta']);
    }

    #[TestDox('SET-02 Match interrompu en 2 sets : 1-1 et 1-0 sans point pour les deux équipes')]
    public function test_interrompu_en_deux_sets(): void
    {
        $poule = $this->poule(2, 'Alpha', 'Beta', 'Gamma', 'Delta');
        $this->match($poule, 'Alpha', 'Beta', 1, 1);
        $this->match($poule, 'Gamma', 'Delta', 1, 0);

        $c = $this->classement($poule);
        self::assertSame(0, $c['Alpha'][0] + $c['Beta'][0] + $c['Gamma'][0] + $c['Delta'][0]);
    }

    #[TestDox('SET-02 Le même score ne vaut pas la même chose selon le format : 2-1 donne 4 et 2 points en 2 sets, 2 et 1 en 3 sets (règle 1R)')]
    public function test_meme_score_selon_le_format(): void
    {
        $court = $this->poule(2, 'Alpha', 'Beta');
        $this->match($court, 'Alpha', 'Beta', 2, 1);
        self::assertSame([4, 2], [$this->classement($court)['Alpha'][0], $this->classement($court)['Beta'][0]]);

        $this->equipes = [];
        $long = $this->poule(3, 'Alpha', 'Beta');
        $this->match($long, 'Alpha', 'Beta', 2, 1);
        self::assertSame([2, 1], [$this->classement($long)['Alpha'][0], $this->classement($long)['Beta'][0]]);
    }

    #[TestDox('SET-02 Les poules d\'une phase en 3 sets ne sont pas touchées par le format court d\'une autre phase')]
    public function test_les_autres_phases_gardent_leur_format(): void
    {
        $em = self::service('doctrine')->getManager();
        $phase2 = $em->getRepository(Phase::class)->findOneBy(['saison' => ChampionnatDeTest::get('saison')->getId(), 'ordre' => 2]);
        $phase2->setSetsGagnants(2);
        $em->flush();

        $poule = $this->poule(3, 'Alpha', 'Beta');
        $this->match($poule, 'Alpha', 'Beta', 3, 0);

        self::assertSame([5, 3, 0], $this->classement($poule)['Alpha']);
    }
}
