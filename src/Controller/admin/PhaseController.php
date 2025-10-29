<?php

namespace App\Controller\admin;

use App\Entity\Journee;
use App\Entity\Phase;
use App\Form\PhaseType;
use App\Repository\PhaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/phase', name: 'admin_phase_')]
final class PhaseController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(PhaseRepository $phaseRepository): Response
    {
        return $this->render('admin/phase/index.html.twig', [
            'phases' => $phaseRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $phase = new Phase();
        $form = $this->createForm(PhaseType::class, $phase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($phase);
            $entityManager->flush();

            return $this->redirectToRoute('admin_phase_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/phase/new.html.twig', [
            'phase' => $phase,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Phase $phase): Response
    {
        return $this->render('admin/phase/show.html.twig', [
            'phase' => $phase,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Phase $phase, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PhaseType::class, $phase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_phase_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/phase/edit.html.twig', [
            'phase' => $phase,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Phase $phase, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$phase->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($phase);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_phase_index', [], Response::HTTP_SEE_OTHER);
    }

}
