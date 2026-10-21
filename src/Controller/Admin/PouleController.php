<?php

namespace App\Controller\Admin;

use App\Competition\CompositionPoules;
use App\Competition\ModeAjout;
use App\Entity\Poule;
use App\Form\PouleEditType;
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
    use SuppressionTrait;

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
    /**
     * Retouches d'une poule (D52) : nom et niveau. Phase commencée (ModeAjout::commencee) : seul le nom se modifie ;
     * phase clôturée : lecture seule. Les équipes se composent dans la mise en place, tant qu'elle n'est pas verrouillée.
     */
    public function edit(Request $request, Poule $poule, EntityManagerInterface $entityManager, ModeAjout $modeAjout, CompositionPoules $composition): Response
    {
        $phase = $poule->getPhase();
        $cloturee = (bool) $phase->getClose();
        $commencee = $cloturee || $modeAjout->commencee($phase);

        $form = $this->createForm(PouleEditType::class, $poule, ['niveau_verrouille' => $commencee, 'disabled' => $cloturee]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$cloturee && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('Poule %s enregistrée.', $poule->getNom()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $phase->getSaison()->getId(), 'openPoule' => $poule->getId()], Response::HTTP_SEE_OTHER);
        }

        $equipes = $poule->getEquipes()->toArray();
        usort($equipes, fn (Equipe $a, Equipe $b) => strcasecmp((string) $a->getNom(), (string) $b->getNom()));

        return $this->render('admin/poule/edit.html.twig', [
            'poule' => $poule,
            'form' => $form,
            'equipes' => $equipes,
            'commencee' => $commencee,
            'cloturee' => $cloturee,
            // Lien vers l'étape « Poules » de la mise en place, tant que la composition de la phase se modifie
            'composable' => !$composition->verrouillee($phase),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Poule $poule, EntityManagerInterface $entityManager, ModeAjout $modeAjout): Response
    {
        $saisonId = $poule->getPhase()->getSaison()->getId();
        if ($this->isCsrfTokenValid('delete'.$poule->getId(), $request->getPayload()->getString('_token')) && $modeAjout->jouee($poule->getPhase())) {
            // Phase jouée : la poule, ses matchs et ses scores ne se suppriment plus (D54) ; jeton absent : message habituel
            $this->addFlash('error', ModeAjout::refusSuppression(sprintf('la poule %s',$poule->getNom()), $poule->getPhase()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $saisonId, 'openPoule' => $poule->getId()], Response::HTTP_SEE_OTHER);
        }
        $this->supprimerEntite($request, $entityManager, $poule, 'delete'.$poule->getId(), 'la poule '.$poule->getNom(), true);

        // Retour à la saison : c'est de là que les poules se gèrent
        return $this->redirectToRoute('admin_saison_show', ['id' => $saisonId], Response::HTTP_SEE_OTHER);
    }













}
