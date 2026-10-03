<?php

namespace App\Controller\Admin;

use App\Entity\Joueur;
use App\Form\JoueurType;
use App\Repository\JoueurRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/joueur', name: 'admin_joueur_')]
final class JoueurController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(JoueurRepository $joueurRepository): Response
    {
        return $this->render('admin/joueur/index.html.twig', [
            'joueurs' => $joueurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, JoueurRepository $joueurRepository): Response
    {
        $joueur = new Joueur();
        $form = $this->createForm(JoueurType::class, $joueur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si l'admin n'a pas choisi de compte lié mais que l'email saisi correspond à
            // un compte existant, on relie automatiquement la fiche à ce compte (ou on
            // réutilise la fiche déjà liée à ce compte, s'il en a déjà une, plutôt que
            // d'en créer une seconde qui violerait la contrainte d'unicité sur Joueur::user).
            if ($joueur->getUser() === null && $joueur->getEmail()) {
                $utilisateurExistant = $userRepository->findOneBy(['email' => $joueur->getEmail()]);
                if ($utilisateurExistant) {
                    $joueurExistant = $joueurRepository->findOneBy(['user' => $utilisateurExistant]);
                    if ($joueurExistant) {
                        $this->addFlash('error', sprintf(
                            'Une fiche joueur existe déjà pour ce compte : %s %s. Modifiez-la directement plutôt que d\'en créer une nouvelle.',
                            $joueurExistant->getPrenom(),
                            $joueurExistant->getNom()
                        ));

                        return $this->redirectToRoute('admin_joueur_edit', ['id' => $joueurExistant->getId()], Response::HTTP_SEE_OTHER);
                    }

                    $joueur->setUser($utilisateurExistant);
                }
            }

            $entityManager->persist($joueur);
            $entityManager->flush();

            return $this->redirectToRoute('admin_joueur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/joueur/new.html.twig', [
            'joueur' => $joueur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Joueur $joueur): Response
    {
        return $this->render('admin/joueur/show.html.twig', [
            'joueur' => $joueur,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Joueur $joueur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(JoueurType::class, $joueur, ['current_joueur_id' => $joueur->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_joueur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/joueur/edit.html.twig', [
            'joueur' => $joueur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Joueur $joueur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$joueur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($joueur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_joueur_index', [], Response::HTTP_SEE_OTHER);
    }
}
