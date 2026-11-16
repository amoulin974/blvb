<?php

namespace App\Competition\Format;

use App\Entity\Creneau;
use App\Entity\Lieu;
use DateTimeImmutable;
use DateTimeInterface;

/** Date d'un match : le jour du créneau dans la semaine de la journée, à l'heure du créneau. */
final class DateMatch
{
    private const JOURS_MAP = [
        'lundi'     => 'monday',
        'mardi'     => 'tuesday',
        'mercredi'  => 'wednesday',
        'jeudi'     => 'thursday',
        'vendredi'  => 'friday',
        'samedi'    => 'saturday',
        'dimanche'  => 'sunday',
        // Support des index numériques (si 1 = Lundi)
        '1' => 'monday',
        '2' => 'tuesday',
        '3' => 'wednesday',
        '4' => 'thursday',
        '5' => 'friday',
        '6' => 'saturday',
        '7' => 'sunday',
    ];

    /**
     * Calcule la date du match en fonction de la journée, du jour du lieu et de l'heure.
     */
    public static function calculer(DateTimeImmutable $dateDebut, string $jourFr, DateTimeInterface $heure): DateTimeImmutable
    {
        $jourFr = strtolower(trim($jourFr));

        if (!isset(self::JOURS_MAP[$jourFr])) {
            throw new \InvalidArgumentException("Jour invalide : $jourFr");
        }

        $jourEn = self::JOURS_MAP[$jourFr];

        // Si la date de début tombe déjà ce jour
        if (strtolower($dateDebut->format('l')) === $jourEn) {
            $dateMatch = $dateDebut;
        } else {
            $dateMatch = $dateDebut->modify("next $jourEn");
        }

        // Applique l'heure
        return $dateMatch->setTime(
            (int) $heure->format('H'),
            (int) $heure->format('i')
        );
    }

    /**
     * Date du premier de ces créneaux où le gymnase est ouvert (D60) ; si tous tombent pendant une fermeture, date du
     * premier : le match est alors signalé (PartieService::phraseFermeture). Null s'il n'y a aucun créneau.
     *
     * @param iterable<Creneau> $creneaux dans l'ordre de préférence
     */
    public static function premierCreneauOuvert(Lieu $lieu, iterable $creneaux, DateTimeImmutable $dateDebut): ?DateTimeImmutable
    {
        $premiere = null;
        foreach ($creneaux as $creneau) {
            $date = self::calculer($dateDebut, (string) $creneau->getJourSemaine(), $creneau->getHeureDebut());
            if ($lieu->fermeLe($date) === null) {
                return $date;
            }
            $premiere ??= $date;
        }

        return $premiere;
    }
}
