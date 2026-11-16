<?php

namespace App\Competition;

use App\Entity\Equipe;

/** Remplace une place (P1.A#-2, BAR1.AB.vainqueur…) par les équipes qu'elle désigne, à partir des résultats. */
final class ResolveurPlace
{
    public function __construct(private ResultatsPhases $resultats)
    {
    }

    /**
     * Équipes désignées, dans l'ordre du classement pour une plage de rangs.
     *
     * @return list<Equipe>
     * @throws NotationInvalide si la notation est invalide, si un rang sort de la poule, ou si le match n'est pas joué
     */
    public function resoudre(Place|string $place): array
    {
        if (\is_string($place)) {
            $place = NotationPlace::analyser($place);
        }

        if (!$place->estRang()) {
            $equipe = $this->resultats->issue($place->phase, $place->match, $place->issue);
            if ($equipe === null) {
                throw new NotationInvalide(sprintf('« %s » : le match n\'a pas encore de résultat.', $place));
            }

            return [$equipe];
        }

        $classement = array_values($this->resultats->classement($place->phase, $place->poule));

        return array_map(fn (int $rang) => $classement[$rang - 1], $place->rangsPour(\count($classement)));
    }
}
