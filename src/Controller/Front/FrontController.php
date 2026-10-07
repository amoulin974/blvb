<?php

namespace App\Controller\Front;


use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Entity\User;
use App\Form\Front\UserChangePasswordType;
use App\Form\Front\UserProfileType;
use App\Repository\LieuRepository;
use App\Repository\MembreEquipeRepository;
use App\Repository\PartieRepository;
use App\Repository\EquipeRepository;
use App\Repository\SaisonRepository;
use App\Service\CalendrierAnalyseService;
use App\Service\ClassementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Exception;

#[Route('/', name: 'front_')]
final class FrontController extends AbstractController
{
    private array $saisons;
    private ?int $idSaisonSelected;



    #[Route('', name: 'index')]
    public function index(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, EquipeRepository $equipeRepository, MembreEquipeRepository $membreEquipeRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }

        $saison=$saisonRepository->find($this->idSaisonSelected);

        $user= $this->getUser();
        $equipesCapitaine=[];
        if ($user){
            $equipesCapitaine = $membreEquipeRepository->findEquipesOuCapitaine($user, $saison);
        }
        $equipe = $equipesCapitaine[0] ?? null;
        $equipesCapitaineIds = array_map(static fn(Equipe $e) => $e->getId(), $equipesCapitaine);

        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        return $this->render('front/index.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'equipe'=>$equipe,
            'equipesCapitaineIds'=>$equipesCapitaineIds,
            'saison' => $saison,
            'phaseouverte' => $phaseouverte,
        ]);
    }

    //Route pour afficher la liste des équipes qui participent à la saison sélectionnée
    #[Route('/equipes', name: 'equipes', methods: ['GET'])]
    public function equipes(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, ClassementService $classementService): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons

        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }
        $saison=$saisonRepository->find($this->idSaisonSelected);


        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        //TODO Déterminer la poule à ouvrir en fonction de l'utilisateur qui est connecté et de la poule en cache


        //Trier les équipes par le classement
//        $classementService->getClassement($saison);

        return $this->render('front/equipes.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
        ]);
    }
    //Route pour une page de réglement
    #[Route('/reglement', name: 'reglement', methods: ['GET'])]
    public function reglement(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
                                               $saisonRepository, ClassementService $classementService): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        return $this->render('front/reglement.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
        ]);
    }

    //Route pour une page de qui-sommes-nous
    #[Route('/qui-sommes-nous', name: 'qui_sommes_nous', methods: ['GET'])]
    public function quiSommesNous(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }

        $groupes=[
            [
                'nom'=> 'A ELITE DES AS',
                'image'=>'t1_elite_des_as.jpg',
                'description'=>'Le niveau le plus relevé ! Ici, les équipes s’affrontent avec passion et stratégie. Les matchs sont spectaculaires et intenses.'
            ],
            [
                'nom'=> 'B ELITE',
                'image'=>'terrain.jpg',
                'description'=>'Les équipes ambitieuses rivalisent dans ce groupe. Les matchs sont équilibrés et chaque victoire se fête avec enthousiasme.'
            ],
            [
                'nom'=> 'C ESPOIR',
                'image'=>'salle.jpg',
                'description'=>'Ici, les équipes apprennent et progressent. Chaque match est un moment convivial et amusant.'
            ],
            [
                'nom'=> 'D HONNEUR',
                'image'=>'trophee_honneur.jpg',
                'description'=>'Le groupe parfait pour se lancer et s’amuser ! L’ambiance est détendue et conviviale.'
            ]
        ];

        return $this->render('front/qui_sommes_nous.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'groupes' => $groupes
        ]);
    }

    //Route pour afficher le détail d'une équipe : calendrier et classenment et info sur le capitaine dans la saison sélectionnée
    #[Route('/equipe/{id}', name: 'equipe_detail', methods: ['GET'])]
    public function equipe_detail(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, ClassementService $classementService, Equipe $equipe, EquipeRepository $equipeRepository, PartieRepository $partieRepository, MembreEquipeRepository $membreEquipeRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        $poules=$equipeRepository->findPoulesBySaison($equipe,$saison);

        //On recupère la liste des matchs
        $matchsByPoule=[];
        foreach ($poules as $poule) {
            $matchsByPoule[$poule->getId()] = $partieRepository->getMatchsByEquipePhase($equipe->getId(), $poule->getId());

            //Pour chaque match on vérifie si l'équipe a gagné ou perdu
            foreach ($matchsByPoule[$poule->getId()] as &$match) {

                $match['resultat']='';
                $reception=false;
                if ($equipe->getNom() === $match["equipe_recoit"]) {$reception=true;}
                if ($reception && $match["score_reception_match"] > $match["score_deplacement_match"]){$match['resultat']="1";}
                if ($reception && $match["score_reception_match"] < $match["score_deplacement_match"]){$match['resultat']="0";}
                if (!$reception && $match["score_deplacement_match"] > $match["score_reception_match"]){$match['resultat']="1";}
                if (!$reception && $match["score_deplacement_match"] < $match["score_reception_match"]){$match['resultat']="0";}

            }
        }

        //On récupère la liste des capitaines des équipes de la même poule que l'équipe sélectionnée
        $listeCapitaines=[];
        foreach ($poules as $poule) {
            if ($poule->getPhase()->getId() === $phaseouverte->getId()) {
                foreach ($poule->getEquipes() as $equipePoule) {
                    $capitaineMembre = $membreEquipeRepository->findCapitaine($equipePoule, $saison);
                    $capitaineUser = $capitaineMembre?->getJoueur()->getUser();
                    if ($capitaineUser !== null && !in_array($capitaineUser->getId(), $listeCapitaines)){
                        $listeCapitaines[] = $capitaineUser->getId();
                    }
                }
            }
        }

        //Si l'utilisateur connecté est dans cette liste on l'autorise à voir les coordonnées des membres de l'équipe sélectionnée
        $user = $this->getUser();
        $canViewCapitaine = false;
        if (($user !== null && in_array($user->getId(), $listeCapitaines)) || $this->isGranted('ROLE_ADMIN')){
              $canViewCapitaine = true;
        }

        //Composition de l'équipe pour la saison sélectionnée
        $membres = $membreEquipeRepository->findRosterBySaison($equipe, $saison);
        $estCapitaineDeCetteEquipe = $user !== null && $membreEquipeRepository->estCapitaine($user, $equipe, $saison);



        return $this->render('front/equipe.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
            'canViewCapitaine'=>$canViewCapitaine,
            'equipe'=>$equipe,
            'matchsByPoule'=>$matchsByPoule,
            'membres'=>$membres,
            'estCapitaineDeCetteEquipe'=>$estCapitaineDeCetteEquipe,
        ]);
    }

    //Route pour afficher le calendrier des matchs de la saison sélectionnée
    #[Route('/calendrier', name: 'calendrier', methods: ['GET'])]
    public function calendrier(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, MembreEquipeRepository $membreEquipeRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        $user = $this->getUser();
        $equipesCapitaineIds = [];
        if ($user) {
            $equipesCapitaineIds = array_map(
                static fn(Equipe $e) => $e->getId(),
                $membreEquipeRepository->findEquipesOuCapitaine($user, $saison)
            );
        }

        return $this->render('front/calendrier.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
            'equipesCapitaineIds'=>$equipesCapitaineIds,
        ]);
    }
    //Route pour afficher le calendrier des matchs de la saison sélectionnée en fonction des lieux

    #[Route('/calendrierlieu', name: 'calendrierlieu', methods: ['GET'])]
    public function calendrierLieu(
        SessionInterface $session,
        Request $request,
        CacheInterface $cache,
        SaisonRepository $saisonRepository,
        LieuRepository $lieuRepository,
        CalendrierAnalyseService $analyseService
    ): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }

        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);
        $lieux=$lieuRepository->findAll();

        foreach ($lieux as $lieu) {
            $partiesDuLieu = [];

            // 1. Regroupement
            foreach ($lieu->getParties() as $partie) {
                $phaseId = $partie->getPoule()->getPhase()->getId();
                $dateKey = $partie->getDate()->format('Y-m-d');

                if (!isset($partiesDuLieu[$phaseId])) {
                    $partiesDuLieu[$phaseId] = [];
                }
                if (!isset($partiesDuLieu[$phaseId][$dateKey])) {
                    $partiesDuLieu[$phaseId][$dateKey] = [];
                }

                $partiesDuLieu[$phaseId][$dateKey][] = $partie;
            }
            // 2. Analyse via le Service (Nettoyé)
            foreach ($partiesDuLieu as $phaseId => $dates) {
                foreach ($dates as $dateKey => $matchs) {
                    $dateObjet = new \DateTime($dateKey);


                    $analysesDuLieu[$phaseId][$dateKey] = $analyseService->analyser(
                        $lieu,
                        $dateObjet,
                        count($matchs)
                    );
                }
            }

            $lieu->partiesByDate = $partiesDuLieu;
            $lieu->analysesByDate = $analysesDuLieu;
        }

        return $this->render('front/calendrier_lieu.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'lieux'=>$lieux,
            'phaseouverte'=>$phaseouverte,
        ]);
    }


    //Route pour afficher le classement des équipes de la saison sélectionnée
    #[Route('/classement', name: 'classement', methods: ['GET'])]
    public function classement(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, ClassementService $classementService): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

//        $classementService->getClassement($saison);
        return $this->render('front/classement.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
        ]);
    }

    //Route appelé quand l'utilisateur change de saison dans le menu déroulant du header
    #[Route('/set-saison', name: 'set_saison', methods: ['POST'])]
    public function setSaison(Request $request, SessionInterface $session, SaisonRepository $saisonRepository): Response
    {
        $idSaisonSelected = $request->request->get('idSaisonSelected'); // récupère l'id envoyé
        $saison = $saisonRepository->find($idSaisonSelected);   // récupère l'objet saison

        if ($saison) {
            $session->set('idSaisonSelected', $saison->getId());   // stocke la saison dans la session
        }
        return $this->redirectToRoute('front_index');  // retourne vers la page principale
    }

    //Page affichée quand il n'y a aucune saison en base de données (plus de saison favorite ni de saison récente à proposer)
    #[Route('/aucune-saison', name: 'no_saison', methods: ['GET'])]
    public function noSaison(): Response
    {
        return $this->render('front/no_saison.html.twig', [
            'saisons' => [],
            'idSaisonSelected' => null,
        ]);
    }

    //Route appelé quand un admin ou un capitaine change le score d'une partie
    #[Route('/front/partie/{id}/api/update', name: 'api_score_update', methods: ['PUT'])]
    public function api_score_update(Request $request, Partie $partie, EntityManagerInterface $em, ClassementService $classementService, MembreEquipeRepository $membreEquipeRepository): JsonResponse
    {

    // Jeton CSRF envoyé par score-modal-controller.js (balise <meta name="csrf-token"> de base_front)
    if (!$this->isCsrfTokenValid('score_update', $request->headers->get('X-CSRF-TOKEN'))) {
        return $this->json(['error' => 'Jeton de sécurité invalide, rechargez la page.'], 403);
    }

    $data = json_decode($request->getContent(), true);
    try{
        $user = $this->getUser();
        //Vérifier si user à le role admin ou s'il est capitaine de l'équipe qui reçoit (pour la saison du match)
        if (!$this->isGranted('ROLE_ADMIN')) {
            $saisonPartie = $partie->getPoule()->getPhase()->getSaison();
            if (!$user || !$membreEquipeRepository->estCapitaine($user, $partie->getIdEquipeRecoit(), $saisonPartie)) {
                throw new Exception("Modification interdite");
            }
        }

        if (!isset($data['scoreReception'])) throw new Exception("Score réception invalide");
        if (!isset($data['scoreDeplacement'])) throw new Exception("Score déplacement invalide");

        if (trim($data['scoreReception']) === '' && trim($data['scoreDeplacement']) === ''){
            //Les deux champs vides : on efface le score (ex. score saisi par erreur sur un match non joué)
            $partie->setNbSetGagnantReception(null);
            $partie->setNbSetGagnantDeplacement(null);
            $newScore = null;
        } elseif ($data['scoreReception'] === 'F' || $data['scoreDeplacement'] === 'F'){
            //Cas d'une forfait
            if ($data['scoreReception'] === 'F' && $data['scoreDeplacement'] === 'F') throw new Exception("Deux forfaits impossibles");
            if ($data['scoreReception'] === 'F'){
                $partie->setNbSetGagnantReception(-1);
                $partie->setNbSetGagnantDeplacement(3);
                $newScore = 'F - 3';
            } else{
                $partie->setNbSetGagnantReception(3);
                $partie->setNbSetGagnantDeplacement(-1);
                $newScore = '3 - F';
            }


        }else{
            //Cas normal
            if (! is_numeric($data['scoreReception']) || ! is_numeric($data['scoreDeplacement'])) throw new Exception ("score invalide");
            $scoreReception = (int)($data['scoreReception']);
            $scoreDeplacement = (int)$data['scoreDeplacement'];
            if ($scoreDeplacement > 3 || $scoreReception > 3 || $scoreDeplacement < 0 || $scoreReception < 0) throw new Exception("Score invalide");

            $partie->setNbSetGagnantReception($scoreReception);
            $partie->setNbSetGagnantDeplacement($scoreDeplacement);
            $newScore = $scoreReception . ' - ' . $scoreDeplacement;


        }
        //Modification du score
        $em->persist($partie);
        $em->flush();

        //Mise à jour du classement
        $classementService->mettreAJourClassementPoule($partie->getPoule());
        return $this->json([
            // Même format que l'affichage du calendrier ("3 - 1"), null si le score a été effacé
            'newScore' => $newScore,

            ],200);



        }catch(Exception $e){
            return $this->json([
            'error' => $e->getMessage(),
            ],400);
        }

    }

    //Récupère les saisons en cache (la liste des saisons ne change pas souvent donc à chaque requête on ne va pas la chercher en base de données)
    //Si le cache a expiré (1 heure ici), on va chercher en base de données et on remplit le cache à nouveau
    public function getSaisonsCache(SaisonRepository $saisonRepository, CacheInterface $cache)
    {
        $this->saisons = $cache->get('saisons_all', function (ItemInterface $item) use ($saisonRepository){
            $item->expiresAfter(3600);
            return $saisonRepository->findBy([], ['favori' => 'DESC']);
        });



    }

    //Récupère la saison sélectionnée dans la session et vérifie qu'elle existe toujours en base
    //(ex: suppression de la saison depuis l'admin). Si elle n'existe pas (ou plus), on retombe sur
    //la saison favorite, puis sur la plus récente. Retourne une redirection vers une page d'erreur
    //s'il n'y a tout simplement aucune saison en base, sinon null pour continuer normalement.
    public function getSaisonSession(SessionInterface $session, Request $request, SaisonRepository $saisonRepository): ?Response
    {
        $idSelected = $session->has('idSaisonSelected') ? $session->get('idSaisonSelected') : null;
        $saison = $idSelected ? $saisonRepository->find($idSelected) : null;

        if (!$saison) {
            // L'id en session est absent, invalide ou ne correspond plus à une saison existante :
            // on retombe sur la saison favorite...
            $saison = $saisonRepository->findOneBy(['favori' => 1]);

            // ... sinon sur la saison la plus récente...
            if (!$saison) {
                $saison = $saisonRepository->findOneBy([], ['date_debut' => 'DESC']);
            }

            // ... sinon il n'y a tout simplement aucune saison en base.
            if (!$saison) {
                $this->idSaisonSelected = null;

                return $this->redirectToRoute('front_no_saison');
            }

            $session->set('idSaisonSelected', $saison->getId());
        }

        $this->idSaisonSelected = $saison->getId();

        return null;
    }

    //Détermine la phase actuelle d'une saison (la phase dont la date de début et de fin englobe la date actuelle)
    //ou la première phase si aucune n'est ouverte
    public function getPhaseActuelle($saison){

        $dateActuelle = new \DateTime();
        foreach ($saison->getPhases() as $phase) {
            if ($dateActuelle >= $phase->getDateDebut() && $dateActuelle <= $phase->getDateFin()) {
                return $phase;
            }

        }
        return $saison->getPhases()->first(); // Retourne la première phase si aucune n'est ouverte
    }

    /**
     * Permet de modifier le profil utilisateur
     */
    #[Route('/mon-profil', name: 'profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SaisonRepository $saisonRepository,
        MembreEquipeRepository $membreEquipeRepository,
        CacheInterface $cache,
        SessionInterface $session
    ): Response {
        $this->getSaisonsCache($saisonRepository, $cache);
        if ($redirect = $this->getSaisonSession($session, $request, $saisonRepository)) {
            return $redirect;
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Groupement par saison des équipes où l'utilisateur est capitaine, pour l'affichage
        // (les objets Equipe/Saison sont conservés, pas juste leur nom, pour pouvoir lier vers
        // la page de gestion de la composition de l'équipe pour cette saison)
        $equipesParSaison = [];
        foreach ($membreEquipeRepository->findMembresCapitaine($user) as $membre) {
            $saison = $membre->getSaison();
            if (!isset($equipesParSaison[$saison->getId()])) {
                $equipesParSaison[$saison->getId()] = [
                    'saison' => $saison,
                    'equipes' => [],
                ];
            }
            $equipesParSaison[$saison->getId()]['equipes'][] = $membre->getEquipe();
        }

        // Initialisation des deux formulaires
        $profileForm = $this->createForm(UserProfileType::class, $user);
        $passwordForm = $this->createForm(UserChangePasswordType::class, $user);

        $profileForm->handleRequest($request);
        $passwordForm->handleRequest($request);

        // Traitement de la mise à jour du profil
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Informations mises à jour.');
            return $this->redirectToRoute('front_profile_edit');
        }

        // Traitement du changement de mot de passe
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $plainPassword = $passwordForm->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $entityManager->flush();
            $this->addFlash('success', 'Mot de passe modifié avec succès.');
            return $this->redirectToRoute('front_profile_edit');
        }

        return $this->render('front/profile_edit.html.twig', [
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm->createView(),
            'user' => $user,
            'equipesParSaison' => $equipesParSaison,
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected
        ]);
    }




}
