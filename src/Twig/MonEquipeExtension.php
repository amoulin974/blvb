<?php

namespace App\Twig;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\MembreEquipeRepository;
use App\Repository\SaisonRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Données de la fonctionnalité « Mon équipe » (mon_equipe_controller.js), disponibles sur toutes les pages publiques :
 *  - mon_equipe_defaut() : équipe dont l'utilisateur connecté est capitaine pour la saison affichée
 *    (choisie automatiquement tant que le visiteur n'a rien choisi lui-même), ou null ;
 *  - mon_equipe_choix()  : équipes de la saison affichée, triées par nom, pour la liste de choix.
 * La saison affichée suit la même règle que FrontController::getSaisonSession :
 * saison en session, sinon saison favorite, sinon la plus récente.
 */
class MonEquipeExtension extends AbstractExtension
{
    private ?Saison $saison = null;
    private bool $saisonChargee = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SaisonRepository $saisonRepository,
        private readonly MembreEquipeRepository $membreEquipeRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mon_equipe_defaut', $this->equipeDefaut(...)),
            new TwigFunction('mon_equipe_choix', $this->equipesAuChoix(...)),
        ];
    }

    /** @return array{id: int, nom: string}|null */
    public function equipeDefaut(): ?array
    {
        $user = $this->security->getUser();
        $saison = $this->saison();
        if (!$user instanceof User || $saison === null) {
            return null;
        }

        $equipes = $this->membreEquipeRepository->findEquipesOuCapitaine($user, $saison);

        return $equipes ? ['id' => $equipes[0]->getId(), 'nom' => $equipes[0]->getNom()] : null;
    }

    /** @return list<array{id: int, nom: string}> */
    public function equipesAuChoix(): array
    {
        $saison = $this->saison();
        if ($saison === null) {
            return [];
        }

        $equipes = [];
        foreach ($saison->getPhases() as $phase) {
            foreach ($phase->getPoules() as $poule) {
                foreach ($poule->getEquipes() as $equipe) {
                    /** @var Equipe $equipe */
                    $equipes[$equipe->getId()] = ['id' => $equipe->getId(), 'nom' => $equipe->getNom()];
                }
            }
        }
        usort($equipes, static fn (array $a, array $b) => strcasecmp($a['nom'], $b['nom']));

        return $equipes;
    }

    private function saison(): ?Saison
    {
        if (!$this->saisonChargee) {
            $this->saisonChargee = true;
            $session = $this->requestStack->getCurrentRequest()?->hasSession() ? $this->requestStack->getSession() : null;
            $id = $session?->get('idSaisonSelected');
            $this->saison = ($id ? $this->saisonRepository->find($id) : null)
                ?? $this->saisonRepository->findOneBy(['favori' => 1])
                ?? $this->saisonRepository->findOneBy([], ['date_debut' => 'DESC']);
        }

        return $this->saison;
    }
}
