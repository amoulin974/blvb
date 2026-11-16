<?php

namespace App\Competition\Format;

use App\Entity\Indisponibilite;
use App\Entity\Phase;

/**
 * Semaines (lundi à dimanche) d'une phase et fermetures de la saison : utilisé par le championnat pour placer ses
 * journées et par l'étape « Journées » de la mise en place pour annoncer les semaines utilisables.
 */
final class Semaines
{
    /** La semaine de la date (lundi 00:00 au dimanche 23:59) chevauche-t-elle une fermeture de la saison ? */
    public static function fermee(\DateTimeImmutable $date, iterable $indisponibilites): bool
    {
        return self::fermeture($date, $indisponibilites) !== null;
    }

    /** Première fermeture que la semaine de la date chevauche, ou null. */
    public static function fermeture(\DateTimeImmutable $date, iterable $indisponibilites): ?Indisponibilite
    {
        $debutSemaine = $date->modify('monday this week')->setTime(0, 0, 0);
        $finSemaine = $date->modify('sunday this week')->setTime(23, 59, 59);

        foreach ($indisponibilites as $indispo) {
            $debut = $indispo->getDateDebut()->setTime(0, 0, 0);
            $fin = $indispo->getDateFin()->setTime(23, 59, 59);
            if ($debutSemaine <= $fin && $finSemaine >= $debut) {
                return $indispo;
            }
        }

        return null;
    }

    /**
     * Semaines de la phase, une par pas de 7 jours depuis son début : total et semaines libres de toute fermeture.
     *
     * @return array{total: int, utilisables: int}
     */
    public static function decompte(Phase $phase): array
    {
        $total = 0;
        $utilisables = 0;
        $date = $phase->getDateDebut();
        $fin = $phase->getDateFin();
        $fermetures = $phase->getSaison()->getIndisponibilites();
        while ($date <= $fin) {
            ++$total;
            if (!self::fermee($date, $fermetures)) {
                ++$utilisables;
            }
            $date = $date->modify('+1 week');
        }

        return ['total' => $total, 'utilisables' => $utilisables];
    }
}
