<?php

namespace App\Competition;

/**
 * Plage de rangs bien écrite mais vide pour la taille de la poule (ex. « 3e à antépénultième » d'une poule de 4) :
 * aucune équipe désignée. Pour une règle de passage, ce n'est pas une erreur.
 */
final class PlageVide extends NotationInvalide
{
}
