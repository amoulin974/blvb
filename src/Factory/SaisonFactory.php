<?php

namespace App\Factory;

use App\Entity\Saison;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Saison>
 */
final class SaisonFactory extends PersistentObjectFactory
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
        return Saison::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        $dateDebut = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 year', '+1 year'));
        $dateFin = $dateDebut->modify('+11 months');
        return [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'favori' => 0,
            'nom' => self::faker()->text(255),
            'points_defaite_faible' => 0,
            'points_defaite_forte' => 1,
            'points_forfait' => 3,
            'points_nul' => 1,
            'points_victoire_faible' => 2,
            'points_victoire_forte' => 3,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Saison $saison): void {})
        ;
    }
}
