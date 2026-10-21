<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\RegleDePassage;
use App\Entity\Saison;
use App\Enum\PhaseType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ajoute à une saison déjà créée les barrages du modèle « BLVB avec barrages » (ModeleBlvbAvecBarrages, D44, D50, D53),
 * pour une saison créée sans eux (commande app:saison:ajouter-barrages) :
 *  - entre les deux phases de championnat : une phase « Barrages », un match par paire de poules voisines (avant-dernier
 *    du haut contre 2e du bas, l'équipe de la poule supérieure reçoit) ; les passages de la première phase sont remplacés
 *    par ceux du modèle (1er monte, 2e et avant-dernier en barrage, dernier descend), le vainqueur joue la seconde phase en
 *    haut, le perdant en bas. Possible tant que la première phase n'est pas clôturée et que la seconde n'a pas commencé ;
 *  - après la phase finale : « Barrages de fin de saison » (mêmes dates que la phase finale), alimentés par la dernière
 *    phase de championnat (avant-dernier et 2e de la poule du dessous), sans effet sur la phase finale ni passage vers la
 *    saison suivante (D50). Possible tant que cette phase n'est pas clôturée.
 * Les entités sont modifiées sans être enregistrées : l'appelant décide (flush) ou abandonne (simulation).
 */
final class AjoutBarrages
{
    /** Règles à écrire une fois les codes attribués : [phase source, source sans le code de phase, poule de destination] */
    /** @var list<array{0: Phase, 1: string, 2: Poule}> */
    private array $aEcrire = [];

    public function __construct(private ModeAjout $modeAjout, private EntityManagerInterface $em)
    {
    }

    /** @return array{actions: list<string>, refus: list<string>, avertissements: list<string>} */
    public function ajouter(Saison $saison, bool $entre, bool $fin): array
    {
        $resultat = ['actions' => [], 'refus' => [], 'avertissements' => []];
        if ($entre) {
            $this->entrePhases($saison, $resultat);
        }
        if ($fin) {
            $this->finDeSaison($saison, $resultat);
        }
        if ($resultat['actions'] !== []) {
            CodesCompetition::attribuer($saison);
            $this->ecrireRegles($saison, $resultat);
        }

        return $resultat;
    }

    private function entrePhases(Saison $saison, array &$resultat): void
    {
        $phases = self::phases($saison);
        $championnats = array_values(array_filter($phases, fn (Phase $p) => $p->getType() === PhaseType::CHAMPIONNAT));
        if (\count($championnats) < 2) {
            $resultat['refus'][] = 'Barrages entre phases : la saison n\'a pas deux phases de championnat.';

            return;
        }
        [$phase1, $phase2] = $championnats;
        foreach ($phases as $p) {
            if ($p->getType() === PhaseType::BARRAGE && $p->getOrdre() > $phase1->getOrdre() && $p->getOrdre() < $phase2->getOrdre()) {
                $resultat['refus'][] = sprintf('Barrages entre phases : « %s » existe déjà entre « %s » et « %s ».', $p->getNom(), $phase1->getNom(), $phase2->getNom());

                return;
            }
        }
        if ($phase1->getClose()) {
            $resultat['refus'][] = sprintf('Barrages entre phases : « %s » est clôturée, ses équipes sont déjà réparties.', $phase1->getNom());

            return;
        }
        if ($this->modeAjout->commencee($phase2)) {
            $resultat['refus'][] = sprintf('Barrages entre phases : « %s » a commencé (équipes placées ou matchs) : on ne place plus de barrages avant elle.', $phase2->getNom());

            return;
        }
        $poules1 = self::parNiveau($phase1);
        $poules2 = self::parNiveau($phase2);
        if (\count($poules1) < 2 || \count($poules1) !== \count($poules2)) {
            $resultat['refus'][] = sprintf('Barrages entre phases : « %s » et « %s » n\'ont pas le même nombre de poules (au moins deux).', $phase1->getNom(), $phase2->getNom());

            return;
        }

        // Phase de barrages à la place de la phase 2 dans l'ordre, la semaine qui suit la fin de la phase 1
        foreach ($phases as $p) {
            if ($p->getOrdre() >= $phase2->getOrdre()) {
                $p->setOrdre($p->getOrdre() + 1);
            }
        }
        $debut = $phase1->getDatefin()->modify('+1 day');
        $barrage = (new Phase())->setNom('Barrages')->setType(PhaseType::BARRAGE)->setOrdre($phase2->getOrdre() - 1)->setClose(0)
            ->setDateDebut($debut)->setDateFin($debut->modify('+6 days'))->setOptions(['reception' => Phase::RECEPTION_NIVEAU_SUPERIEUR]);
        $saison->addPhase($barrage);
        $this->em->persist($barrage);
        $matchs = $this->matchs($barrage, $poules1, 'Barrage');
        if ($phase2->getDatedebut() <= $barrage->getDatefin()) {
            $resultat['avertissements'][] = sprintf('Les barrages (du %s au %s) chevauchent le début de « %s » (%s) : ajustez les dates dans « Modifier la phase » si besoin.',
                $debut->format('d/m/Y'), $barrage->getDatefin()->format('d/m/Y'), $phase2->getNom(), $phase2->getDatedebut()->format('d/m/Y'));
        }

        // Passages de la phase 1 remplacés par ceux du modèle ; issues des barrages vers la phase 2
        foreach ($phase1->getReglesDePassage()->toArray() as $regle) {
            $phase1->removeRegleDePassage($regle);
        }
        $dernier = \count($poules1) - 1;
        foreach ($poules1 as $i => $poule) {
            $l = $poule->getCode();
            $haut = $i > 0;
            $bas = $i < $dernier;
            $this->aEcrire[] = [$phase1, "$l#1", $poules2[$haut ? $i - 1 : $i]];
            if ($haut) {
                $this->aEcrire[] = [$phase1, "$l#2", $matchs[$i - 1]];
            }
            $this->aEcrire[] = [$phase1, "$l#".($haut ? '3' : '2').'..'.($bas ? '-3' : '-2'), $poules2[$i]];
            if ($bas) {
                $this->aEcrire[] = [$phase1, "$l#-2", $matchs[$i]];
                $this->aEcrire[] = [$phase1, "$l#-1", $poules2[$i + 1]];
            } else {
                $this->aEcrire[] = [$phase1, "$l#-1", $poules2[$i]];
            }
        }
        foreach ($matchs as $i => $match) {
            $this->aEcrire[] = [$barrage, $match->getCode().'.vainqueur', $poules2[$i]];
            $this->aEcrire[] = [$barrage, $match->getCode().'.perdant', $poules2[$i + 1]];
        }
        $resultat['actions'][] = sprintf('Phase « Barrages » ajoutée entre « %s » et « %s », du %s au %s : %s.', $phase1->getNom(), $phase2->getNom(),
            $debut->format('d/m/Y'), $barrage->getDatefin()->format('d/m/Y'), implode(', ', array_map(fn (Poule $m) => $m->getNom(), $matchs)));
        $resultat['actions'][] = sprintf('Passages de « %s » remplacés par ceux du modèle (1er monte, 2e et avant-dernier en barrage, dernier descend).', $phase1->getNom());
    }

    private function finDeSaison(Saison $saison, array &$resultat): void
    {
        $phases = self::phases($saison);
        $finales = array_values(array_filter($phases, fn (Phase $p) => $p->getType() === PhaseType::FINALE));
        if ($finales === []) {
            $resultat['refus'][] = 'Barrages de fin de saison : la saison n\'a pas de phase finale.';

            return;
        }
        $finale = $finales[\count($finales) - 1];
        $source = null;
        foreach ($phases as $p) {
            if ($p->getOrdre() > $finale->getOrdre() && $p->getType() === PhaseType::BARRAGE) {
                $resultat['refus'][] = sprintf('Barrages de fin de saison : « %s » existe déjà après « %s ».', $p->getNom(), $finale->getNom());

                return;
            }
            if ($p->getOrdre() < $finale->getOrdre() && $p->getType() === PhaseType::CHAMPIONNAT) {
                $source = $p;
            }
        }
        if ($source === null) {
            $resultat['refus'][] = 'Barrages de fin de saison : aucune phase de championnat avant la phase finale.';

            return;
        }
        if ($source->getClose()) {
            $resultat['refus'][] = sprintf('Barrages de fin de saison : « %s » est clôturée, ses équipes sont déjà réparties.', $source->getNom());

            return;
        }
        $poules = self::parNiveau($source);
        if (\count($poules) < 2) {
            $resultat['refus'][] = sprintf('Barrages de fin de saison : « %s » n\'a qu\'une poule.', $source->getNom());

            return;
        }

        $ordre = max(array_map(fn (Phase $p) => $p->getOrdre(), $phases)) + 1;
        $barrage = (new Phase())->setNom('Barrages de fin de saison')->setType(PhaseType::BARRAGE)->setOrdre($ordre)->setClose(0)
            ->setDateDebut($finale->getDatedebut())->setDateFin($finale->getDatefin())->setOptions(['reception' => Phase::RECEPTION_NIVEAU_SUPERIEUR]);
        $saison->addPhase($barrage);
        $this->em->persist($barrage);
        $matchs = $this->matchs($barrage, $poules, 'Barrage de fin de saison');

        // Avant-dernier d'une poule et 2e de la poule du dessous ; les passages vers la phase finale ne changent pas
        foreach ($poules as $i => $poule) {
            $l = $poule->getCode();
            if ($i > 0) {
                $this->aEcrire[] = [$source, "$l#2", $matchs[$i - 1]];
            }
            if ($i < \count($poules) - 1) {
                $this->aEcrire[] = [$source, "$l#-2", $matchs[$i]];
            }
        }
        $resultat['actions'][] = sprintf('Phase « Barrages de fin de saison » ajoutée après « %s » (mêmes dates : du %s au %s) : %s.', $finale->getNom(),
            $finale->getDatedebut()->format('d/m/Y'), $finale->getDatefin()->format('d/m/Y'), implode(', ', array_map(fn (Poule $m) => $m->getNom(), $matchs)));
        $resultat['actions'][] = sprintf('Passages ajoutés à « %s » : l\'avant-dernier de chaque poule et le 2e de la poule du dessous jouent un barrage de fin de saison ; passages vers « %s » inchangés.', $source->getNom(), $finale->getNom());
        $suivante = array_filter($source->getReglesDePassage()->toArray(), fn (RegleDePassage $r) => $r->versSaisonSuivante());
        if ($suivante !== []) {
            $resultat['avertissements'][] = sprintf('« %s » garde %d passage%s vers la saison suivante : avec des barrages de fin de saison, le modèle n\'en a pas (poules de la saison suivante remplies à la main, D50) ; retirez-les à l\'étape Passages si besoin.',
                $source->getNom(), \count($suivante), \count($suivante) > 1 ? 's' : '');
        }
    }

    /**
     * Un match (poule de deux équipes) par paire de poules voisines : « Barrage A / B », code AB, niveau de la poule du haut
     *
     * @param list<Poule> $poules par niveau
     * @return list<Poule>
     */
    private function matchs(Phase $barrage, array $poules, string $prefixe): array
    {
        $matchs = [];
        for ($i = 0; $i < \count($poules) - 1; ++$i) {
            [$haut, $bas] = [$poules[$i], $poules[$i + 1]];
            $match = (new Poule())->setNom(sprintf('%s %s / %s', $prefixe, $haut->getCode(), $bas->getCode()))
                ->setCode($haut->getCode().$bas->getCode())->setNiveau($haut->getNiveau())->setNbEquipesPrevu(2);
            $barrage->addPoule($match);
            $this->em->persist($match);
            $matchs[] = $match;
        }

        return $matchs;
    }

    /** Règles écrites avec les codes des phases (attribués après l'ajout des phases), à la suite des règles existantes */
    private function ecrireRegles(Saison $saison, array &$resultat): void
    {
        foreach ($this->aEcrire as [$phase, $source, $destination]) {
            $ordre = 0;
            foreach ($phase->getReglesDePassage() as $regle) {
                $ordre = max($ordre, $regle->getOrdre());
            }
            $phase->addRegleDePassage((new RegleDePassage())->setOrdre($ordre + 1)
                ->setSource($phase->getCode().'.'.$source)->setPouleDestination($destination));
        }
        $this->aEcrire = [];
        foreach (self::phases($saison) as $phase) {
            foreach (VerificationPassages::verifier($phase, $phase->getReglesDePassage()) as $probleme) {
                $resultat['avertissements'][] = sprintf('« %s » : %s', $phase->getNom(), $probleme);
            }
        }
    }

    /** @return list<Phase> dans l'ordre */
    private static function phases(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return $phases;
    }

    /** @return list<Poule> */
    private static function parNiveau(Phase $phase): array
    {
        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [$a->getNiveau() ?? PHP_INT_MAX, (string) $a->getCode()] <=> [$b->getNiveau() ?? PHP_INT_MAX, (string) $b->getCode()]);

        return array_values($poules);
    }
}
