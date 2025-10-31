<?php

namespace App\Controller\Admin;

use App\Entity\Journee;
use App\Form\JourneeType;
use App\Repository\JourneeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/journee', name: 'admin_journee_')]
final class JourneeController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(JourneeRepository $journeeRepository): Response
    {
        return $this->render('admin/journee/index.html.twig', [
            'journees' => $journeeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $journee = new Journee();
        $form = $this->createForm(JourneeType::class, $journee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($journee);
            $entityManager->flush();

            return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/journee/new.html.twig', [
            'journee' => $journee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Journee $journee): Response
    {
        return $this->render('admin/journee/show.html.twig', [
            'journee' => $journee,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Journee $journee, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(JourneeType::class, $journee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/journee/edit.html.twig', [
            'journee' => $journee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Journee $journee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$journee->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($journee);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
    }
}
