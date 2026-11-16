<?php

namespace App\Competition;

use App\Entity\Equipe;

/**
 * Accès aux résultats d'une saison dont ResolveurPlace a besoin, désignés par leurs codes (phase, poule, match).
 * Isolé derrière une interface pour tester la résolution sans base de données ; l'implémentation Doctrine
 * arrive avec les règles de passage (lot 3, docs/wizard-saison/lots.md).
 */
interface ResultatsPhases
{
    /**
     * Équipes de la poule dans l'ordre du classement final (la première est 1re).
     *
     * @return list<Equipe>
     * @throws NotationInvalide si la phase ou la poule n'existe pas
     */
    public function classement(string $phase, string $poule): array;

    /**
     * Vainqueur ou perdant du match ; null tant que le match n'est pas joué.
     *
     * @param Place::VAINQUEUR|Place::PERDANT $issue
     * @throws NotationInvalide si la phase ou le match n'existe pas
     */
    public function issue(string $phase, string $match, string $issue): ?Equipe;
}
