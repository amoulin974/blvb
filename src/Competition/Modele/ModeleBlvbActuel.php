<?php

namespace App\Competition\Modele;

use App\Competition\CodesCompetition;
use App\Competition\ReglesParDefaut;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;
use DateTimeImmutable;

/**
 * Format BLVB actuel (schéma 2025, docs/formats-competition.md F1), repris de l'ancien config/app/saison.yaml :
 * phase 1 et phase 2 en championnat, 4 poules de niveau (A à D, 2 montées et 2 descentes), puis une phase finale par
 * tableau de niveau ; règles de passage du schéma 2025 (ReglesParDefaut). Barème, départage et sport : ceux de la
 * saison (valeurs par défaut).
 */
final class ModeleBlvbActuel implements ModeleSaison
{
    public const CODE = 'blvb_actuel';

    /** Phases : nom, type, début et fin (« n » = année de début de la saison), poules (nom => niveau). */
    private const PHASES = [
        ['Phase 1', PhaseType::CHAMPIONNAT, '01/09/n', '01/02/n+1'],
        ['Phase 2', PhaseType::CHAMPIONNAT, '01/02/n+1', '01/06/n+1'],
        // Fin au 31 juillet, comme la saison (l'ancien YAML disait 1er août, hors de la saison)
        ['Phase finale', PhaseType::FINALE, '01/06/n+1', '31/07/n+1'],
    ];
    private const POULES = ['Poule A' => 1, 'Poule B' => 2, 'Poule C' => 3, 'Poule D' => 4];

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
        return 'Format BLVB actuel';
    }

    public function description(): string
    {
        return 'Phase 1 et phase 2 en championnat sur 4 poules de niveau (2 montées, 2 descentes), puis une phase finale par tableau de niveau.';
    }

    public function appliquer(Saison $saison): void
    {
        foreach (self::PHASES as $ordre => [$nom, $type, $debut, $fin]) {
            $phase = (new Phase())
                ->setNom($this->prefixeNoms.$nom)
                ->setType($type)
                ->setOrdre($ordre)
                ->setClose(0)
                ->setDateDebut(self::date($debut, $saison))
                ->setDateFin(self::date($fin, $saison));
            $saison->addPhase($phase);
            foreach (self::POULES as $nomPoule => $niveau) {
                $phase->addPoule((new Poule())->setNom($this->prefixeNoms.$nomPoule)->setNiveau($niveau)->setNbMonteeDefaut(2)->setNbDescenteDefaut(2));
            }
        }

        // Codes des phases et des poules, puis règles de passage par défaut entre phases consécutives
        CodesCompetition::attribuer($saison);
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => $a->getOrdre() <=> $b->getOrdre());
        foreach ($phases as $i => $phase) {
            foreach (ReglesParDefaut::pourPhase($phase, $phases[$i + 1] ?? null) as $regle) {
                $phase->addRegleDePassage($regle);
            }
        }
    }

    /** « 01/09/n » → 1er septembre de l'année de début de la saison ; « n+1 » : l'année suivante. */
    private static function date(string $date, Saison $saison): DateTimeImmutable
    {
        $annee = (int) $saison->getDateDebut()->format('Y');
        $date = str_replace(['n+1', 'n'], [(string) ($annee + 1), (string) $annee], $date);

        return DateTimeImmutable::createFromFormat('!d/m/Y', $date) // « ! » : à minuit, comme les dates de la saison
            ?: throw new \LogicException("Date de modèle invalide : $date");
    }
}
