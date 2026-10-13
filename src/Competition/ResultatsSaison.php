<?php

namespace App\Competition;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Repository\ClassementRepository;

/**
 * Résultats d'une saison pour ResolveurPlace, lus en base : classements des poules, désignées par les codes de phase et
 * de poule (CodesCompetition). Les issues de match (BAR1.AB.vainqueur) arriveront avec les barrages (lot 6).
 */
final class ResultatsSaison implements ResultatsPhases
{
    public function __construct(
        private Saison $saison,
        private ClassementRepository $classementRepository,
    ) {
    }

    public function classement(string $phase, string $poule): array
    {
        foreach ($this->saison->getPhases() as $p) {
            if ($p->getCode() !== $phase) {
                continue;
            }
            foreach ($p->getPoules() as $po) {
                if ($po->getCode() === $poule) {
                    return array_map(
                        fn ($c) => $c->getEquipe(),
                        $this->classementRepository->findBy(['poule' => $po], ['position' => 'ASC']),
                    );
                }
            }
            throw new NotationInvalide(sprintf('La phase %s n\'a pas de poule %s.', $phase, $poule));
        }

        throw new NotationInvalide(sprintf('La saison n\'a pas de phase %s.', $phase));
    }

    public function issue(string $phase, string $match, string $issue): ?Equipe
    {
        throw new NotationInvalide(sprintf('« %s.%s.%s » : les vainqueurs et perdants de match ne sont pas encore pris en charge.', $phase, $match, $issue));
    }
}
