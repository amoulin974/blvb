<?php

namespace App\Controller\Front;

use App\Repository\LieuRepository;
use App\Repository\PartieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Saison;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Partie;
use App\Entity\Classement;
use App\Repository\EquipeRepository;
use App\Service\CalendrierAnalyseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SaisonRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\ClassementService;
use Exception;

#[Route('/', name: 'front_')]
final class FrontController extends AbstractController
{
    private array $saisons;
    private ?int $idSaisonSelected;



    #[Route('', name: 'index')]
    public function index(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository $saisonRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        $this->getSaisonSession($session, $request);

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
        return $this->render('front/index.html.twig', [
            'groupes'=>$groupes,
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
        ]);
    }

    //Route pour afficher la liste des équipes qui participent à la saison sélectionnée
    #[Route('/equipes', name: 'equipes', methods: ['GET'])]
    public function equipes(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, ClassementService $classementService): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons

        $this->getSaisonsCache($saisonRepository, $cache);
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);

        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        //TODO Déterminer la poule à ouvrir en fonction de l'utilisateur qui est connecté et de la poule en cache


        //Trier les équipes par le classement
        $classementService->getClassement($saison);

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
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        return $this->render('front/reglement.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
        ]);
    }

    //Route pour afficher le détail d'une équipe : calendrier et classenment et info sur le capitaine dans la saison sélectionnée
    #[Route('/equipe/{id}', name: 'equipe_detail', methods: ['GET'])]
    public function equipe_detail(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository, ClassementService $classementService, int $id, EquipeRepository $equipeRepository, PartieRepository $partieRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        /**Equipe $equipe */
        $equipe=$equipeRepository->find($id);
        //Trier les équipes par le classement
        $classementService->getClassement($saison);
        $poules=$equipe->getPoules();

        //On recupère la liste des matchs
        $matchsByPoule=[];
        foreach ($poules as $poule) {
            $matchsByPoule[$poule->getId()] = $partieRepository->getMatchsByEquipePhase($equipe->getId(), $poule->getId());
        }

        //On récupère la liste des capitaines des équipes de la même poule que l'équipe sélectionnée
        $listeCapitaines=[];
        foreach ($poules as $poule) {
            if ($poule->getPhase()->getId() === $phaseouverte->getId()) {
                foreach ($poule->getEquipes() as $equipePoule) {
                    if ($equipePoule->getCapitaine() !== null && !in_array($equipePoule->getCapitaine()->getId(), $listeCapitaines)){
                        $listeCapitaines[] = $equipePoule->getCapitaine()->getId();
                    }
                }
            }
        }

        //Si l'utilisateur connecté est dans cette liste on l'autorise à voir les coordonnées du capitaine de l'équipe sélectionnée
        $user = $this->getUser();
        $canViewCapitaine = false;
        if (($user !== null && in_array($user->getId(), $listeCapitaines)) || $this->isGranted('ROLE_ADMIN')){
              $canViewCapitaine = true;
        }





        return $this->render('front/equipe.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
            'canViewCapitaine'=>$canViewCapitaine,
            'equipe'=>$equipe,
            'matchsByPoule'=>$matchsByPoule
        ]);
    }

    //Route pour afficher le calendrier des matchs de la saison sélectionnée
    #[Route('/calendrier', name: 'calendrier', methods: ['GET'])]
    public function calendrier(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
        $this->getSaisonsCache($saisonRepository, $cache);
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        return $this->render('front/calendrier.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
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
        $this->getSaisonSession($session, $request);

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
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);
        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);

        $classementService->getClassement($saison);
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

        //Route appelé quand un admin ou un capitaine change le score d'une partie
        #[Route('/front/partie/{id}/api/update', name: 'api_score_update', methods: ['PUT'])]
        public function api_score_update(Request $request, Partie $partie, EntityManagerInterface $em, ClassementService $classementService): JsonResponse
        {

        $data = json_decode($request->getContent(), true);
        try{
            $user = $this->getUser();
            //Vérifier si user à le role admin ou s'il est capitaine de l'équipe qui reçoit
            if (!$this->isGranted('ROLE_ADMIN') && $partie->getIdEquipeRecoit()->getCapitaine()->getId() != $user->getId()) throw new Exception("Modification interdite");

            if (!isset($data['scoreReception'])) throw new Exception("Score réception invalide");
            if (!isset($data['scoreDeplacement'])) throw new Exception("Score déplacement invalide");

            if ($data['scoreReception'] === 'F' || $data['scoreDeplacement'] === 'F'){
                //Cas d'une forfait
                if ($data['scoreReception'] === 'F' && $data['scoreDeplacement'] === 'F') throw new Exception("Deux forfaits impossibles");
                if ($data['scoreReception'] === 'F'){
                    $partie->setNbSetGagnantReception(-1);
                    $partie->setNbSetGagnantDeplacement(3);
                } else{
                    $partie->setNbSetGagnantReception(3);
                    $partie->setNbSetGagnantDeplacement(-1);
                }


            }else{
                //Cas normal
                if (! is_numeric($data['scoreReception']) || ! is_numeric($data['scoreDeplacement'])) throw new Exception ("score invalide");
                $scoreReception = (int)($data['scoreReception']);
                $scoreDeplacement = (int)$data['scoreDeplacement'];
                if ($scoreDeplacement > 3 || $scoreReception > 3 || $scoreDeplacement < 0 || $scoreReception < 0) throw new Exception("Score invalide");

                $partie->setNbSetGagnantReception($scoreReception);
                $partie->setNbSetGagnantDeplacement($scoreDeplacement);


            }
            //Modification du score
            $em->persist($partie);
            $em->flush();

            //Mise à jour du classement
            $classementService->mettreAJourClassementPoule($partie->getPoule());
            return $this->json([
                'newScore' => $scoreReception . ' à ' . $scoreDeplacement,

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

    //Récupère la saison sélectionnée dans la session ou met la saison par défaut
    public function getSaisonSession(SessionInterface $session, Request $request){
        if ($session->has('idSaisonSelected')){
            $this->idSaisonSelected = $session->get('idSaisonSelected');
        } else{
            $this->idSaisonSelected = $this->saisons[0]->getId() ;
        }
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
        return $saison->getPhases()[0]; // Retourne la première phase si aucune n'est ouverte
    }




}
