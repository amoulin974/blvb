<?php

namespace App\Security;

use App\Entity\Partie;
use App\Entity\User;
use App\Service\SaisieScoreService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * is_granted('SAISIR_SCORE', partie) : l'administrateur, le capitaine de l'équipe qui reçoit
 * (saison du match) et les responsables du club de l'équipe qui reçoit (SaisieScoreService).
 *
 * @extends Voter<string, Partie>
 */
final class ScoreVoter extends Voter
{
    public const SAISIR = 'SAISIR_SCORE';

    public function __construct(
        private readonly Security $security,
        private readonly SaisieScoreService $saisieScore,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::SAISIR && $subject instanceof Partie;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $token->getUser();
        $recoit = $subject->getIdEquipeRecoit();
        if (!$user instanceof User || $recoit === null) {
            return false;
        }

        $saison = $subject->getPoule()?->getPhase()?->getSaison();

        return $saison !== null && \in_array($recoit->getId(), $this->saisieScore->equipesAutoriseesIds($user, $saison), true);
    }
}
