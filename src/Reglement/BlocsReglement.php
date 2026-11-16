<?php

namespace App\Reglement;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Registre des éléments calculés du règlement, dans l'ordre du panneau de la page d'édition. */
final class BlocsReglement
{
    /** Ordre du panneau ; un élément absent de la liste vient ensuite */
    private const ORDRE = ['schema_phases', 'bareme', 'departage', 'calendrier'];

    /** @var array<string, BlocReglement> */
    private array $blocs = [];

    /** @param iterable<BlocReglement> $blocs */
    public function __construct(#[AutowireIterator('app.bloc_reglement')] iterable $blocs)
    {
        foreach ($blocs as $bloc) {
            $this->blocs[$bloc->code()] = $bloc;
        }
        $rang = fn (string $code) => array_search($code, self::ORDRE, true) === false ? \count(self::ORDRE) : array_search($code, self::ORDRE, true);
        uksort($this->blocs, fn (string $a, string $b) => $rang($a) <=> $rang($b));
    }

    public function bloc(string $code): ?BlocReglement
    {
        return $this->blocs[$code] ?? null;
    }

    /** @return list<BlocReglement> */
    public function tous(): array
    {
        return array_values($this->blocs);
    }
}
