<?php

namespace App\Competition;

use App\Competition\Format\Formats;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Service\CalendrierAnalyseService;
use App\Service\OptimisationService;
use App\Service\PartieService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Matchs d'une phase, étape « Matchs » de la mise en place du wizard (docs/wizard-saison/ecrans-mise-en-place.md,
 * §15 à §21, D35 à D40) : état affiché (matchs créés et prévus, déplacements de suite, surcharges de gymnase, équipes
 * sans gymnase) recalculé à partir des matchs existants, et actions (générer la phase, générer ou régénérer une poule).
 * La génération elle-même est celle de PartieService (optimiseur pour le championnat).
 */
final class MiseEnPlaceMatchs
{
    public function __construct(
        private EntityManagerInterface $em,
        private Formats $formats,
        private PartieService $parties,
        private OptimisationService $optimisation,
        private CalendrierAnalyseService $analyse,
        private MiseEnPlaceJournees $journees,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *   poules: list<array{poule: Poule, effectif: int, prevus: int, crees: int, deplacements: ?int, statut: string, sansGymnase: list<Equipe>}>,
     *   problemes: list<array{niveau: string, texte: string, ancre: ?string, lien: ?string}>,
     *   surcharges: list<string>,
     *   crees: int, prevus: int, sansMatch: int, avecMatch: int, bloquant: bool, raison: ?string, verrouillee: bool,
     *   journeesManquantes: bool, elimination: bool
     * }
     */
    public function etat(Phase $phase): array
    {
        $generateur = $this->formats->pourPhase($phase);
        $elimination = $phase->getType() === PhaseType::FINALE;

        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [(int) $a->getNiveau(), $a->getNom()] <=> [(int) $b->getNiveau(), $b->getNom()]);

        $deplacements = [];
        foreach ($this->optimisation->getAlternanceParPoule($phase) as $donnees) {
            $n = 0;
            foreach ($donnees['rows'] as $row) {
                foreach ($row['cells'] as $cell) {
                    if ($cell !== null && $cell['break']) {
                        ++$n;
                    }
                }
            }
            $deplacements[$donnees['poule']->getId()] = $n;
        }

        $lignes = [];
        $problemes = [];
        $totalCrees = 0;
        $totalPrevus = 0;
        $sansMatch = 0;
        $avecMatch = 0;
        $journeesManquantes = false;
        $raison = null;
        $sansGymnaseTotal = [];
        foreach ($poules as $poule) {
            $effectif = \count($poule->getEquipes());
            $prevus = $generateur?->nombreMatchs($poule) ?? 0;
            $crees = \count($poule->getParties());
            $totalCrees += $crees;
            $totalPrevus += $prevus;
            $ancre = 'poule-'.$poule->getId();
            $journeesPrevues = $generateur?->nombreJournees($poule) ?? 0;
            $journeesOk = $effectif >= 2 && \count($poule->getJournees()) === $journeesPrevues && $journeesPrevues > 0;

            if ($crees === 0) {
                ++$sansMatch;
                if ($effectif < 2) {
                    $statut = 'impossible';
                    $texte = sprintf('La %s n\'a pas assez d\'équipes (deux au moins) pour avoir des matchs.', $poule->getNom());
                } elseif (!$journeesOk) {
                    $statut = 'sans_journees';
                    $journeesManquantes = true;
                    $texte = sprintf('La %s n\'a pas toutes ses journées.', $poule->getNom());
                } else {
                    $statut = 'aucun';
                    $texte = sprintf('La %s n\'a aucun match. Générez-les pour continuer.', $poule->getNom());
                }
                $raison ??= $texte;
            } else {
                ++$avecMatch;
                $statut = $crees === $prevus ? 'ok' : 'ecart';
                if ($crees !== $prevus) {
                    $problemes[] = ['niveau' => 'warning', 'ancre' => $ancre, 'lien' => null,
                        'texte' => sprintf('%s : %d match%s pour %d équipe%s (%d prévu%s).', $poule->getNom(), $crees, $crees > 1 ? 's' : '', $effectif, $effectif > 1 ? 's' : '', $prevus, $prevus > 1 ? 's' : '')];
                }
            }

            $sansGymnase = [];
            foreach ($poule->getEquipes() as $equipe) {
                $lieu = $equipe->getLieu();
                if ($lieu === null || $lieu->getCreneaux()->isEmpty()) {
                    $sansGymnase[] = $equipe;
                    $sansGymnaseTotal[$equipe->getId()] = $equipe;
                }
            }

            $lignes[] = [
                'poule' => $poule, 'effectif' => $effectif, 'prevus' => $prevus, 'crees' => $crees,
                'deplacements' => $elimination ? null : ($deplacements[$poule->getId()] ?? 0),
                'statut' => $statut, 'sansGymnase' => $sansGymnase,
            ];
        }

        // Poules sans match : une erreur par poule, sauf quand aucune poule n'en a encore (simple information)
        if ($sansMatch > 0 && $avecMatch > 0) {
            foreach ($lignes as $l) {
                if ($l['crees'] === 0) {
                    $problemes[] = ['niveau' => 'error', 'ancre' => 'poule-'.$l['poule']->getId(), 'lien' => $l['statut'] === 'sans_journees' ? 'journees' : null,
                        'texte' => match ($l['statut']) {
                            'impossible' => sprintf('%s : pas assez d\'équipes pour avoir des matchs.', $l['poule']->getNom()),
                            'sans_journees' => sprintf('%s : ses journées ne sont pas créées.', $l['poule']->getNom()),
                            default => sprintf('%s : aucun match pour l\'instant.', $l['poule']->getNom()),
                        }];
                }
            }
        }
        if ($journeesManquantes && $avecMatch === 0) {
            $problemes[] = ['niveau' => 'error', 'ancre' => null, 'lien' => 'journees',
                'texte' => 'Des poules n\'ont pas toutes leurs journées : créez-les avant de générer les matchs.'];
        }
        if ($sansGymnaseTotal !== []) {
            $noms = array_map(fn (Equipe $e) => (string) $e->getNom(), array_values($sansGymnaseTotal));
            sort($noms);
            $n = \count($noms);
            $problemes[] = ['niveau' => 'warning', 'ancre' => null, 'lien' => 'equipes',
                'texte' => sprintf('%d équipe%s sans gymnase utilisable : %s. %s matchs à domicile seront joués au gymnase par défaut.', $n, $n > 1 ? 's' : '', implode(', ', $noms), $n > 1 ? 'Leurs' : 'Ses')];
        }
        $surcharges = $this->surcharges($phase);
        foreach ($surcharges as $texte) {
            $problemes[] = ['niveau' => 'warning', 'ancre' => null, 'lien' => null, 'texte' => $texte];
        }

        return [
            'poules' => $lignes,
            'problemes' => $problemes,
            'surcharges' => $surcharges,
            'crees' => $totalCrees,
            'prevus' => $totalPrevus,
            'sansMatch' => $sansMatch,
            'avecMatch' => $avecMatch,
            'bloquant' => $sansMatch > 0 || $lignes === [],
            'raison' => $lignes === [] ? 'Cette phase n\'a aucune poule : ajoutez-en une à l\'étape Phases.' : $raison,
            'verrouillee' => $this->journees->verrouillee($phase),
            'journeesManquantes' => $journeesManquantes,
            'elimination' => $elimination,
        ];
    }

    /**
     * Surcharges de gymnase de la phase, en phrases : lieu et jour où il y a plus de matchs que le créneau n'en accepte.
     *
     * @return list<string>
     */
    private function surcharges(Phase $phase): array
    {
        $parLieuEtDate = [];
        foreach ($phase->getPoules() as $poule) {
            foreach ($poule->getParties() as $partie) {
                $lieu = $partie->getLieu();
                if (!$lieu instanceof Lieu || !$partie->getDate()) {
                    continue;
                }
                $cle = $lieu->getId().'|'.$partie->getDate()->format('Y-m-d');
                $parLieuEtDate[$cle] ??= ['lieu' => $lieu, 'date' => $partie->getDate(), 'n' => 0];
                ++$parLieuEtDate[$cle]['n'];
            }
        }
        uasort($parLieuEtDate, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        $phrases = [];
        foreach ($parLieuEtDate as $ligne) {
            $analyse = $this->analyse->analyser($ligne['lieu'], $ligne['date'], $ligne['n']);
            if ($analyse['alerte_capacite']) {
                $phrases[] = sprintf('%s est surchargé le %s : %d matchs pour %d possibles.', $ligne['lieu']->getNom(), $ligne['date']->format('d/m'), $ligne['n'], $analyse['capacite_max']);
            }
        }

        return $phrases;
    }

    /**
     * Applique une action de l'étape et rend le message de statut.
     *
     * Actions : generer-phase (toutes les poules ensemble, pour partager les gymnases), generer-{poule} (poule sans match)
     * et regenerer-{poule} (supprime les matchs de la poule puis les recrée). Suppression et création se font dans une
     * transaction : un échec du calcul ne fait perdre aucun match.
     *
     * @return array{ok: bool, message: string, ancre: ?string, avertissement: ?string}
     */
    public function appliquer(Phase $phase, string $action): array
    {
        if ($this->journees->verrouillee($phase)) {
            return $this->echec('Cette phase est lancée (scores saisis ou clôturée) : ses matchs ne peuvent plus être refaits ici.');
        }
        $poule = null;
        if ($action !== 'generer-phase') {
            if (!preg_match('/^(generer|regenerer)-(\d+)$/', $action, $m)) {
                return $this->echec('Action inconnue : rien n\'a été modifié.');
            }
            foreach ($phase->getPoules() as $candidate) {
                if ($candidate->getId() === (int) $m[2]) {
                    $poule = $candidate;
                }
            }
            if ($poule === null) {
                return $this->echec('Poule introuvable : rien n\'a été modifié.');
            }
            if ($m[1] === 'generer' && \count($poule->getParties()) > 0) {
                return $this->echec(sprintf('La %s a déjà ses matchs : utilisez « Régénérer les matchs de la poule » pour les refaire.', $poule->getNom()), 'poule-'.$poule->getId());
            }
        }

        $generateur = $this->formats->pourPhase($phase);
        foreach ($poule !== null ? [$poule] : $phase->getPoules()->toArray() as $concernee) {
            $effectif = \count($concernee->getEquipes());
            if ($effectif < 2) {
                if ($poule !== null) {
                    return $this->echec(sprintf('La %s n\'a pas assez d\'équipes (deux au moins) pour avoir des matchs.', $concernee->getNom()), 'poule-'.$concernee->getId());
                }
                continue;
            }
            $attendues = $generateur?->nombreJournees($concernee) ?? 0;
            if (\count($concernee->getJournees()) !== $attendues || $attendues === 0) {
                return $this->echec(sprintf('La %s n\'a pas toutes ses journées : créez-les à l\'étape Journées avant de générer les matchs.', $concernee->getNom()), 'poule-'.$concernee->getId());
            }
        }

        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();
        try {
            // Contrôle refait dans la transaction : deux envois simultanés ne doivent pas créer les matchs en double
            if ($poule !== null && $m[1] === 'generer' && $this->nbMatchsEnBase($poule) > 0) {
                $connexion->rollBack();

                return $this->echec(sprintf('La %s a déjà ses matchs : utilisez « Régénérer les matchs de la poule » pour les refaire.', $poule->getNom()), 'poule-'.$poule->getId());
            }
            if ($poule === null) {
                $this->parties->creerCalendrierOptimise($phase);
            } else {
                $this->parties->creerCalendrierOptimisePourPoule($poule);
            }
            $connexion->commit();
        } catch (\Throwable $e) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }
            $this->em->clear();
            $this->logger->error('Génération des matchs échouée (phase {phase}) : {message}', ['phase' => $phase->getId(), 'message' => $e->getMessage()]);

            return $this->echec('Le calcul n\'a pas abouti. Aucun match n\'a été modifié. Vérifiez que les équipes ont un gymnase avec des créneaux, puis recommencez.');
        }

        // État frais : les collections des entités déjà chargées ne contiennent pas les matchs qui viennent d'être créés
        $pouleId = $poule?->getId();
        $this->em->clear();
        $phase = $this->em->find(Phase::class, $phase->getId());
        $poule = $pouleId === null ? null : $this->em->find(Poule::class, $pouleId);
        $surcharges = $this->surcharges($phase);
        $avertissement = $surcharges === [] ? null : 'Gymnases : '.implode(' ; ', $surcharges);
        if ($poule !== null) {
            $n = \count($poule->getParties());

            return ['ok' => true, 'message' => sprintf('%d match%s créé%s pour %s.', $n, $n > 1 ? 's' : '', $n > 1 ? 's' : '', $poule->getNom()),
                'ancre' => 'poule-'.$poule->getId(), 'avertissement' => $avertissement];
        }
        $n = 0;
        $nbPoules = 0;
        foreach ($phase->getPoules() as $p) {
            if (\count($p->getParties()) > 0) {
                $n += \count($p->getParties());
                ++$nbPoules;
            }
        }

        return ['ok' => true, 'message' => sprintf('%d match%s créé%s pour %d poule%s.', $n, $n > 1 ? 's' : '', $n > 1 ? 's' : '', $nbPoules, $nbPoules > 1 ? 's' : ''),
            'ancre' => null, 'avertissement' => $avertissement];
    }

    private function nbMatchsEnBase(Poule $poule): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Partie p WHERE p.poule = :poule')
            ->setParameter('poule', $poule)->getSingleScalarResult();
    }

    /** @return array{ok: bool, message: string, ancre: ?string, avertissement: ?string} */
    private function echec(string $message, ?string $ancre = null): array
    {
        return ['ok' => false, 'message' => $message, 'ancre' => $ancre, 'avertissement' => null];
    }
}
