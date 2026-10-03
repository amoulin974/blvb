<?php

namespace App\Controller\Front;

use App\Entity\Equipe;
use App\Entity\MembreEquipe;
use App\Entity\Saison;
use App\Form\Front\AjouterJoueurType;
use App\Form\Front\CreerJoueurType;
use App\Repository\JoueurRepository;
use App\Repository\MembreEquipeRepository;
use App\Repository\SaisonRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Page de gestion de la composition d'une équipe pour une saison donnée (liste des
// joueurs, ajout, retrait, désignation du capitaine). Partagée entre les admins et le
// capitaine de l'équipe pour cette saison : pas de contrôleur admin séparé, le contrôle
// d'accès se fait en ligne (cf. verifierAcces), comme le reste du projet le fait déjà
// (voir FrontController::api_score_update) plutôt que via un Voter.
#[Route('/equipe/{equipe}/saison/{saison}/composition', name: 'front_equipe_composition_')]
final class EquipeCompositionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Equipe $equipe, Saison $saison, MembreEquipeRepository $membreEquipeRepository, SaisonRepository $saisonRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);

        $membres = $membreEquipeRepository->findRosterBySaison($equipe, $saison);
        $joueursExclusIds = array_map(static fn(MembreEquipe $m) => $m->getJoueur()->getId(), $membres);

        $ajouterForm = $this->createForm(AjouterJoueurType::class, null, [
            'joueurs_exclus_ids' => $joueursExclusIds,
            'action' => $this->generateUrl('front_equipe_composition_ajouter', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]),
        ]);
        $creerForm = $this->createForm(CreerJoueurType::class, null, [
            'action' => $this->generateUrl('front_equipe_composition_creer', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]),
        ]);

        return $this->render('front/equipe_composition/index.html.twig', [
            'equipe' => $equipe,
            'saison' => $saison,
            'membres' => $membres,
            'ajouterForm' => $ajouterForm,
            'creerForm' => $creerForm,
            // Le header du site (base_front.html.twig) a besoin de ces deux variables
            // pour afficher le sélecteur de saison, comme sur toutes les pages front.
            'saisons' => $saisonRepository->findAll(),
            'idSaisonSelected' => $saison->getId(),
        ]);
    }

    #[Route('/ajouter', name: 'ajouter', methods: ['POST'])]
    public function ajouter(Request $request, Equipe $equipe, Saison $saison, EntityManagerInterface $entityManager, MembreEquipeRepository $membreEquipeRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);

        $form = $this->createForm(AjouterJoueurType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $joueur = $form->get('joueur')->getData();

            if ($membreEquipeRepository->findOneBy(['equipe' => $equipe, 'saison' => $saison, 'joueur' => $joueur])) {
                $this->addFlash('error', 'Ce joueur fait déjà partie de la composition.');
            } else {
                $membre = (new MembreEquipe())
                    ->setEquipe($equipe)
                    ->setSaison($saison)
                    ->setJoueur($joueur);

                $entityManager->persist($membre);
                $entityManager->flush();
                $this->addFlash('success', 'Joueur ajouté à la composition.');
            }
        } else {
            $this->addFlash('error', 'Impossible d\'ajouter ce joueur.');
        }

        return $this->redirectToRoute('front_equipe_composition_index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
    }

    #[Route('/creer', name: 'creer', methods: ['POST'])]
    public function creer(Request $request, Equipe $equipe, Saison $saison, EntityManagerInterface $entityManager, MembreEquipeRepository $membreEquipeRepository, UserRepository $userRepository, JoueurRepository $joueurRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);

        $form = $this->createForm(CreerJoueurType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $joueur = $form->getData();

            // Si l'email saisi correspond à un compte existant du site, on relie la fiche
            // à ce compte (ou on réutilise la fiche déjà liée à ce compte, s'il en a déjà
            // une) plutôt que de créer une fiche orpheline portant le même email.
            $utilisateurExistant = $joueur->getEmail() ? $userRepository->findOneBy(['email' => $joueur->getEmail()]) : null;
            if ($utilisateurExistant) {
                $joueur = $joueurRepository->findOneBy(['user' => $utilisateurExistant]) ?? $joueur->setUser($utilisateurExistant);
            }

            if ($membreEquipeRepository->findOneBy(['equipe' => $equipe, 'saison' => $saison, 'joueur' => $joueur])) {
                $this->addFlash('error', 'Ce joueur fait déjà partie de la composition.');

                return $this->redirectToRoute('front_equipe_composition_index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
            }

            $membre = (new MembreEquipe())
                ->setEquipe($equipe)
                ->setSaison($saison)
                ->setJoueur($joueur);

            if (!$joueur->getId()) {
                $entityManager->persist($joueur);
            }
            $entityManager->persist($membre);
            $entityManager->flush();
            $this->addFlash('success', $utilisateurExistant
                ? 'Fiche joueur ajoutée à la composition et liée au compte existant correspondant à cet email.'
                : 'Fiche joueur créée et ajoutée à la composition.');
        } else {
            $this->addFlash('error', 'Impossible de créer cette fiche joueur.');
        }

        return $this->redirectToRoute('front_equipe_composition_index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
    }

    #[Route('/{membre}/retirer', name: 'retirer', methods: ['POST'])]
    public function retirer(Request $request, Equipe $equipe, Saison $saison, MembreEquipe $membre, EntityManagerInterface $entityManager, MembreEquipeRepository $membreEquipeRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);
        $this->verifierMembreDeCetteComposition($membre, $equipe, $saison);

        if ($this->isCsrfTokenValid('retirer'.$membre->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($membre);
            $entityManager->flush();
            $this->addFlash('success', 'Joueur retiré de la composition.');
        }

        return $this->redirectToRoute('front_equipe_composition_index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
    }

    #[Route('/{membre}/capitaine', name: 'capitaine', methods: ['POST'])]
    public function definirCapitaine(Request $request, Equipe $equipe, Saison $saison, MembreEquipe $membre, MembreEquipeRepository $membreEquipeRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);
        $this->verifierMembreDeCetteComposition($membre, $equipe, $saison);

        if ($this->isCsrfTokenValid('capitaine'.$membre->getId(), $request->getPayload()->getString('_token'))) {
            $membreEquipeRepository->definirCapitaine($equipe, $saison, $membre);
            $this->addFlash('success', 'Nouveau capitaine désigné.');
        }

        return $this->redirectToRoute('front_equipe_composition_index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
    }

    private function verifierAcces(Equipe $equipe, Saison $saison, MembreEquipeRepository $membreEquipeRepository): void
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$membreEquipeRepository->estCapitaine($user, $equipe, $saison)) {
            throw $this->createAccessDeniedException();
        }
    }

    // Empêche de manipuler un membre d'une autre équipe/saison en trafiquant l'id dans l'URL
    // (seuls equipe/saison dans l'URL sont vérifiés par verifierAcces).
    private function verifierMembreDeCetteComposition(MembreEquipe $membre, Equipe $equipe, Saison $saison): void
    {
        if ($membre->getEquipe() !== $equipe || $membre->getSaison() !== $saison) {
            throw $this->createNotFoundException();
        }
    }
}
