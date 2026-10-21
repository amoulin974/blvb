<?php

namespace App\Competition;

use App\Entity\Equipe;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Reglement\Texte;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Composition des poules d'une phase, étape « Poules » de la mise en place du wizard (docs/wizard-saison/
 * archive/ecrans-mise-en-place.md, D35 à D38) : état affiché (poules, origine de chaque équipe, équipes à placer, problèmes) et
 * actions (déplacer, retirer, placer, ajouter).
 *
 * Une équipe n'a pas de lien direct avec une saison : elle « est dans la saison » parce qu'elle figure dans une poule.
 * L'origine d'une équipe se déduit donc de sa poule la plus récente avant cette phase (phase précédente de la saison,
 * à défaut dernière saison antérieure).
 */
final class CompositionPoules
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** Phases d'une saison dans l'ordre. @return list<Phase> */
    public static function phases(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return array_values($phases);
    }

    /** Première phase non clôturée : celle dont les poules sont à composer. */
    public static function phaseACompleter(Saison $saison): ?Phase
    {
        foreach (self::phases($saison) as $phase) {
            if (!$phase->getClose()) {
                return $phase;
            }
        }

        return null;
    }

    /** Phase clôturée, ou déjà des matchs ou des classements : la composition ne se modifie plus (D34). */
    public function verrouillee(Phase $phase): bool
    {
        if ($phase->getClose()) {
            return true;
        }
        $parties = $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Partie p JOIN p.poule po WHERE po.phase = :phase')
            ->setParameter('phase', $phase)->getSingleScalarResult();
        $classements = $this->em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Classement c JOIN c.poule po WHERE po.phase = :phase AND (c.points <> 0 OR c.setGagnes > 0 OR c.setPerdus > 0)')
            ->setParameter('phase', $phase)->getSingleScalarResult();

        return $parties > 0 || $classements > 0;
    }

    /**
     * @return array{
     *   poules: list<array{poule: Poule, equipes: list<array{equipe: Equipe, origine: array{type: string, texte: string}}>, effectif: int, prevu: ?int, ecart: bool}>,
     *   aPlacer: list<array{equipe: Equipe, origine: array{type: string, texte: string}}>,
     *   autres: list<Equipe>,
     *   problemes: list<array{niveau: string, texte: string, ancre: ?string}>,
     *   placees: int, prevues: ?int, bloquant: bool, raison: ?string, verrouillee: bool
     * }
     */
    public function etat(Phase $phase): array
    {
        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [(int) $a->getNiveau(), $a->getNom()] <=> [(int) $b->getNiveau(), $b->getNom()]);

        $placees = [];
        foreach ($poules as $poule) {
            foreach ($poule->getEquipes() as $equipe) {
                $placees[$equipe->getId()] = $equipe;
            }
        }
        // Un barrage ne réunit qu'une partie des équipes de la phase précédente : les autres n'y jouent pas, elles ne sont pas « à placer »
        $candidates = $phase->getType() === PhaseType::BARRAGE ? [] : array_diff_key($this->equipesDeReference($phase), $placees);
        $tous = $this->em->getRepository(Equipe::class)->findBy([], ['nom' => 'ASC']);
        $autres = array_values(array_filter($tous, fn (Equipe $e) => !isset($placees[$e->getId()]) && !isset($candidates[$e->getId()])));

        $origines = $this->origines($phase, $placees + $candidates);

        $lignes = [];
        $problemes = [];
        $bloquant = false;
        $raison = null;
        $prevues = null;
        foreach ($poules as $poule) {
            $equipes = $poule->getEquipes()->toArray();
            usort($equipes, fn (Equipe $a, Equipe $b) => strcasecmp((string) $a->getNom(), (string) $b->getNom()));
            $effectif = \count($equipes);
            $prevu = $poule->getNbEquipesPrevu();
            $prevues = $prevu === null ? $prevues : ($prevues ?? 0) + $prevu;
            $ecart = $prevu !== null && $prevu !== $effectif;
            $lignes[] = [
                'poule' => $poule,
                'equipes' => array_map(fn (Equipe $e) => ['equipe' => $e, 'origine' => $this->origine($origines[$e->getId()] ?? null, $poule)], $equipes),
                'effectif' => $effectif,
                'prevu' => $prevu,
                'ecart' => $ecart,
            ];
            if ($effectif === 0) {
                $bloquant = true;
                $texte = sprintf('%s n\'a aucune équipe. Ajoutez-en une pour continuer.', Texte::lePoule($poule));
                $raison ??= $texte;
                $problemes[] = ['niveau' => 'error', 'texte' => $texte, 'ancre' => 'poule-'.$poule->getId()];
            } elseif ($ecart) {
                $problemes[] = ['niveau' => 'warning', 'texte' => sprintf('%s : %d équipe%s pour %d prévue%s.', $poule->getNom(), $effectif, $effectif > 1 ? 's' : '', $prevu, $prevu > 1 ? 's' : ''), 'ancre' => 'poule-'.$poule->getId()];
            }
        }

        $aPlacer = [];
        foreach ($candidates as $equipe) {
            $aPlacer[] = ['equipe' => $equipe, 'origine' => $this->origine($origines[$equipe->getId()] ?? null, null)];
        }
        usort($aPlacer, fn ($a, $b) => strcasecmp((string) $a['equipe']->getNom(), (string) $b['equipe']->getNom()));
        if ($aPlacer !== []) {
            $n = \count($aPlacer);
            array_unshift($problemes, ['niveau' => 'warning', 'texte' => sprintf('%d équipe%s ne %s dans aucune poule.', $n, $n > 1 ? 's' : '', $n > 1 ? 'sont' : 'est'), 'ancre' => 'a-placer']);
        }

        return [
            'poules' => $lignes,
            'aPlacer' => $aPlacer,
            'autres' => $autres,
            'problemes' => $problemes,
            'placees' => \count($placees),
            'prevues' => $prevues,
            'bloquant' => $bloquant,
            'raison' => $raison,
            // Au moins une équipe placée vient d'une poule précédente : composition pré-remplie, à vérifier
            'copie' => array_filter($lignes, fn (array $l) => array_filter($l['equipes'], fn (array $e) => $e['origine']['type'] !== 'nouvelle') !== []) !== [],
            'verrouillee' => $this->verrouillee($phase),
        ];
    }

    /**
     * Applique une action de l'étape et rend le message de statut.
     *
     * Actions : deplacer-{équipe} et placer-{équipe} (poule choisie dans $vers[{équipe}]), retirer-{équipe},
     * ajouter-{poule} (équipe choisie dans $ajout[{poule}]).
     *
     * @param array<int|string, mixed> $vers
     * @param array<int|string, mixed> $ajout
     *
     * @return array{ok: bool, message: string, ancre: ?string}
     */
    public function appliquer(Phase $phase, string $action, array $vers, array $ajout): array
    {
        if ($this->verrouillee($phase)) {
            return ['ok' => false, 'message' => 'Cette phase est lancée (matchs programmés ou clôturée) : sa composition ne se modifie plus ici.', 'ancre' => null];
        }
        if (!preg_match('/^(deplacer|placer|retirer|ajouter)-(\d+)$/', $action, $m)) {
            return ['ok' => false, 'message' => 'Action inconnue : rien n\'a été modifié.', 'ancre' => null];
        }
        $id = (int) $m[2];

        if ($m[1] === 'ajouter') {
            $poule = $this->pouleDeLaPhase($phase, $id);
            $equipe = $this->equipe($ajout[$id] ?? null);
            if ($poule === null || $equipe === null) {
                return ['ok' => false, 'message' => 'Choisissez l\'équipe à ajouter.', 'ancre' => $poule ? 'poule-'.$poule->getId() : null];
            }

            return $this->mettre($phase, $equipe, $poule);
        }

        $equipe = $this->equipe($id);
        if ($equipe === null) {
            return ['ok' => false, 'message' => 'Équipe introuvable : rien n\'a été modifié.', 'ancre' => null];
        }
        if ($m[1] === 'retirer') {
            $ancienne = $this->pouleDe($phase, $equipe);
            if ($ancienne === null) {
                return ['ok' => false, 'message' => sprintf('%s n\'est dans aucune poule de cette phase.', $equipe->getNom()), 'ancre' => null];
            }
            $ancienne->removeEquipe($equipe);
            $this->em->flush();

            return ['ok' => true, 'message' => sprintf('%s est retirée de « %s » : elle revient dans « Équipes à placer ».', $equipe->getNom(), $ancienne->getNom()), 'ancre' => 'poule-'.$ancienne->getId()];
        }

        $cible = $this->pouleDeLaPhase($phase, (int) ($vers[$id] ?? 0));
        if ($cible === null) {
            return ['ok' => false, 'message' => sprintf('Choisissez la poule où placer %s.', $equipe->getNom()), 'ancre' => null];
        }

        return $this->mettre($phase, $equipe, $cible);
    }

    /** Place l'équipe dans la poule, après l'avoir retirée de sa poule actuelle de la phase (une seule poule par phase). */
    private function mettre(Phase $phase, Equipe $equipe, Poule $cible): array
    {
        $ancienne = $this->pouleDe($phase, $equipe);
        if ($ancienne === $cible) {
            return ['ok' => true, 'message' => sprintf('%s est déjà dans « %s ».', $equipe->getNom(), $cible->getNom()), 'ancre' => 'poule-'.$cible->getId()];
        }
        $ancienne?->removeEquipe($equipe);
        $cible->addEquipe($equipe);
        $this->em->flush();

        return ['ok' => true, 'message' => sprintf('%s est dans « %s ».', $equipe->getNom(), $cible->getNom()), 'ancre' => 'poule-'.$cible->getId()];
    }

    private function pouleDeLaPhase(Phase $phase, int $id): ?Poule
    {
        foreach ($phase->getPoules() as $poule) {
            if ($poule->getId() === $id) {
                return $poule;
            }
        }

        return null;
    }

    private function pouleDe(Phase $phase, Equipe $equipe): ?Poule
    {
        foreach ($phase->getPoules() as $poule) {
            if ($poule->getEquipes()->contains($equipe)) {
                return $poule;
            }
        }

        return null;
    }

    private function equipe(mixed $id): ?Equipe
    {
        return \is_int($id) || (\is_string($id) && ctype_digit($id)) ? $this->em->find(Equipe::class, (int) $id) : null;
    }

    /**
     * Équipes qui jouaient juste avant : celles de la phase précédente de la saison, à défaut celles de la dernière
     * saison antérieure.
     *
     * @return array<int, Equipe> par identifiant
     */
    private function equipesDeReference(Phase $phase): array
    {
        $phases = $this->phasesDeReference($phase);
        if ($phases === []) {
            return [];
        }
        $equipes = [];
        foreach ($this->em->createQuery('SELECT DISTINCT e FROM App\Entity\Equipe e JOIN e.Poules po WHERE po.phase IN (:phases)')
            ->setParameter('phases', $phases)->getResult() as $equipe) {
            $equipes[$equipe->getId()] = $equipe;
        }

        return $equipes;
    }

    /** @return list<Phase> */
    private function phasesDeReference(Phase $phase): array
    {
        $saison = $phase->getSaison();
        $precedente = null;
        foreach (self::phases($saison) as $candidate) {
            if ($candidate === $phase) {
                break;
            }
            $precedente = $candidate;
        }
        if ($precedente !== null) {
            return [$precedente];
        }
        $anterieure = $this->em->createQuery('SELECT s FROM App\Entity\Saison s WHERE s.brouillon = false AND s.date_debut < :debut ORDER BY s.date_debut DESC')
            ->setParameter('debut', $saison->getDateDebut())->setMaxResults(1)->getOneOrNullResult();

        return $anterieure instanceof Saison ? array_values($anterieure->getPhases()->toArray()) : [];
    }

    /**
     * Poule la plus récente de chaque équipe avant cette phase.
     *
     * @param array<int, Equipe> $equipes
     *
     * @return array<int, Poule>
     */
    private function origines(Phase $phase, array $equipes): array
    {
        if ($equipes === []) {
            return [];
        }
        $saison = $phase->getSaison();
        $lignes = $this->em->createQuery(
            'SELECT e.id AS eid, po.id AS pid FROM App\Entity\Poule po JOIN po.equipes e JOIN po.phase ph JOIN ph.saison s
             WHERE e.id IN (:ids) AND ((s = :saison AND ph.ordre < :ordre) OR (s.brouillon = false AND s.date_debut < :debut))
             ORDER BY s.date_debut DESC, ph.ordre DESC, po.id DESC'
        )->setParameters(['ids' => array_keys($equipes), 'saison' => $saison, 'ordre' => $phase->getOrdre(), 'debut' => $saison->getDateDebut()])
            ->getResult();

        $origines = [];
        foreach ($lignes as $ligne) {
            // Premier résultat de chaque équipe = poule la plus récente (tri de la requête)
            $origines[$ligne['eid']] ??= $this->em->find(Poule::class, $ligne['pid']);
        }

        return $origines;
    }

    /** @return array{type: string, texte: string} */
    private function origine(?Poule $avant, ?Poule $maintenant): array
    {
        if ($avant === null) {
            return ['type' => 'nouvelle', 'texte' => 'Nouvelle équipe'];
        }
        if ($maintenant === null) {
            return ['type' => 'avant', 'texte' => sprintf('Jouait en %s', $avant->getNom())];
        }
        // Barrage : l'équipe y vient de sa poule (montée et descente se décident au barrage)
        if ($maintenant->getPhase()?->getType() === PhaseType::BARRAGE) {
            return ['type' => 'barrage', 'texte' => 'Vient '.Texte::dePoule($avant)];
        }
        $niveauAvant = (int) $avant->getNiveau();
        $niveauMaintenant = (int) $maintenant->getNiveau();

        return match (true) {
            $niveauAvant > $niveauMaintenant => ['type' => 'montee', 'texte' => sprintf('Montée depuis %s', $avant->getNom())],
            $niveauAvant < $niveauMaintenant => ['type' => 'descente', 'texte' => sprintf('Descente depuis %s', $avant->getNom())],
            default => ['type' => 'reste', 'texte' => sprintf('Reste en %s', $maintenant->getNom())],
        };
    }
}
