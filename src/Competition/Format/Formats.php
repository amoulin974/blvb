<?php

namespace App\Competition\Format;

use App\Entity\Phase;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Générateur de calendrier d'une phase, parmi les formats déclarés (services GenerateurFormat). */
class Formats
{
    /** @param iterable<GenerateurFormat> $generateurs */
    public function __construct(
        #[AutowireIterator(GenerateurFormat::TAG)] private iterable $generateurs,
    ) {
    }

    /** Générateur du format de la phase, ou null si aucun ne la prend en charge. */
    public function pourPhase(Phase $phase): ?GenerateurFormat
    {
        foreach ($this->generateurs as $generateur) {
            if ($generateur->supporte($phase)) {
                return $generateur;
            }
        }

        return null;
    }
}
