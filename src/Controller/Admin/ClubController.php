<?php

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Form\ClubType;
use App\Repository\ClubRepository;
use App\Repository\SaisonRepository;
use Symfony\Component\Form\FormInterface;
use App\Service\ClubImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Clubs : nom, équipes, gymnases et responsables (qui peuvent saisir les scores des matchs reçus
 * par les équipes du club, voir App\Security\ScoreVoter).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/club', name: 'admin_club_')]
final class ClubController extends AbstractController
{
    use SuppressionTrait;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ClubRepository $clubRepository, SaisonRepository $saisonRepository): Response
    {
        return $this->render('admin/club/index.html.twig', [
            'clubs' => $clubRepository->findTousAvecRelations(),
            'saison' => $saisonRepository->findCourante(),
        ]);
    }

    /** Responsables choisis dans le formulaire : désignés pour la saison en cours. */
    private function appliquerResponsables(FormInterface $form, Club $club, SaisonRepository $saisonRepository): void
    {
        $saison = $saisonRepository->findCourante();
        if ($saison !== null && $form->has('responsables')) {
            $club->setResponsables($saison, $form->get('responsables')->getData());
        }
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ClubImageService $images, SaisonRepository $saisonRepository): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club, ['saison' => $saisonRepository->findCourante()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images->appliquerFormulaire($form, $club);
            $this->appliquerResponsables($form, $club, $saisonRepository);
            $entityManager->persist($club);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Le club %s a été créé.', $club->getNom()));

            return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/club/new.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager, ClubImageService $images, SaisonRepository $saisonRepository): Response
    {
        $form = $this->createForm(ClubType::class, $club, ['saison' => $saisonRepository->findCourante()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images->appliquerFormulaire($form, $club);
            $this->appliquerResponsables($form, $club, $saisonRepository);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Le club %s a été enregistré.', $club->getNom()));

            return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $entityManager, ClubImageService $images): Response
    {
        // Les équipes restent, sans club ; les désignations de responsables sont supprimées avec le club ;
        // les gymnases et les comptes ne sont pas touchés
        $image = $club->getImage();
        $supprime = $this->supprimerEntite($request, $entityManager, $club, 'delete'.$club->getId(), 'le club '.$club->getNom(), false, static function () use ($club): void {
            foreach ($club->getEquipes()->toArray() as $equipe) {
                $club->removeEquipe($equipe);
            }
        });
        if ($supprime && $image !== null) {
            $images->retirer($club); // supprime aussi le fichier de l'image
        }

        return $this->redirectToRoute('admin_club_index', [], Response::HTTP_SEE_OTHER);
    }
}
