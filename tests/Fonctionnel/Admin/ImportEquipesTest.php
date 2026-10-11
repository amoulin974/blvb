<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Repository\ClubRepository;
use App\Repository\EquipeRepository;
use App\Tests\Support\TestFonctionnel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Import Excel des équipes (admin) : « NOM (SIGLE) » est rattaché au club de ce nom court (créé s'il n'existe pas),
 * et le nom enregistré ne contient plus le sigle (REP-04, tests/SPECIFICATIONS.md).
 */
final class ImportEquipesTest extends TestFonctionnel
{
    /** @param list<array{string, string}> $lignes [nom de l'équipe, gymnase] */
    private function fichierExcel(array $lignes): string
    {
        $classeur = new Spreadsheet();
        $classeur->getActiveSheet()->fromArray([['Nom', 'Gymnase'], ...$lignes]);
        $chemin = sys_get_temp_dir().'/import-equipes-'.bin2hex(random_bytes(3)).'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return $chemin;
    }

    #[TestDox('L\'import rattache « NOM (SIGLE) » au club du sigle, crée un club inconnu, et laisse sans club une équipe sans sigle')]
    public function test_import_avec_sigles(): void
    {
        $fichier = $this->fichierExcel([
            ['NOUVELLE EQUIPE (CTA)', 'Gymnase G1'],
            ['  EQUIPE INCONNUE (XYZ) ', 'Gymnase G2'],
            ['EQUIPE SANS SIGLE', 'Gymnase G1'],
            ['LES COPAINS (du lundi)', 'Gymnase G1'],
        ]);

        $this->connecter('admin');
        $crawler = $this->client->request('GET', '/admin/equipe/excel/explication');
        $form = $crawler->selectButton('Importer le fichier')->form();
        $form['import_excel_equipe[file]']->upload($fichier);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $equipes = self::getContainer()->get(EquipeRepository::class);

        $cta = $equipes->findOneBy(['nom' => 'NOUVELLE EQUIPE']);
        self::assertNotNull($cta, 'sigle retiré du nom enregistré');
        self::assertSame('CTA', $cta->getClub()?->getNomCourt(), 'club existant réutilisé');
        self::assertSame('NOUVELLE EQUIPE (CTA)', $cta->getNom());

        $xyz = $equipes->findOneBy(['nom' => 'EQUIPE INCONNUE']);
        self::assertSame('XYZ', $xyz?->getClub()?->getNomCourt(), 'club créé pour un sigle inconnu');
        self::assertCount(1, self::getContainer()->get(ClubRepository::class)->findBy(['nomCourt' => 'XYZ']));

        self::assertNull($equipes->findOneBy(['nom' => 'EQUIPE SANS SIGLE'])?->getClub());
        self::assertNull($equipes->findOneBy(['nom' => 'LES COPAINS (du lundi)'])?->getClub(), 'parenthèses ordinaires : pas de club');
    }
}
