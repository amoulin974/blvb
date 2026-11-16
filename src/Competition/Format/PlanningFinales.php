<?php

namespace App\Competition\Format;

use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Journée des finales (format C3, décisions D45 et D46) : les derniers tours de tous les tableaux d'une phase à
 * élimination se jouent le même jour, dans un gymnase, par créneaux d'autant de matchs que de terrains.
 * Ordre : demi-finales d'abord (s'il y en a), puis petites finales et finales, chaque fois du niveau le plus bas au
 * niveau 1 ; la finale du niveau 1 est le dernier match. Les finales commencent toujours à un créneau qui suit les
 * demi-finales, puisque leurs équipes en sortent.
 */
final class PlanningFinales
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Journées d'un tableau jouées le jour des finales : ses $nbTours dernières journées.
     *
     * @return list<Journee> dans l'ordre des numéros
     */
    public static function journeesRegroupees(Poule $poule, int $nbTours): array
    {
        if ($nbTours <= 0) {
            return [];
        }
        $journees = $poule->getJournees()->toArray();
        usort($journees, fn (Journee $a, Journee $b) => $a->getNumero() <=> $b->getNumero());

        return array_slice($journees, -$nbTours);
    }

    /**
     * Place les matchs de la journée des finales (gymnase, date, heure) ; sans effet sans l'option. Les créneaux sont
     * calculés pour toute la phase, mais seuls les matchs de $seulement sont placés quand elle est indiquée : régénérer
     * un tableau ne déplace pas les finales des autres, éventuellement retouchées à la main. Un match qui a un score
     * n'est jamais déplacé.
     */
    public function planifier(Phase $phase, ?Poule $seulement = null): void
    {
        $option = $phase->getJourneeFinales();
        $nbTours = $phase->nbToursJourneeFinales();
        if ($option === null || $nbTours === 0 || $option['date'] === null) {
            return;
        }
        $lieu = $option['lieu'] !== null ? $this->em->find(Lieu::class, $option['lieu']) : null;
        $lieu ??= $this->em->getRepository(Lieu::class)->getLieuByDefaut();

        // Tableaux du niveau le plus bas au niveau 1
        $poules = $phase->getPoules()->toArray();
        usort($poules, fn (Poule $a, Poule $b) => [$b->getNiveau() ?? 0, (string) $a->getNom()] <=> [$a->getNiveau() ?? 0, (string) $b->getNom()]);

        $demis = $finales = [];
        foreach ($poules as $poule) {
            $journees = self::journeesRegroupees($poule, $nbTours);
            if ($journees === []) {
                continue;
            }
            $derniere = array_pop($journees);
            foreach ($journees as $journee) { // la demi-finale, quand elle est regroupée et que le tableau en a une
                array_push($demis, ...$this->matchs($journee));
            }
            $matchs = $this->matchs($derniere);
            usort($matchs, fn (Partie $a, Partie $b) => (int) !$a->isParentPerdants() <=> (int) !$b->isParentPerdants()); // petite finale d'abord
            array_push($finales, ...$matchs);
        }

        $debut = $option['date']->setTime(...array_map('intval', explode(':', $option['heure'])));
        $creneau = 0;
        foreach ([$demis, $finales] as $groupe) {
            foreach (array_values($groupe) as $i => $match) {
                if (($seulement !== null && $match->getPoule() !== $seulement) || $match->getNbSetGagnantReception() !== null) {
                    continue;
                }
                $minutes = ($creneau + intdiv($i, $option['terrains'])) * $option['duree'];
                $match->setDate($debut->modify(sprintf('+%d minutes', $minutes)));
                if ($lieu !== null) {
                    $match->setLieu($lieu);
                }
            }
            $creneau += (int) ceil(\count($groupe) / $option['terrains']);
        }
        $this->em->flush();
    }

    /** @return list<Partie> matchs de la journée, dans l'ordre de leur nom (« Demi-finale - Match 1 », « 2 »…) */
    private function matchs(Journee $journee): array
    {
        // Requête plutôt que la collection : les matchs viennent d'être créés dans la même unité de travail
        $matchs = $this->em->getRepository(Partie::class)->findBy(['journee' => $journee]);
        usort($matchs, fn (Partie $a, Partie $b) => strnatcmp((string) $a->getNom(), (string) $b->getNom()));

        return $matchs;
    }
}
