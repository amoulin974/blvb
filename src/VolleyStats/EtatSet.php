<?php

namespace App\VolleyStats;

use App\Entity\FeuilleMatchEvenement;
use App\Entity\FeuilleMatchSet;

/**
 * État d'un set recalculé depuis sa composition de départ et son journal (D67) : score, joueurs aux postes 1 à 6 (ordre
 * officiel du service), équipe au service, fin atteinte. Les événements annulés (« Précédent ») sont ignorés.
 *
 * Rotation : quand l'équipe gagne le point sur le service adverse, elle récupère le service et tourne d'un poste (le joueur
 * du poste 2 passe au poste 1). La place libre d'un set commencé à 5 tourne avec les autres.
 */
final readonly class EtatSet
{
    /**
     * @param array{0: int, 1: int} $score nous, eux
     * @param list<?int> $postes identifiants de FeuilleMatchJoueur aux postes 1 à 6
     */
    private function __construct(
        public array $score,
        public array $postes,
        public ?string $service,
        public bool $finAtteinte,
    ) {
    }

    /** État du set, ou juste avant l'événement d'ordre $avantOrdre (joueurs sur le terrain au moment d'une action) */
    public static function calculer(FeuilleMatchSet $set, ?int $avantOrdre = null): self
    {
        $score = $set->getScoreDepart();
        $postes = $set->getPostes();
        $service = $set->getPremierService();
        foreach ($set->getEvenements() as $evenement) {
            if ($avantOrdre !== null && $evenement->getOrdre() >= $avantOrdre) {
                break;
            }
            if ($evenement->estAnnule()) {
                continue;
            }
            $d = $evenement->getDonnees();
            switch ($evenement->getType()) {
                case FeuilleMatchEvenement::POINT:
                    if ($d['pour'] === FeuilleMatchSet::NOUS) {
                        ++$score[0];
                        if ($service === FeuilleMatchSet::EUX) {
                            $postes = self::tourner($postes);
                        }
                        $service = FeuilleMatchSet::NOUS;
                    } else {
                        ++$score[1];
                        $service = FeuilleMatchSet::EUX;
                    }
                    break;
                case FeuilleMatchEvenement::REMPLACEMENT:
                    $place = array_search($d['sort'], $postes, true);
                    if ($place !== false) {
                        $postes[$place] = $d['entre'];
                    }
                    break;
                case FeuilleMatchEvenement::CORRECTION:
                    $score = [$d['nous'], $d['eux']];
                    $service = $d['service'];
                    break;
                case FeuilleMatchEvenement::ROTATION:
                    $postes = self::tourner($postes);
                    break;
            }
        }

        return new self($score, $postes, $service, max($score) >= $set->getPointsFin());
    }

    /** Un cran de rotation : poste 2 → 1, 3 → 2… 1 → 6 @param list<?int> $postes @return list<?int> */
    public static function tourner(array $postes): array
    {
        return [...\array_slice($postes, 1), $postes[0]];
    }

    /** Joueur au service, s'il est à nous (null : service adverse, ou place libre au service) */
    public function serveur(): ?int
    {
        return $this->service === FeuilleMatchSet::NOUS ? $this->postes[0] : null;
    }

    /** @return array<string, mixed> pour la page (JSON) */
    public function versTableau(): array
    {
        return ['score' => $this->score, 'postes' => $this->postes, 'service' => $this->service, 'finAtteinte' => $this->finAtteinte];
    }
}
