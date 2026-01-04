<?php

namespace App\Factory;

use App\Entity\Creneau;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Creneau>
 */
final class CreneauFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Creneau::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'capacite' => self::faker()->randomNumber(),
            'heureDebut' => \DateTimeImmutable::createFromMutable(self::faker()->datetime()),
            'heureFin' => \DateTimeImmutable::createFromMutable(self::faker()->datetime()),
            'jourSemaine' => self::faker()->randomNumber(),
            'lieu' => LieuFactory::new(),
            'prioritaire' => self::faker()->randomNumber(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Creneau $creneau): void {})
        ;
    }
}
