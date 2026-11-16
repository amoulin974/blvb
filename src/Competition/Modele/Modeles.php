<?php

namespace App\Competition\Modele;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Modèles de saison proposés au départ du wizard (services ModeleSaison). */
class Modeles
{
    /** @var array<string, ModeleSaison> */
    private array $parCode = [];

    /** @param iterable<ModeleSaison> $modeles */
    public function __construct(
        #[AutowireIterator(ModeleSaison::TAG)] iterable $modeles = [new ModeleBlvbActuel(), new ModeleBlvbAvecBarrages()],
    ) {
        foreach ($modeles as $modele) {
            $this->parCode[$modele->code()] = $modele;
        }
    }

    /** @return array<string, ModeleSaison> code => modèle */
    public function tous(): array
    {
        return $this->parCode;
    }

    public function parCode(string $code): ?ModeleSaison
    {
        return $this->parCode[$code] ?? null;
    }
}
