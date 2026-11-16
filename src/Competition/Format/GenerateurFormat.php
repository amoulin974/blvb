<?php

namespace App\Competition\Format;

use App\Entity\Phase;
use App\Entity\Poule;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Génération du calendrier d'une poule selon le format de sa phase (championnat, élimination…) : journées, puis
 * matchs. Une implémentation par format (docs/wizard-saison/archive/plan.md) ; Formats choisit celle d'une phase.
 */
#[AutoconfigureTag(self::TAG)]
interface GenerateurFormat
{
    public const TAG = 'app.generateur_format';

    public function supporte(Phase $phase): bool;

    /** Nombre de journées que ce format crée pour l'effectif actuel de la poule (0 s'il y a moins de deux équipes). */
    public function nombreJournees(Poule $poule): int;

    /** Nombre de matchs que ce format crée pour l'effectif actuel de la poule (0 s'il y a moins de deux équipes). */
    public function nombreMatchs(Poule $poule): int;

    /** Crée les journées de la poule (les anciennes sont déjà supprimées) ; message d'erreur, ou null si tout va bien. */
    public function creerJournees(Poule $poule): ?string;

    /** Crée les matchs de la poule dans ses journées (les anciens matchs sont déjà supprimés), sans optimisation. */
    public function genererMatchs(Poule $poule): void;

    /**
     * Les matchs de ce format sont-ils placés par l'optimiseur de PartieService (lieux, breaks, équilibre domicile /
     * extérieur) ? Sinon, genererMatchs est utilisé et ses matchs comptent comme occupation fixe des gymnases.
     */
    public function estOptimisable(): bool;
}
