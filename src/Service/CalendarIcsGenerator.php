<?php

// src/Service/CalendarIcsGenerator.php
namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Repository\PartieRepository;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Twig\ScoreExtension;

/**
 * Agenda (.ics) des matchs d'une équipe, conçu pour être un ABONNEMENT (et non une copie) :
 *  - identifiant d'événement stable (un par match) : un match déplacé met à jour l'événement existant,
 *    réimporter le fichier ne crée pas de doublons ;
 *  - fuseau Europe/Paris explicite, nom d'agenda lisible, fréquence d'actualisation indiquée ;
 *  - chaque événement renvoie vers la fiche de l'équipe (informations à jour), même si l'agenda a été importé et figé.
 * L'empreinte de cache (ETag) ignore l'horodatage de génération : elle ne change que si le contenu change.
 */
class CalendarIcsGenerator
{
    private const FUSEAU = 'Europe/Paris';
    private const ACTUALISATION = 'PT6H';

    public function __construct(
        private readonly PartieRepository $partieRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Agenda d'une équipe pour UNE saison (toutes phases confondues) : c'est l'abonnement proposé aux joueurs.
     *
     * @return array{ical: string, etag: string}
     */
    public function generateIcalForEquipeSaison(Equipe $equipe, Saison $saison): array
    {
        $parties = $this->partieRepository->findByEquipeSaison($equipe, $saison);

        return $this->construire($parties, $equipe, sprintf('BLVB · %s · %s', $equipe->getNom(), $saison->getNom()));
    }

    /**
     * Ancien agenda limité à une poule (anciens liens, conservés pour ne pas casser les abonnements existants).
     *
     * @return array{ical: string, etag: string}
     */
    public function generateIcalForEquipe(Poule $poule, Equipe $equipe): array
    {
        $parties = [];
        foreach ($poule->getParties() as $partie) {
            if ($partie->getIdEquipeRecoit() === $equipe || $partie->getIdEquipeDeplace() === $equipe) {
                $parties[] = $partie;
            }
        }

        return $this->construire($parties, $equipe, sprintf('BLVB · %s · %s', $equipe->getNom(), $poule->getNom()));
    }

    /**
     * @param Partie[] $parties
     *
     * @return array{ical: string, etag: string}
     */
    private function construire(array $parties, Equipe $equipe, string $nomAgenda): array
    {
        $fuseau = new \DateTimeZone(self::FUSEAU);
        $urlFiche = $this->urlGenerator->generate('front_equipe_detail', ['id' => $equipe->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $events = [];
        $premier = $dernier = null;
        foreach ($parties as $partie) {
            if ($partie->getDate() === null) {
                continue; // match pas encore planifié
            }
            // Les dates sont enregistrées en heure locale : on les interprète telles quelles en Europe/Paris
            $debut = new \DateTimeImmutable($partie->getDate()->format('Y-m-d H:i:s'), $fuseau);
            $fin = $debut->modify('+2 hours'); // durée d'un match, ajustable
            $premier = $premier === null || $debut < $premier ? $debut : $premier;
            $dernier = $dernier === null || $fin > $dernier ? $fin : $dernier;

            $lieu = $partie->getLieu();
            $adresse = $lieu?->getAdresse();
            $description = array_filter([
                sprintf('%s · %s · Journée %s', $partie->getPoule()?->getPhase()?->getNom(), $partie->getPoule()?->getNom(), $partie->getJournee()?->getNumero()),
                $partie->getNbSetGagnantReception() !== null && $partie->getNbSetGagnantDeplacement() !== null
                    ? (ScoreExtension::forfaitDe($partie)
                        ? sprintf('Résultat : forfait de %s', ScoreExtension::forfaitDe($partie)->getNom())
                        : sprintf('Résultat : %d–%d', $partie->getNbSetGagnantReception(), $partie->getNbSetGagnantDeplacement()))
                    : null,
                "Les horaires peuvent changer. Informations à jour : $urlFiche",
            ]);

            $event = (new Event(new UniqueIdentifier(sprintf('match-%d@blvb', $partie->getId()))))
                ->setSummary(sprintf('%s vs %s', $partie->getIdEquipeRecoit()?->getNom(), $partie->getIdEquipeDeplace()?->getNom()))
                ->setDescription(implode("\n", $description))
                ->setUrl(new Uri($urlFiche))
                ->setOccurrence(new TimeSpan(new DateTime($debut, true), new DateTime($fin, true)));
            if ($lieu !== null) {
                $event->setLocation(new Location($lieu->getNom().($adresse ? " - $adresse" : '')));
            }
            $events[] = $event;
        }

        $calendar = new Calendar($events);
        $calendar->setPublishedTTL(new \DateInterval(self::ACTUALISATION));
        $maintenant = new \DateTimeImmutable('now', $fuseau);
        $calendar->addTimeZone(TimeZone::createFromPhpDateTimeZone(
            $fuseau,
            ($premier ?? $maintenant)->modify('-1 month'),
            ($dernier ?? $maintenant)->modify('+1 month'),
        ));

        $ical = (string) (new CalendarFactory())->createCalendar($calendar);
        $ical = $this->ajouterEnTetes($ical, $nomAgenda);

        // L'horodatage de génération (DTSTAMP) change à chaque appel : on l'ignore pour que l'ETag ne reflète que le contenu
        $etag = '"'.md5(preg_replace('/^DTSTAMP:.*$/m', '', $ical)).'"';

        return ['ical' => $ical, 'etag' => $etag];
    }

    /** Nom de l'agenda, fuseau et fréquence d'actualisation conseillée (lus par Google, Apple, Outlook). */
    private function ajouterEnTetes(string $ical, string $nomAgenda): string
    {
        $nom = addcslashes($nomAgenda, ",;\\");
        $lignes = [
            "X-WR-CALNAME:$nom",
            "NAME:$nom",
            'X-WR-TIMEZONE:'.self::FUSEAU,
            'REFRESH-INTERVAL;VALUE=DURATION:'.self::ACTUALISATION,
        ];
        $ajout = implode("\r\n", array_map($this->plier(...), $lignes))."\r\n";

        return preg_replace('/^(VERSION:2\.0\r?\n)/m', '$1'.str_replace('\\', '\\\\', $ajout), $ical, 1);
    }

    /** Repli des lignes de plus de 75 octets (RFC 5545), sans couper un caractère UTF-8. */
    private function plier(string $ligne): string
    {
        $morceaux = [];
        while (strlen($ligne) > 75) {
            $coupe = 75;
            while ($coupe > 0 && (ord($ligne[$coupe]) & 0xC0) === 0x80) {
                --$coupe;
            }
            $morceaux[] = substr($ligne, 0, $coupe);
            $ligne = ' '.substr($ligne, $coupe);
        }
        $morceaux[] = $ligne;

        return implode("\r\n", $morceaux);
    }
}
