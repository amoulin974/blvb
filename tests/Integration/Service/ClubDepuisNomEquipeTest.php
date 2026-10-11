<?php

namespace App\Tests\Integration\Service;

use App\Entity\Club;
use App\Factory\ClubFactory;
use App\Factory\EquipeFactory;
use App\Repository\ClubRepository;
use App\Repository\EquipeRepository;
use App\Service\ClubDepuisNomEquipeService;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;

/**
 * Reprise des noms d'équipes « NOM (SIGLE) » : clubs créés, nom court retiré du nom enregistré,
 * remis à l'affichage (Equipe::getNom). Service ClubDepuisNomEquipeService et commande app:clubs:depuis-noms-equipes.
 */
final class ClubDepuisNomEquipeTest extends TestIntegration
{
    public static function noms(): iterable
    {
        yield 'nom court en fin de nom' => ['EROAK (AOVB)', 'EROAK', 'AOVB'];
        yield 'espaces autour' => ['  Hariboules (BAY) ', 'Hariboules', 'BAY'];
        yield 'chiffres dans le nom' => ['LA D3 D\'ORMUZ (ASPTT)', 'LA D3 D\'ORMUZ', 'ASPTT'];
        yield 'sans nom court' => ['LEO LAGRANGE', 'LEO LAGRANGE', null];
        yield 'parenthèses qui ne sont pas un sigle' => ['LES COPAINS (du lundi)', 'LES COPAINS (du lundi)', null];
        yield 'parenthèses au milieu' => ['A (B) C', 'A (B) C', null];
    }

    #[DataProvider('noms')]
    #[TestDox('Séparer le nom de l\'équipe et le nom court du club : $_dataName')]
    public function test_separer(string $nom, string $attendu, ?string $court): void
    {
        self::assertSame(['nom' => $attendu, 'court' => $court], self::service(ClubDepuisNomEquipeService::class)->separer($nom));
    }

    private function lancer(bool $force): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:clubs:depuis-noms-equipes'));
        $tester->execute($force ? ['--force' => true] : []);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }

    #[TestDox('La commande crée les clubs, rattache les équipes et retire le nom court du nom enregistré')]
    public function test_commande(): void
    {
        $existant = ClubFactory::createOne(['nom' => 'Anglet Olympique Volley-Ball', 'nomCourt' => 'AOVB']);
        EquipeFactory::createOne(['nom' => 'EROAK (AOVB)']);
        EquipeFactory::createOne(['nom' => 'KAYOU (AOVB)']);
        EquipeFactory::createOne(['nom' => 'CAPOUT (CAP)']);
        EquipeFactory::createOne(['nom' => 'LA CAPSULE (CAP)']);
        EquipeFactory::createOne(['nom' => 'SAMOUSSAS']);

        $this->lancer(true);

        $equipes = self::service(EquipeRepository::class);
        $eroak = $equipes->findOneBy(['nom' => 'EROAK']);
        self::assertNotNull($eroak, 'nom enregistré sans le club');
        self::assertSame('EROAK (AOVB)', $eroak->getNom(), 'nom affiché avec le club');
        self::assertSame($existant->getId(), $eroak->getClub()->getId(), 'club existant réutilisé');

        $cap = self::service(ClubRepository::class)->findOneBy(['nomCourt' => 'CAP']);
        self::assertInstanceOf(Club::class, $cap, 'club créé');
        self::assertSame('CAP', $cap->getNom(), 'nom long provisoire = nom court');
        self::assertSame(['CAPOUT', 'LA CAPSULE'], array_map(fn ($e) => $e->getNomSansClub(), $cap->getEquipes()->toArray()));
        self::assertNull($equipes->findOneBy(['nom' => 'SAMOUSSAS'])->getClub());
    }

    #[TestDox('Sans --force, la commande ne modifie rien ; relancée après application, elle ne change plus rien')]
    public function test_simulation_puis_relance(): void
    {
        EquipeFactory::createOne(['nom' => 'EROAK (AOVB)']);

        $affichage = $this->lancer(false);
        self::assertStringContainsString('Simulation', $affichage);
        self::assertNotNull(self::service(EquipeRepository::class)->findOneBy(['nom' => 'EROAK (AOVB)']));
        self::assertNull(self::service(ClubRepository::class)->findOneBy(['nomCourt' => 'AOVB']));

        $this->lancer(true);
        $this->lancer(true);
        self::assertCount(1, self::service(ClubRepository::class)->findBy(['nomCourt' => 'AOVB']));
        self::assertSame('EROAK (AOVB)', self::service(EquipeRepository::class)->findOneBy(['nom' => 'EROAK'])->getNom());
    }
}
