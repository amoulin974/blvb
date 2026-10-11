<?php

namespace App\Twig;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\MembreEquipeRepository;
use App\Service\ContactCapitaineService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * contact_capitaine_adverse(partie, saison) : pour un capitaine connecté dont l'équipe joue ce match,
 * le capitaine de l'équipe adverse et ses coordonnées. null dans tous les autres cas (visiteur, utilisateur
 * qui n'est pas capitaine d'une des deux équipes, administrateur : l'administrateur passe par la fiche équipe).
 * La règle d'accès est appliquée ici, côté serveur, et non dans les gabarits.
 */
class ContactCapitaineExtension extends AbstractExtension
{
    /** @var array<int, int[]> id de saison => ids des équipes dont l'utilisateur est capitaine */
    private array $equipesCapitaine = [];

    public function __construct(
        private readonly Security $security,
        private readonly MembreEquipeRepository $membreEquipeRepository,
        private readonly ContactCapitaineService $contactCapitaineService,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('contact_capitaine_adverse', $this->contactAdverse(...))];
    }

    /** @return array{adversaire: Equipe, contact: array|null}|null */
    public function contactAdverse(Partie $partie, Saison $saison): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $mesEquipes = $this->equipesCapitaine[$saison->getId()]
            ??= array_map(static fn (Equipe $e) => $e->getId(), $this->membreEquipeRepository->findEquipesOuCapitaine($user, $saison));

        $recoit = $partie->getIdEquipeRecoit();
        $deplace = $partie->getIdEquipeDeplace();
        if (in_array($recoit->getId(), $mesEquipes, true)) {
            $adversaire = $deplace;
        } elseif (in_array($deplace->getId(), $mesEquipes, true)) {
            $adversaire = $recoit;
        } else {
            return null;
        }

        return ['adversaire' => $adversaire, 'contact' => $this->contactCapitaineService->pour($adversaire, $saison)];
    }
}
