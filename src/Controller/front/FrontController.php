<?php

namespace App\Controller\front;

use phpDocumentor\Reflection\Types\Integer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Saison;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SaisonRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

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
        return $this->render('front/index.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
        ]);
    }

    //Route pour afficher la liste des équipes qui participent à la saison sélectionnée
    #[Route('/equipes', name: 'equipes', methods: ['GET'])]
    public function equipes(SessionInterface $session, Request $request, CacheInterface $cache, SaisonRepository
    $saisonRepository): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons

        $this->getSaisonsCache($saisonRepository, $cache);
        $this->getSaisonSession($session, $request);
        $saison=$saisonRepository->find($this->idSaisonSelected);

        //Déterminer la phase à ouvrir
        $phaseouverte=$this->getPhaseActuelle($saison);
        
        //TODO Déterminer la poule à ouvrir en fonction de l'utilisateur qui est connecté et de la poule en cache


        return $this->render('front/equipes.html.twig', [
            'saisons' => $this->saisons,
            'idSaisonSelected' => $this->idSaisonSelected,
            'saison'=>$saison,
            'phaseouverte'=>$phaseouverte,
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
