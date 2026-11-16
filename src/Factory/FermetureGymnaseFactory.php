<?php

namespace App\Factory;

use App\Entity\FermetureGymnase;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Période de fermeture d'un gymnase.
 *
 * @extends PersistentObjectFactory<FermetureGymnase>
 */
final class FermetureGymnaseFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return FermetureGymnase::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'lieu' => LieuFactory::new(),
            'motif' => 'Travaux',
            'dateDebut' => new \DateTimeImmutable('monday next week'),
            'dateFin' => new \DateTimeImmutable('sunday next week'),
        ];
    }
}
