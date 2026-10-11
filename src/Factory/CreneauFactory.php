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
        // Mercredi 20:30-22:30, 1 terrain, priorité 1 (jourSemaine : 1 = lundi … 7 = dimanche)
        return [
            'capacite' => 1,
            'heureDebut' => new \DateTimeImmutable('20:30'),
            'heureFin' => new \DateTimeImmutable('22:30'),
            'jourSemaine' => 3,
            'lieu' => LieuFactory::new(),
            'prioritaire' => 1,
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
