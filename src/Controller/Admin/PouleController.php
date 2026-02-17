<?php

namespace App\Controller\Admin;

use App\Entity\Poule;
use App\Form\PouleType;
use App\Entity\Journee;
use App\Entity\Equipe;
use App\Repository\JourneeRepository;
use App\Repository\PouleRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/poule', name: 'admin_poule_')]
final class PouleController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(PouleRepository $pouleRepository): Response
    {
        return $this->render('admin/poule/index.html.twig', [
            'poules' => $pouleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $poule = new Poule();
        $form = $this->createForm(PouleType::class, $poule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($poule);
            $entityManager->flush();

            return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/poule/new.html.twig', [
            'poule' => $poule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Poule $poule): Response
    {
        return $this->render('admin/poule/show.html.twig', [
            'poule' => $poule,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Poule $poule, EntityManagerInterface $entityManager): Response
    {

        // On récupère 'origin', si absent il vaudra null
        $origin = $request->query->get('origin');

        $form = $this->createForm(PouleType::class, $poule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Poule mise à jour avec succès !');

            if ($origin==='saison_show'){
                return $this->redirectToRoute('admin_saison_show', ['id' => $poule->getPhase()->getSaison()->getId(),'_fragment' => 'phase_' . $poule->getPhase()->getId()], Response::HTTP_SEE_OTHER);
            }else{
                return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
            }

        }

        return $this->render('admin/poule/edit.html.twig', [
            'poule' => $poule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Poule $poule, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$poule->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($poule);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
    }













}
