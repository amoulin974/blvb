<?php

namespace App\Competition;

use App\Entity\Phase;
use App\Entity\Saison;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wizard en mode ajout d'une saison lancée (docs/wizard-saison/decisions.md, D51) : les phases commencées, et toutes
 * celles qui les précèdent, sont figées ; les phases placées après la dernière phase commencée restent libres.
 */
final class ModeAjout
{
    public function __construct(private CompositionPoules $composition, private EntityManagerInterface $em)
    {
    }

    /**
     * Phase jouée (D54) : clôturée, au moins un score saisi ou un classement non nul. Ses matchs, journées et poules ne
     * se suppriment plus en masse ; avant le premier score, on peut encore supprimer et régénérer.
     */
    public function jouee(Phase $phase): bool
    {
        if ($phase->getClose()) {
            return true;
        }
        $scores = $this->em->createQuery('SELECT COUNT(p.id) FROM App\Entity\Partie p JOIN p.poule po WHERE po.phase = :phase AND (p.nb_set_gagnant_reception IS NOT NULL OR p.nb_set_gagnant_deplacement IS NOT NULL)')
            ->setParameter('phase', $phase)->getSingleScalarResult();
        if ($scores > 0) {
            return true;
        }
        $classements = $this->em->createQuery('SELECT COUNT(c.id) FROM App\Entity\Classement c JOIN c.poule po WHERE po.phase = :phase AND (c.points <> 0 OR c.setGagnes > 0 OR c.setPerdus > 0)')
            ->setParameter('phase', $phase)->getSingleScalarResult();

        return $classements > 0;
    }

    /** Message de refus d'une suppression en masse dans une phase jouée (D54), ex. $quoi = « les matchs de Poule A ». */
    public static function refusSuppression(string $quoi, Phase $phase): string
    {
        $raison = $phase->getClose()
            ? sprintf('%s est clôturée', $phase->getNom())
            : sprintf('des matchs de %s ont déjà été joués', $phase->getNom());

        return sprintf('Impossible de supprimer %s : %s. Rien n\'a été modifié.', $quoi, $raison);
    }

    /**
     * Phases figées, dans l'ordre : de la première phase jusqu'à la dernière phase commencée (vide si aucune ne l'est).
     *
     * @return list<Phase>
     */
    public function phasesFigees(Saison $saison): array
    {
        $phases = CompositionPoules::phases($saison);
        $derniere = -1;
        foreach ($phases as $i => $phase) {
            if ($this->commencee($phase)) {
                $derniere = $i;
            }
        }

        return \array_slice($phases, 0, $derniere + 1);
    }

    /**
     * Phase commencée : clôturée, avec un match ou un classement non nul (CompositionPoules::verrouillee), avec des
     * équipes dans ses poules (composition faite) ou des lignes de classement, même à zéro (leur poule ne se supprime
     * pas sans elles).
     */
    public function commencee(Phase $phase): bool
    {
        if ($this->composition->verrouillee($phase)) {
            return true;
        }
        foreach ($phase->getPoules() as $poule) {
            if (!$poule->getEquipes()->isEmpty() || !$poule->getClassements()->isEmpty()) {
                return true;
            }
        }

        return false;
    }
}
