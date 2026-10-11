<?php

namespace App\Factory;

use App\Entity\Club;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Club>
 */
final class ClubFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Club::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'nom' => 'Club '.self::faker()->unique()->city(),
        ];
    }
}
