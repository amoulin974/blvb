<?php

namespace App\Controller\admin;

use App\Controller\IsGranted;
use App\Entity\Lieu;
use App\Entity\Equipe;
use App\Form\Type\ImportExcelEquipeType;
use App\Form\EquipeType;
use App\Repository\EquipeRepository;
use App\Repository\LieuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use function PHPUnit\Framework\isArray;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/equipe', name: 'admin_equipe_')]
final class EquipeController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(EquipeRepository $equipeRepository): Response
    {
        return $this->render('admin/equipe/index.html.twig', [
            'equipes' => $equipeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $equipe = new Equipe();
        $form = $this->createForm(EquipeType::class, $equipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($equipe);
            $entityManager->flush();

            return $this->redirectToRoute('admin_equipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/equipe/new.html.twig', [
            'equipe' => $equipe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Equipe $equipe): Response
    {
        return $this->render('admin/equipe/show.html.twig', [
            'equipe' => $equipe,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipe $equipe, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(EquipeType::class, $equipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_equipe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/equipe/edit.html.twig', [
            'equipe' => $equipe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Equipe $equipe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$equipe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($equipe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_equipe_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/excel/explication', name: 'excel_explication', methods: ['GET', 'POST'])]
    public function excelExplication(Request $request, EntityManagerInterface $em, LieuRepository $lieuRepo): Response
    {
        $form = $this->createForm(ImportExcelEquipeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                // Charger le fichier Excel
                $spreadsheet = IOFactory::load($file->getPathname());
                $rows = $spreadsheet->getActiveSheet()->toArray();

                // Boucler sur les lignes (sauter l’en-tête)
                foreach ($rows as $index => $row) {
                    if ($index === 0) continue; // ignorer la première ligne
                    [$nomEquipe, $nomLieu] = $row;

                    $lieu = $lieuRepo->findOneBy(['nom' => $nomLieu]);
                    if (!$lieu) continue; // ignorer si lieu introuvable

                    $equipe = new Equipe();
                    $equipe->setNom($nomEquipe);
                    $equipe->setIdLieu($lieu);

                    $em->persist($equipe);
                }

                $em->flush();
                $this->addFlash('success', 'Import réussi !');
            }

            return $this->redirectToRoute('admin_equipe_index');
        }

        return $this->render('admin/equipe/excelexplication.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/excel/download', name: 'excel_download', methods: ['GET'])]
    public function excelDownload(Request $request, LieuRepository $lieuRepo): Response
    {
        // 1️⃣ Créer le classeur
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 2️⃣ Ajouter les en-têtes de colonnes

        $sheet->setCellValue('A1', 'Nom de l\'équipe');
        $sheet->setCellValue('B1', 'Lieu entrainement');

        //Ajout des lieux dans la zone E3 jusqu'à E....
        $lieux = $lieuRepo->findAll();
        $sheet->setCellValue('E3', 'Utiliser un de ces lieux');
        $i=4;

        foreach ($lieux as $lieu) {

            $sheet->setCellValue('E' . $i, $lieu->getNom());
            $i++;
        }


        // Crée la liste déroulante pour les cellules B2 à B22
        if (isArray($lieux) && count($lieux) > 0) {
            for ($row = 2; $row <= 22; $row++) {
                $validation = $sheet->getCell('B' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Erreur dans le choix du lieu');
                $validation->setError('Il faut choisir un lieu proposé dans la liste');
                $validation->setPromptTitle('Choisir parmi les lieux');
                $validation->setPrompt('Déroulez la liste pour choisir un lieu');

                // 👇 Plage des valeurs pour la liste (ici colonne E)
                $validation->setFormula1('=$E$4:$E$' . (count($lieux)+3));
            }
        }

        // 4️⃣ Préparer la réponse HTTP pour le téléchargement
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="modele_import.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }


}
