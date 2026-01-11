<?php

namespace App\Controller\Admin;


use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Form\PartieType;
use App\Form\PartieCalendarType;
use App\Repository\PartieRepository;
use App\Service\PartieService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function PHPUnit\Framework\throwException;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/partie', name: 'admin_partie_')]
final class PartieController extends AbstractController
{
    #[Route("/", name: 'index', methods: ['GET'])]
    public function index(PartieRepository $partieRepository): Response
    {
        return $this->render('admin/partie/index.html.twig', [
            'parties' => $partieRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $partie = new Partie();
        $form = $this->createForm(PartieType::class, $partie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($partie);
            $entityManager->flush();

            return $this->redirectToRoute('admin_partie_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/partie/new.html.twig', [
            'partie' => $partie,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Partie $partie): Response
    {
        return $this->render('admin/partie/show.html.twig', [
            'partie' => $partie,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Partie $partie, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PartieType::class, $partie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_partie_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/partie/profile_dit.html.twig', [
            'partie' => $partie,
            'form' => $form,
        ]);
    }

    #[Route('/admin/partie/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Partie $partie, EntityManagerInterface $entityManager): Response
    {
        // On récupère les infos pour la redirection avant la suppression
        $poule = $partie->getPoule();
        $saisonId = $poule->getPhase()->getSaison()->getId();

        // Vérification du jeton CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete'.$partie->getId(), $request->request->get('_token'))) {
            $entityManager->remove($partie);
            $entityManager->flush();

            $this->addFlash('success', 'Le match a été supprimé avec succès.');
        }

        // On redirige vers la vue "show" de la saison en ajoutant l'ancre vers la poule
        return $this->redirectToRoute('admin_saison_show', [
            'id' => $saisonId,
            'openPoule' => $poule->getId()
        ]);
    }

    //fonction qui crée les matchs pour une poule
    #[Route('/{id}/createpartie', name: 'createpartie', methods: ['POST'])]
    public function createPartie(Poule $poule, Request $request, EntityManagerInterface $entityManager, PartieService $partieService): Response
    {
        $partieService->createCalendar($poule);
        return $this->redirectToRoute('admin_saison_show', ['id' => $poule->getPhase()->getSaison()->getId()]);
    }

    //Affiche le calendrier des journées pour une poule

    #[Route('/{id}/getpartiecalendar/{journee}', name: 'getpartiecalendar', methods: ['GET'])]
    public function getPartieCalendar (Poule $poule, Journee $journee): Response
    {

        // Vérifie l'appartenance
        if ($journee->getPoule() !== $poule) {
            throw $this->createNotFoundException("Cette journée n'appartient pas à cette poule.");
        }

        return $this->render('admin/poule/creatematch.html.twig', [
            'error' => "",
            'journee' => $journee,
            'poule' => $poule,

        ]);
    }

    //Route utilisé par fullcalendar pour retrouver les parties d'une journée
    #[Route('/{id}/api/{journee}', name: 'api', methods: ['GET'])]
    public function apiParties(Poule $poule, Journee $journee): JsonResponse
    {

        $parties = $journee->getParties();

        $data = [];

        foreach ($parties as $partie) {
            $start = $partie->getDate(); // DateTimeImmutable
            $end = $start->modify('+1 hour');

            //Dans les phase finales, les matchs sont créés alors qu'on ne connait pas encore léquipe qui reçoit et l'équipe qui se déplace
            //Donc si il manque un des deux, on utilise le nom de la partie
            if ($partie->getIdEquipeRecoit() === null || $partie->getIdEquipeDeplace() === null) {
                $title = $partie->getNom();
            }else{
                $title = $partie->getIdEquipeRecoit()->getNom(). "vs" . $partie->getIdEquipeDeplace()->getNom();
            }



            $data[] = [
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'id' => $partie->getId(),
                'title' => $title,
                'start' => $start->format(\DateTimeInterface::ATOM),
                'end' => $end->format(\DateTimeInterface::ATOM),
            ];
        }

        return $this->json($data);
    }

    //Route utilisée par fullcalendar pour afficher le formulaire d'édit d'une partie
    // dans la modale qui apparait quand on clique sur une partie
    #[Route('/{poule}/api/{partie}/getmodal', name: 'api_getmodal', methods: ['GET', 'POST'])]
    public function apiGetModalEdit(Poule $poule, Partie $partie, Request $request, EntityManagerInterface $em): Response
    {
        if ($partie->getPoule() !== $poule) throw $this->createNotFoundException("Cette partie n'appartient pas à cette poule");
        $form = $this->createForm(PartieCalendarType::class, $partie,[
        'poule' => $poule,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->json(['success' => true]);
        }

        return $this->render('admin/partie/_form_modal.html.twig', [
            'form' => $form->createView(),
        ]);

    }

    //Route utilisée par fullcalendar pour afficher le formulaire de création d'une partie
    // dans la modale qui apparait quand on clique sur une partie
    #[Route('/{poule}/api/getmodal/new/{journee}', name: 'api_getmodalNew', methods: ['GET', 'POST'])]
    public function apiGetModalNew(Poule $poule, Request $request, Journee $journee, EntityManagerInterface $em): Response
    {
        $partie= new Partie();
        $partie->setPoule($poule);
        $partie->setJournee($journee);
        $form = $this->createForm(PartieCalendarType::class, $partie, [
            'poule' => $poule,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($partie);
            $em->flush();

            return $this->json(['success' => true]);
        }

        return $this->render('admin/partie/_form_modal.html.twig', [
            'form' => $form->createView(),
        ]);

    }



    //Ajoute une journée Méthode appelé par l'API lors du click sur une date vide dans le calendrier
    #[Route('/{poule}/api', name: 'api_add', methods: ['POST'])]
    public function apiPartiesAdd(Request $request, Poule $poule, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $partie = new Partie();
        if (isset($data['datedebut'])) {
            $partie->setDateDebut(new \DateTimeImmutable($data['datedebut']));
        }
        if (isset($data['datefin'])) {
            $partie->setDateFin(new \DateTimeImmutable($data['datefin']));
        }
        //On calcule le numéro de la journée en ajoutant 1 au nombre de journées existantes
        //(la méthode regulariseNumeroJournee sera appelée après pour tout remettre en ordre si besoin)
        $nbJournees = count($poule->getJournees());
        $journee->setPoule($poule);
        $poule->addJournee($journee);
        $em->persist($journee);
        $em->flush();

        $this->regulariseNumeroJournee($poule, $em);

        return $this->json([
            'id' => $journee->getId(),
            'title' => 'Journée ' . $journee->getNumero(),
            'start' => $journee->getDateDebut()->format('Y-m-d'),
            'end' => $journee->getDateFin()->format('Y-m-d'),
        ]);
    }

    //Met à jour une journée Méthode appelé par l'API lors du déplacement d'une journée dans le calendrier
    #[Route('/{poule}/api/{partie}', name: 'api_update', methods: ['PUT'])]
    public function apiJourneesUpdate(Request $request, Poule $poule, Partie $partie, EntityManagerInterface $em): JsonResponse
    {
        if ($partie->getPoule() !== $poule) throw $this->createNotFoundException("Cette partie n'appartient pas à cette poule");
        $data = json_decode($request->getContent(), true);
        if (isset($data['datedebut'])) {
            $partie->setDate(new \DateTimeImmutable($data['datedebut']));
        }


        $em->flush();



        return $this->json([
            'id' => $partie->getId(),
            'datedebut' => $partie->getDate()->format('Y-m-d H:i:s'),
        ]);
    }

    //Supprime une partie Méthode appelé par l'API lors du click sur le bouton supprimer dans la modale du calendrier
    #[Route('/{poule}/api/{partie}', name: 'api_delete', methods: ['DELETE'])]
    public function apiPartiesDelete(Request $request, Poule $poule, Partie $partie, EntityManagerInterface $em): JsonResponse
    {
        //On supprime la journée
        $em->remove($partie);
        $em->flush();


        //On renvoie la liste des parties restantes
        $data=[
            "success"=>true,
        ];



        return $this->json($data);
    }

}
