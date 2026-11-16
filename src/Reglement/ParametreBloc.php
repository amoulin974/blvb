<?php

namespace App\Reglement;

/** Paramètre d'un code court (« phase » de « [bareme phase=P2] »), décrit dans le panneau de la page d'édition. */
final class ParametreBloc
{
    /**
     * @param array<string, string> $valeurs valeurs possibles dans la saison → libellé (« P2 » → « Phase 2 »)
     */
    public function __construct(
        public readonly string $nom,
        public readonly string $role,
        public readonly array $valeurs,
        public readonly string $defaut,
    ) {
    }

    /** Liste des valeurs possibles avec leur libellé : « P1 (Phase 1), P2 (Phase 2) » */
    public function valeursEnClair(): string
    {
        return implode(', ', array_map(fn ($valeur, $libelle) => sprintf('%s (%s)', $valeur, $libelle), array_keys($this->valeurs), $this->valeurs));
    }
}
