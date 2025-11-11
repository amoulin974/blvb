<?php

namespace App\Controller\Front;


use App\Entity\Poule;
use App\Entity\Equipe;
use App\Repository\PouleRepository;
use App\Service\CalendarIcsGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class IcalController extends AbstractController
{
    private CalendarIcsGenerator $calendarGenerator;

    public function __construct(CalendarIcsGenerator $calendarGenerator)
    {
        $this->calendarGenerator = $calendarGenerator;
    }
    #[Route('/calendar/{poule}/{equipe}.ics', name: 'calendar_ics', methods: ['GET'])]
    public function ics(Poule $poule, Equipe $equipe , Request $request, PouleRepository $pouleRepository): Response
    {
        if (!$poule || !$equipe) {
            throw $this->createNotFoundException('Poule ou équipe introuvable');
        }

        // Génère le calendrier et calcule ETag
        $result = $this->calendarGenerator->generateIcalForEquipe($poule, $equipe);
        $ical = $result['ical'];
        $etag = $result['etag'];

        if ($request->headers->get('if-none-match') === $etag) {
            return new Response('', Response::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=300, s-maxage=600',
            ]);
        }

        return new Response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('inline; filename="%s-%s.ics"', $poule->getNom(), $equipe->getNom()),
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=300, s-maxage=600',
        ]);
    }
}
