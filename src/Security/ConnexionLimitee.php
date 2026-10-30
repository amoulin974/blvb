<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/** Essai de connexion refusé sans vérifier le mot de passe : il faut encore attendre (LimiteurConnexion). */
final class ConnexionLimitee extends CustomUserMessageAuthenticationException
{
}
