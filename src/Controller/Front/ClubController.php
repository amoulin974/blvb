<?php

namespace App\Controller\Front;

use App\Entity\Club;
use App\Entity\FermetureGymnase;
use App\Form\FermetureGymnaseType;
use App\Form\Front\ClubFicheType;
use App\Repository\ClubRepository;
use App\Repository\FermetureGymnaseRepository;
use App\Repository\SaisonRepository;
use App\Security\ClubVoter;
use App\Service\ClubImageService;
use App\Service\FermetureGymnaseService;
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

    /**
     * Fermetures des gymnases du club (D60). Un match programmé pendant la période empêche le responsable d'enregistrer :
     * il écrit aux administrateurs, qui peuvent enregistrer malgré le match puis le replacer.
     */
    #[Route('/{id}/fermetures', name: 'fermetures', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ClubVoter::MODIFIER, subject: 'club')]
    public function fermetures(Request $request, Club $club, EntityManagerInterface $entityManager, FermetureGymnaseRepository $fermetures, FermetureGymnaseService $service): Response
    {
        $fermeture = new FermetureGymnase();
        $form = $this->createForm(FermetureGymnaseType::class, $fermeture, ['lieux' => $club->getLieux()->toArray()]);
        $form->handleRequest($request);

        $matchsConcernes = [];
        $emailsAdmins = [];
        $contactAdmins = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $matchsConcernes = $service->matchsConcernes($fermeture);
            if ($matchsConcernes === [] || $this->isGranted('ROLE_ADMIN')) {
                $entityManager->persist($fermeture);
                $entityManager->flush();
                $this->addFlash('success', sprintf('La fermeture de %s est enregistrée.', $fermeture->getLieu()->getNom()));
                if ($matchsConcernes !== []) {
                    $this->addFlash('warning', $service->avertissementAdmin($matchsConcernes));
                }

                return $this->redirectToRoute('front_club_fermetures', ['id' => $club->getId()], Response::HTTP_SEE_OTHER);
            }
            $emailsAdmins = $service->emailsContact();
            $contactAdmins = $service->lienContactAdmins($fermeture, $matchsConcernes, $emailsAdmins);
        }

        return $this->render('front/club/fermetures.html.twig', [
            'club' => $club,
            'form' => $form,
            'fermetures' => $fermetures->enCoursOuAVenir($club->getLieux()),
            'matchsConcernes' => $matchsConcernes,
            'contactAdmins' => $contactAdmins,
            'emailsAdmins' => $emailsAdmins,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Tout responsable du club retire une fermeture d'un gymnase rattaché au club, même saisie par un autre club. */
    #[Route('/{id}/fermetures/{fermeture}/supprimer', name: 'fermeture_supprimer', requirements: ['id' => '\d+', 'fermeture' => '\d+'], methods: ['POST'])]
    #[IsGranted(ClubVoter::MODIFIER, subject: 'club')]
    public function supprimerFermeture(Request $request, Club $club, FermetureGymnase $fermeture, EntityManagerInterface $entityManager): Response
    {
        if (!$club->getLieux()->contains($fermeture->getLieu())) {
            throw $this->createNotFoundException('Ce gymnase n\'est pas rattaché à ce club.');
        }
        if ($this->isCsrfTokenValid('supprimer_fermeture'.$fermeture->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fermeture);
            $entityManager->flush();
            $this->addFlash('success', 'La fermeture est retirée.');
        } else {
            $this->addFlash('warning', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été modifié.');
        }

        return $this->redirectToRoute('front_club_fermetures', ['id' => $club->getId()], Response::HTTP_SEE_OTHER);
    }
}
