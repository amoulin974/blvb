<?php

namespace App\Competition\Format;

use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Enum\PhaseType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Élimination directe (format B1 de docs/formats-competition.md), la phase finale : tableau à placement standard
 * (1-8, 4-5, 3-6, 2-7 pour 8 équipes : le 1er et le 2e ne se rencontrent qu'en finale), une journée par tour, matchs
 * reliés par Partie::parentMatch1/2. Quand le nombre d'équipes n'est pas une puissance de 2, le tableau est complété
 * par des exemptions : les meilleures têtes de série passent directement au tour suivant et un tour de barrages oppose
 * les moins bien classées (12 équipes : 5-12, 6-11, 7-10, 8-9, les 4 premières sont exemptées).
 * Option de la phase `petite_finale` (B2) : match pour la 3e place entre les perdants des demi-finales.
 *
 * Les têtes de série suivent le classement de la phase précédente (rang dans sa poule, puis niveau de sa poule).
 */
final class Elimination implements GenerateurFormat
{
    public function __construct(private EntityManagerInterface $em, private PlanningFinales $planning)
    {
    }

    public function supporte(Phase $phase): bool
    {
        return $phase->getType() === PhaseType::FINALE;
    }

    public function estOptimisable(): bool
    {
        return false;
    }

    public function nombreMatchs(Poule $poule): int
    {
        $n = count($poule->getEquipes());
        if ($n < 2) {
            return 0;
        }

        return $n - 1 + ($this->petiteFinale($poule, $n) ? 1 : 0);
    }

    public function nombreJournees(Poule $poule): int
    {
        return self::nombreJourneesPour(count($poule->getEquipes()));
    }

    /** Nombre de journées (tours) d'un tableau de $n équipes, barrages compris. */
    public static function nombreJourneesPour(int $n): int
    {
        if ($n < 2) {
            return 0;
        }
        $puissance = (int) pow(2, floor(log($n, 2)));
        $tours = (int) log($puissance, 2);

        return $n === $puissance ? $tours : $tours + 1;
    }

    /**
     * Journée des finales trop tôt : les $avant tours qui la précèdent se jouent une semaine chacun à partir du début de
     * la phase ; message (relu par l'ergonome) si la date tombe avant la fin de leur dernière semaine, sinon null.
     */
    public static function messageFinalesTropTot(\DateTimeImmutable $debutPhase, \DateTimeImmutable $date, int $avant): ?string
    {
        if ($avant <= 0) {
            return null;
        }
        $fin = $debutPhase->modify(sprintf('+%d days', 7 * $avant - 1));
        if ($date > $fin) {
            return null;
        }

        return $avant > 1
            ? sprintf('Journée des finales trop tôt (%s) : les %d tours précédents se jouent une semaine chacun, du %s au %s. Choisissez une date après le %s.',
                $date->format('d/m/Y'), $avant, $debutPhase->format('d/m/Y'), $fin->format('d/m/Y'), $fin->format('d/m/Y'))
            : sprintf('Journée des finales trop tôt (%s) : le tour précédent se joue la semaine du %s au %s. Choisissez une date après le %s.',
                $date->format('d/m/Y'), $debutPhase->format('d/m/Y'), $fin->format('d/m/Y'), $fin->format('d/m/Y'));
    }

    public function creerJournees(Poule $poule): ?string
    {
        $equipes = $poule->getEquipes();
        $nbEquipe = count($equipes);

        if ($nbEquipe < 2) {
            return "Il faut au moins 2 équipes pour une phase finale.";
        }

        // 1. Calculer la puissance de 2 inférieure la plus proche
        // Exemple : pour 12, log2(12) = 3.58 -> floor = 3 -> 2^3 = 8.
        $puissanceInf = pow(2, floor(log($nbEquipe, 2)));

        // 2. Déterminer s'il y a des barrages
        $aDesBarrages = ($nbEquipe != $puissanceInf);

        // 3. Calculer le nombre total de journées
        // Si barrages : 1 (barrage) + log2(puissanceInf)
        // Exemple 12 équipes : 1 (barrage) + 3 (1/4, 1/2, F) = 4 journées.
        $nbJourneesFinales = (int)log($puissanceInf, 2);
        $totalJournees = $aDesBarrages ? $nbJourneesFinales + 1 : $nbJourneesFinales;

        $phase = $poule->getPhase();
        $debutPhase = $phase->getDateDebut();
        $currentDate = clone $debutPhase;

        // Journée des finales (D45) : les derniers tours se jouent le même jour ; les précédents, une semaine chacun, avant
        $regroupes = min($phase->nbToursJourneeFinales(), $totalJournees);
        $dateFinales = $phase->getJourneeFinales()['date'] ?? null;
        if ($regroupes > 0 && $dateFinales !== null) {
            if (($message = self::messageFinalesTropTot($debutPhase, $dateFinales, $totalJournees - $regroupes)) !== null) {
                return $message;
            }
        } else {
            $regroupes = 0;
        }

        for ($i = 1; $i <= $totalJournees; $i++) {
            $journee = new Journee();

            $nom = $this->getNomJournee($i, $totalJournees, $aDesBarrages);
            $journee->setNom($nom);
            $journee->setNumero($i);

            if ($i > $totalJournees - $regroupes) {
                $journee->setDateDebut($dateFinales)->setDateFin($dateFinales);
            } else {
                $journee->setDateDebut(new \DateTimeImmutable($currentDate->format('Y-m-d')));
                $journee->setDateFin(new \DateTimeImmutable($currentDate->modify('+6 days')->format('Y-m-d')));
            }
            $journee->setPoule($poule);

            $this->em->persist($journee);
            $currentDate = $currentDate->modify('+1 week');
        }

        $this->em->flush();
        return null;
    }

    //Fonction qui nomme les journées
    private function getNomJournee(int $index, int $total, bool $hasBarrage): string
    {
        if ($hasBarrage && $index === 1) {
            return "Barrages";
        }

        // On calcule l'index réel par rapport à la finale (la finale est toujours le dernier tour)
        $distanceDeLaFinale = $total - $index;

        return match ($distanceDeLaFinale) {
            0 => "Finale",
            1 => "Demi-finale",
            2 => "Quart de finale",
            3 => "8ème de finale",
            4 => "16ème de finale",
            default => "Tour " . $index,
        };
    }

    /**
     * Ordre des têtes de série d'un tableau de $taille places (puissance de 2) : la position i et i+1 se rencontrent au
     * premier tour. 8 → 1, 8, 4, 5, 2, 7, 3, 6 (1-8, 4-5, 2-7, 3-6) ; le 1er et le 2e sont dans des moitiés opposées.
     *
     * @return list<int>
     */
    public static function placementStandard(int $taille): array
    {
        $tetes = [1];
        for ($n = 1; $n < $taille; $n *= 2) {
            $suite = [];
            foreach ($tetes as $tete) {
                $suite[] = $tete;
                $suite[] = 2 * $n + 1 - $tete;
            }
            $tetes = $suite;
        }

        return $tetes;
    }

    /**
     * Équipes de la poule dans l'ordre des têtes de série : rang dans la poule de la phase précédente, puis niveau de
     * cette poule, puis nom (ordre stable quand le classement n'est pas connu).
     *
     * @return list<Equipe>
     */
    private function ordreDeTete(Poule $poule): array
    {
        $phase = $poule->getPhase();
        $precedentes = [];
        foreach ($phase->getSaison()->getPhases() as $autre) {
            if ($autre->getOrdre() < $phase->getOrdre()) {
                $precedentes[] = $autre;
            }
        }
        usort($precedentes, fn (Phase $x, Phase $y) => $y->getOrdre() <=> $x->getOrdre());

        $cle = function (Equipe $equipe) use ($precedentes): array {
            foreach ($precedentes as $autre) {
                foreach ($autre->getPoules() as $source) {
                    if (!$source->getEquipes()->contains($equipe)) {
                        continue;
                    }
                    foreach ($source->getClassements() as $classement) {
                        if ($classement->getEquipe() === $equipe && $classement->getPosition() !== null) {
                            return [$classement->getPosition(), $source->getNiveau() ?? PHP_INT_MAX, (string) $equipe->getNom()];
                        }
                    }

                    return [PHP_INT_MAX, $source->getNiveau() ?? PHP_INT_MAX, (string) $equipe->getNom()];
                }
            }

            return [PHP_INT_MAX, PHP_INT_MAX, (string) $equipe->getNom()];
        };

        $equipes = $poule->getEquipes()->toArray();
        usort($equipes, fn (Equipe $a, Equipe $b) => $cle($a) <=> $cle($b));

        return array_values($equipes);
    }

    private function petiteFinale(Poule $poule, int $nbEquipes): bool
    {
        return $nbEquipes >= 4 && $poule->getPhase()?->aPetiteFinale() === true;
    }

    public function genererMatchs(Poule $poule): void
    {
        $equipes = $this->ordreDeTete($poule);
        $n = \count($equipes);
        $journees = $poule->getJournees()->toArray();
        usort($journees, fn (Journee $j1, Journee $j2) => $j1->getNumero() <=> $j2->getNumero());
        if ($n < 2 || $journees === []) {
            return;
        }

        // Tableau complet de $taille places (puissance de 2 ≥ n) ; une place vide donne une exemption
        $taille = (int) pow(2, ceil(log($n, 2)));
        $tetes = self::placementStandard($taille);
        $tours = (int) log($taille, 2);

        // Nœud du tableau : ['equipe' => Equipe] (exemptée) ou ['match' => Partie]
        $creer = function (int $tour, int $rang) use ($poule, $journees, $tours): Partie {
            $journee = $journees[min($tour - 1, \count($journees) - 1)];
            $match = (new Partie())->setJournee($journee)->setPoule($poule)->setNom($journee->getNom().' - Match '.$rang);
            $this->em->persist($match);

            return $match;
        };

        $noeuds = [];
        $rang = 0;
        for ($i = 0; $i < $taille / 2; ++$i) {
            [$a, $b] = [$tetes[2 * $i], $tetes[2 * $i + 1]];
            if ($b > $n) {
                $noeuds[] = ['equipe' => $equipes[$a - 1]]; // exemptée du premier tour
                continue;
            }
            $match = $creer(1, ++$rang)->setIdEquipeRecoit($equipes[$a - 1])->setIdEquipeDeplace($equipes[$b - 1]);
            $noeuds[] = ['match' => $match];
        }

        $matchs = array_filter(array_column($noeuds, 'match'));
        // Demi-finales, pour la petite finale : le premier tour quand le tableau compte 4 places
        $demis = $tours === 2 ? array_values(array_filter(array_column($noeuds, 'match'))) : [];
        for ($tour = 2; $tour <= $tours; ++$tour) {
            $suivants = [];
            $rang = 0;
            for ($j = 0; $j < \count($noeuds); $j += 2) {
                $match = $creer($tour, ++$rang);
                foreach ([$noeuds[$j], $noeuds[$j + 1]] as $cote => $noeud) {
                    if (isset($noeud['equipe'])) {
                        $cote === 0 ? $match->setIdEquipeRecoit($noeud['equipe']) : $match->setIdEquipeDeplace($noeud['equipe']);
                    } else {
                        $cote === 0 ? $match->setParentMatch1($noeud['match']) : $match->setParentMatch2($noeud['match']);
                    }
                }
                $suivants[] = ['match' => $match];
                $matchs[] = $match;
            }
            if ($tour === $tours - 1) {
                $demis = array_column($suivants, 'match');
            }
            $noeuds = $suivants;
        }

        // Petite finale (B2) : perdants des deux demi-finales, jouée le jour de la finale
        if ($this->petiteFinale($poule, $n) && \count($demis) === 2) {
            $petite = $creer($tours, 1)->setParentMatch1($demis[0])->setParentMatch2($demis[1])->setParentPerdants(true);
            $petite->setNom('Petite finale');
            $matchs[] = $petite;
        }

        // Lieu et date : chez l'équipe qui reçoit si elle est connue, sinon lieu par défaut, au premier créneau du lieu
        $lieuRepository = $this->em->getRepository(Lieu::class);
        /** @var Partie $match */
        foreach ($matchs as $match) {
            $lieu = $match->getIdEquipeRecoit()?->getLieu() ?? $lieuRepository->getLieuByDefaut();
            if ($lieu !== null) {
                $match->setLieu($lieu);
            }
            $creneau = $lieu?->getCreneaux()->first() ?: null;
            $debut = $match->getJournee()->getDateDebut();
            $match->setDate($creneau
                ? DateMatch::calculer($debut, $creneau->getJourSemaine(), $creneau->getHeureDebut())
                : $debut->setTime(20, 0));
        }

        $this->em->flush();

        // Journée des finales (D46) : gymnase et heure de ce tableau, à sa place dans le planning de toute la phase
        $this->planning->planifier($poule->getPhase(), $poule);
    }
}
