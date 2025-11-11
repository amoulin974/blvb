<?php

// src/Service/CalendarIcsGenerator.php
namespace App\Service;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Domain\ValueObject\Location;
use Psr\Log\LoggerInterface;
use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Repository\PartieRepository;
use DateTimeImmutable;

class CalendarIcsGenerator
{
    private Event $event;
    private Calendar $calendar;
    private CalendarFactory $componentFactory;
    private string $calendarComponent;
    public function __construct(
        private PartieRepository $partieRepository
    ) {}
    public function generateIcalForEquipe(Poule $poule, Equipe $equipe, ){


        $allparties=$poule->getParties();
        $parties=[];
        foreach ($allparties as $partie) {
            if ($partie->getIdEquipeRecoit() === $equipe || $partie->getIdEquipeDeplace() === $equipe) {
                $parties[] = $partie;
            }
        }

                $events = [];
        foreach ($parties as $partie) {
            $date = $partie->getDate(); // DateTimeImmutable

            $recoit = $partie->getIdEquipeRecoit()->getNom();
            $deplace = $partie->getIdEquipeDeplace()->getNom();
            $lieu = $partie->getIdLieu()->getNom();
            $adresse = method_exists($partie->getIdLieu(), 'getAdresse')
                ? $partie->getIdLieu()->getAdresse()
                : '';

            // Titre de l'événement
            $summary = "$recoit vs $deplace";

            // Création de l'événement ICS
            $event = (new Event())
                ->setSummary($summary)
                ->setLocation(new Location($lieu . ($adresse ? " - $adresse" : "")))
                ->setOccurrence(
                    new TimeSpan(
                        new DateTime($date, false),
                        // Match ≈ 2h => ajustable
                        new DateTime($date->modify('+2 hours'), false)
                    )
                );

            $events[] = $event;
        }
        // Construction du calendrier
        $calendar = new Calendar($events);
        $calendarComponent = (new CalendarFactory())->createCalendar($calendar);

        // Génération d'ETag (pour le cache navigateur)
        $icalString = (string) $calendarComponent;
        $etag = '"' . md5($icalString) . '"';

        return [
            'ical' => $icalString,
            'etag' => $etag,
        ];
    }





//    private EventRepository $eventRepository;
//    private LoggerInterface $logger;
//
//    public function __construct(EventRepository $eventRepository, LoggerInterface $logger)
//    {
//        $this->eventRepository = $eventRepository;
//        $this->logger = $logger;
//    }
//
//    /**
//     * Retourne un tableau: ['ical' => string, 'etag' => string]
//     */
//    public function generateForToken(string $token): array
//    {
//        $events = $this->eventRepository->findForToken($token);
//
//        $calendar = new Calendar('my-domain.example'); // change domain/name
//
//        // Construit ETag basé sur updatedAt de chaque event (ou hachage global)
//        $timestamps = [];
//        foreach ($events as $e) {
//            $timestamps[] = $e->getUpdatedAt()->getTimestamp();
//        }
//        // Si tu veux inclure le nombre d'événements, etc.
//        $etag = '"' . md5(implode(',', $timestamps) . count($events)) . '"';
//
//        foreach ($events as $e) {
//            $ve = new ICalEvent();
//
//            // UID: unique and stable per event
//            $uid = sprintf('event-%d@%s', $e->getId(), 'my-domain.example');
//            $ve->setUniqueId($uid);
//
//            // Summary / title
//            $ve->setSummary($e->getTitle());
//
//            // Description
//            if ($e->getDescription()) {
//                $ve->setDescription($e->getDescription());
//            }
//
//            // Start / End (Eluceo accepte DateTimeInterface)
//            $ve->setDtStart($e->getStartAt());
//            if ($e->getEndAt()) {
//                $ve->setDtEnd($e->getEndAt());
//            }
//
//            // DTSTAMP: when the iCal event was last generated (use updatedAt)
//            $ve->setCreated($e->getUpdatedAt()); // created here used as DTSTAMP/CREATED depending on version
//            // SEQUENCE: clients use this integer to detect updates
//            $sequence = (int) $e->getUpdatedAt()->getTimestamp();
//            $ve->setSequence($sequence);
//
//            $calendar->addComponent($ve);
//        }
//
//        $icalString = $calendar->render();
//
//        return [
//            'ical' => $icalString,
//            'etag' => $etag,
//        ];
//    }
}
