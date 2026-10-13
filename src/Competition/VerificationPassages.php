<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\RegleDePassage;

/**
 * Vérification des règles de passage d'une phase (docs/wizard-saison/decisions.md, D5) : chaque règle est lisible et
 * désigne une poule de cette phase ; chaque rang de chaque poule (selon son nombre d'équipes) a exactement une
 * destination dans la phase suivante, et au plus une pour la saison suivante.
 * Rend des messages lisibles par un administrateur bénévole (règles en clair, notation entre crochets) ;
 * liste vide si tout va bien.
 */
final class VerificationPassages
{
    /**
     * @param iterable<RegleDePassage> $regles
     * @param array<string, ?int>|null $tailles nombre d'équipes par code de poule (wizard : nombre prévu) ; à défaut,
     *                                         les équipes de la poule. Taille inconnue ou nulle : seule l'écriture des
     *                                         règles est vérifiée pour cette poule.
     * @return list<string>
     */
    public static function verifier(Phase $phase, iterable $regles, ?array $tailles = null): array
    {
        $poules = [];
        $nbEquipes = [];
        foreach ($phase->getPoules() as $poule) {
            $poules[$poule->getCode()] = $poule;
            $nbEquipes[$poule->getCode()] = $tailles !== null ? (int) ($tailles[$poule->getCode()] ?? 0) : \count($poule->getEquipes());
        }

        $erreurs = [];
        // [destination : 'phase' ou 'saison'][code de poule][rang] = nombre de règles
        $couverture = ['phase' => [], 'saison' => []];
        $saisonSuivante = [];
        foreach ($regles as $regle) {
            try {
                $place = NotationPlace::analyser($regle->getSource());
            } catch (NotationInvalide) {
                $erreurs[] = sprintf('Règle « %s » ignorée : elle est mal écrite.', self::nom($regle));
                continue;
            }
            if ($regle->getPouleDestination() === null && $regle->getNiveauSaisonSuivante() === null) {
                $erreurs[] = sprintf('Règle « %s » ignorée : aucune poule d\'arrivée n\'est indiquée.', self::nom($regle));
                continue;
            }
            if ($place->phase !== $phase->getCode()) {
                $erreurs[] = sprintf('Règle « %s » ignorée : elle concerne une autre phase.', self::nom($regle));
                continue;
            }
            if (!$place->estRang()) {
                $erreurs[] = sprintf('Règle « %s » ignorée : le passage selon le résultat d\'un match n\'est pas encore possible.', self::nom($regle));
                continue;
            }
            if (!isset($poules[$place->poule])) {
                $erreurs[] = sprintf('Règle « %s » ignorée : il n\'y a pas de poule %s dans « %s ».', self::nom($regle), $place->poule, $phase->getNom());
                continue;
            }

            $type = $regle->versSaisonSuivante() ? 'saison' : 'phase';
            if ($type === 'saison') {
                $saisonSuivante[$place->poule] = true;
            }
            if ($nbEquipes[$place->poule] === 0) {
                continue; // taille inconnue : pas de contrôle des rangs
            }
            try {
                $rangs = $place->rangsPour($nbEquipes[$place->poule]);
            } catch (PlageVide) {
                $rangs = [];
            } catch (NotationInvalide $e) {
                $erreurs[] = sprintf('Règle « %s » ignorée : %s', self::nom($regle), $e->getMessage());
                continue;
            }
            foreach ($rangs as $rang) {
                $couverture[$type][$place->poule][$rang] = ($couverture[$type][$place->poule][$rang] ?? 0) + 1;
            }
        }

        foreach ($poules as $code => $poule) {
            $nb = $nbEquipes[$code];
            foreach (['phase', 'saison'] as $type) {
                if ($type === 'saison' && !isset($saisonSuivante[$code])) {
                    continue; // pas de règle pour la saison suivante dans cette poule : rien à vérifier
                }
                $sans = $doubles = [];
                for ($rang = 1; $rang <= $nb; ++$rang) {
                    $n = $couverture[$type][$code][$rang] ?? 0;
                    if ($n === 0) {
                        $sans[] = $rang;
                    } elseif ($n > 1) {
                        $doubles[] = $rang;
                    }
                }
                if ($sans !== []) {
                    $erreurs[] = $type === 'phase'
                        ? sprintf('%s : aucune poule prévue pour %s dans la phase suivante.', $poule->getNom(), self::rangs($sans))
                        : sprintf('%s : aucun niveau prévu pour %s la saison prochaine.', $poule->getNom(), self::rangs($sans));
                }
                if ($doubles !== []) {
                    $verbe = \count($doubles) > 1 ? 'sont prévus' : 'est prévu';
                    $erreurs[] = $type === 'phase'
                        ? sprintf('%s : %s %s dans plusieurs poules ; seule la première règle compte.', $poule->getNom(), self::rangs($doubles), $verbe)
                        : sprintf('%s : %s %s à plusieurs niveaux la saison prochaine ; seule la première règle compte.', $poule->getNom(), self::rangs($doubles), $verbe);
                }
            }
        }

        return $erreurs;
    }

    /** [3] → « le 3e », [3, 4] → « les 3e et 4e », [1, 2, 3] → « les 1er, 2e et 3e » */
    /** La règle telle que l'utilisateur la lit : en clair, avec la notation seulement si elle est illisible. */
    private static function nom(RegleDePassage $regle): string
    {
        return $regle->enClair() ?: (string) $regle;
    }

    private static function rangs(array $rangs): string
    {
        $noms = array_map(fn (int $r) => Place::rangEnClair($r), $rangs);
        if (\count($noms) === 1) {
            return 'le '.$noms[0];
        }
        $dernier = array_pop($noms);

        return 'les '.implode(', ', $noms).' et '.$dernier;
    }
}
