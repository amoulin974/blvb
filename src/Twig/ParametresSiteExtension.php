<?php

namespace App\Twig;

use App\Entity\ParametresSite;
use App\Repository\ParametresSiteRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** parametres_site() : paramètres du site (D62), pour le logo de l'en-tête et l'adresse de contact du pied de page. */
final class ParametresSiteExtension extends AbstractExtension
{
    public function __construct(private readonly ParametresSiteRepository $parametres)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('parametres_site', fn (): ParametresSite => $this->parametres->courants()),
        ];
    }
}
