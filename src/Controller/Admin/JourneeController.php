<?php

namespace App\Controller\Admin;

use App\Entity\Journee;
use App\Entity\Poule;
use App\Form\JourneeType;
use App\Repository\JourneeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/journee', name: 'admin_journee_')]
final class JourneeController extends AbstractController
{
    #[Route(name: 'index', methods: ['GET'])]
    public function index(JourneeRepository $journeeRepository): Response
    {
        return $this->render('admin/journee/index.html.twig', [
            'journees' => $journeeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $journee = new Journee();
        $form = $this->createForm(JourneeType::class, $journee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($journee);
            $entityManager->flush();

            return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/journee/new.html.twig', [
            'journee' => $journee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Journee $journee): Response
    {
        return $this->render('admin/journee/show.html.twig', [
            'journee' => $journee,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Journee $journee, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(JourneeType::class, $journee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/journee/edit.html.twig', [
            'journee' => $journee,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Journee $journee, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$journee->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($journee);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_journee_index', [], Response::HTTP_SEE_OTHER);
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

        return $this->redirectToRoute('admin_saison_show', ['id' => $poule->getPhase()->getSaison()->getId()]);
    }

    #[Route('/{id}/api/journees', name: 'api', methods: ['GET'])]
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

    //Affiche le calendrier des journées pour une poule
    #[Route('/{id}/getjourneecalendar', name: 'getjourneecalendar', methods: ['GET'])]
    public function getJourneeCalendar (Poule $poule, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/poule/createjournee.html.twig', [
            'error' => "",
            'poule' => $poule,

        ]);
    }



    //Met à jour une journée Méthode appelé par l'API lors du déplacement d'une journée dans le calendrier
    #[Route('/{poule}/api/{id}', name: 'api_update', methods: ['PUT'])]
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
    #[Route('/{poule}/api/{id}', name: 'api_delete', methods: ['DELETE'])]
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
    #[Route('/{poule}/api', name: 'api_add', methods: ['POST'])]
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
