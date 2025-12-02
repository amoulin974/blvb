<?php


namespace App\Service;

use App\Entity\Lieu;

class CalendrierAnalyseService
{
    /**
     * Analyse les conflits pour un lieu et une date donnée.
     */
    public function analyser(Lieu $lieu, \DateTimeInterface $date, int $nbMatchs): array
    {
        $jourSemaineMatch = (int)$date->format('N'); // 1 (Lundi) à 7 (Dimanche)
        $creneaux = $lieu->getCreneaux();

        // --- 1. Calculs préliminaires ---
        $capaciteJour = 0;
        $prioriteJour = null; // La priorité du jour du match (si un créneau existe)
        $maxPrioriteGymnase = 0; // La meilleure priorité disponible dans tout le gymnase
        $meilleurJourLibelle = '';

        foreach ($creneaux as $creneau) {
            // On cherche la meilleure priorité de tout le gymnase pour comparer plus tard
            if ($creneau->getPrioritaire() > $maxPrioriteGymnase) {
                $maxPrioriteGymnase = $creneau->getPrioritaire();
                $meilleurJourLibelle = $creneau->getJourLibelle();
            }

            // Si ce créneau correspond au jour du match
            if ($creneau->getJourSemaine() === $jourSemaineMatch) {
                $capaciteJour += $creneau->getCapacite();

                // On prend la priorité de ce créneau.
                // S'il y a plusieurs créneaux le même jour, on prend le max des deux.
                if ($prioriteJour === null || $creneau->getPrioritaire() > $prioriteJour) {
                    $prioriteJour = $creneau->getPrioritaire();
                }
            }
        }

        // --- 2. Détection des Alertes ---

        // A. Alerte Capacité
        // Surcharge si : Pas de créneau ce jour là (capacite 0) OU nbMatchs dépasse la capacité
        $alerteCapacite = ($nbMatchs > $capaciteJour);

        // B. Alerte Priorité
        // On alerte si :
        // 1. Le jour utilisé a une priorité définie
        // 2. MAIS cette priorité est STRICTEMENT INFÉRIEURE à la meilleure priorité du gymnase
        $alertePriorite = false;
        $alerteHorscreneau = false;
        if ($prioriteJour !== null && $maxPrioriteGymnase > 0) {
            if ($prioriteJour < $maxPrioriteGymnase) {
                $alertePriorite = true;
            }
        } elseif ($prioriteJour === null) {
            // aler car date hors créneau officiel.
            $alerteHorscreneau = true;
        }

        return [
            'alerte_capacite' => $alerteCapacite,
            'capacite_max' => $capaciteJour,
            'alerte_priorite' => $alertePriorite,
            'alerte_horscrenau' => $alerteHorscreneau,
            'jour_prefere' => $meilleurJourLibelle,
            'priorite_actuelle' => $prioriteJour ?? 0,
            'priorite_max' => $maxPrioriteGymnase
        ];
    }
}
