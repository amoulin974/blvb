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
            'datedebut' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'datefin' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'nom' => self::faker()->text(255),
            'ordre' => self::faker()->randomNumber(),
            'saison' => null, // TODO add App\Entity\Saison type manually
            'type' => self::faker()->randomElement(PhaseType::cases()),
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
