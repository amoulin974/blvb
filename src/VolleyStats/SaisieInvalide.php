<?php

namespace App\VolleyStats;

/**
 * Saisie refusée sur la page du match : le message est destiné à l'utilisateur (neutre, sans nom de joueur repris de la
 * requête). Toute autre exception reste interne et donne un message générique.
 */
final class SaisieInvalide extends \DomainException
{
}
