<?php

namespace App\Twig;

use App\Entity\Reglement;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Reglement\Texte;
use App\Repository\ReglementRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** reglement_saison(saison) : règlement de la saison, ou null s'il n'est pas encore rédigé (liens « Modifier / Rédiger le règlement », D59). */
final class ReglementExtension extends AbstractExtension
{
    public function __construct(private readonly ReglementRepository $reglementRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('reglement_saison', fn (Saison $saison): ?Reglement => $this->reglementRepository->findDeSaison($saison)),
            // « la poule A », « le barrage A / B » (App\Reglement\Texte::lePoule)
            new TwigFunction('le_poule', fn (Poule $poule, bool $majuscule = false): string => Texte::lePoule($poule, $majuscule)),
        ];
    }
}
