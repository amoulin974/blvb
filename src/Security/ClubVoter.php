<?php

namespace App\Security;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * is_granted('CLUB_MODIFIER', club) : l'administrateur et les responsables du club peuvent modifier sa fiche
 * (nom long, nom court, description, lien). Équipes, gymnases et responsables restent gérés dans l'admin.
 *
 * @extends Voter<string, Club>
 */
final class ClubVoter extends Voter
{
    public const MODIFIER = 'CLUB_MODIFIER';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MODIFIER && $subject instanceof Club;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $this->security->isGranted('ROLE_ADMIN') || ($user instanceof User && $subject->estResponsable($user));
    }
}
