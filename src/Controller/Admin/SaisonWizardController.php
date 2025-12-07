<?php

namespace App\Controller\Admin;

use App\Entity\Saison;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/saison/{id}/wizard', name: 'admin_saison_wizard_')]
class SaisonWizardController extends AbstractController
{
    #[Route('/etape/{step}', name: 'dispatch')]
    public function dispatch(Saison $saison, string $step = 'configuration'): Response
    {
        // Cette méthode sert juste d'aiguillage pour afficher la barre de progression
        // et inclure le bon template

        return $this->render('admin/saisonwizard/layout.html.twig', [
            'saison' => $saison,
            'current_step' => $step,
        ]);
    }
}
