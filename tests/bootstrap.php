<?php

use App\EventListener\SaisonListener;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Les tests construisent eux-mêmes phases et poules : la création automatique à l'enregistrement
// d'une saison est désactivée (tests/SPECIFICATIONS.md §3.2). Un test qui la couvre la réactive.
SaisonListener::$enabled = false;
