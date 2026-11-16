<?php

namespace App\Reglement;

use App\Entity\Saison;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Élément calculé du règlement (docs/wizard-saison/decisions.md, D55, D58) : inséré dans le texte par un code court
 * (« [bareme phase=P2] ») et recalculé à chaque affichage à partir des paramètres de la saison.
 */
#[AutoconfigureTag('app.bloc_reglement')]
interface BlocReglement
{
    /** Code court, sans crochets (« bareme ») */
    public function code(): string;

    /** Nom affiché dans le panneau de la page d'édition (« Barème des points ») */
    public function libelle(): string;

    /** Ce que montre l'élément, en une phrase */
    public function description(): string;

    /**
     * Paramètres acceptés, avec leurs valeurs possibles dans la saison
     *
     * @return list<ParametreBloc>
     */
    public function parametres(Saison $saison): array;

    /**
     * HTML de l'élément pour la saison
     *
     * @param array<string, string> $parametres paramètres du code court, déjà vérifiés (nom connu, valeur possible)
     * @throws BlocInapplicable si l'élément n'a pas d'objet dans cette saison
     */
    public function rendre(Saison $saison, array $parametres): string;
}
