<?php

namespace App\Competition\Modele;

use App\Entity\Saison;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Modèle de saison fourni par l'application, point de départ du wizard (docs/wizard-saison/decisions.md, D10) : il
 * construit la structure d'une saison neuve (phases, poules, codes, règles de passage, barème, départage, sport).
 * Les modèles sont fixes ; la saison créée se modifie ensuite librement.
 */
#[AutoconfigureTag(self::TAG)]
interface ModeleSaison
{
    public const TAG = 'app.modele_saison';

    public function code(): string;

    public function libelle(): string;

    /** Une ou deux phrases pour l'administrateur : ce que contient le modèle. */
    public function description(): string;

    /**
     * Construit la structure dans la saison (dates de la saison déjà fixées), sans rien enregistrer : l'appelant
     * persiste les phases et les poules (les règles de passage suivent la phase).
     */
    public function appliquer(Saison $saison): void;
}
