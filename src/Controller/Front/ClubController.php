<?php

namespace App\Controller\Front;

use App\Entity\Club;
use App\Form\Front\ClubFicheType;
use App\Security\ClubVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fiche publique d'un club (nom long, abréviation, description, lien, équipes, gymnases) et sa modification
 * par les responsables du club ou l'admin (ClubVoter).
 */
#[Route('/club', name: 'front_club_')]
final class ClubController extends AbstractController
{
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Club $club): Response
    {
        return $this->render('front/club/show.html.twig', [
            'club' => $club,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ClubVoter::MODIFIER, subject: 'club')]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClubFicheType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'La fiche du club a été enregistrée.');

            return $this->redirectToRoute('front_club_show', ['id' => $club->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front/club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
