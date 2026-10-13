<?php

namespace App\Controller\Front;

use App\Entity\Club;
use App\Form\Front\ClubFicheType;
use App\Repository\ClubRepository;
use App\Repository\SaisonRepository;
use App\Security\ClubVoter;
use App\Service\ClubImageService;
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
    /** Liste des clubs (sous-menu « Le championnat ») : cartes cliquables, triées par nom long. */
    #[Route('s', name: 'index', methods: ['GET'])]
    public function index(ClubRepository $clubRepository, SaisonRepository $saisonRepository): Response
    {
        $saison = $saisonRepository->findFavoritePubliee();

        return $this->render('front/club/index.html.twig', [
            'clubs' => $clubRepository->findTousAvecRelations(),
            'saison' => $saison,
            'nombreEquipes' => $saison ? $clubRepository->nombreEquipesEngagees($saison) : [],
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Club $club, ClubRepository $clubRepository, SaisonRepository $saisonRepository): Response
    {
        // Seules les équipes engagées dans la saison en cours sont listées, avec leur poule
        $saison = $saisonRepository->findFavoritePubliee();

        return $this->render('front/club/show.html.twig', [
            'club' => $club,
            'saison' => $saison,
            'equipesEngagees' => $saison ? $clubRepository->equipesEngagees($club, $saison) : [],
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ClubVoter::MODIFIER, subject: 'club')]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager, ClubImageService $images): Response
    {
        $abreviationAvant = $club->getNomCourt();
        $form = $this->createForm(ClubFicheType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images->appliquerFormulaire($form, $club);
            $entityManager->flush();
            $this->addFlash('success', 'La fiche du club a été enregistrée.');
            // Abréviation modifiée : rappeler l'effet sur le nom affiché des équipes
            if ($club->getNomCourt() !== $abreviationAvant && !$club->getEquipes()->isEmpty()) {
                $this->addFlash('success', sprintf(
                    'L\'abréviation est passée de %s à %s : les équipes du club s\'affichent désormais ainsi, par exemple « %s ».',
                    mb_strtoupper((string) $abreviationAvant), mb_strtoupper((string) $club->getNomCourt()), $club->getEquipes()->first()->getNom()
                ));
            }

            return $this->redirectToRoute('front_club_show', ['id' => $club->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front/club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
