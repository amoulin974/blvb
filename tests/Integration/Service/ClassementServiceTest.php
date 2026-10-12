<?php

namespace App\Tests\Integration\Service;

use App\Entity\Classement;
use App\Entity\Equipe;
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
 * CLA-02 à CLA-11 : calcul du classement d'une poule, sur des poules construites pour chaque test (barème de la
 * saison de test : victoire 3-0/3-1 = 5, 3-2 = 4, défaite 2-3 = 2, 1-3/0-3 = 1, forfait = -3 ; règle 1R fixe).
 */
final class ClassementServiceTest extends TestIntegration
{
    private Poule $poule;
    /** @var array<string, Equipe> */
    private array $equipes = [];
    private object $journee;

    protected function setUp(): void
    {
        parent::setUp();
        ChampionnatDeTest::load();
    }

    /** Poule de test de la phase 1 avec ces équipes (dans l'ordre de création donné). */
    private function poule(string ...$noms): void
    {
        foreach ($noms as $nom) {
            $this->equipes[$nom] = EquipeFactory::createOne(['nom' => $nom, 'lieu' => null]);
        }
        $this->poule = PouleFactory::createOne(['phase' => ChampionnatDeTest::get('phase.1'), 'nom' => 'Poule classement', 'niveau' => 9, 'equipes' => array_values($this->equipes)]);
        $this->journee = JourneeFactory::createOne(['poule' => $this->poule, 'numero' => 1,
            'date_debut' => new \DateTimeImmutable('-20 days'), 'date_fin' => new \DateTimeImmutable('-1 day')]);
    }

    private function match(string $recoit, string $deplace, ?int $setsR, ?int $setsD, string $quand = '-10 days', ?Poule $poule = null): void
    {
        PartieFactory::createOne(['journee' => $this->journee, 'poule' => $poule ?? $this->poule, 'idEquipeRecoit' => $this->equipes[$recoit],
            'idEquipeDeplace' => $this->equipes[$deplace], 'lieu' => null, 'date' => new \DateTimeImmutable($quand),
            'nbSetGagnantReception' => $setsR, 'nbSetGagnantDeplacement' => $setsD]);
    }

    /** Calcule le classement et le renvoie : nom => [position, points, sets gagnés, sets perdus], dans l'ordre. */
    private function classement(?Poule $poule = null): array
    {
        $em = self::service('doctrine')->getManager();
        $em->clear();
        $poule = $em->find(Poule::class, ($poule ?? $this->poule)->getId());
        self::service(ClassementService::class)->mettreAJourClassementPoule($poule);
        $em->clear();

        $lignes = [];
        foreach (self::service(ClassementRepository::class)->findBy(['poule' => $poule->getId()], ['position' => 'ASC']) as $c) {
            /** @var Classement $c */
            $lignes[$c->getEquipe()->getNomSansClub()] = [$c->getPosition(), $c->getPoints(), $c->getSetGagnes(), $c->getSetPerdus()];
        }

        return $lignes;
    }

    #[TestDox('CLA-02 Le barème vient de la saison de la poule')]
    public function test_bareme_de_la_saison(): void
    {
        $this->poule('Alpha', 'Beta');
        $this->match('Alpha', 'Beta', 3, 0);
        self::assertSame(5, $this->classement()['Alpha'][1]);

        $em = self::service('doctrine')->getManager();
        $saison = $em->find(\App\Entity\Saison::class, ChampionnatDeTest::get('saison')->getId());
        $saison->setPointsVictoireForte(7);
        $em->flush();
        self::assertSame(7, $this->classement()['Alpha'][1]);
    }

    #[TestDox('CLA-03/04 Points à domicile et à l\'extérieur additionnés ; un match d\'une autre poule ne compte pas')]
    public function test_domicile_exterieur_et_poule(): void
    {
        $this->poule('Alpha', 'Beta', 'Gamma');
        $this->match('Alpha', 'Beta', 3, 2);   // Alpha 4, Beta 2
        $this->match('Gamma', 'Alpha', 1, 3);  // Alpha 5 à l'extérieur, Gamma 1
        $autrePoule = PouleFactory::createOne(['phase' => ChampionnatDeTest::get('phase.1'), 'nom' => 'Autre', 'niveau' => 8, 'equipes' => [$this->equipes['Alpha'], $this->equipes['Beta']]]);
        $this->match('Beta', 'Alpha', 3, 0, '-5 days', $autrePoule); // ne doit pas compter ici

        $c = $this->classement();
        self::assertSame(9, $c['Alpha'][1]);
        self::assertSame(2, $c['Beta'][1]);
        self::assertSame(1, $c['Gamma'][1]);
    }

    #[TestDox('CLA-05 Sets : un forfait compte 0 set ; un match interrompu à 2-2 ne compte pas dans le set-average (règle 1R)')]
    public function test_sets(): void
    {
        $this->poule('Alpha', 'Beta', 'Gamma');
        $this->match('Alpha', 'Beta', 3, 1);
        $this->match('Beta', 'Gamma', -1, 3);  // forfait de Beta
        $this->match('Gamma', 'Alpha', 2, 2);  // interrompu à 2-2

        $c = $this->classement();
        self::assertSame([3, 1], [$c['Alpha'][2], $c['Alpha'][3]], 'Alpha : 3-1 seulement');
        self::assertSame([1, 6], [$c['Beta'][2], $c['Beta'][3]], 'Beta : 1-3 puis forfait (0 gagné, 3 perdus)');
        self::assertSame([3, 0], [$c['Gamma'][2], $c['Gamma'][3]], 'Gamma : 3 sets gagnés par forfait, 2-2 non compté');
        self::assertSame(1 + 5, $c['Alpha'][1], 'Alpha : 5 (victoire) + 1 (2-2)');
        self::assertSame(1 + 5, $c['Gamma'][1], 'Gamma : 5 (victoire par forfait) + 1 (2-2)');
        self::assertSame(1 + -3, $c['Beta'][1], 'Beta : 1 (défaite) - 3 (forfait)');
    }

    #[TestDox('CLA-06 Ordre : points décroissants, puis différence de sets')]
    public function test_ordre(): void
    {
        $this->poule('Alpha', 'Beta', 'Gamma', 'Delta');
        $this->match('Alpha', 'Gamma', 3, 2); // Alpha 4 (+1)
        $this->match('Beta', 'Delta', 3, 0);  // Beta 5 (+3)
        $this->match('Delta', 'Gamma', 3, 1); // Delta 5 (+2-3=-1)… puis 1+5=6 (-1) ; Gamma 2+1=3

        self::assertSame(['Delta', 'Beta', 'Alpha', 'Gamma'], array_keys($this->classement()), 'Delta 6 pts, Beta 5, Alpha 4, Gamma 3');

        $this->match('Alpha', 'Delta', 3, 0, '-3 days'); // Alpha 9 (+4), Delta 7 (-4)
        self::assertSame(['Alpha', 'Delta', 'Beta', 'Gamma'], array_keys($this->classement()));
    }

    #[TestDox('CLA-07/09 Égalité parfaite : le dernier match joué entre les deux équipes les départage (aller-retour)')]
    public function test_confrontation_directe_dernier_match(): void
    {
        $this->poule('Alpha', 'Beta');
        $this->match('Alpha', 'Beta', 3, 0, '-10 days'); // aller : Alpha
        $this->match('Beta', 'Alpha', 3, 0, '-3 days');  // retour, plus récent : Beta
        $c = $this->classement();
        self::assertSame($c['Alpha'][1], $c['Beta'][1], 'mêmes points');
        self::assertSame(['Beta', 'Alpha'], array_keys($c), 'Beta a gagné le dernier match');
    }

    #[TestDox('CLA-07 Le vainqueur du match direct est classé devant, même s\'il a joué à l\'extérieur')]
    public function test_confrontation_directe_aller_seulement(): void
    {
        $this->poule('Alpha', 'Beta');
        $this->match('Alpha', 'Beta', 3, 0, '-3 days');  // le plus récent : Alpha
        $this->match('Beta', 'Alpha', 3, 0, '-10 days');
        self::assertSame(['Alpha', 'Beta'], array_keys($this->classement()));
    }

    #[TestDox('CLA-08 Match direct non joué ou nul (2-2) : aucune préférence, ordre alphabétique stable')]
    public function test_confrontation_non_jouee_ou_nulle(): void
    {
        $this->poule('Zeta', 'Alpha');
        $this->match('Zeta', 'Alpha', null, null);
        self::assertSame(['Alpha', 'Zeta'], array_keys($this->classement()), 'non joué');

        $this->match('Alpha', 'Zeta', 2, 2, '-2 days');
        self::assertSame(['Alpha', 'Zeta'], array_keys($this->classement()), 'interrompu à 2-2');
    }

    #[TestDox('CLA-10/11 Positions 1 à n sans trou ; recalcul idempotent ; une équipe ajoutée apparaît avec 0 point')]
    public function test_positions_idempotence_nouvelle_equipe(): void
    {
        $this->poule('Alpha', 'Beta', 'Gamma');
        $this->match('Alpha', 'Beta', 3, 1);
        $premier = $this->classement();
        self::assertSame([1, 2, 3], array_column(array_values($premier), 0));
        self::assertSame($premier, $this->classement(), 'deuxième calcul identique');
        self::assertCount(3, self::service(ClassementRepository::class)->findBy(['poule' => $this->poule->getId()]), 'pas de ligne en double');

        $em = self::service('doctrine')->getManager();
        $poule = $em->find(Poule::class, $this->poule->getId());
        $poule->addEquipe(EquipeFactory::createOne(['nom' => 'Nouvelle', 'lieu' => null]));
        $em->flush();
        $c = $this->classement();
        self::assertArrayHasKey('Nouvelle', $c);
        self::assertSame(0, $c['Nouvelle'][1]);
        self::assertSame([1, 2, 3, 4], array_column(array_values($c), 0));
    }
}
