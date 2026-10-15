<?php

namespace App\Controller\Admin;

use App\Competition\MiseEnPlaceJournees;
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
    use SuppressionTrait;

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
        $saisonId = $phase->getSaison()->getId();
        $this->supprimerEntite($request, $entityManager, $phase, 'delete'.$phase->getId(), 'la phase '.$phase->getNom(), true);

        // Retour à la saison : c'est de là que les phases se gèrent
        return $this->redirectToRoute('admin_saison_show', ['id' => $saisonId], Response::HTTP_SEE_OTHER);
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
    public function optimiserTout(Request $request, Phase $phase, PartieService $partieService, MiseEnPlaceJournees $verrou): Response
    {
        if (!($this->isCsrfTokenValid('optimisertout'.$phase->getId(), $request->getPayload()->getString('_token')))) {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été modifié.');
        } elseif ($verrou->verrouillee($phase)) {
            $this->addFlash('error', 'Cette phase est lancée (scores saisis ou clôturée) : ses matchs ne peuvent plus être refaits. Rien n\'a été modifié.');
        } else {
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
        Request $request
    ): Response {
        $saison = $phase->getSaison();
        if (!$this->isCsrfTokenValid('cloturer'.$phase->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. La phase n\'a pas été clôturée.');

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }
        // Une phase déjà clôturée ne l'est pas une seconde fois : cela recréerait les journées et les matchs de la
        // phase suivante, et effacerait ses scores
        if ($phase->getClose()) {
            $this->addFlash('error', sprintf('La phase %s est déjà clôturée. Rien n\'a été modifié.', $phase->getNom()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }

        // 1. Trouver la phase suivante (même règle que PhaseService : ordre immédiatement supérieur)
        $phaseSuivante = $phaseService->phaseSuivante($phase);

        if (!$phaseSuivante) {
            $this->addFlash('error', 'Il n’y a pas de phase suivante pour clôturer celle-ci.');
            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }

        // Mêmes conditions que le bouton de la fiche saison, vérifiées côté serveur : saison validée, phases
        // précédentes clôturées, chaque poule a ses journées
        $refus = null;
        if ($saison->isBrouillon()) {
            $refus = 'La saison n\'est pas encore validée : terminez le wizard avant de clôturer une phase.';
        } elseif (!$phaseService->estCloturable($phase)) {
            $refus = sprintf('La phase %s ne peut pas être clôturée : les phases précédentes doivent l\'être, et chaque poule doit avoir ses journées.', $phase->getNom());
        }
        if ($refus !== null) {
            $this->addFlash('error', $refus.' Rien n\'a été modifié.');

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }

        // Règles de passage : la clôture se fait toujours, les problèmes reviennent en avertissements (D24).
        // Les journées et les matchs de la phase suivante ne sont plus créés ici (D40) : on renvoie à la mise en place,
        // où l'on vérifie d'abord la composition des poules.
        $avertissements = $phaseService->cloturerEtBasculer($phase);
        if ($avertissements === []) {
            $this->addFlash('success', sprintf('Phase clôturée : les équipes ont été réparties dans « %s ». Vérifiez les poules, puis créez les journées et les matchs.', $phaseSuivante->getNom()));
        } else {
            // Message à liste (titre, détails, conseil), rendu par base_admin.html.twig
            $this->addFlash('warning', [
                'titre' => sprintf('Phase clôturée, mais certaines équipes n\'ont peut-être pas été réparties comme prévu dans « %s ».', $phaseSuivante->getNom()),
                'details' => $avertissements,
                'conseil' => 'Pour corriger : placez les équipes depuis cet écran. Signalez aussi le problème au responsable du site : il est le seul à pouvoir corriger les règles de passage pour l\'instant.',
            ]);
        }

        return $this->redirectToRoute('admin_saison_miseenplace_poules', ['id' => $saison->getId(), 'phase' => $phaseSuivante->getId()]);
    }

}
