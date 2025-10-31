<?php

namespace App\Controller\Admin;


use App\Entity\Partie;
use App\Form\PartieType;
use App\Repository\PartieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/partie', name: 'admin_partie_')]
final class PartieController extends AbstractController
{
    #[Route("/", name: 'index', methods: ['GET'])]
    public function index(PartieRepository $partieRepository): Response
    {
        return $this->render('admin/partie/index.html.twig', [
            'parties' => $partieRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $partie = new Partie();
        $form = $this->createForm(PartieType::class, $partie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($partie);
            $entityManager->flush();

            return $this->redirectToRoute('admin_partie_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/partie/new.html.twig', [
            'partie' => $partie,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Partie $partie): Response
    {
        return $this->render('admin/partie/show.html.twig', [
            'partie' => $partie,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partie $partie, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PartieType::class, $partie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_partie_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/partie/edit.html.twig', [
            'partie' => $partie,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Partie $partie, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$partie->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($partie);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_partie_index', [], Response::HTTP_SEE_OTHER);
    }
}
