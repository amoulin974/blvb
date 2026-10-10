<?php

namespace App\Factory;

use App\Entity\Joueur;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fiche joueur (sans compte par défaut). Adresses en @blvb.test uniquement.
 *
 * @extends PersistentObjectFactory<Joueur>
 */
final class JoueurFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Joueur::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'nom' => self::faker()->lastName(),
            'prenom' => self::faker()->firstName(),
            'email' => self::faker()->unique()->userName().'@blvb.test',
            'telephone' => null,
            'user' => null,
        ];
    }
}
