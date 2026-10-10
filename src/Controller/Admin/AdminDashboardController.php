<?php

namespace App\Controller\Admin;

use App\Repository\EquipeRepository;
use App\Repository\JoueurRepository;
use App\Repository\LieuRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(EquipeRepository $equipes, JoueurRepository $joueurs, UserRepository $users, LieuRepository $lieux): Response
    {
        return $this->render('/admin/admin_dashboard/index.html.twig', [
            'nombres' => [
                'equipes' => $equipes->count([]),
                'joueurs' => $joueurs->count([]),
                'utilisateurs' => $users->count([]),
                'gymnases' => $lieux->count([]),
            ],
        ]);
    }
}
