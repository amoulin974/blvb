<?php

namespace App\Competition;

use App\Entity\Indisponibilite;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Repository\ClassementRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Nouvelle saison par copie d'une saison existante, point de départ du wizard (docs/wizard-saison/decisions.md, D3,
 * D10, D22) : sport, barème, départage, phases (dates décalées comme le début de saison), poules, codes et règles de
 * passage sont recopiés ; les montées et descentes « pour la saison suivante » de la saison copiée pré-remplissent
 * les poules de la première phase, si la phase qui les porte est clôturée.
 */
final class CopieSaison
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClassementRepository $classementRepository,
    ) {
    }

    /**
     * Crée et enregistre la nouvelle saison.
     *
     * @param bool $copierIndisponibilites périodes sans match recopiées, décalées comme les dates de la saison
     * @return array{saison: Saison, avertissements: list<string>}
     */
    public function copier(Saison $source, string $nom, DateTimeImmutable $debut, DateTimeImmutable $fin, bool $copierIndisponibilites = true): array
    {
        $decalage = (int) $source->getDateDebut()->diff($debut)->format('%r%a');
        $decaler = fn (?DateTimeImmutable $date) => $date?->modify(sprintf('%+d days', $decalage));

        $saison = (new Saison())
            ->setNom($nom)
            ->setDateDebut($debut)
            ->setDateFin($fin)
            ->setSport($source->getSport())
            ->setPointsVictoireForte($source->getPointsVictoireForte())
            ->setPointsVictoireFaible($source->getPointsVictoireFaible())
            ->setPointsNul($source->getPointsNul())
            ->setPointsDefaiteForte($source->getPointsDefaiteForte())
            ->setPointsDefaiteFaible($source->getPointsDefaiteFaible())
            ->setPointsForfait($source->getPointsForfait())
            ->setDepartage($source->getDepartage());

        // Phases et poules ; correspondance ancienne poule → nouvelle, pour les destinations des règles
        $poules = [];
        $phases = self::phasesDansLOrdre($source);
        foreach ($phases as $ancienne) {
            $phase = (new Phase())
                ->setNom($ancienne->getNom())
                ->setCode($ancienne->getCode())
                ->setType($ancienne->getType())
                ->setOrdre($ancienne->getOrdre())
                ->setClose(0)
                ->setDateDebut($decaler($ancienne->getDateDebut()))
                ->setDateFin($decaler($ancienne->getDateFin()))
                ->setBareme($ancienne->getBareme())
                ->setOptions($ancienne->getOptions());
            $saison->addPhase($phase);
            foreach ($ancienne->getPoules() as $anciennePoule) {
                $poule = (new Poule())
                    ->setNom($anciennePoule->getNom())
                    ->setCode($anciennePoule->getCode())
                    ->setNiveau($anciennePoule->getNiveau())
                    ->setNbMonteeDefaut($anciennePoule->getNbMonteeDefaut())
                    ->setNbDescenteDefaut($anciennePoule->getNbDescenteDefaut())
                    // Nombre d'équipes prévu : celui saisi, sinon l'effectif de la poule copiée (D29)
                    ->setNbEquipesPrevu($anciennePoule->getNbEquipesPrevu() ?? ($anciennePoule->getEquipes()->count() ?: null));
                $phase->addPoule($poule);
                $poules[spl_object_id($anciennePoule)] = $poule;
            }
        }
        foreach ($phases as $i => $ancienne) {
            foreach ($ancienne->getReglesDePassage() as $regle) {
                $saison->getPhases()->get($i)->addRegleDePassage((new RegleDePassage())
                    ->setOrdre($regle->getOrdre())
                    ->setSource($regle->getSource())
                    ->setPouleDestination($regle->getPouleDestination() ? $poules[spl_object_id($regle->getPouleDestination())] ?? null : null)
                    ->setNiveauSaisonSuivante($regle->getNiveauSaisonSuivante())
                    ->setPlacement($regle->getPlacement())
                    ->setPointsConserves($regle->getPointsConserves()));
            }
        }
        CodesCompetition::attribuer($saison);

        if ($copierIndisponibilites) {
            foreach ($source->getIndisponibilites() as $indisponibilite) {
                $saison->addIndisponibilite((new Indisponibilite())
                    ->setNom($indisponibilite->getNom())
                    ->setDateDebut($decaler($indisponibilite->getDateDebut()))
                    ->setDateFin($decaler($indisponibilite->getDateFin())));
            }
        }

        $avertissements = $this->preRemplir($source, $saison);

        $this->em->persist($saison);
        foreach ($saison->getPhases() as $phase) {
            $this->em->persist($phase);
            foreach ($phase->getPoules() as $poule) {
                $this->em->persist($poule);
            }
        }
        $this->em->flush();

        return ['saison' => $saison, 'avertissements' => $avertissements];
    }

    /**
     * Aperçu d'une saison à copier pour l'étape 1 du wizard : résumé (« 3 phases · 12 poules ») et, si ses montées et
     * descentes ne sont pas encore connues, l'avertissement que les poules de la première phase seront à composer.
     *
     * @return array{resume: string, avertissement: ?string}
     */
    public static function apercu(Saison $source): array
    {
        $phases = self::phasesDansLOrdre($source);
        $nbPoules = array_sum(array_map(fn (Phase $p) => $p->getPoules()->count(), $phases));
        $resume = sprintf('%d phase%s · %d poule%s', \count($phases), \count($phases) > 1 ? 's' : '', $nbPoules, $nbPoules > 1 ? 's' : '');

        $phaseRegles = null;
        foreach ($phases as $phase) {
            foreach ($phase->getReglesDePassage() as $regle) {
                if ($regle->versSaisonSuivante()) {
                    $phaseRegles = $phase;
                }
            }
        }
        $premiere = $phases[0] ?? null;
        $avertissement = match (true) {
            $premiere === null => null,
            $phaseRegles === null => sprintf('Cette saison ne prévoit pas de montées et descentes : les poules de « %s » seront à composer.', $premiere->getNom()),
            !$phaseRegles->getClose() => sprintf('Cette saison n\'est pas terminée (« %s » n\'est pas clôturée) : les poules de « %s » seront à composer.', $phaseRegles->getNom(), $premiere->getNom()),
            default => null,
        };

        return ['resume' => $resume, 'avertissement' => $avertissement];
    }

    /**
     * Place les équipes dans les poules de la première phase de la nouvelle saison, selon les règles « saison
     * suivante » de la saison copiée (D22) : destination = poule de même niveau, à défaut la première poule.
     *
     * @return list<string> avertissements
     */
    private function preRemplir(Saison $source, Saison $saison): array
    {
        $phaseRegles = null;
        foreach (self::phasesDansLOrdre($source) as $phase) {
            foreach ($phase->getReglesDePassage() as $regle) {
                if ($regle->versSaisonSuivante()) {
                    $phaseRegles = $phase;
                }
            }
        }
        $premiere = self::phasesDansLOrdre($saison)[0] ?? null;
        if ($premiere === null || $premiere->getPoules()->isEmpty()) {
            return [];
        }
        if ($phaseRegles === null) {
            return [sprintf('La saison « %s » ne prévoit pas de montées et descentes pour la saison suivante : les poules de « %s » sont à composer.', $source->getNom(), $premiere->getNom())];
        }
        if (!$phaseRegles->getClose()) {
            return [sprintf('« %s » de la saison « %s » n\'est pas clôturée : les montées et descentes ne sont pas encore connues, les poules de « %s » sont à composer.', $phaseRegles->getNom(), $source->getNom(), $premiere->getNom())];
        }

        $parNiveau = [];
        foreach ($premiere->getPoules() as $poule) {
            $parNiveau[$poule->getNiveau()] ??= $poule;
        }
        $resolveur = new ResolveurPlace(new ResultatsSaison($source, $this->classementRepository));
        $avertissements = [];
        $placees = [];
        foreach ($phaseRegles->getReglesDePassage() as $regle) {
            if (!$regle->versSaisonSuivante()) {
                continue;
            }
            try {
                $equipes = $resolveur->resoudre($regle->getSource());
            } catch (NotationInvalide) {
                continue; // plage vide, ou règle inapplicable
            }
            $destination = $parNiveau[$regle->getNiveauSaisonSuivante()] ?? $premiere->getPoules()->first();
            if (!isset($parNiveau[$regle->getNiveauSaisonSuivante()])) {
                $avertissements[] = sprintf('Pas de poule de niveau %d dans « %s » : les équipes concernées sont placées dans « %s ».', $regle->getNiveauSaisonSuivante(), $premiere->getNom(), $destination->getNom());
            }
            foreach ($equipes as $equipe) {
                if (!isset($placees[spl_object_id($equipe)])) {
                    $placees[spl_object_id($equipe)] = true;
                    $destination->addEquipe($equipe);
                }
            }
        }

        return array_values(array_unique($avertissements));
    }

    /** @return list<Phase> */
    private static function phasesDansLOrdre(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => $a->getOrdre() <=> $b->getOrdre());

        return array_values($phases);
    }
}
