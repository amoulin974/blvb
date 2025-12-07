<?php

namespace App\Controller\Admin;

use App\Entity\Equipe;
use App\Entity\User;
use App\Form\Type\ImportExcelUserType;
use App\Form\UserType;
use App\Repository\LieuRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function PHPUnit\Framework\isArray;


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/user', name: 'admin_user_')]
final class UserController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            if (!in_array('ROLE_USER', $user->getRoles())) {
                $roles[] = 'ROLE_USER';
                $user->setRoles($roles);
            }
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager,  UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
            $entityManager->flush();

            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    //Route qui affiche la page qui permet de télécharger le fichier Excel d'import et qui traite l'upload de ce fichier


    //TODO : gérer les cases vides. Corriger le non affichage du rapport d'erreur
    #[Route('/excel/explication', name: 'excel_explication', methods: ['GET', 'POST'])]
    public function excelExplication(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        ValidatorInterface $validator): Response
    {
        $rapportErreurs = [];
        $countImported = 0;

        $form = $this->createForm(ImportExcelUserType::class);
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

                    // Le numéro de ligne Excel (Index + 1 car l'index commence à 0)
                    $numLigne = $index + 1;

                    //1. Check format (ligne vide ou incomplète)
                    if (empty($row[0]) || count($row) < 3) {
                        $rapportErreurs[] = [
                            'ligne' => $numLigne,
                            'email' => $row[0] ?? 'Non défini',
                            'raison' => 'Format de ligne incorrect ou colonnes manquantes'
                        ];
                        continue;
                    }

                    // 2. Nettoyage avec trim() pour enlever les espaces accidentels
                    // L'opérateur ?? '' évite les erreurs si une case est vide dans l'excel
                    $email = strtolower(trim($row[0] ?? ''));
                    $nom = strtolower(trim($row[1] ?? ''));
                    $prenom = strtolower(trim($row[2] ?? ''));
                    $telephone = strtolower(trim($row[3] ?? ''));

                    // 3. Check doublon
                    if ($userRepository->findOneBy(['email' => $email])) {
                        $rapportErreurs[] = [
                            'ligne' => $numLigne,
                            'email' => $email,
                            'raison' => 'Cet email existe déjà en base de données'
                        ];
                        continue;
                    }

                    // --- CREATION DE L'UTILISATEUR ---
                    $user = new User();
                    $user->setEmail($email);
                    $user->setNom($nom);
                    $user->setPrenom($prenom);
                    $user->setTelephone($telephone);
                    $user->setIsVerified(true);

                    // On met ROLE_USER par défaut (ou autre selon tes besoins)
                    $user->setRoles(['ROLE_USER']);

                    // --- GESTION DU MOT DE PASSE ---

                    // Générer une chaîne aléatoire (ex: "a1b2c3d4e5...")
                    // random_bytes(10) génère des octets, bin2hex les convertit en chaîne lisible
                    $plainPassword = bin2hex(random_bytes(10));

                    // Hacher le mot de passe
                    $hashedPassword = $passwordHasher->hashPassword(
                        $user,
                        $plainPassword
                    );

                    // Assigner le mot de passe haché
                    $user->setPassword($hashedPassword);

                    // -------------------------------

                    // 4. Validation Symfony (Vérifie les Assert dans ton entité User)
                    $errors = $validator->validate($user);

                    if (count($errors) > 0) {
                        // On récupère le premier message d'erreur pour faire simple
                        $msg = $errors[0]->getMessage();

                        $rapportErreurs[] = [
                            'ligne' => $numLigne,
                            'email' => $email,
                            'raison' => "Donnée invalide : $msg"
                        ];
                        continue;
                    }

                    $em->persist($user);
                    $countImported++;
                }

                $em->flush();

                // Feedback complet pour l'admin
                //Attention, après un post, il faut forcément faire une redirection vers une page GET pour éviter le F5 qui recharge les données
                // . C'est pour cela que le rapport d'erreur est mis en session
                if (empty($rapportErreurs)) {
                    // Tout est parfait, on redirige
                    $this->addFlash('success', "Succès ! $countImported utilisateurs importés.");
                    return $this->redirectToRoute('admin_user_index');
                } else {
                    // Il y a eu des soucis, on ajoute un message d'avertissement
                    $this->addFlash('warning', "L'import est terminé avec des rejets ($countImported succès). Voir le rapport ci-dessous.");
                    // On ne redirige PAS, on laisse le code descendre jusqu'au render()
                    $request->getSession()->set('rapportErreursExcel', $rapportErreurs);
                    return $this->redirectToRoute('admin_user_excel_explication');
                }
            }



        }

        $rapportErreurs = $request->getSession()->get('rapportErreursExcel', []);
        $request->getSession()->remove('rapportErreursExcel');
        return $this->render('admin/user/excelexplication.html.twig',
            [ 'form' => $form->createView(),
                'rapportErreurs'=>$rapportErreurs
            ]);
    }




    //Route qui génère le fichier Excel d'import de membres
    #[Route('/excel/download', name: 'excel_download', methods: ['GET'])]
    public function excelDownload(Request $request, LieuRepository $lieuRepo): Response
    {
        // 1️⃣ Créer le classeur
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 2️⃣ Ajouter les en-têtes de colonnes

        $sheet->setCellValue('A1', 'Email');
        $sheet->setCellValue('B1', 'Nom');
        $sheet->setCellValue('C1', 'Prénom');
        $sheet->setCellValue('D1', 'Téléphone');



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
