<?php

namespace App\Controller\Admin;


use App\Entity\FermetureGymnase;
use App\Entity\Lieu;
use App\Form\FermetureGymnaseType;
use App\Form\LieuType;
use App\Repository\FermetureGymnaseRepository;
use App\Repository\LieuRepository;
use App\Service\FermetureGymnaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/lieu', name: 'admin_lieu_')]
final class LieuController extends AbstractController
{
    use SuppressionTrait;

    #[Route("/", name: 'index', methods: ['GET'])]
    public function index(LieuRepository $lieuRepository): Response
    {
        return $this->render('admin/lieu/index.html.twig', [
            'lieux' => $lieuRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lieu = new Lieu();
        // Pré-remplir avec les 7 jours


        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lieu);
            $entityManager->flush();

            return $this->redirectToRoute('admin_lieu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/lieu/new.html.twig', [
            'lieu' => $lieu,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Lieu $lieu): Response
    {
        return $this->render('admin/lieu/show.html.twig', [
            'lieu' => $lieu,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lieu $lieu, EntityManagerInterface $entityManager): Response
    {




        $form = $this->createForm(LieuType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_lieu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/lieu/edit.html.twig', [
            'lieu' => $lieu,
            'form' => $form,
        ]);
    }

    /** Fermetures du gymnase (D60) : l'admin enregistre même si des matchs sont programmés, qui lui sont signalés. */
    #[Route('/{id}/fermetures', name: 'fermetures', methods: ['GET', 'POST'])]
    public function fermetures(Request $request, Lieu $lieu, EntityManagerInterface $entityManager, FermetureGymnaseRepository $fermetures, FermetureGymnaseService $service): Response
    {
        $fermeture = (new FermetureGymnase())->setLieu($lieu);
        $form = $this->createForm(FermetureGymnaseType::class, $fermeture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matchsConcernes = $service->matchsConcernes($fermeture);
            $entityManager->persist($fermeture);
            $entityManager->flush();
            $this->addFlash('success', 'La fermeture est enregistrée.');
            if ($matchsConcernes !== []) {
                $this->addFlash('warning', $service->avertissementAdmin($matchsConcernes));
            }

            return $this->redirectToRoute('admin_lieu_fermetures', ['id' => $lieu->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/lieu/fermetures.html.twig', [
            'lieu' => $lieu,
            'form' => $form,
            'fermetures' => $fermetures->enCoursOuAVenir([$lieu]),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/fermetures/{fermeture}/supprimer', name: 'fermeture_supprimer', methods: ['POST'])]
    public function supprimerFermeture(Request $request, Lieu $lieu, FermetureGymnase $fermeture, EntityManagerInterface $entityManager): Response
    {
        if ($fermeture->getLieu() !== $lieu) {
            throw $this->createNotFoundException('Cette fermeture ne concerne pas ce gymnase.');
        }
        if ($this->isCsrfTokenValid('supprimer_fermeture'.$fermeture->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fermeture);
            $entityManager->flush();
            $this->addFlash('success', 'La fermeture est retirée.');
        } else {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été modifié.');
        }

        return $this->redirectToRoute('admin_lieu_fermetures', ['id' => $lieu->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Lieu $lieu, EntityManagerInterface $entityManager): Response
    {
        $this->supprimerEntite($request, $entityManager, $lieu, 'delete'.$lieu->getId(), 'le gymnase '.$lieu->getNom(), false);

        return $this->redirectToRoute('admin_lieu_index', [], Response::HTTP_SEE_OTHER);
    }
}
