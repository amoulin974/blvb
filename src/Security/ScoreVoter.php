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
 * is_granted('SAISIR_SCORE', partie) : l'administrateur, le capitaine de l'une ou l'autre équipe
 * (saison du match) et les responsables du club de l'une ou l'autre équipe (SaisieScoreService).
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

        return $user instanceof User && $this->saisieScore->peutSaisir($user, $subject);
    }
}
