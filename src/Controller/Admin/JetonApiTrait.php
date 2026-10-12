<?php

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * API du calendrier de l'admin (journées et matchs déplacés, ajoutés, supprimés) : calendarController.js envoie le
 * jeton CSRF « update_journee » dans l'en-tête X-CSRF-TOKEN (valeur data-calendar-csrf-token-value des gabarits).
 */
trait JetonApiTrait
{
    /** Réponse 403 si le jeton est absent ou faux, null sinon. */
    private function refusSansJeton(Request $request): ?JsonResponse
    {
        if ($this->isCsrfTokenValid('update_journee', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return null;
        }

        return $this->json(['success' => false, 'error' => 'Jeton de sécurité invalide : rechargez la page.'], 403);
    }
}
