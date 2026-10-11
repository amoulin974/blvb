<?php

namespace App\Factory;

use App\Entity\Indisponibilite;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Période sans match d'une saison (vacances scolaires…).
 *
 * @extends PersistentObjectFactory<Indisponibilite>
 */
final class IndisponibiliteFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Indisponibilite::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'nom' => 'Vacances scolaires',
            'dateDebut' => new \DateTimeImmutable('monday next week'),
            'dateFin' => new \DateTimeImmutable('sunday next week +1 week'),
            'saison' => SaisonFactory::new(),
        ];
    }
}
