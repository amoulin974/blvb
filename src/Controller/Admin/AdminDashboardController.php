<?php

namespace App\Controller\Admin;

use App\Repository\PartieRepository;
use App\Service\TableauDeBord;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    /** Tableau de bord (D64) : « À faire », saisons suivies, prochaines fermetures, accès rapides. */
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(TableauDeBord $tableauDeBord): Response
    {
        return $this->render('/admin/admin_dashboard/index.html.twig', $tableauDeBord->donnees() + [
            'lignesVisibles' => TableauDeBord::LIGNES_VISIBLES,
        ]);
    }

    /** Matchs passés sans score des saisons suivies, saisis dans la fenêtre du calendrier (D64.3). */
    #[Route('/admin/scores', name: 'admin_scores_index', methods: ['GET'])]
    public function scores(TableauDeBord $tableauDeBord, PartieRepository $parties): Response
    {
        return $this->render('/admin/admin_dashboard/scores.html.twig', [
            'matchs' => $parties->sansScoreAvant($tableauDeBord->saisonsDesMatchs(), new \DateTimeImmutable()),
            'seuilJours' => TableauDeBord::SEUIL_SCORES_JOURS,
            'seuil' => new \DateTimeImmutable(sprintf('-%d days', TableauDeBord::SEUIL_SCORES_JOURS)),
        ]);
    }
}
