<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Conditions d'utilisation du site : mentions légales (LCEN, art. 6 III), règles d'utilisation,
 * données personnelles (RGPD) et cookies. Contenu dans templates/front/conditions.html.twig.
 */
final class ConditionsController extends AbstractController
{
    #[Route('/conditions-utilisation', name: 'front_conditions', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/conditions.html.twig');
    }
}
