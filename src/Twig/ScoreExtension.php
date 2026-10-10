<?php

namespace App\Twig;

use App\Entity\Equipe;
use App\Entity\Partie;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Affichage des scores. Un forfait est enregistré à -1 set pour l'équipe forfait (FrontController::api_score_update) :
 * il s'affiche « F » (ex. « F–3 »), jamais « -1 ».
 *  - filtre  sets           : {{ partie.nbSetGagnantReception|sets }} → « 3 », « F » ou « » (non joué)
 *  - fonction forfait_de()  : équipe déclarée forfait pour ce match, ou null (texte pour lecteurs d'écran, agenda…)
 */
class ScoreExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('sets', self::sets(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('forfait_de', self::forfaitDe(...)),
        ];
    }

    public static function sets(?int $sets): string
    {
        return match (true) {
            $sets === null => '',
            $sets === -1 => 'F',
            default => (string) $sets,
        };
    }

    public static function forfaitDe(Partie $partie): ?Equipe
    {
        return match (-1) {
            $partie->getNbSetGagnantReception() => $partie->getIdEquipeRecoit(),
            $partie->getNbSetGagnantDeplacement() => $partie->getIdEquipeDeplace(),
            default => null,
        };
    }
}
