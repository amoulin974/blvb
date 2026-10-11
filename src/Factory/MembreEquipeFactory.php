<?php

namespace App\Factory;

use App\Entity\MembreEquipe;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Appartenance d'un joueur à une équipe pour une saison (composition) ; capitaine ou simple membre.
 *
 * @extends PersistentObjectFactory<MembreEquipe>
 */
final class MembreEquipeFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return MembreEquipe::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'equipe' => EquipeFactory::new(),
            'saison' => SaisonFactory::new(),
            'joueur' => JoueurFactory::new(),
            'capitaine' => false,
        ];
    }

    public function capitaine(): self
    {
        return $this->with(['capitaine' => true]);
    }
}
