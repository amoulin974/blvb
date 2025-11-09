<?php

namespace App\Service;
class PlanningService
{
    public function structureParLieuEtDateParSemaine(array $rawData): array
    {
        $result = [];

        foreach ($rawData as $row) {
            $journee = $row['journee_id'];
            $lieu = $row['lieu_id'];
            $date = $row['date_match'];

            $result[$journee]['dates'][$date] = $date;
            $result[$journee]['lieux'][$lieu]['nbTerrains'] = $row['nb_terrains'];
            $result[$journee]['lieux'][$lieu]['nom'] = $row['lieu_nom'];
            $result[$journee]['lieux'][$lieu]['parDate'][$date] = (int)$row['nb_matchs'];
        }

        // Tri des dates à l'intérieur de chaque semaine
        foreach ($result as $journee => $data) {
            $dates = $data['dates'];
            sort($dates);
            $result[$journee]['dates'] = $dates;
        }

        return $result;
    }
}
