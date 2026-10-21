<?php

namespace App\Competition;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Repository\ClassementRepository;

/**
 * Résultats d'une saison pour ResolveurPlace, lus en base : classements des poules, désignées par les codes de phase et
 * de poule (CodesCompetition), et issues des matchs de barrage (BAR.AB.vainqueur, D42).
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

    /**
     * Un match est la poule de deux équipes de même code d'une phase de barrages (D42). Null tant que son score n'est
     * pas saisi ; un forfait (-1 set) donne la victoire à l'autre équipe.
     */
    public function issue(string $phase, string $match, string $issue): ?Equipe
    {
        foreach ($this->saison->getPhases() as $p) {
            if ($p->getCode() !== $phase) {
                continue;
            }
            foreach ($p->getPoules() as $poule) {
                if ($poule->getCode() !== $match) {
                    continue;
                }
                $partie = $poule->getParties()->first() ?: null;
                $recus = $partie?->getNbSetGagnantReception();
                $deplaces = $partie?->getNbSetGagnantDeplacement();
                if ($partie === null || $recus === null || $deplaces === null || $recus === $deplaces) {
                    return null;
                }
                $recoitGagne = $recus > $deplaces;

                return ($issue === Place::VAINQUEUR) === $recoitGagne ? $partie->getIdEquipeRecoit() : $partie->getIdEquipeDeplace();
            }
            throw new NotationInvalide(sprintf('La phase %s n\'a pas de match %s.', $phase, $match));
        }

        throw new NotationInvalide(sprintf('La saison n\'a pas de phase %s.', $phase));
    }
}
