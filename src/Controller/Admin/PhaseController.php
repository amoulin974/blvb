<?php

namespace App\Controller\Admin;

use App\Entity\Journee;
use App\Entity\Phase;
use App\Form\PhaseFormType;
use App\Repository\LieuRepository;
use App\Repository\PhaseRepository;
use App\Service\CalendrierAnalyseService;
use App\Service\OptimisationService;
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

    #[Route('/{id}/optimiser', name: 'optimiser', methods: ['GET'])]
    public function optimiser(
        Phase $phase,
        OptimisationService $optimisationService,
        CalendrierAnalyseService $analyseService,
        LieuRepository $lieuRepository
    ): Response
    {
        $alternance = $optimisationService->getAlternanceParPoule($phase);

        // Regroupe les matchs de la phase par lieu puis par date, et analyse la charge de chaque lieu ce jour-là
        $chargeLieux = [];
        foreach ($lieuRepository->findAll() as $lieu) {
            $partiesParDate = [];
            foreach ($lieu->getParties() as $partie) {
                if ($partie->getPoule()->getPhase() !== $phase || !$partie->getDate()) {
                    continue;
                }
                $dateKey = $partie->getDate()->format('Y-m-d');
                $partiesParDate[$dateKey][] = $partie;
            }

            foreach ($partiesParDate as $dateKey => $parties) {
                $date = new \DateTimeImmutable($dateKey);
                $chargeLieux[] = [
                    'lieu' => $lieu,
                    'date' => $date,
                    'nbMatchs' => count($parties),
                    'analyse' => $analyseService->analyser($lieu, $date, count($parties)),
                ];
            }
        }

        usort($chargeLieux, fn($a, $b) => $a['date'] <=> $b['date']);

        return $this->render('admin/phase/optimiser.html.twig', [
            'phase' => $phase,
            'alternance' => $alternance,
            'chargeLieux' => $chargeLieux,
        ]);
    }

    //Supprime et recrée les matchs de toutes les poules de la phase
    #[Route('/{id}/optimisertout', name: 'optimisertout', methods: ['POST'])]
    public function optimiserTout(Request $request, Phase $phase, PartieService $partieService): Response
    {
        if ($this->isCsrfTokenValid('optimisertout'.$phase->getId(), $request->getPayload()->getString('_token'))) {
            $rapport = $partieService->creerCalendrierOptimise($phase);

            foreach ($rapport as $ligne) {
                if ($ligne['type'] !== 'championnat') {
                    continue;
                }

                $this->addFlash(
                    $ligne['surcharges'] === [] ? 'success' : 'warning',
                    sprintf('%s : %d break(s)', $ligne['poule'], $ligne['nbBreaks'])
                    . ($ligne['surcharges'] !== [] ? ' — ' . implode(' ; ', $ligne['surcharges']) : '')
                );
            }
        }

        return $this->redirectToRoute('admin_phase_optimiser', ['id' => $phase->getId()]);
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
