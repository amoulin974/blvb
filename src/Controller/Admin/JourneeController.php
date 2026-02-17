<?php

namespace App\Controller\Admin;

use App\Entity\Journee;
use App\Entity\Poule;
use App\Form\JourneeType;
use App\Repository\JourneeRepository;
use App\Service\JourneeService;
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
    public function createJournee(Poule $poule, JourneeService $journeeService): Response
    {
        $error = $journeeService->creerJournees($poule);

        if ($error) {
            $this->addFlash('error', $error);
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
