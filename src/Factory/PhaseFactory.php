<?php

namespace App\Factory;

use App\Entity\Phase;
use App\Enum\PhaseType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Phase>
 */
final class PhaseFactory extends PersistentObjectFactory
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
        return Phase::class;
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
            'datedebut' => new \DateTimeImmutable('-1 month'),
            'datefin' => new \DateTimeImmutable('+4 months'),
            'nom' => 'Phase 1',
            'ordre' => 1,
            'saison' => SaisonFactory::new(),
            'type' => PhaseType::CHAMPIONNAT,
            'close' => 0,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Phase $phase): void {})
        ;
    }
}
