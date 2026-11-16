<?php

namespace App\Reglement\Bloc;

use App\Entity\Indisponibilite;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Reglement\BlocInapplicable;
use App\Reglement\Texte;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

/**
 * [calendrier] : une ligne par semaine, regroupée par phase (journées des poules, semaines sans match, journée des
 * finales) ; les tours d'une phase à élimination sont « à jouer avant le … » (D58). « Cette semaine » est écrit.
 */
final class BlocCalendrier extends BlocParPhase
{
    public function __construct(
        private Environment $twig,
        private EntityManagerInterface $em,
    ) {
    }

    public function code(): string
    {
        return 'calendrier';
    }

    public function libelle(): string
    {
        return 'Calendrier de la saison';
    }

    public function description(): string
    {
        return 'Les semaines de chaque phase : journées des poules, semaines sans match et journée des finales.';
    }

    public function rendre(Saison $saison, array $parametres): string
    {
        $phases = self::phases($saison, $parametres);
        if ($phases === []) {
            throw new BlocInapplicable('la saison n\'a pas encore de phase.');
        }
        $aujourdhui = new \DateTimeImmutable('today');

        return $this->twig->render('reglement/blocs/calendrier.html.twig', [
            'phases' => array_map(fn (Phase $phase) => [
                'nom' => $phase->getNom(),
                'infos' => Texte::periode($phase->getDatedebut(), $phase->getDatefin()),
                'lignes' => $this->lignes($saison, $phase, $aujourdhui),
            ], $phases),
        ]);
    }

    /** @return list<array{date: string, programme: string, detail: ?string, type: string, cetteSemaine: bool}> */
    private function lignes(Saison $saison, Phase $phase, \DateTimeImmutable $aujourdhui): array
    {
        $finales = $phase->getType() === PhaseType::FINALE ? $phase->getJourneeFinales() : null;
        $jourFinales = $finales['date'] ?? null;

        // Journées des poules, par semaine (lundi) ; celles de la journée des finales à part
        $semaines = [];
        $joursFinales = [];
        foreach ($phase->getPoules() as $poule) {
            foreach ($poule->getJournees() as $journee) {
                if ($journee->getDateDebut() === null) {
                    continue;
                }
                if ($jourFinales !== null && $journee->getDateDebut()->format('Y-m-d') === $jourFinales->format('Y-m-d')) {
                    $joursFinales[] = [$poule, $journee];
                    continue;
                }
                $lundi = $journee->getDateDebut()->modify('monday this week')->setTime(0, 0);
                $semaines[$lundi->format('Y-m-d')][] = [$poule, $journee];
            }
        }

        $lignes = [];
        foreach ($semaines as $jour => $entrees) {
            $lundi = new \DateTimeImmutable($jour);
            $lignes[] = [
                'tri' => $lundi,
                'date' => Texte::date($lundi),
                'programme' => $phase->getType() === PhaseType::FINALE ? $this->tours($phase, $entrees) : $this->journees($phase, $entrees),
                'detail' => $phase->getType() === PhaseType::FINALE
                    ? 'À jouer avant le '.Texte::date(max(array_map(fn ($e) => $e[1]->getDateFin() ?? $e[1]->getDateDebut(), $entrees)))
                    : null,
                'type' => 'journee',
                'cetteSemaine' => $aujourdhui >= $lundi && $aujourdhui <= $lundi->modify('+6 days'),
            ];
        }

        if ($jourFinales !== null) {
            $lieu = $finales['lieu'] !== null ? $this->em->find(Lieu::class, $finales['lieu']) : null;
            $lignes[] = [
                'tri' => $jourFinales,
                'date' => Texte::date($jourFinales),
                'programme' => 'Journée des finales'.($joursFinales !== [] ? ' : '.$this->tours($phase, $joursFinales) : ''),
                'detail' => Texte::date($jourFinales, 'EEEE d MMMM yyyy').', '.Texte::heure($finales['heure']).($lieu !== null ? ', '.$lieu->getNom() : ''),
                'type' => 'finales',
                'cetteSemaine' => $aujourdhui->format('o-W') === $jourFinales->format('o-W'),
            ];
        }

        if ($semaines === [] && $jourFinales === null) {
            $lignes[] = ['tri' => $phase->getDatedebut(), 'date' => '', 'programme' => 'Calendrier des journées pas encore établi.', 'detail' => null, 'type' => 'vide', 'cetteSemaine' => false];
        }

        // Semaines sans match qui touchent la phase
        foreach ($saison->getIndisponibilites() as $fermeture) {
            /** @var Indisponibilite $fermeture */
            if ($fermeture->getDateDebut() === null || $fermeture->getDateFin() === null
                || ($phase->getDatefin() !== null && $fermeture->getDateDebut() > $phase->getDatefin())
                || ($phase->getDatedebut() !== null && $fermeture->getDateFin() < $phase->getDatedebut())) {
                continue;
            }
            $lignes[] = [
                'tri' => $fermeture->getDateDebut(),
                'date' => Texte::date($fermeture->getDateDebut()),
                'programme' => 'Pas de match : '.$fermeture->getNom(),
                'detail' => Texte::periode($fermeture->getDateDebut(), $fermeture->getDateFin()),
                'type' => 'fermeture',
                'cetteSemaine' => $aujourdhui >= $fermeture->getDateDebut()->setTime(0, 0) && $aujourdhui <= $fermeture->getDateFin(),
            ];
        }

        usort($lignes, fn ($a, $b) => [$a['tri'], $a['type'] === 'fermeture' ? 0 : 1] <=> [$b['tri'], $b['type'] === 'fermeture' ? 0 : 1]);

        return array_map(function (array $ligne) {
            unset($ligne['tri']);

            return $ligne;
        }, $lignes);
    }

    /**
     * Journées d'une semaine de championnat ou de barrages : « Journée 1, toutes les poules »,
     * « Poules A et B : journée 9 · poules C et D : journée 10 », « Barrages, tous les matchs »
     *
     * @param list<array{0: Poule, 1: Journee}> $entrees
     */
    private function journees(Phase $phase, array $entrees): string
    {
        $parNumero = [];
        foreach ($entrees as [$poule, $journee]) {
            $parNumero[(int) $journee->getNumero()][spl_object_id($poule)] = $poule;
        }
        ksort($parNumero);
        $toutes = \count($phase->getPoules());

        if ($phase->getType() === PhaseType::BARRAGE) {
            $poules = array_merge(...array_map('array_values', array_values($parNumero)));

            return \count($poules) === $toutes ? 'Barrages, tous les matchs' : self::majuscule(self::groupe($poules));
        }
        if (\count($parNumero) === 1) {
            $numero = array_key_first($parNumero);
            $poules = array_values($parNumero[$numero]);

            return \count($poules) === $toutes ? sprintf('Journée %d, toutes les poules', $numero) : sprintf('Journée %d : %s', $numero, self::groupe($poules));
        }

        return self::majuscule(implode(' · ', array_map(fn (int $numero, array $poules) => sprintf('%s : journée %d', self::groupe(array_values($poules)), $numero),
            array_keys($parNumero), $parNumero)));
    }

    /**
     * Tours d'une semaine de phase à élimination : « Quarts de finale, tous les tableaux », « Barrages : tableau 3 »
     *
     * @param list<array{0: Poule, 1: Journee}> $entrees
     */
    private function tours(Phase $phase, array $entrees): string
    {
        $parTour = [];
        foreach ($entrees as [$poule, $journee]) {
            $tour = self::tour((string) $journee->getNom());
            // Nom libre (journées reprises de l'ancien site) : « Matchs », le nom de la phase est en tête du groupe
            $tour = \in_array($tour, self::TOURS, true) || preg_match('/^Tour \d+$/', $tour) ? $tour : 'Matchs';
            $parTour[$tour][spl_object_id($poule)] = $poule;
        }
        $toutes = \count($phase->getPoules());

        return implode(' · ', array_map(fn (string $tour, array $poules) => \count($poules) === $toutes && $toutes > 1
            ? $tour.', tous les tableaux'
            : $tour.' : '.self::groupe(array_values($poules)), array_keys($parTour), $parTour));
    }

    /** Noms des tours, tels que TableauElimination les écrit */
    public const TOURS = ['Barrages', 'Barrage', 'Seizièmes de finale', 'Huitièmes de finale', 'Quarts de finale', 'Demi-finales', 'Finale'];

    /**
     * Nom d'une journée d'élimination (Elimination::getNomJournee) au pluriel : « Quart de finale » → « Quarts de finale » ;
     * « Tour 5 » et les noms libres (journées reprises de l'ancien site) restent tels quels
     */
    public static function tour(string $nom): string
    {
        return match ($nom) {
            'Quart de finale' => 'Quarts de finale',
            'Demi-finale' => 'Demi-finales',
            '8ème de finale' => 'Huitièmes de finale',
            '16ème de finale' => 'Seizièmes de finale',
            default => $nom,
        };
    }

    /**
     * « poule A », « poules A et B », sinon les noms énumérés
     *
     * @param list<Poule> $poules
     */
    private static function groupe(array $poules): string
    {
        usort($poules, fn (Poule $a, Poule $b) => [$a->getNiveau() ?? PHP_INT_MAX, (string) $a->getNom()] <=> [$b->getNiveau() ?? PHP_INT_MAX, (string) $b->getNom()]);
        if (\count($poules) === 1) {
            return Texte::poule($poules[0]);
        }
        $codes = [];
        foreach ($poules as $poule) {
            if (!preg_match('/^poule\s+(.+)$/iu', (string) $poule->getNom(), $m)) {
                return Texte::enumeration(array_map(Texte::poule(...), $poules));
            }
            $codes[] = $m[1];
        }

        return 'poules '.Texte::enumeration($codes);
    }

    private static function majuscule(string $texte): string
    {
        return mb_strtoupper(mb_substr($texte, 0, 1)).mb_substr($texte, 1);
    }
}
