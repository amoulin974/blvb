<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeInterface;

class PlanificationMatchService
{
    private const JOURS_MAP = [
        'lundi'     => 'monday',
        'mardi'     => 'tuesday',
        'mercredi'  => 'wednesday',
        'jeudi'     => 'thursday',
        'vendredi'  => 'friday',
        'samedi'    => 'saturday',
        'dimanche'  => 'sunday',
    ];

    /**
     * Calcule la date du match en fonction de la journée, du jour du lieu et de l'heure.
     */
    public function calculerDateMatch(DateTimeImmutable $dateDebut, string $jourFr, DateTimeInterface $heure): DateTimeImmutable
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
}
