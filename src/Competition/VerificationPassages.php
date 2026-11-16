<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\RegleDePassage;

/**
 * Vérification des règles de passage d'une phase (docs/wizard-saison/decisions.md, D5) : chaque règle est lisible et
 * désigne une poule de cette phase ; chaque rang de chaque poule (selon son nombre d'équipes) a exactement une
 * destination dans la phase suivante, et au plus une pour la saison suivante. Un rang peut aussi aller dans une autre
 * phase (la phase 2 alimente la phase finale et les barrages de fin de saison, D50) : une seule destination par phase.
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
        // [destination : 'phase' ou 'saison'][code de poule][rang][phase d'arrivée] = nombre de règles
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
            // Issue d'un match (D42) : le match est la poule de deux équipes de même code ; vainqueur = rang 1, perdant = rang 2
            $codePoule = $place->estRang() ? $place->poule : $place->match;
            if (!isset($poules[$codePoule])) {
                $erreurs[] = sprintf('Règle « %s » ignorée : il n\'y a pas de %s %s dans « %s ».', self::nom($regle), $place->estRang() ? 'poule' : 'match', $codePoule, $phase->getNom());
                continue;
            }

            $type = $regle->versSaisonSuivante() ? 'saison' : 'phase';
            if ($type === 'saison') {
                $saisonSuivante[$codePoule] = true;
            }
            if ($nbEquipes[$codePoule] === 0) {
                continue; // taille inconnue : pas de contrôle des rangs
            }
            if (!$place->estRang()) {
                $rangs = [$place->issue === Place::VAINQUEUR ? 1 : 2];
            } else {
                try {
                    $rangs = $place->rangsPour($nbEquipes[$codePoule]);
                } catch (PlageVide) {
                    $rangs = [];
                } catch (NotationInvalide $e) {
                    $erreurs[] = sprintf('Règle « %s » ignorée : %s', self::nom($regle), $e->getMessage());
                    continue;
                }
            }
            $arrivee = $type === 'phase' ? spl_object_id($regle->getPouleDestination()?->getPhase() ?? $phase) : 0;
            foreach ($rangs as $rang) {
                $couverture[$type][$codePoule][$rang][$arrivee] = ($couverture[$type][$codePoule][$rang][$arrivee] ?? 0) + 1;
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
                    $arrivees = $couverture[$type][$code][$rang] ?? [];
                    if ($arrivees === []) {
                        $sans[] = $rang;
                    } elseif (max($arrivees) > 1) {
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
