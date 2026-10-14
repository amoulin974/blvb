<?php

namespace App\Competition;

use App\Competition\Format\Formats;
use App\Competition\Format\Semaines;
use App\Entity\Journee;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Service\JourneeService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Journées d'une phase, étape « Journées » de la mise en place du wizard (docs/wizard-saison/ecrans-mise-en-place.md,
 * §8 à §14, D35 à D39) : état affiché (journées de chaque poule, nombre prévu pour l'effectif, problèmes) et actions
 * (générer les journées manquantes, générer ou régénérer celles d'une poule). La création elle-même est celle de
 * JourneeService, donc du générateur du format de la phase.
 */
final class MiseEnPlaceJournees
{
    public function __construct(
        private EntityManagerInterface $em,
        private Formats $formats,
        private JourneeService $journees,
    ) {
    }

    /** Phase clôturée, ou déjà des classements ou des scores saisis : ses journées ne peuvent plus être refaites ici (D34). */
    public function verrouillee(Phase $phase): bool
    {
        if ($phase->getClose()) {
            return true;
        }
        $classements = $this->em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Classement c JOIN c.poule po WHERE po.phase = :phase')
            ->setParameter('phase', $phase)->getSingleScalarResult();
        $scores = $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Partie p JOIN p.poule po WHERE po.phase = :phase AND p.nb_set_gagnant_reception IS NOT NULL')
            ->setParameter('phase', $phase)->getSingleScalarResult();

        return $classements > 0 || $scores > 0;
    }

    /**
     * @return array{
     *   poules: list<array{poule: Poule, effectif: int, attendues: int, creees: int, journees: list<Journee>, statut: string, memeQue: ?Poule, nbParties: int}>,
     *   problemes: list<array{niveau: string, texte: string, ancre: ?string, lien: ?string}>,
     *   semaines: ?array{total: int, utilisables: int, fermees: int},
     *   sansJournee: int, avecJournee: int, bloquant: bool, raison: ?string, verrouillee: bool
     * }
     */
    public function etat(Phase $phase): array
    {
        $generateur = $this->formats->pourPhase($phase);
        $championnat = $phase->getType() === PhaseType::CHAMPIONNAT;
        $premiere = $phase->getPoules()->first() ?: null;
        $fermetures = $championnat ? $phase->getSaison()->getIndisponibilites() : [];

        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [(int) $a->getNiveau(), $a->getNom()] <=> [(int) $b->getNiveau(), $b->getNom()]);

        $semaines = $championnat ? Semaines::decompte($phase) : null;
        $lignes = [];
        $problemes = [];
        $sansJournee = 0;
        $avecJournee = 0;
        $plusLongue = 0;
        $raison = null;
        foreach ($poules as $poule) {
            $effectif = \count($poule->getEquipes());
            $attendues = $generateur?->nombreJournees($poule) ?? 0;
            $journees = $poule->getJournees()->toArray();
            usort($journees, fn (Journee $a, Journee $b) => $a->getNumero() <=> $b->getNumero());
            $creees = \count($journees);
            $plusLongue = max($plusLongue, $attendues);
            $ancre = 'poule-'.$poule->getId();

            if ($creees === 0) {
                ++$sansJournee;
                $statut = $effectif < 2 ? 'impossible' : 'aucune';
                $raison ??= $effectif < 2
                    ? sprintf('%s n\'a pas assez d\'équipes (deux au moins) pour avoir des journées.', $poule->getNom())
                    : sprintf('%s n\'a aucune journée. Générez-les pour continuer.', $poule->getNom());
            } else {
                ++$avecJournee;
                $statut = $creees === $attendues ? 'ok' : 'ecart';
                if ($creees !== $attendues) {
                    $problemes[] = ['niveau' => 'warning', 'ancre' => $ancre, 'lien' => null,
                        'texte' => sprintf('%s : %d journée%s pour %d équipe%s (%d prévue%s).', $poule->getNom(), $creees, $creees > 1 ? 's' : '', $effectif, $effectif > 1 ? 's' : '', $attendues, $attendues > 1 ? 's' : '')];
                }
                $this->verifierDates($phase, $poule, $journees, $fermetures, $ancre, $problemes);
            }

            $memeQue = null;
            if ($creees > 0 && $premiere instanceof Poule && $premiere !== $poule && \count($premiere->getEquipes()) === $effectif
                && $this->dates($premiere->getJournees()->toArray()) === $this->dates($journees)) {
                $memeQue = $premiere;
            }

            $lignes[] = [
                'poule' => $poule, 'effectif' => $effectif, 'attendues' => $attendues, 'creees' => $creees,
                'journees' => $journees, 'statut' => $statut, 'memeQue' => $memeQue, 'nbParties' => \count($poule->getParties()),
            ];
        }

        if ($semaines !== null && $sansJournee > 0 && $plusLongue > $semaines['utilisables']) {
            array_unshift($problemes, ['niveau' => 'error', 'ancre' => null, 'lien' => 'phases',
                'texte' => sprintf('La phase est trop courte : il faut %d semaine%s, il n\'y en a que %d une fois les fermetures retirées. Allongez la phase ou réduisez les fermetures.', $plusLongue, $plusLongue > 1 ? 's' : '', $semaines['utilisables'])]);
        }
        // Poules sans journée : une erreur par poule, sauf quand aucune poule n'en a encore (simple information)
        if ($sansJournee > 0 && $avecJournee > 0) {
            foreach ($lignes as $l) {
                if ($l['creees'] === 0) {
                    $problemes[] = ['niveau' => 'error', 'ancre' => 'poule-'.$l['poule']->getId(), 'lien' => null,
                        'texte' => $l['statut'] === 'impossible'
                            ? sprintf('%s : pas assez d\'équipes pour avoir des journées.', $l['poule']->getNom())
                            : sprintf('%s : aucune journée pour l\'instant.', $l['poule']->getNom())];
                }
            }
        }

        return [
            'poules' => $lignes,
            'problemes' => $problemes,
            'semaines' => $semaines === null ? null : $semaines + ['fermees' => $semaines['total'] - $semaines['utilisables']],
            'sansJournee' => $sansJournee,
            'avecJournee' => $avecJournee,
            'bloquant' => $sansJournee > 0 || $lignes === [],
            'raison' => $lignes === [] ? 'Cette phase n\'a aucune poule : ajoutez-en une à l\'étape Phases.' : $raison,
            'verrouillee' => $this->verrouillee($phase),
        ];
    }

    /**
     * @param list<Journee>              $journees
     * @param iterable<mixed>            $fermetures
     * @param list<array<string, mixed>> $problemes
     */
    private function verifierDates(Phase $phase, Poule $poule, array $journees, iterable $fermetures, string $ancre, array &$problemes): void
    {
        $debut = $phase->getDateDebut();
        $fin = $phase->getDateFin();
        $derniere = $journees[\count($journees) - 1];
        if ($derniere->getDateFin() > $fin) {
            $problemes[] = ['niveau' => 'warning', 'ancre' => $ancre, 'lien' => null,
                'texte' => sprintf('La %s se termine le %s, après la fin de la phase (%s).', $poule->getNom(), $derniere->getDateFin()->format('d/m/Y'), $fin->format('d/m/Y'))];
        }
        if ($journees[0]->getDateDebut() < $debut->modify('monday this week')) {
            $problemes[] = ['niveau' => 'warning', 'ancre' => $ancre, 'lien' => null,
                'texte' => sprintf('La %s commence le %s, avant le début de la phase (%s).', $poule->getNom(), $journees[0]->getDateDebut()->format('d/m/Y'), $debut->format('d/m/Y'))];
        }
        foreach ($journees as $journee) {
            $fermeture = Semaines::fermeture($journee->getDateDebut(), $fermetures);
            if ($fermeture !== null) {
                $problemes[] = ['niveau' => 'warning', 'ancre' => $ancre, 'lien' => null,
                    'texte' => sprintf('La journée %d de la %s tombe pendant la fermeture « %s » (du %s au %s).', $journee->getNumero(), $poule->getNom(), $fermeture->getNom(), $fermeture->getDateDebut()->format('d/m/Y'), $fermeture->getDateFin()->format('d/m/Y'))];
            }
        }
    }

    /**
     * @param list<Journee> $journees
     *
     * @return list<string>
     */
    private function dates(array $journees): array
    {
        usort($journees, fn (Journee $a, Journee $b) => $a->getNumero() <=> $b->getNumero());

        return array_map(fn (Journee $j) => $j->getDateDebut()->format('Y-m-d').'/'.$j->getDateFin()->format('Y-m-d'), $journees);
    }

    /**
     * Applique une action de l'étape et rend le message de statut.
     *
     * Actions : generer-toutes (poules sans journée seulement), generer-{poule} (poule sans journée), regenerer-{poule}
     * (supprime les journées, et par cascade les matchs, puis les recrée).
     *
     * @return array{ok: bool, message: string, ancre: ?string}
     */
    public function appliquer(Phase $phase, string $action): array
    {
        if ($this->verrouillee($phase)) {
            return ['ok' => false, 'message' => 'Cette phase est lancée (scores saisis ou clôturée) : ses journées ne peuvent plus être refaites ici.', 'ancre' => null];
        }
        if ($action === 'generer-toutes') {
            $faites = [];
            $erreurs = [];
            foreach ($phase->getPoules() as $poule) {
                if (\count($poule->getJournees()) > 0) {
                    continue;
                }
                $erreur = $this->creer($poule);
                if ($erreur === null) {
                    $n = \count($poule->getJournees());
                    $faites[] = sprintf('%s : %d journée%s créée%s.', $poule->getNom(), $n, $n > 1 ? 's' : '', $n > 1 ? 's' : '');
                } else {
                    $erreurs[] = $erreur;
                }
            }
            if ($faites === [] && $erreurs === []) {
                return ['ok' => true, 'message' => 'Toutes les poules ont déjà leurs journées.', 'ancre' => null];
            }
            $message = $faites === [] ? '' : implode(' ', $faites).' ';

            return ['ok' => $erreurs === [], 'message' => trim($message.implode(' ', $erreurs)), 'ancre' => null];
        }
        if (!preg_match('/^(generer|regenerer)-(\d+)$/', $action, $m)) {
            return ['ok' => false, 'message' => 'Action inconnue : rien n\'a été modifié.', 'ancre' => null];
        }
        $poule = null;
        foreach ($phase->getPoules() as $candidate) {
            if ($candidate->getId() === (int) $m[2]) {
                $poule = $candidate;
            }
        }
        if ($poule === null) {
            return ['ok' => false, 'message' => 'Poule introuvable : rien n\'a été modifié.', 'ancre' => null];
        }
        if ($m[1] === 'generer' && \count($poule->getJournees()) > 0) {
            return ['ok' => false, 'message' => sprintf('%s a déjà ses journées : utilisez « Régénérer les journées » pour les refaire.', $poule->getNom()), 'ancre' => 'poule-'.$poule->getId()];
        }
        $erreur = $this->creer($poule);
        if ($erreur !== null) {
            return ['ok' => false, 'message' => $erreur, 'ancre' => 'poule-'.$poule->getId()];
        }
        $n = \count($poule->getJournees());

        return ['ok' => true, 'message' => sprintf('%d journée%s créée%s pour %s.', $n, $n > 1 ? 's' : '', $n > 1 ? 's' : '', $poule->getNom()), 'ancre' => 'poule-'.$poule->getId()];
    }

    /** Crée les journées de la poule ; en cas d'erreur du générateur, ne laisse aucune journée partielle. */
    private function creer(Poule $poule): ?string
    {
        // Faisabilité vérifiée avant de supprimer quoi que ce soit : un échec ne doit pas faire perdre les journées
        // (ni leurs matchs) existantes. Les dates d'une poule de même effectif que la première sont recopiées.
        $phase = $poule->getPhase();
        $premiere = $phase->getPoules()->first() ?: null;
        $copie = $premiere instanceof Poule && $premiere !== $poule && \count($premiere->getJournees()) > 0
            && \count($premiere->getEquipes()) === \count($poule->getEquipes());
        if ($phase->getType() === PhaseType::CHAMPIONNAT && !$copie) {
            $attendues = $this->formats->pourPhase($phase)?->nombreJournees($poule) ?? 0;
            $utilisables = Semaines::decompte($phase)['utilisables'];
            if ($attendues > $utilisables) {
                return sprintf('%s : la phase est trop courte : il faut %d semaine%s, il n\'y en a que %d une fois les fermetures retirées. Allongez la phase ou réduisez les fermetures.%s',
                    $poule->getNom(), $attendues, $attendues > 1 ? 's' : '', $utilisables,
                    \count($poule->getJournees()) > 0 ? ' Les journées actuelles sont conservées.' : '');
            }
        }

        $erreur =$this->journees->creerJournees($poule);
        if ($erreur === null) {
            return null;
        }
        foreach ($poule->getJournees()->toArray() as $journee) {
            $this->em->remove($journee);
        }
        $poule->getJournees()->clear();
        $this->em->flush();

        return sprintf('%s : %s', $poule->getNom(), $erreur);
    }
}
