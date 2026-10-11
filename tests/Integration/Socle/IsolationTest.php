<?php

namespace App\Tests\Integration\Socle;

use App\Factory\EquipeFactory;
use App\Repository\EquipeRepository;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Lot 0 : chaque test part d'une base propre (transaction annulée après chaque test).
 * Les deux tests s'exécutent dans l'ordre de déclaration.
 */
final class IsolationTest extends TestIntegration
{
    #[TestDox('Une donnée créée par un test…')]
    public function test_un_test_cree_une_equipe(): void
    {
        EquipeFactory::createOne(['nom' => 'EQUIPE TEMOIN ISOLATION']);

        self::assertNotNull(self::service(EquipeRepository::class)->findOneBy(['nom' => 'EQUIPE TEMOIN ISOLATION']));
    }

    #[TestDox('… n\'existe plus dans le test suivant')]
    public function test_le_test_suivant_ne_la_voit_pas(): void
    {
        self::assertNull(self::service(EquipeRepository::class)->findOneBy(['nom' => 'EQUIPE TEMOIN ISOLATION']));
    }
}
