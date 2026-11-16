<?php

namespace App\Controller\Admin;

use App\Form\VolleyStatsReglagesType;
use App\Repository\ParametresSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Réglages de Volley Stats (D68) : seuil d'échanges détaillés pour les classements des joueurs. */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/volley-stats', name: 'admin_volley_stats_')]
final class VolleyStatsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, ParametresSiteRepository $depot, EntityManagerInterface $em): Response
    {
        $parametres = $depot->courants();
        $form = $this->createForm(VolleyStatsReglagesType::class, $parametres);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($parametres);
            $em->flush();
            $this->addFlash('success', sprintf('Seuil enregistré : %d %% d\'échanges détaillés.', $parametres->getSeuilEchangesDetailles()));

            return $this->redirectToRoute('admin_volley_stats_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/volley_stats/index.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
