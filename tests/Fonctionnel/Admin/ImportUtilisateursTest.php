<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Joueur;
use App\Entity\User;
use App\Factory\JoueurFactory;
use App\Factory\UserFactory;
use App\Tests\Support\TestFonctionnel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\TestDox;

/** IMP-01 à IMP-05 : import Excel des comptes utilisateurs (admin), avec un fichier généré par le test. */
final class ImportUtilisateursTest extends TestFonctionnel
{
    private function importer(array $lignes): string
    {
        $classeur = new Spreadsheet();
        $classeur->getActiveSheet()->fromArray([['E-mail', 'Nom', 'Prénom', 'Téléphone'], ...$lignes]);
        $chemin = sys_get_temp_dir().'/import-utilisateurs-'.bin2hex(random_bytes(3)).'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/user/excel/explication');
        $form = $crawler->selectButton('Importer le fichier')->form();
        $form[array_key_first($form->getPhpFiles()).'[file]']->upload($chemin);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        return preg_replace('/\s+/', ' ', $this->client->getCrawler()->filter('main')->text());
    }

    private function compte(string $email): ?User
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return $em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function fiche(string $email): ?Joueur
    {
        return self::getContainer()->get('doctrine')->getManager()->getRepository(Joueur::class)->findOneBy(['email' => $email]);
    }

    #[TestDox('IMP-01 à IMP-05 Import : comptes et fiches créés, fiche existante reliée, fiche déjà liée signalée, e-mail invalide et compte existant rejetés')]
    public function test_import(): void
    {
        JoueurFactory::createOne(['prenom' => 'Fiche', 'nom' => 'Sanscompte', 'email' => 'fiche.sans.compte@blvb.test', 'telephone' => '06 55 44 33 22']);
        $autre = UserFactory::createOne(['email' => 'autre.compte@blvb.test']);
        $ficheLiee = JoueurFactory::createOne(['prenom' => 'Fiche', 'nom' => 'Liee', 'email' => 'fiche.liee@blvb.test', 'user' => $autre]);
        $nbComptes = self::getContainer()->get('doctrine')->getManager()->getRepository(User::class)->count([]);
        $nbFiches = self::getContainer()->get('doctrine')->getManager()->getRepository(Joueur::class)->count([]);

        $page = $this->importer([
            ['nouveau.compte@blvb.test', 'Compte', 'Nouveau', '06 01 02 03 04'],
            ['Fiche.Sans.Compte@BLVB.test', 'Sanscompte', 'Fiche', ''],
            ['fiche.liee@blvb.test', 'Liee', 'Fiche', ''],
            ['pas-un-email', 'Invalide', 'Ligne', ''],
            ['cap.a2@blvb.test', 'Bidart', 'Claude', ''],
        ]);

        // IMP-01 : compte et fiche créés, liés
        $nouveau = $this->compte('nouveau.compte@blvb.test');
        self::assertNotNull($nouveau);
        self::assertSame(['ROLE_USER'], $nouveau->getRoles());
        self::assertSame($nouveau->getId(), $this->fiche('nouveau.compte@blvb.test')?->getUser()?->getId());

        // IMP-02 : fiche existante (casse différente) reliée, pas de doublon
        $relie = $this->compte('fiche.sans.compte@blvb.test');
        self::assertNotNull($relie);
        self::assertSame($relie->getId(), $this->fiche('fiche.sans.compte@blvb.test')->getUser()?->getId());

        // IMP-03 : fiche déjà liée à un autre compte : signalée, inchangée
        self::assertStringContainsString('déjà liée à un autre compte', $page);
        self::assertStringContainsString('fiche.liee@blvb.test', $page);
        self::assertSame($autre->getId(), self::getContainer()->get('doctrine')->getManager()->find(Joueur::class, $ficheLiee->getId())->getUser()?->getId());

        // IMP-04 et IMP-05 : lignes rejetées avec leur motif, les autres importées
        self::assertStringContainsString('Adresse e-mail invalide', $page);
        self::assertStringContainsString('existe déjà', $page);
        self::assertStringContainsString('3 succès', $page);

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame($nbComptes + 3, $em->getRepository(User::class)->count([]), 'trois comptes créés, aucun doublon');
        self::assertSame($nbFiches + 1, $em->getRepository(Joueur::class)->count([]), 'une seule fiche créée');
        self::assertCount(1, $em->getRepository(User::class)->findBy(['email' => 'cap.a2@blvb.test']));
    }
}
