<?php

namespace App\Controller\Admin;

use App\Entity\Journee;
use App\Entity\Phase;
use App\Form\PhaseFormType;
use App\Repository\PhaseRepository;
use App\Service\PhaseService;
use App\Service\JourneeService;
use App\Service\PartieService;
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
        $form = $this->createForm(PhaseFormType::class, $phase);
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
        $form = $this->createForm(PhaseFormType::class, $phase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_phase_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/phase/profile_dit.html.twig', [
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

//    Route appellée par le bouton qui permet de cloturer une phase depuis show d'une saison
    #[Route('/{id}/cloturer', name: 'cloturer', methods: ['POST'])]
    public function cloturer(
        Phase $phase,
        EntityManagerInterface $entityManager,
        PhaseService $phaseService,
        JourneeService $journeeService,
        PartieService $partieService
    ): Response {
        $saison = $phase->getSaison();
        $phases = $saison->getPhases();

        // 1. Trouver la phase suivante
        $phaseSuivante = null;
        $trouve = false;
        foreach ($phases as $p) {
            if ($trouve) {
                $phaseSuivante = $p;
                break;
            }
            if ($p->getId() === $phase->getId()) {
                $trouve = true;
            }
        }

        if (!$phaseSuivante) {
            $this->addFlash('error', 'Il n’y a pas de phase suivante pour clôturer celle-ci.');
            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }else{
            $phaseService->cloturerEtBasculer($phase);
            foreach($phaseSuivante->getPoules() as $poule){
                $journeeService->creerJournees($poule);
                $partieService->createCalendar($poule);
            }

            $this->addFlash('success', 'Équipes basculées avec succès.');
        }



        // Redirection vers la vue de la saison pour voir le résultat
        return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
    }

}
