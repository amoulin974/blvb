<?php

namespace App\Controller\admin;

use App\Entity\Poule;
use App\Form\PouleType;
use App\Entity\Journee;
use App\Repository\PouleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/{id}/createjournee', name: 'createjournee', methods: ['GET'])]
    public function createJournee(Poule $poule, Request $request, EntityManagerInterface $entityManager): Response
    {
        //Récupère la poule

        //On compte le nombre d'équipes dans la poule

        //Si il n'y a pas au moins deux équipes dans la poule on renvoit un message d'erreur indiquant qu'il faut commencer par entrer les équipes
        $error=null;
        $nbEquipe=count($poule->getEquipes());
        if ($nbEquipe<2){
           $error="Il faut au moins deux équipes dans la poule";
        }else{
            $nbMatch=($nbEquipe*$nbEquipe-1)/2;
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
            //On supprime les journées de cette poules
            $oldJournees = $poule->getJournees();
            foreach ($oldJournees as $journee) {
                $entityManager->remove($journee);
            }
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

        return $this->render('admin/poule/createjournee.html.twig', [
        'poule' => $poule,
        'error' => $error,
        ]);
    }
    #[Route('/{id}/getjourneecalendar', name: 'getjourneecalendar', methods: ['GET'])]
    public function getJourneeCalendar (Poule $poule, Request $request, EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/poule/createjournee.html.twig', [
            'error' => "",
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

//        var_dump($data);
//        foreach ($journees as $journee) {
//            $data[] = [
//                'id' => $journee->getId(),
//                'title' => 'Journée ' . $journee->getNumero(),
//                'start' => $journee->getDateDebut()->format('Y-m-d'),
//                'end' => $journee->getDateFin()->format('Y-m-d'),
//            ];
//        }
        $em->flush();

        return $this->json([
            'id' => $journee->getId(),
            'datedebut' => $journee->getDateDebut()->format('Y-m-d H:i:s'),
            'datefin' => $journee->getDateFin()->format('Y-m-d H:i:s')
        ]);
    }
}
