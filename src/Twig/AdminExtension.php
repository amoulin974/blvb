<?php

namespace App\Twig;

use App\Entity\Saison;
use App\Repository\SaisonRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Back-office : saison mise en avant dans le menu (« Saison en cours »).
 * Saison favorite (celle affichée sur le site), sinon la plus récente.
 */
class AdminExtension extends AbstractExtension
{
    private ?Saison $saison = null;
    private bool $saisonChargee = false;

    public function __construct(private readonly SaisonRepository $saisonRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_saison_courante', $this->saisonCourante(...)),
        ];
    }

    public function saisonCourante(): ?Saison
    {
        if (!$this->saisonChargee) {
            $this->saisonChargee = true;
            $this->saison = $this->saisonRepository->findOneBy(['favori' => 1])
                ?? $this->saisonRepository->findOneBy([], ['date_debut' => 'DESC']);
        }

        return $this->saison;
    }
}
