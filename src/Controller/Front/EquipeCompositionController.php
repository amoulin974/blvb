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
// joueurs, ajout, retrait, désignation du capitaine). Une seule logique, deux cadres :
//  - côté site (routes front_equipe_composition_*) : le capitaine de l'équipe pour cette saison
//    (ou un admin qui navigue sur le site), gabarit front/equipe_composition/index.html.twig ;
//  - côté back-office (routes admin_equipe_composition_*, sous /admin, réservées aux admins) :
//    gabarit admin/equipe/composition.html.twig.
// Chaque action redirige vers le côté d'où elle a été appelée. Le contenu commun est dans
// front/equipe_composition/_composition.html.twig. Le contrôle d'accès se fait en ligne
// (cf. verifierAcces), comme le reste du projet (voir FrontController::api_score_update).
final class EquipeCompositionController extends AbstractController
{
    private const FRONT = '/equipe/{equipe}/saison/{saison}/composition';
    private const ADMIN = '/admin/equipe/{equipe}/saison/{saison}/composition';

    #[Route(self::FRONT, name: 'front_equipe_composition_index', methods: ['GET'])]
    #[Route(self::ADMIN, name: 'admin_equipe_composition_index', methods: ['GET'])]
    public function index(Request $request, Equipe $equipe, Saison $saison, MembreEquipeRepository $membreEquipeRepository, SaisonRepository $saisonRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);
        $prefixe = $this->prefixe($request);

        $membres = $membreEquipeRepository->findRosterBySaison($equipe, $saison);
        $joueursExclusIds = array_map(static fn(MembreEquipe $m) => $m->getJoueur()->getId(), $membres);

        $ajouterForm = $this->createForm(AjouterJoueurType::class, null, [
            'joueurs_exclus_ids' => $joueursExclusIds,
            'action' => $this->generateUrl($prefixe.'ajouter', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]),
        ]);
        $creerForm = $this->createForm(CreerJoueurType::class, null, [
            'action' => $this->generateUrl($prefixe.'creer', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]),
        ]);

        $vue = [
            'equipe' => $equipe,
            'saison' => $saison,
            'membres' => $membres,
            'ajouterForm' => $ajouterForm,
            'creerForm' => $creerForm,
            'prefixe' => $prefixe,
        ];

        if ($prefixe === 'admin_equipe_composition_') {
            // Saisons où l'équipe joue (via ses poules), pour passer de l'une à l'autre
            $saisons = [];
            foreach ($equipe->getPoules() as $poule) {
                $s = $poule->getPhase()->getSaison();
                $saisons[$s->getId()] = $s;
            }
            $saisons[$saison->getId()] = $saison;
            uasort($saisons, static fn (Saison $a, Saison $b) => $b->getDateDebut() <=> $a->getDateDebut());

            return $this->render('admin/equipe/composition.html.twig', $vue + ['saisonsEquipe' => array_values($saisons)]);
        }

        return $this->render('front/equipe_composition/index.html.twig', $vue + [
            // Le header du site (base_front.html.twig) a besoin de ces deux variables
            // pour afficher le sélecteur de saison, comme sur toutes les pages front.
            'saisons' => $saisonRepository->findAll(),
            'idSaisonSelected' => $saison->getId(),
        ]);
    }

    // Préfixe des routes du côté (site ou back-office) par lequel la requête est arrivée
    private function prefixe(Request $request): string
    {
        return str_starts_with((string) $request->attributes->get('_route'), 'admin_')
            ? 'admin_equipe_composition_'
            : 'front_equipe_composition_';
    }

    private function retour(Request $request, Equipe $equipe, Saison $saison): Response
    {
        return $this->redirectToRoute($this->prefixe($request).'index', ['equipe' => $equipe->getId(), 'saison' => $saison->getId()]);
    }

    #[Route(self::FRONT.'/ajouter', name: 'front_equipe_composition_ajouter', methods: ['POST'])]
    #[Route(self::ADMIN.'/ajouter', name: 'admin_equipe_composition_ajouter', methods: ['POST'])]
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

        return $this->retour($request, $equipe, $saison);
    }

    #[Route(self::FRONT.'/creer', name: 'front_equipe_composition_creer', methods: ['POST'])]
    #[Route(self::ADMIN.'/creer', name: 'admin_equipe_composition_creer', methods: ['POST'])]
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

                return $this->retour($request, $equipe, $saison);
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

        return $this->retour($request, $equipe, $saison);
    }

    #[Route(self::FRONT.'/{membre}/retirer', name: 'front_equipe_composition_retirer', methods: ['POST'])]
    #[Route(self::ADMIN.'/{membre}/retirer', name: 'admin_equipe_composition_retirer', methods: ['POST'])]
    public function retirer(Request $request, Equipe $equipe, Saison $saison, MembreEquipe $membre, EntityManagerInterface $entityManager, MembreEquipeRepository $membreEquipeRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);
        $this->verifierMembreDeCetteComposition($membre, $equipe, $saison);

        if ($this->isCsrfTokenValid('retirer'.$membre->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($membre);
            $entityManager->flush();
            $this->addFlash('success', sprintf('%s %s a été retiré(e) de la composition.', $membre->getJoueur()->getPrenom(), $membre->getJoueur()->getNom()));
        } else {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez.');
        }

        return $this->retour($request, $equipe, $saison);
    }

    #[Route(self::FRONT.'/{membre}/capitaine', name: 'front_equipe_composition_capitaine', methods: ['POST'])]
    #[Route(self::ADMIN.'/{membre}/capitaine', name: 'admin_equipe_composition_capitaine', methods: ['POST'])]
    public function definirCapitaine(Request $request, Equipe $equipe, Saison $saison, MembreEquipe $membre, MembreEquipeRepository $membreEquipeRepository): Response
    {
        $this->verifierAcces($equipe, $saison, $membreEquipeRepository);
        $this->verifierMembreDeCetteComposition($membre, $equipe, $saison);

        if ($this->isCsrfTokenValid('capitaine'.$membre->getId(), $request->getPayload()->getString('_token'))) {
            $membreEquipeRepository->definirCapitaine($equipe, $saison, $membre);
            $this->addFlash('success', sprintf('%s %s est maintenant capitaine.', $membre->getJoueur()->getPrenom(), $membre->getJoueur()->getNom()));
        } else {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez.');
        }

        return $this->retour($request, $equipe, $saison);
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
