<?php

namespace App\Controller\Front;

use App\Entity\Equipe;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Service\CalendarIcsGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class IcalController extends AbstractController
{
    public function __construct(
        private readonly CalendarIcsGenerator $calendarGenerator,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Agenda d'une équipe pour une saison donnée : l'adresse d'abonnement proposée sur la fiche équipe.
     * La saison fait partie de l'adresse : l'abonnement ne bascule jamais tout seul sur la saison suivante
     * (les joueurs changent souvent d'équipe d'une saison à l'autre).
     */
    #[Route('/calendar/saison/{saison}/equipe/{equipe}.ics', name: 'calendar_ics_equipe', requirements: ['saison' => '\d+', 'equipe' => '\d+'], methods: ['GET'])]
    public function icsEquipeSaison(Saison $saison, Equipe $equipe, Request $request): Response
    {
        return $this->reponse(
            $this->calendarGenerator->generateIcalForEquipeSaison($equipe, $saison),
            $request,
            sprintf('BLVB-%s-%s', $equipe->getNom(), $saison->getNom())
        );
    }

    /** Ancien agenda limité à une poule, conservé pour les abonnements existants. */
    #[Route('/calendar/{poule}/{equipe}.ics', name: 'calendar_ics', methods: ['GET'])]
    public function ics(Poule $poule, Equipe $equipe, Request $request): Response
    {
        return $this->reponse(
            $this->calendarGenerator->generateIcalForEquipe($poule, $equipe),
            $request,
            sprintf('BLVB-%s-%s', $equipe->getNom(), $poule->getNom())
        );
    }

    /** @param array{ical: string, etag: string} $resultat */
    private function reponse(array $resultat, Request $request, string $nomFichier): Response
    {
        $entetesCache = ['ETag' => $resultat['etag'], 'Cache-Control' => 'public, max-age=300, s-maxage=600'];

        if ($request->headers->get('if-none-match') === $resultat['etag']) {
            return new Response('', Response::HTTP_NOT_MODIFIED, $entetesCache);
        }

        return new Response($resultat['ical'], Response::HTTP_OK, $entetesCache + [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $this->slugger->slug($nomFichier)->lower().'.ics'
            ),
        ]);
    }
}
