<?php

namespace App\Competition;

/** Notation de place mal écrite, ou impossible à appliquer à une poule (rang hors de la poule, plage vide). */
final class NotationInvalide extends \InvalidArgumentException
{
}
