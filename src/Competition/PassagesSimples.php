<?php

namespace App\Competition;

use App\Entity\Poule;
use App\Entity\RegleDePassage;

/**
 * Saisie simple des passages d'une poule (wizard, étape 4, D29) : « les m premiers montent vers…, les d derniers
 * descendent vers…, les autres vont vers… » ; vers une phase finale : « tout le classement vers le tableau… » et, pour
 * la saison suivante, les mêmes montées et descentes vers des niveaux.
 *
 * lire() reconnaît des règles de cette forme (sinon la poule passe en mode détaillé) ; regles() produit les règles.
 * Mêmes formes que ReglesParDefaut : P1.A#1..2, P1.A#3..-3, P1.A#-2..-1.
 */
final class PassagesSimples
{
    /**
     * Valeurs simples des règles d'une poule source, ou null si ses règles n'ont pas la forme simple.
     *
     * @param list<RegleDePassage> $regles règles de la poule (dans l'ordre)
     * @return array{montee: int, versMontee: ?Poule, descente: int, versDescente: ?Poule, versAutres: ?Poule,
     *               saisonSuivante: bool, niveauMontee: ?int, niveauDescente: ?int, niveauAutres: ?int}|null
     */
    public static function lire(string $prefixe, array $regles, bool $versFinale): ?array
    {
        $versPoules = array_values(array_filter($regles, fn (RegleDePassage $r) => !$r->versSaisonSuivante()));
        $versSaison = array_values(array_filter($regles, fn (RegleDePassage $r) => $r->versSaisonSuivante()));
        $valeurs = ['montee' => 0, 'versMontee' => null, 'descente' => 0, 'versDescente' => null, 'versAutres' => null,
            'saisonSuivante' => false, 'niveauMontee' => null, 'niveauDescente' => null, 'niveauAutres' => null];

        if ($versFinale) {
            // Tout le classement vers un tableau (une seule règle)
            if (\count($versPoules) !== 1 || $versPoules[0]->getSource() !== $prefixe.'1..-1') {
                return null;
            }
            $valeurs['versAutres'] = $versPoules[0]->getPouleDestination();
            if ($versSaison === []) {
                return $valeurs;
            }
            $forme = self::forme($prefixe, $versSaison);
            if ($forme === null) {
                return null;
            }

            return array_merge($valeurs, ['saisonSuivante' => true, 'montee' => $forme['montee'], 'descente' => $forme['descente'],
                'niveauMontee' => $forme['montee'] > 0 ? $versSaison[0]->getNiveauSaisonSuivante() : null,
                'niveauAutres' => $versSaison[$forme['montee'] > 0 ? 1 : 0]->getNiveauSaisonSuivante(),
                'niveauDescente' => $forme['descente'] > 0 ? end($versSaison)->getNiveauSaisonSuivante() : null]);
        }

        if ($versSaison !== []) {
            return null;
        }
        $forme = self::forme($prefixe, $versPoules);
        if ($forme === null) {
            return null;
        }

        return array_merge($valeurs, ['montee' => $forme['montee'], 'descente' => $forme['descente'],
            'versMontee' => $forme['montee'] > 0 ? $versPoules[0]->getPouleDestination() : null,
            'versAutres' => $versPoules[$forme['montee'] > 0 ? 1 : 0]->getPouleDestination(),
            'versDescente' => $forme['descente'] > 0 ? end($versPoules)->getPouleDestination() : null]);
    }

    /**
     * Règles (non enregistrées) d'une poule depuis les valeurs simples, dans l'ordre : montées, autres, descentes ;
     * vers une phase finale : le tableau, puis la saison suivante si demandée.
     *
     * @return list<RegleDePassage>
     */
    public static function regles(string $prefixe, array $valeurs, bool $versFinale): array
    {
        $montee = max(0, (int) $valeurs['montee']);
        $descente = max(0, (int) $valeurs['descente']);
        $regles = [];
        if ($versFinale) {
            $regles[] = (new RegleDePassage())->setSource($prefixe.'1..-1')->setPouleDestination($valeurs['versAutres']);
            if ($valeurs['saisonSuivante']) {
                foreach (self::sources($prefixe, $montee, $descente) as [$source, $role]) {
                    $regles[] = (new RegleDePassage())->setSource($source)->setNiveauSaisonSuivante($valeurs['niveau'.ucfirst($role)]);
                }
            }

            return $regles;
        }
        foreach (self::sources($prefixe, $montee, $descente) as [$source, $role]) {
            $regles[] = (new RegleDePassage())->setSource($source)->setPouleDestination($valeurs['vers'.ucfirst($role)]);
        }

        return $regles;
    }

    /**
     * Sources de la forme simple, avec leur rôle : montee (#1..m), autres (#m+1..-(d+1)), descente (#-d..-1).
     *
     * @return list<array{string, string}>
     */
    public static function sources(string $prefixe, int $montee, int $descente): array
    {
        $sources = [];
        if ($montee > 0) {
            $sources[] = [$prefixe.'1..'.$montee, 'montee'];
        }
        $sources[] = [$prefixe.($montee + 1).'..'.(-$descente - 1), 'autres'];
        if ($descente > 0) {
            $sources[] = [$prefixe.(-$descente).'..-1', 'descente'];
        }

        return $sources;
    }

    /** Nombres de montées et de descentes si les règles ont exactement la forme simple, sinon null. */
    private static function forme(string $prefixe, array $regles): ?array
    {
        $sources = array_map(fn (RegleDePassage $r) => $r->getSource(), $regles);
        $montee = preg_match('/^'.preg_quote($prefixe, '/').'1\.\.([1-9]\d*)$/', $sources[0] ?? '', $m) ? (int) $m[1] : 0;
        $descente = preg_match('/^'.preg_quote($prefixe, '/').'-([1-9]\d*)\.\.-1$/', end($sources) ?: '', $m) ? (int) $m[1] : 0;

        return array_column(self::sources($prefixe, $montee, $descente), 0) === $sources
            ? ['montee' => $montee, 'descente' => $descente]
            : null;
    }
}
