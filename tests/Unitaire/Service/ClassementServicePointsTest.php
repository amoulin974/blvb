<?php

namespace App\Tests\Unitaire\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Repository\ClassementRepository;
use App\Service\ClassementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * CLA-01 (tests/SPECIFICATIONS.md) : points attribués selon le score, règlement du championnat (règle 1D).
 * Le barème de test a des valeurs toutes différentes, pour qu'une inversion de règle soit visible :
 * victoire forte 5, victoire faible 4, défaite forte 2, défaite faible 1, forfait -3.
 */
final class ClassementServicePointsTest extends TestCase
{
    private ClassementService $service;
    private Saison $saison;

    protected function setUp(): void
    {
        $this->service = new ClassementService(
            $this->createStub(ClassementRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->saison = (new Saison())
            ->setPointsVictoireForte(5)
            ->setPointsVictoireFaible(4)
            ->setPointsDefaiteForte(2)
            ->setPointsDefaiteFaible(1)
            ->setPointsForfait(-3);
    }

    /**
     * Sets de l'équipe qui reçoit / de l'équipe qui se déplace (-1 = forfait), points attendus pour chacune.
     *
     * @return iterable<string, array{?int, ?int, int, int}>
     */
    public static function scores(): iterable
    {
        yield '3-0 : victoire forte / défaite faible' => [3, 0, 5, 1];
        yield '3-1 : victoire forte / défaite faible' => [3, 1, 5, 1];
        yield '3-2 : victoire faible / défaite forte' => [3, 2, 4, 2];
        yield '2-3 : défaite forte / victoire faible' => [2, 3, 2, 4];
        yield '1-3 : défaite faible / victoire forte' => [1, 3, 1, 5];
        yield '0-3 : défaite faible / victoire forte' => [0, 3, 1, 5];
        yield 'forfait de l\'équipe qui se déplace' => [3, -1, 5, -3];
        yield 'forfait de l\'équipe qui reçoit' => [-1, 3, -3, 5];
        yield 'match non joué' => [null, null, 0, 0];
    }

    #[DataProvider('scores')]
    #[TestDox('Points selon le score : $_dataName')]
    public function test_points_selon_le_score(?int $setsReception, ?int $setsDeplacement, int $pointsReception, int $pointsDeplacement): void
    {
        $recoit = (new Equipe())->setNom('Reçoit');
        $deplace = (new Equipe())->setNom('Se déplace');
        $partie = (new Partie())
            ->setIdEquipeRecoit($recoit)
            ->setIdEquipeDeplace($deplace)
            ->setNbSetGagnantReception($setsReception)
            ->setNbSetGagnantDeplacement($setsDeplacement);
        $recoit->addPartiesReception($partie);
        $deplace->addPartiesDeplacement($partie);

        self::assertSame($pointsReception, $this->service->calculerPointsEquipe($recoit, $this->saison));
        self::assertSame($pointsDeplacement, $this->service->calculerPointsEquipe($deplace, $this->saison));
    }

    #[TestDox('Le barème du règlement par défaut : forfait = 3 points pour le gagnant, -1 pour le perdant')]
    public function test_bareme_par_defaut_conforme_au_reglement(): void
    {
        $saison = new Saison();
        $recoit = (new Equipe())->setNom('Reçoit');
        $deplace = (new Equipe())->setNom('Se déplace');
        $partie = (new Partie())
            ->setIdEquipeRecoit($recoit)
            ->setIdEquipeDeplace($deplace)
            ->setNbSetGagnantReception(3)
            ->setNbSetGagnantDeplacement(-1);
        $recoit->addPartiesReception($partie);
        $deplace->addPartiesDeplacement($partie);

        self::assertSame(3, $this->service->calculerPointsEquipe($recoit, $saison));
        self::assertSame(-1, $this->service->calculerPointsEquipe($deplace, $saison));
    }
}
