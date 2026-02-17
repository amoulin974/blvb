<?php

namespace App\Controller\Admin;

use App\Entity\Indisponibilite;
use App\Form\IndisponibiliteType;
use App\Repository\IndisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/indisponibilite')]
final class IndisponibiliteController extends AbstractController
{
    #[Route(name: 'app_indisponibilite_index', methods: ['GET'])]
    public function index(IndisponibiliteRepository $indisponibiliteRepository): Response
    {
        return $this->render('indisponibilite/index.html.twig', [
            'indisponibilites' => $indisponibiliteRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_indisponibilite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $indisponibilite = new Indisponibilite();
        $form = $this->createForm(IndisponibiliteType::class, $indisponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($indisponibilite);
            $entityManager->flush();

            return $this->redirectToRoute('app_indisponibilite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('indisponibilite/new.html.twig', [
            'indisponibilite' => $indisponibilite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_indisponibilite_show', methods: ['GET'])]
    public function show(Indisponibilite $indisponibilite): Response
    {
        return $this->render('indisponibilite/show.html.twig', [
            'indisponibilite' => $indisponibilite,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_indisponibilite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Indisponibilite $indisponibilite, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(IndisponibiliteType::class, $indisponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_indisponibilite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('indisponibilite/edit.html.twig', [
            'indisponibilite' => $indisponibilite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_indisponibilite_delete', methods: ['POST'])]
    public function delete(Request $request, Indisponibilite $indisponibilite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$indisponibilite->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($indisponibilite);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_indisponibilite_index', [], Response::HTTP_SEE_OTHER);
    }
}
