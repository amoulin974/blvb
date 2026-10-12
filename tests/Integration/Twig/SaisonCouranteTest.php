<?php

namespace App\Tests\Integration\Twig;

use App\Entity\Saison;
use App\Factory\SaisonFactory;
use App\Repository\SaisonRepository;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/** ADM-01 : saison courante (admin_saison_courante, droits des responsables de club) = favorite, sinon la plus récente. */
final class SaisonCouranteTest extends TestIntegration
{
    #[TestDox('ADM-01 Saison courante : la saison favorite, sinon celle qui commence le plus tard ; aucune saison → null')]
    public function test_saison_courante(): void
    {
        self::assertNull(self::service(SaisonRepository::class)->findCourante(), 'aucune saison');
        self::ensureKernelShutdown();
        self::bootKernel();

        SaisonFactory::createOne(['nom' => 'Ancienne', 'favori' => 0, 'date_debut' => new \DateTimeImmutable('-2 years'), 'date_fin' => new \DateTimeImmutable('-1 year')]);
        $favorite = SaisonFactory::createOne(['nom' => 'Favorite', 'favori' => 1, 'date_debut' => new \DateTimeImmutable('-1 year'), 'date_fin' => new \DateTimeImmutable('-1 month')]);
        $recente = SaisonFactory::createOne(['nom' => 'Récente', 'favori' => 0, 'date_debut' => new \DateTimeImmutable('-1 month'), 'date_fin' => new \DateTimeImmutable('+10 months')]);
        self::assertSame($favorite->getId(), self::service(SaisonRepository::class)->findCourante()?->getId(), 'la favorite, même si une autre est plus récente');

        $em = self::service('doctrine')->getManager();
        $em->find(Saison::class, $favorite->getId())->setFavori(0);
        $em->flush();
        self::ensureKernelShutdown();
        self::bootKernel();
        self::assertSame($recente->getId(), self::service(SaisonRepository::class)->findCourante()?->getId(), 'sans favorite : la plus récente');
    }
}
