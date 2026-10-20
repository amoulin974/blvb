<?php

namespace App\Reglement\Bloc;

use App\Competition\Classement\CritereDepartage;
use App\Entity\Saison;
use App\Reglement\BlocReglement;
use Twig\Environment;

/** [departage] : critères de classement de la saison, dans l'ordre, avec l'aide du wizard (D58). Sans paramètre. */
final class BlocDepartage implements BlocReglement
{
    public function __construct(private Environment $twig)
    {
    }

    public function code(): string
    {
        return 'departage';
    }

    public function libelle(): string
    {
        return 'Départage des équipes à égalité';
    }

    public function description(): string
    {
        return 'Les critères de classement des équipes d\'une poule, dans l\'ordre, valables pour toute la saison.';
    }

    public function parametres(Saison $saison): array
    {
        return [];
    }

    public function rendre(Saison $saison, array $parametres): string
    {
        return $this->twig->render('reglement/blocs/departage.html.twig', [
            'criteres' => array_map(fn (CritereDepartage $c) => ['libelle' => $c->libelle(), 'aide' => $c->aide()], $saison->getDepartage()),
        ]);
    }
}
