<?php

namespace App\Twig;

use App\Entity\Partie;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * score_saisissable(partie, droitsSaisie) : la personne connectée peut-elle saisir le score de ce match ? Même règle que
 * App\Service\SaisieScoreService::peutSaisir (admin, capitaine ou responsable du club de l'une ou l'autre équipe), à partir
 * des droits calculés une fois par page (SaisieScoreService::droits) ; la seule copie de la règle côté gabarits.
 */
class SaisieScoreExtension extends AbstractExtension
{
    public function __construct(private readonly Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('score_saisissable', $this->saisissable(...)),
        ];
    }

    /** @param array{capitaine?: list<int>, club?: list<int>}|null $droits */
    public function saisissable(Partie $partie, ?array $droits): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }
        $equipes = array_merge($droits['capitaine'] ?? [], $droits['club'] ?? []);

        return \in_array($partie->getIdEquipeRecoit()?->getId(), $equipes, true)
            || ($partie->getIdEquipeDeplace() !== null && \in_array($partie->getIdEquipeDeplace()->getId(), $equipes, true));
    }
}
