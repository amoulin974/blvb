<?php

namespace App\Competition;

use App\Competition\Format\Formats;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\Partie;
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
        $totalJoues = 0;
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
            $joues = 0;
            foreach ($poule->getParties() as $partie) {
                if ($this->estJoue($partie)) {
                    ++$joues;
                }
            }
            $totalJoues += $joues;
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
                'joues' => $joues, 'aVenir' => $crees - $joues, 'verrouillee' => $this->pouleVerrouillee($poule),
                'aVenirPasses' => $this->nbPasses($poule),
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
            'cloturee' => (bool) $phase->getClose(),
            'joues' => $totalJoues,
            'aVenir' => $totalCrees - $totalJoues,
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
     * Actions : generer-phase (toutes les poules ensemble, pour partager les gymnases), generer-{poule} (poule sans match),
     * regenerer-{poule} (supprime les matchs de la poule puis les recrée) et replacer-phase / replacer-{poule} (ne
     * replace que les matchs à venir ; exige la confirmation qu'aucun match déjà joué n'attend la saisie de son score).
     * Suppression et création se font dans une transaction : un échec du calcul ne fait perdre aucun match.
     *
     * Les générations complètes sont refusées pour une phase dont des scores sont saisis (phase entière) ou pour une
     * poule qui a des matchs joués ; une phase clôturée n'est plus modifiable.
     *
     * @return array{ok: bool, message: string, ancre: ?string, avertissement: ?string}
     */
    public function appliquer(Phase $phase, string $action, bool $confirme = false): array
    {
        if ($phase->getClose()) {
            return $this->echec('Cette phase est clôturée : ses matchs ne peuvent plus être modifiés ici.');
        }
        $poule = null;
        $mode = 'phase';
        if (in_array($action, ['generer-phase', 'replacer-phase'], true)) {
            $mode = substr($action, 0, (int) strpos($action, '-'));
        } else {
            if (!preg_match('/^(generer|regenerer|replacer)-(\d+)$/D', $action, $m)) {
                return $this->echec('Action inconnue : rien n\'a été modifié.');
            }
            $mode = $m[1];
            foreach ($phase->getPoules() as $candidate) {
                if ($candidate->getId() === (int) $m[2]) {
                    $poule = $candidate;
                }
            }
            if ($poule === null) {
                return $this->echec('Poule introuvable : rien n\'a été modifié.');
            }
            if ($mode === 'generer' && \count($poule->getParties()) > 0) {
                return $this->echec(sprintf('La %s a déjà ses matchs : utilisez « Régénérer les matchs de la poule » pour les refaire.', $poule->getNom()), 'poule-'.$poule->getId());
            }
        }
        $ancre = $poule === null ? null : 'poule-'.$poule->getId();
        $concernees = $poule !== null ? [$poule] : $phase->getPoules()->toArray();

        if ($mode === 'replacer') {
            if (!$confirme) {
                $echec = $this->echec('Cochez la case de confirmation (scores des matchs joués saisis), puis recommencez. Rien n\'a été modifié.', $ancre);
                $echec['ouvrir'] = $poule === null ? 'phase' : (string) $poule->getId();

                return $echec;
            }
            $aVenir = 0;
            foreach ($concernees as $c) {
                if (\count($c->getEquipes()) >= 2 && \count($c->getJournees()) === 0) {
                    return $this->echec(sprintf('La %s n\'a aucune journée : créez-les à l\'étape Journées. Rien n\'a été modifié.', $c->getNom()), 'poule-'.$c->getId());
                }
                foreach ($c->getParties() as $partie) {
                    if (!$this->estJoue($partie)) {
                        ++$aVenir;
                    }
                }
            }
            if ($aVenir === 0) {
                return $this->echec('Il n\'y a aucun match à venir à replacer. Rien n\'a été modifié.', $ancre);
            }
        } elseif ($poule === null && $this->journees->verrouillee($phase)) {
            return $this->echec('Cette phase est lancée (scores saisis) : ses matchs joués sont figés. Utilisez « Replacer les matchs à venir ».');
        } elseif ($poule !== null && $this->pouleVerrouillee($poule)) {
            return $this->echec(sprintf('La %s a des matchs joués : ses matchs ne peuvent plus être refaits en entier. Utilisez « Replacer les matchs à venir ».', $poule->getNom()), $ancre);
        }

        $generateur = $this->formats->pourPhase($phase);
        foreach ($concernees as $concernee) {
            $effectif = \count($concernee->getEquipes());
            if ($effectif < 2) {
                if ($poule !== null) {
                    return $this->echec(sprintf('La %s n\'a pas assez d\'équipes (deux au moins) pour avoir des matchs.', $concernee->getNom()), 'poule-'.$concernee->getId());
                }
                continue;
            }
            if ($mode === 'replacer') {
                continue; // les journées existent déjà : les matchs à venir y sont replacés
            }
            $attendues = $generateur?->nombreJournees($concernee) ?? 0;
            if (\count($concernee->getJournees()) !== $attendues || $attendues === 0) {
                return $this->echec(sprintf('La %s n\'a pas toutes ses journées : créez-les à l\'étape Journées avant de générer les matchs.', $concernee->getNom()), 'poule-'.$concernee->getId());
            }
        }

        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();
        $rapport = [];
        try {
            // Contrôle refait dans la transaction : deux envois simultanés ne doivent pas créer les matchs en double
            if ($poule !== null && $mode === 'generer' && $this->nbMatchsEnBase($poule) > 0) {
                $connexion->rollBack();

                return $this->echec(sprintf('La %s a déjà ses matchs : utilisez « Régénérer les matchs de la poule » pour les refaire.', $poule->getNom()), 'poule-'.$poule->getId());
            }
            if ($mode === 'replacer') {
                $rapport = $this->parties->replacerMatchsAVenir($phase, $poule);
                if ($rapport === []) {
                    $connexion->rollBack();

                    return $this->echec('Aucun match ne peut être replacé ici : les tableaux à élimination se régénèrent en entier tant qu\'aucun match n\'est joué. Rien n\'a été modifié.', $ancre);
                }
            } elseif ($poule === null) {
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

        if ($mode === 'replacer') {
            $replaces = array_sum(array_column($rapport, 'replaces'));
            $conserves = array_sum(array_column($rapport, 'conserves'));

            // Équipe ajoutée après la génération : ses matchs n'existent pas, le replacement ne les crée pas
            $manquants = 0;
            $generateurFrais = $this->formats->pourPhase($phase);
            foreach ($poule !== null ? [$poule] : $phase->getPoules()->toArray() as $p) {
                $manquants += max(0, ($generateurFrais?->nombreMatchs($p) ?? 0) - \count($p->getParties()));
            }
            if ($manquants > 0) {
                $avertissement = trim(($avertissement ?? '').' '.sprintf('%d match%s manque%s (équipe ajoutée ou retirée depuis la génération ?) : utilisez « Régénérer » pour les créer.', $manquants, $manquants > 1 ? 's' : '', $manquants > 1 ? 'nt' : ''));
            }

            return ['ok' => true,
                'message' => sprintf('%d match%s à venir replacé%s ; %d match%s joué%s conservé%s.', $replaces, $replaces > 1 ? 's' : '', $replaces > 1 ? 's' : '', $conserves, $conserves > 1 ? 's' : '', $conserves > 1 ? 's' : '', $conserves > 1 ? 's' : ''),
                'ancre' => $poule === null ? null : 'poule-'.$poule->getId(), 'avertissement' => $avertissement];
        }
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

    /** Matchs sans score dont la date est déjà passée : à signaler, car ils ont peut-être été joués sans score saisi. */
    private function nbPasses(Poule $poule): int
    {
        $n = 0;
        $aujourdhui = new \DateTimeImmutable('today');
        foreach ($poule->getParties() as $partie) {
            if (!$this->estJoue($partie) && $partie->getDate() !== null && $partie->getDate() < $aujourdhui) {
                ++$n;
            }
        }

        return $n;
    }

    /** Un match est « joué » dès qu'un score est saisi (l'un ou l'autre côté). */
    private function estJoue(Partie $partie): bool
    {
        return $partie->getNbSetGagnantReception() !== null || $partie->getNbSetGagnantDeplacement() !== null;
    }

    /** Poule dont des matchs sont joués (score saisi) ou dont le classement a déjà bougé. */
    private function pouleVerrouillee(Poule $poule): bool
    {
        foreach ($poule->getParties() as $partie) {
            if ($this->estJoue($partie)) {
                return true;
            }
        }

        return (int) $this->em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Classement c WHERE c.poule = :poule AND (c.points <> 0 OR c.setGagnes > 0 OR c.setPerdus > 0)')
            ->setParameter('poule', $poule)->getSingleScalarResult() > 0;
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
