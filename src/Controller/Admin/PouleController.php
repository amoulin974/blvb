<?php

namespace App\Controller\Admin;

use App\Entity\Poule;
use App\Form\PouleType;
use App\Entity\Journee;
use App\Entity\Equipe;
use App\Repository\PouleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\PlanificationMatchService;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/poule', name: 'admin_poule_')]
final class PouleController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(PouleRepository $pouleRepository): Response
    {
        return $this->render('admin/poule/index.html.twig', [
            'poules' => $pouleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $poule = new Poule();
        $form = $this->createForm(PouleType::class, $poule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($poule);
            $entityManager->flush();

            return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/poule/new.html.twig', [
            'poule' => $poule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Poule $poule): Response
    {
        return $this->render('admin/poule/show.html.twig', [
            'poule' => $poule,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Poule $poule, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PouleType::class, $poule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Poule mise à jour avec succès !');
            return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/poule/edit.html.twig', [
            'poule' => $poule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Poule $poule, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$poule->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($poule);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_poule_index', [], Response::HTTP_SEE_OTHER);
    }

    //Crée les journées pour une poule
    #[Route('/{id}/createjournee', name: 'createjournee', methods: ['POST'])]
    public function createJournee(Poule $poule, Request $request, EntityManagerInterface $entityManager): Response
    {
        //On compte le nombre d'équipes dans la poule
        //Si il n'y a pas au moins deux équipes dans la poule on renvoit un message d'erreur indiquant qu'il faut commencer par entrer les équipes
        $error=null;
        $nbEquipe=count($poule->getEquipes());
        if ($nbEquipe<2){
           $error="Il faut au moins deux équipes dans la poule";
        }else{

            //On supprime les journées de cette poules
            $oldJournees = $poule->getJournees();
            foreach ($oldJournees as $journee) {
                $entityManager->remove($journee);
            }

            //On vérifie si la première poule de la phase à déjà des journées et si le nombre d'équipe est identique. Si c'est le cas on copie les journées
            $phase=$poule->getPhase();
            $poulesPhase=$phase->getPoules();
            $firstPoule=$poulesPhase[0];


            //Si la première poule a le même nombre d'équipes et des journées on les copie
            if (count($firstPoule->getEquipes())==$nbEquipe && count($firstPoule->getJournees())>0){
                foreach($firstPoule->getJournees() as $journeefirstpoule){
                    $newJournee=new Journee;
                    $newJournee->setDateDebut($journeefirstpoule->getDateDebut());
                    $newJournee->setDateFin($journeefirstpoule->getDateFin());
                    $newJournee->setNumero($journeefirstpoule->getNumero());
                    $newJournee->setPoule($poule);
                    $entityManager->persist($newJournee);

                }
                $entityManager->flush();
            }else{
                //Calcul du nombre de journées nécessaires
                $nbMatch=$nbEquipe*($nbEquipe-1)/2;
                if ($nbEquipe % 2 == 0){
                    $nbMatchParJour = $nbEquipe/2;
                }else{
                    $nbMatchParJour = ($nbEquipe-1)/2;
                }
                $nbJournee = $nbMatch/$nbMatchParJour;


                // Dates de la phase
                $debutPhase = $poule->getPhase()->getDateDebut();
                $finPhase = $poule->getPhase()->getDateFin();

                // Vérifie si la phase peut contenir toutes les journées (1 semaine par journée)
                $dureePhaseEnSemaines = intval($finPhase->diff($debutPhase)->days / 7) + 1;
                if ($dureePhaseEnSemaines < $nbJournee) {
                    $error = "La phase est trop courte pour contenir toutes les journées.";
                } else {

                    $entityManager->flush();

                    // Génération des journées
                    $currentDate = clone $debutPhase;
                    for ($i = 1; $i <= $nbJournee; $i++) {

                        // Skip Noël et Jour de l'an
                        $annee = (int)$currentDate->format('Y');
                        $noel = new \DateTimeImmutable("$annee-12-25");
                        $jourAn = new \DateTimeImmutable(($annee+1)."-01-01");

                        while (($currentDate <= $finPhase) &&
                            ($currentDate <= $noel && $currentDate >= $noel->modify('-6 days') ||
                                $currentDate <= $jourAn && $currentDate >= $jourAn->modify('-6 days'))
                        ) {
                            $currentDate = $currentDate->modify('+1 week');
                        }

                        $journee = new Journee();
                        $journee->setNumero($i);

                        // Début de la semaine (lundi)
                        $debutSemaine = $currentDate->modify('Monday this week');
                        $finSemaine = $currentDate->modify('Sunday this week');

                        $journee->setDateDebut(new \DateTimeImmutable($debutSemaine->format('Y-m-d')));
                        $journee->setDateFin(new \DateTimeImmutable($finSemaine->format('Y-m-d')));
                        $journee->setPoule($poule);

                        $entityManager->persist($journee);

                        // Passe à la semaine suivante
                        $currentDate = $currentDate->modify('+1 week');
                    }

                    $entityManager->flush();
                }
            }

    }

    //Affiche lecalendrier pour pouvoir déplacer les journées
        return $this->render('admin/poule/createjournee.html.twig', [
        'poule' => $poule,
        'error' => $error,
        ]);
    }

    //fonction qui crée les matchs pour une poule
    #[Route('/{id}/createpartie', name: 'createpartie', methods: ['POST'])]
    public function createMatch(Poule $poule, Request $request, EntityManagerInterface $entityManager, PlanificationMatchService $planificationService): Response
    {
        $error=null;
        foreach ($poule->getJournees() as $journee) {
            //On supprime les matchs de cette journée
            $oldParties = $journee->getParties();
            foreach ($oldParties as $partie) {
                $entityManager->remove($partie);
            }
        }
        $entityManager->flush();
        //On crée les matchs
        $equipes = $poule->getEquipes()->toArray();
        $nbEquipe = count($equipes);
        if ($nbEquipe<2){
           $error="Il faut au moins deux équipes dans la poule";
        }else{
            //Algorithme de la ronde


            $equipeArray = $equipes;
            if ($nbEquipe % 2 != 0) {
                $equipeBye = new Equipe();
                $equipeBye->setNom("BYE");
                $equipeArray[] = $equipeBye;
                $nbEquipe++; // On met à jour le nombre total
            }
            $rounds = $nbEquipe - 1;
            $matchesPerRound = $nbEquipe / 2;
            $journees = $poule->getJournees()->toArray();
            // Tri selon l’attribut "numero"
            usort($journees, function($j1, $j2){
                return $j1->getNumero() <=> $j2->getNumero();
            });

            for ($round = 0; $round < $rounds; $round++) {
                $journee = $journees[$round];
                for ($match = 0; $match < $matchesPerRound; $match++) {
                    $homeIndex = ($round + $match) % ($nbEquipe - 1);
                    $awayIndex = ($nbEquipe - 1 - $match + $round) % ($nbEquipe - 1);

                    // La dernière équipe reste fixe
                    if ($match == 0) {
                        $awayIndex = $nbEquipe - 1;
                    }

                    // Si l'une des équipes est fictive, on ne crée pas le match
                    if ($equipeArray[$homeIndex]->getNom() === "BYE" || $equipeArray[$awayIndex]->getNom() === "BYE") {
                        continue;
                    }

                    $partie = new \App\Entity\Partie();

                    $dateMatch = $planificationService->calculerDateMatch(
                        $journee->getDateDebut(),
                        $equipeArray[$homeIndex]->getIdLieu()->getJour(),
                        $equipeArray[$homeIndex]->getIdLieu()->getHeure()
                    );
                    $partie->setDate($dateMatch);
                    $partie->setIdLieu($equipeArray[$homeIndex]->getIdLieu());
                    $partie->setIdEquipeRecoit($equipeArray[$homeIndex]);
                    $partie->setIdEquipeDeplace($equipeArray[$awayIndex]);
                    $partie->setIdJournee($journee);
                    $partie->setPoule($poule);
                    $entityManager->persist($partie);
                }
            }
            $entityManager->flush();

           }
        return $this->render('admin/poule/createjournee.html.twig', [
        'poule' => $poule,
        'error' => $error,
        ]);
    }


    //Affiche le calendrier des journées pour une poule
    #[Route('/{id}/getjourneecalendar', name: 'getjourneecalendar', methods: ['GET'])]
    public function getJourneeCalendar (Poule $poule, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/poule/createjournee.html.twig', [
            'error' => "",
            'poule' => $poule,

        ]);
    }

    //Affiche le calendrier des journées pour une poule
    #[Route('/{id}/getpartiecalendar', name: 'getpartiecalendar', methods: ['GET'])]
    public function getPartieCalendar (Journee $journee, Request $request, EntityManagerInterface $entityManager): Response
    {
        $poule=$journee->getPoule();
        return $this->render('admin/poule/creatematch.html.twig', [
            'error' => "",
            'journee' => $journee,
            'poule' => $poule,

        ]);
    }

    #[Route('/{id}/api/journees', name: 'api_journees', methods: ['GET'])]
    public function apiJournees(Poule $poule): JsonResponse
    {
        $journees = $poule->getJournees();

        $data = [];

        foreach ($journees as $journee) {
            $data[] = [
                'id' => $journee->getId(),
                'title' => 'Journée ' . $journee->getNumero(),
                'start' => $journee->getDateDebut()->format('Y-m-d'),
                'end' => $journee->getDateFin()->format('Y-m-d'),
            ];
        }

        return $this->json($data);
    }

    //Met à jour une journée Méthode appelé par l'API lors du déplacement d'une journée dans le calendrier
    #[Route('/{poule}/api/journees/{id}', name: 'api_journees_update', methods: ['PUT'])]
    public function apiJourneesUpdate(Request $request, Poule $poule, Journee $journee, EntityManagerInterface $em): JsonResponse
    {

        $data = json_decode($request->getContent(), true);
        if (isset($data['datedebut'])) {
            $journee->setDateDebut(new \DateTimeImmutable($data['datedebut']));
        }
        if (isset($data['datefin'])) {
            $journee->setDateFin(new \DateTimeImmutable($data['datefin']));
        }

        $em->flush();

        $this->regulariseNumeroJournee($poule, $em);

        return $this->json([
            'id' => $journee->getId(),
            'datedebut' => $journee->getDateDebut()->format('Y-m-d H:i:s'),
            'datefin' => $journee->getDateFin()->format('Y-m-d H:i:s')
        ]);
    }

    //Supprime une journée Méthode appelé par l'API lors du click sur le bouton supprimer dans la modale du calendrier
    #[Route('/{poule}/api/journees/{id}', name: 'api_journees_delete', methods: ['DELETE'])]
    public function apiJourneesDelete(Request $request, Poule $poule, Journee $journee, EntityManagerInterface $em): JsonResponse
    {
        //On supprime la journée
        $em->remove($journee);
        $em->flush();


        //On renvoie la liste des journées restantes
        $journees = $poule->getJournees();
        $this->regulariseNumeroJournee($poule, $em);
        $data = [];

        foreach ($journees as $journee) {
            $data[] = [
                'id' => $journee->getId(),
                'title' => 'Journée ' . $journee->getNumero(),
                'start' => $journee->getDateDebut()->format('Y-m-d'),
                'end' => $journee->getDateFin()->format('Y-m-d'),
            ];
        }

        return $this->json($data);
    }

    //Ajoute une journée Méthode appelé par l'API lors du click sur une date vide dans le calendrier
    #[Route('/{poule}/api/journees', name: 'api_journees_add', methods: ['POST'])]
    public function apiJourneesAdd(Request $request, Poule $poule, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $journee = new Journee();
        if (isset($data['datedebut'])) {
            $journee->setDateDebut(new \DateTimeImmutable($data['datedebut']));
        }
        if (isset($data['datefin'])) {
            $journee->setDateFin(new \DateTimeImmutable($data['datefin']));
        }
        //On calcule le numéro de la journée en ajoutant 1 au nombre de journées existantes
        //(la méthode regulariseNumeroJournee sera appelée après pour tout remettre en ordre si besoin)
        $nbJournees = count($poule->getJournees());
        $journee->setNumero($nbJournees + 1);
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

    #[Route('/{id}/api/parties', name: 'api_parties', methods: ['GET'])]
    public function apiParties(Poule $poule): JsonResponse
    {
        $parties = $poule->getParties();

        $data = [];

        foreach ($parties as $partie) {
            $start = $partie->getDate(); // DateTimeImmutable
            $end = $start->modify('+1 hour');
            $data[] = [
                'id' => $partie->getId(),
                'title' => $partie->getIdEquipeRecoit()->getNom(). "vs" . $partie->getIdEquipeDeplace()->getNom(),
                'start' => $start->format('c'),
                'end' => $end->format('c'),
            ];
        }

        return $this->json($data);
    }

    //Ajoute une journée Méthode appelé par l'API lors du click sur une date vide dans le calendrier
    #[Route('/{poule}/api/parties', name: 'api_parties_add', methods: ['POST'])]
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



    //Réordonne les numéros des journées d'une poule en fonction de leurs dates de début
    public function regulariseNumeroJournee(Poule $poule, EntityManagerInterface $em): void
    {
        $journees = $poule->getJournees()->toArray();
        usort($journees, function (Journee $a, Journee $b) {
            return $a->getDateDebut() <=> $b->getDateDebut();
        });

        foreach ($journees as $index => $journee) {
            $journee->setNumero($index + 1);
            $em->persist($journee);
        }

        $em->flush();
    }
}
