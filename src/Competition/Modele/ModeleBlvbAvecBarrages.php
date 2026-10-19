<?php

namespace App\Competition\Modele;

use App\Competition\CodesCompetition;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;
use DateTimeImmutable;

/**
 * Format BLVB avec barrages (proposition du bureau, docs/formats-competition.md F2, D44) : comme le format actuel,
 * mais entre la phase 1 et la phase 2, une semaine de barrages entre poules voisines. Dans chaque poule, le 1er monte
 * directement et le dernier descend directement ; l'avant-dernier d'une poule joue un match sec contre le 2e de la
 * poule du dessous, reçu par l'équipe de la poule supérieure (D43). Le vainqueur joue dans la poule supérieure à la
 * phase 2, le perdant dans la poule inférieure. Les autres équipes restent dans leur poule.
 *
 * Les barrages sont des poules de deux équipes (D42) codées « AB », « BC », « CD » ; les règles de passage de la phase 1
 * les alimentent, et celles de la phase de barrages (`BAR.AB.vainqueur`, `.perdant`) alimentent la phase 2.
 *
 * Fin de saison (D50) : la phase 2 alimente la phase finale comme avant, et envoie aussi son avant-dernier et le 2e de la
 * poule du dessous dans une seconde phase de barrages, placée après la phase finale et jouée en même temps. Ces
 * barrages n'influencent pas la phase finale ; leurs résultats servent à remplir à la main les poules de la saison
 * suivante : le modèle n'enregistre aucun passage vers la saison suivante.
 */
final class ModeleBlvbAvecBarrages implements ModeleSaison
{
    public const CODE = 'blvb_avec_barrages';

    /** Poules de niveau, du plus haut au plus bas (nom, niveau). */
    private const LETTRES = ['A', 'B', 'C', 'D'];

    /** @param string $prefixeNoms préfixe des noms de phases et de poules (jeux de données de test : « test_ ») */
    public function __construct(private string $prefixeNoms = '')
    {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Format BLVB avec barrages';
    }

    public function description(): string
    {
        return 'Comme le format actuel, avec des barrages entre la phase 1 et la phase 2 : le 1er monte, le dernier descend, l\'avant-dernier d\'une poule joue contre le 2e de la poule du dessous. La phase 2 se termine par des barrages de fin de saison, joués en même temps que la phase finale, sur le même principe. Ces barrages ne changent pas la phase finale. Les poules de la saison suivante sont à remplir à la main.';
    }

    public function appliquer(Saison $saison): void
    {
        $phase1 = $this->phase($saison, 'Phase 1', PhaseType::CHAMPIONNAT, 0, '01/09/n', '01/02/n+1');
        $barrage = $this->phase($saison, 'Barrages', PhaseType::BARRAGE, 1, '01/02/n+1', '08/02/n+1');
        $barrage->setOptions(['reception' => Phase::RECEPTION_NIVEAU_SUPERIEUR]);
        $phase2 = $this->phase($saison, 'Phase 2', PhaseType::CHAMPIONNAT, 2, '08/02/n+1', '01/06/n+1');
        $finale = $this->phase($saison, 'Phase Finale', PhaseType::FINALE, 3, '01/06/n+1', '31/07/n+1');
        $barrageFin = $this->phase($saison, 'Barrages de fin de saison', PhaseType::BARRAGE, 4, '01/06/n+1', '31/07/n+1');
        $barrageFin->setOptions(['reception' => Phase::RECEPTION_NIVEAU_SUPERIEUR]);

        // Poules de niveau des phases 1, 2 et finale (1 montée, 1 descente : zones du classement et règles par défaut)
        $poules2 = $poulesFinale = $matchs = $matchsFin = [];
        foreach ([$phase1, $phase2, $finale] as $phase) {
            foreach (self::LETTRES as $i => $lettre) {
                $poule = (new Poule())->setNom($this->prefixeNoms.'Poule '.$lettre)->setCode($lettre)->setNiveau($i + 1)
                    ->setNbMonteeDefaut(1)->setNbDescenteDefaut(1);
                $phase->addPoule($poule);
                if ($phase === $phase2) {
                    $poules2[$i] = $poule;
                }
                if ($phase === $finale) {
                    $poulesFinale[$i] = $poule;
                }
            }
        }
        // Un barrage par paire de poules voisines
        for ($i = 0; $i < \count(self::LETTRES) - 1; ++$i) {
            $code = self::LETTRES[$i].self::LETTRES[$i + 1];
            $matchs[$i] = (new Poule())->setNom($this->prefixeNoms.'Barrage '.self::LETTRES[$i].' / '.self::LETTRES[$i + 1])
                ->setCode($code)->setNiveau($i + 1)->setNbEquipesPrevu(2);
            $barrage->addPoule($matchs[$i]);
            $matchsFin[$i] = (new Poule())->setNom($this->prefixeNoms.'Barrage de fin de saison '.self::LETTRES[$i].' / '.self::LETTRES[$i + 1])
                ->setCode($code)->setNiveau($i + 1)->setNbEquipesPrevu(2);
            $barrageFin->addPoule($matchsFin[$i]);
        }

        // Codes des phases (P1, BAR, P2, F) ; ceux des poules sont déjà posés
        CodesCompetition::attribuer($saison);
        $c1 = $phase1->getCode();
        $cBarrage = $barrage->getCode();

        // Phase 1 : montée directe, barrage (avant-dernier du haut, 2e du bas), maintien, descente directe
        $ordre = 0;
        $regle = function (Phase $phase, int &$ordre, string $source, Poule $destination) {
            $phase->addRegleDePassage((new RegleDePassage())->setOrdre(++$ordre)->setSource($source)->setPouleDestination($destination));
        };
        foreach (self::LETTRES as $i => $lettre) {
            $haut = $i > 0;
            $bas = $i < \count(self::LETTRES) - 1;
            $regle($phase1, $ordre, "$c1.$lettre#1", $poules2[$haut ? $i - 1 : $i]);
            if ($haut) {
                $regle($phase1, $ordre, "$c1.$lettre#2", $matchs[$i - 1]);
            }
            $milieu = ($haut ? '3' : '2').'..'.($bas ? '-3' : '-2');
            $regle($phase1, $ordre, "$c1.$lettre#$milieu", $poules2[$i]);
            if ($bas) {
                $regle($phase1, $ordre, "$c1.$lettre#-2", $matchs[$i]);
                $regle($phase1, $ordre, "$c1.$lettre#-1", $poules2[$i + 1]);
            } else {
                $regle($phase1, $ordre, "$c1.$lettre#-1", $poules2[$i]);
            }
        }
        // Chaque rang a une seule destination : la poule la plus haute n'a pas de barrage vers le haut (son 2e reste dans
        // la plage « milieu »), la plus basse n'en a pas vers le bas (son avant-dernier y reste aussi).

        // Barrages : le vainqueur joue en haut, le perdant en bas
        $ordre = 0;
        foreach ($matchs as $i => $match) {
            $regle($barrage, $ordre, $cBarrage.'.'.$match->getCode().'.vainqueur', $poules2[$i]);
            $regle($barrage, $ordre, $cBarrage.'.'.$match->getCode().'.perdant', $poules2[$i + 1]);
        }

        // Phase 2 (D50) : toute la poule va dans le tableau de son niveau ; l'avant-dernier d'une poule et le 2e de la poule du
        // dessous jouent en plus un barrage de fin de saison, sans effet sur le tableau. Aucun passage vers la saison suivante.
        $c2 = $phase2->getCode();
        $ordre = 0;
        foreach (self::LETTRES as $i => $lettre) {
            $regle($phase2, $ordre, "$c2.$lettre#1..-1", $poulesFinale[$i]);
            if ($i > 0) {
                $regle($phase2, $ordre, "$c2.$lettre#2", $matchsFin[$i - 1]);
            }
            if ($i < \count(self::LETTRES) - 1) {
                $regle($phase2, $ordre, "$c2.$lettre#-2", $matchsFin[$i]);
            }
        }
    }

    private function phase(Saison $saison, string $nom, PhaseType $type, int $ordre, string $debut, string $fin): Phase
    {
        $phase = (new Phase())
            ->setNom($this->prefixeNoms.$nom)
            ->setType($type)
            ->setOrdre($ordre)
            ->setClose(0)
            ->setDateDebut(self::date($debut, $saison))
            ->setDateFin(self::date($fin, $saison));
        $saison->addPhase($phase);

        return $phase;
    }

    /** « 01/09/n » → 1er septembre de l'année de début de la saison ; « n+1 » : l'année suivante. */
    private static function date(string $date, Saison $saison): DateTimeImmutable
    {
        $annee = (int) $saison->getDateDebut()->format('Y');
        $date = str_replace(['n+1', 'n'], [(string) ($annee + 1), (string) $annee], $date);

        return DateTimeImmutable::createFromFormat('!d/m/Y', $date)
            ?: throw new \LogicException("Date de modèle invalide : $date");
    }
}
