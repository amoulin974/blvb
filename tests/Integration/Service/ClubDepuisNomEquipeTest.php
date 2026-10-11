<?php

namespace App\Tests\Integration\Service;

use App\Entity\Equipe;
use App\Factory\ClubFactory;
use App\Factory\EquipeFactory;
use App\Factory\LieuFactory;
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
        yield 'sigle en fin de nom' => ['EROAK (AOVB)', 'EROAK', 'AOVB'];
        yield 'espaces autour' => ['  Hariboules (BAY) ', 'Hariboules', 'BAY'];
        yield 'chiffres dans le nom' => ['LA D3 D\'ORMUZ (ASPTT)', 'LA D3 D\'ORMUZ', 'ASPTT'];
        yield 'sans parenthèses' => ['LEO LAGRANGE', 'LEO LAGRANGE', null];
        yield 'parenthèses au milieu' => ['A (B) C', 'A (B) C', null];
    }

    #[DataProvider('noms')]
    #[TestDox('Séparer le nom de l\'équipe et le sigle : $_dataName')]
    public function test_separer(string $nom, string $attendu, ?string $court): void
    {
        self::assertSame(['nom' => $attendu, 'court' => $court], self::service(ClubDepuisNomEquipeService::class)->separer($nom));
    }

    #[TestDox('Des parenthèses ordinaires ne créent pas de club ; un sigle inconnu crée un club ; un ancien sigle est traduit')]
    public function test_rattacher(): void
    {
        $service = self::service(ClubDepuisNomEquipeService::class);

        $copains = EquipeFactory::createOne(['nom' => 'LES COPAINS (du lundi)']);
        self::assertSame('sans_club', $service->rattacher($copains)['statut']);
        self::assertSame('LES COPAINS (du lundi)', $copains->getNomSansClub());

        $inconnue = EquipeFactory::createOne(['nom' => 'NOUVELLE (XYZ)']);
        $resultat = $service->rattacher($inconnue);
        self::assertTrue($resultat['cree']);
        self::assertSame('NOUVELLE (XYZ)', $inconnue->getNom());

        $ancienne = EquipeFactory::createOne(['nom' => 'ADISKIDEAK (SJDL)']);
        $service->rattacher($ancienne, ['SJDL' => 'VBLC']);
        self::assertSame('ADISKIDEAK', $ancienne->getNomSansClub());
        self::assertSame('ADISKIDEAK (VBLC)', $ancienne->getNom());
    }

    private function lancer(bool $force): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:clubs:depuis-noms-equipes'));
        $tester->execute($force ? ['--force' => true] : []);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }

    private function equipe(string $nom): ?Equipe
    {
        return self::service(EquipeRepository::class)->findOneBy(['nom' => $nom]);
    }

    #[TestDox('La commande crée les clubs du référentiel, rattache les équipes et retire le sigle du nom enregistré')]
    public function test_commande(): void
    {
        LieuFactory::createOne(['nom' => 'Anglet']);
        LieuFactory::createOne(['nom' => 'Tosse']);
        $existant = ClubFactory::createOne(['nom' => 'Nom long saisi dans l\'admin', 'nomCourt' => 'ASPTT', 'description' => 'Saisie du responsable']);
        EquipeFactory::createOne(['nom' => 'EROAK (AOVB)']);
        EquipeFactory::createOne(['nom' => 'ADISKIDEAK (SJDL)']);
        EquipeFactory::createOne(['nom' => 'PERLEMPACK (ASPTT)']);
        EquipeFactory::createOne(['nom' => 'LEO LAGRANGE']);
        EquipeFactory::createOne(['nom' => ' les Tamalous']);
        EquipeFactory::createOne(['nom' => 'SANS CLUB']);

        $affichage = $this->lancer(true);

        $clubs = self::service(ClubRepository::class);
        $aovb = $clubs->findOneBy(['nomCourt' => 'AOVB']);
        self::assertSame('Anglet Olympique Volley Ball', $aovb->getNom());
        self::assertSame(['Anglet'], $aovb->getLieux()->map(fn ($l) => $l->getNom())->getValues());
        self::assertSame('Eroak (AOVB)', $this->equipe('EROAK')->getNom(), 'nom enregistré sans sigle, affiché avec');

        self::assertSame('Adiskideak (VBLC)', $this->equipe('ADISKIDEAK')->getNom(), 'ancien sigle SJDL traduit en VBLC');
        self::assertSame('Volley Ball Luzien et Cibourien', $clubs->findOneBy(['nomCourt' => 'VBLC'])->getNom());

        self::assertSame($existant->getId(), $this->equipe('PERLEMPACK')->getClub()->getId(), 'club existant réutilisé');
        self::assertSame('Nom long saisi dans l\'admin', $clubs->findOneBy(['nomCourt' => 'ASPTT'])->getNom(), 'nom long existant conservé');
        self::assertSame('Saisie du responsable', $clubs->findOneBy(['nomCourt' => 'ASPTT'])->getDescription(), 'description existante conservée');
        self::assertSame('https://bayonnecotebasque.asptt.com/activity/volley-ball/', $clubs->findOneBy(['nomCourt' => 'ASPTT'])->getUrl(), 'lien vide complété');
        self::assertStringStartsWith('Section volley-ball de l\'Anglet Olympique', $aovb->getDescription(), 'fiche remplie à la création');
        self::assertSame('https://anglet-omnisports.fr/sections/anglet-olympique-volleyball.html', $aovb->getUrl());

        self::assertSame('Léo Lagrange (LÉO)', $this->equipe('LEO LAGRANGE')->getNom(), 'équipe sans sigle du référentiel');
        self::assertSame('Les Tamalous (TOSSE)', $this->equipe('les Tamalous')->getNom(), 'espaces retirés, club Tosse, nom réécrit');
        self::assertSame('Perlempack', $this->equipe('PERLEMPACK')->getNomSansClub(), 'écriture du nom : majuscule initiale');
        self::assertSame('SANS CLUB', $this->equipe('SANS CLUB')->getNomSansClub(), 'nom absent de la table : inchangé');
        self::assertStringContainsString('Noms absents de la table d\'écriture', $affichage);
        self::assertNull($this->equipe('SANS CLUB')->getClub());
        self::assertCount(9, $clubs->findAll());
        self::assertStringContainsString('Gymnase « Biarritz » introuvable', $affichage);
    }

    #[TestDox('Sans --force, la commande ne modifie rien ; relancée après application, elle ne change plus rien')]
    public function test_simulation_puis_relance(): void
    {
        EquipeFactory::createOne(['nom' => 'EROAK (AOVB)']);

        $affichage = $this->lancer(false);
        self::assertStringContainsString('Simulation', $affichage);
        self::assertNotNull($this->equipe('EROAK (AOVB)'));
        self::assertCount(0, self::service(ClubRepository::class)->findAll());

        $this->lancer(true);
        $this->lancer(true);
        self::assertCount(1, self::service(ClubRepository::class)->findBy(['nomCourt' => 'AOVB']));
        self::assertSame('Eroak (AOVB)', $this->equipe('EROAK')->getNom());
    }
}
