<?php
// src/Service/PhaseService.php
namespace App\Service;

use App\Competition\CodesCompetition;
use App\Competition\NotationInvalide;
use App\Competition\ReglesParDefaut;
use App\Competition\ResolveurPlace;
use App\Competition\ResultatsSaison;
use App\Competition\VerificationPassages;
use App\Entity\Phase;
use App\Entity\RegleDePassage;
use App\Enum\PhaseType;
use App\Repository\ClassementRepository;
use App\Service\ClassementService;
use Doctrine\ORM\EntityManagerInterface;

class PhaseService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClassementService $classementService,
        private ClassementRepository $classementRepository,
    ) {}

    /** Phase qui suit dans la saison : le plus petit ordre supérieur à celui de la phase (null si c'est la dernière). */
    public function phaseSuivante(Phase $phase): ?Phase
    {
        $suivante = null;
        foreach ($phase->getSaison()->getPhases() as $p) {
            if ($p->getOrdre() > $phase->getOrdre() && ($suivante === null || $p->getOrdre() < $suivante->getOrdre())) {
                $suivante = $p;
            }
        }

        return $suivante;
    }

    /**
     * Règles de passage de la phase : celles enregistrées, ou à défaut celles du schéma 2025 calculées à la volée
     * (docs/wizard-saison/decisions.md, D26).
     *
     * @return list<RegleDePassage>
     */
    public function reglesDePassage(Phase $phase): array
    {
        CodesCompetition::attribuer($phase->getSaison());
        $regles = $phase->getReglesDePassage()->toArray();
        if ($regles !== []) {
            return array_values($regles);
        }

        // Une phase de barrages, ou celle qui l'alimente, n'a pas de règles « par défaut » : les passages sont à saisir
        // (étape Passages du wizard) ; sans règle, la clôture signale les équipes sans destination (D24)
        $suivante = $this->phaseSuivante($phase);
        if ($phase->getType() === PhaseType::BARRAGE || $suivante?->getType() === PhaseType::BARRAGE) {
            return [];
        }

        return ReglesParDefaut::pourPhase($phase, $suivante);
    }

    /**
     * Une phase finale sans règle de passage ne transmet pas d'équipes (D50) : elle se clôture sans rien répartir, même
     * si une autre phase (barrages de fin de saison) la suit.
     */
    public function estTerminale(Phase $phase): bool
    {
        return $phase->getType() === PhaseType::FINALE && $phase->getReglesDePassage()->isEmpty();
    }

    /**
     * Une phase se clôture si elle a au moins une poule, que chaque poule a ses journées (sans quoi son classement n'a
     * pas de sens) et que les phases qui la précèdent sont clôturées.
     */
    public function estCloturable(Phase $phase): bool
    {
        if ($phase->getClose() || $phase->getPoules()->isEmpty()) {
            return false;
        }
        foreach ($phase->getPoules() as $poule) {
            if ($poule->getJournees()->isEmpty()) {
                return false;
            }
        }
        foreach ($phase->getSaison()->getPhases() as $autre) {
            if ($autre !== $phase && $autre->getOrdre() < $phase->getOrdre() && !$autre->getClose()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Problèmes que les règles de passage de la phase poseraient à la clôture (règles par défaut comprises), à afficher
     * AVANT de cliquer sur « Clôturer » (D37). Ne modifie rien.
     *
     * @return list<string>
     */
    public function problemesAvantCloture(Phase $phase): array
    {
        if ($this->estTerminale($phase)) {
            return [];
        }

        return array_values(VerificationPassages::verifier($phase, $this->reglesDePassage($phase)));
    }

    /**
     * Clôture la phase : les équipes passent dans les poules de la phase suivante selon les règles de passage, puis la
     * phase est marquée close. Sans effet sur une phase déjà close ou sans phase suivante.
     *
     * Les règles s'appliquent dans l'ordre ; une équipe déjà placée ne l'est pas une seconde fois ; une règle impossible
     * à appliquer est ignorée. La clôture se fait toujours ; les problèmes sont rendus en avertissements (D24).
     * Les règles vers la saison suivante sont seulement vérifiées : le wizard les appliquera (D22).
     *
     * @return list<string> avertissements, liste vide si tout s'est bien passé
     */
    public function cloturerEtBasculer(Phase $phaseActuelle): array
    {
        if ($phaseActuelle->getClose()) {
            return []; // déjà clôturée : ne jamais redistribuer les équipes une seconde fois
        }

        if ($this->estTerminale($phaseActuelle)) {
            $phaseActuelle->setClose(1);
            $this->em->flush();

            return [];
        }

        $phaseSuivante = $this->phaseSuivante($phaseActuelle);
        if (!$phaseSuivante) {
            return []; // Pas de phase suivante, fin de saison
        }

        // Classements à jour avant de les lire (une poule sans score saisi n'a pas encore de ligne de classement)
        foreach ($phaseActuelle->getPoules() as $poule) {
            $this->classementService->mettreAJourClassementPoule($poule);
        }

        $regles = $this->reglesDePassage($phaseActuelle);
        $problemesRegles = VerificationPassages::verifier($phaseActuelle, $regles);

        $resolveur = new ResolveurPlace(new ResultatsSaison($phaseActuelle->getSaison(), $this->classementRepository));
        $placees = [];    // équipe et phase d'arrivée : une équipe peut aller dans deux phases (D50), jamais deux fois dans la même
        $avecPoule = [];  // équipes placées au moins une fois
        foreach ($regles as $regle) {
            $destination = $regle->getPouleDestination();
            if ($destination === null) {
                continue; // destination dans la saison suivante (D22)
            }
            try {
                $equipes = $resolveur->resoudre($regle->getSource());
            } catch (NotationInvalide) {
                continue; // plage vide (aucune équipe à ces rangs), ou règle inapplicable : déjà signalée par la vérification
            }
            foreach ($equipes as $equipe) {
                $cle = spl_object_id($equipe).'|'.($destination->getPhase()?->getId() ?? 0);
                if (isset($placees[$cle])) {
                    continue;
                }
                $placees[$cle] = true;
                $avecPoule[spl_object_id($equipe)] = true;
                $destination->addEquipe($equipe);
            }
        }

        $sansDestination = [];
        foreach ($phaseActuelle->getPoules() as $poule) {
            foreach ($poule->getEquipes() as $equipe) {
                if (!isset($avecPoule[spl_object_id($equipe)])) {
                    $sansDestination[] = $equipe->getNom();
                }
            }
        }
        // Les équipes sans poule d'abord : c'est ce que l'administrateur peut corriger lui-même
        $avertissements = [];
        if ($sansDestination !== []) {
            $sansDestination = array_values(array_unique($sansDestination));
            sort($sansDestination);
            $avertissements[] = sprintf(\count($sansDestination) > 1 ? 'Équipes sans poule dans la phase suivante : %s.' : 'Équipe sans poule dans la phase suivante : %s.', implode(', ', $sansDestination));
        }
        $avertissements = array_merge($avertissements, $problemesRegles);

        // Initialiser les classements de la phase suivante (à 0 point)
        $this->em->flush();
        foreach ($phaseSuivante->getPoules() as $pNext) {
            $this->classementService->mettreAJourClassementPoule($pNext);
        }

        // Clôture de la phase actuelle
        $phaseActuelle->setClose(1);
        $this->em->flush();

        return array_values(array_unique($avertissements));
    }
}
