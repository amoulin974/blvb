<?php

namespace App\Security;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\MembreEquipeRepository;
use App\VolleyStats\MatchEquipe;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Page du match d'une équipe (Volley Stats, D67), sujet MatchEquipe :
 *  - VOIR : admin, membre de l'équipe ou responsable de son club, **pour la saison du match** (jamais la saison choisie dans
 *    le menu) ; l'équipe adverse n'y a pas accès ;
 *  - MODIFIER : les mêmes tant que la page n'est pas gelée (7 jours après la fin, ou 30 jours après la date du match pour
 *    une page jamais terminée, D66, D67) ; l'admin toujours.
 * Ne pas reprendre SaisieScoreService::peutSaisir, qui donne droit sur l'une ou l'autre équipe du match.
 */
final class FeuilleMatchVoter extends Voter
{
    public const VOIR = 'FEUILLE_MATCH_VOIR';
    public const MODIFIER = 'FEUILLE_MATCH_MODIFIER';

    public const JOURS_CORRECTION = 7;
    public const JOURS_SANS_FIN = 30;

    public function __construct(
        private Security $security,
        private MembreEquipeRepository $membres,
        private ClockInterface $horloge,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::MODIFIER], true) && $subject instanceof MatchEquipe;
    }

    /** @param MatchEquipe $subject */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$subject->estValide()) {
            return false;
        }
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }
        $user = $token->getUser();
        if (!$user instanceof User || !$this->ayantDroit($user, $subject)) {
            return false;
        }

        return $attribute === self::VOIR || !$this->estGelee($subject);
    }

    private function ayantDroit(User $user, MatchEquipe $match): bool
    {
        return $this->ayantDroitSurEquipe($user, $match->equipe, $match->saison());
    }

    /**
     * Même règle pour les entrées de la fiche équipe (« Préparer le match », « Page du match », « Sets joués ») : admin,
     * membre de l'équipe ou responsable de son club pour cette saison (celle des matchs listés).
     */
    public function ayantDroitSurEquipe(?User $user, Equipe $equipe, ?Saison $saison): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $user !== null && $saison !== null
            && ($this->membres->estMembre($user, $equipe, $saison) || (bool) $equipe->getClub()?->estResponsable($user, $saison));
    }

    /** Page figée : seule l'admin la corrige encore */
    public function estGelee(MatchEquipe $match): bool
    {
        $maintenant = $this->horloge->now();
        $terminee = $match->feuille?->getTermineeLe();
        if ($terminee !== null) {
            return $terminee->modify(sprintf('+%d days', self::JOURS_CORRECTION)) < $maintenant;
        }
        $date = $match->partie->getDate();

        return $date !== null && $date->modify(sprintf('+%d days', self::JOURS_SANS_FIN)) < $maintenant;
    }
}
