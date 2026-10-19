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
 * Phase de barrages (format C4 de docs/formats-competition.md, D42, D43) : chaque poule de la phase est un match sec
 * entre deux équipes, sur une seule journée. Qui reçoit dépend de l'option `reception` de la phase : l'équipe issue de la
 * poule de niveau supérieur (défaut), la mieux classée dans sa poule, ou terrain neutre (lieu par défaut).
 * Le vainqueur et le perdant sont ensuite désignés par `PHASE.POULE.vainqueur` / `.perdant`.
 */
final class Barrage implements GenerateurFormat
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function supporte(Phase $phase): bool
    {
        return $phase->getType() === PhaseType::BARRAGE;
    }

    public function estOptimisable(): bool
    {
        return false;
    }

    public function nombreMatchs(Poule $poule): int
    {
        return \count($poule->getEquipes()) >= 2 ? 1 : 0;
    }

    public function nombreJournees(Poule $poule): int
    {
        return $this->nombreMatchs($poule);
    }

    public function creerJournees(Poule $poule): ?string
    {
        $nb = \count($poule->getEquipes());
        if ($nb !== 2) {
            return sprintf('%s : un barrage oppose exactement deux équipes (%d dans la poule).', $poule->getNom(), $nb);
        }

        $debut = \DateTimeImmutable::createFromInterface($poule->getPhase()->getDatedebut());
        $journee = (new Journee())
            ->setNom('Barrage')
            ->setNumero(1)
            ->setDateDebut($debut)
            ->setDateFin($debut->modify('+6 days'))
            ->setPoule($poule);
        $this->em->persist($journee);
        $this->em->flush();

        return null;
    }

    public function genererMatchs(Poule $poule): void
    {
        $equipes = array_values($poule->getEquipes()->toArray());
        $journees = $poule->getJournees()->toArray();
        usort($journees, fn (Journee $a, Journee $b) => $a->getNumero() <=> $b->getNumero());
        if (\count($equipes) !== 2 || $journees === []) {
            return;
        }

        $phase = $poule->getPhase();
        $reception = $phase->getReception();
        [$recoit, $deplace] = $this->ordre($phase, $poule, $equipes[0], $equipes[1], $reception);

        $lieuRepository = $this->em->getRepository(Lieu::class);
        $lieu = $reception === Phase::RECEPTION_NEUTRE ? $lieuRepository->getLieuByDefaut() : ($recoit->getLieu() ?? $lieuRepository->getLieuByDefaut());

        $partie = (new Partie())
            ->setJournee($journees[0])
            ->setPoule($poule)
            ->setNom('Barrage '.($poule->getCode() ?: $poule->getNom()))
            ->setIdEquipeRecoit($recoit)
            ->setIdEquipeDeplace($deplace);
        if ($lieu !== null) {
            $partie->setLieu($lieu);
        }
        $creneau = $lieu?->getCreneaux()->first() ?: null;
        $debut = $journees[0]->getDateDebut();
        $partie->setDate($creneau
            ? DateMatch::calculer($debut, $creneau->getJourSemaine(), $creneau->getHeureDebut())
            : $debut->setTime(20, 0));

        $this->em->persist($partie);
        $this->em->flush();
    }

    /**
     * Équipe qui reçoit, puis celle qui se déplace.
     *
     * @return array{0: Equipe, 1: Equipe}
     */
    private function ordre(Phase $phase, Poule $poule, Equipe $a, Equipe $b, string $reception): array
    {
        if ($reception !== Phase::RECEPTION_NEUTRE) {
            $origineA = $this->origine($phase, $a);
            $origineB = $this->origine($phase, $b);
            // Équipe de la poule supérieure (niveau le plus petit), ou la mieux classée dans sa poule
            [$cleA, $cleB] = $reception === Phase::RECEPTION_MIEUX_CLASSE
                ? [$origineA['position'], $origineB['position']]
                : [$origineA['niveau'], $origineB['niveau']];
            if ($cleB !== null && ($cleA === null || $cleB < $cleA)) {
                return [$b, $a];
            }
        }

        return [$a, $b];
    }

    /**
     * Niveau et rang de l'équipe dans la poule qu'elle occupait dans la phase précédente.
     *
     * @return array{niveau: ?int, position: ?int}
     */
    private function origine(Phase $phase, Equipe $equipe): array
    {
        $precedentes = [];
        foreach ($phase->getSaison()->getPhases() as $autre) {
            if ($autre->getOrdre() < $phase->getOrdre() && $autre->getType() !== PhaseType::FINALE) {
                $precedentes[] = $autre;
            }
        }
        usort($precedentes, fn (Phase $x, Phase $y) => $y->getOrdre() <=> $x->getOrdre());

        foreach ($precedentes as $autre) {
            foreach ($autre->getPoules() as $poule) {
                if (!$poule->getEquipes()->contains($equipe)) {
                    continue;
                }
                $position = null;
                foreach ($poule->getClassements() as $classement) {
                    if ($classement->getEquipe() === $equipe) {
                        $position = $classement->getPosition();
                    }
                }

                return ['niveau' => $poule->getNiveau(), 'position' => $position];
            }
        }

        return ['niveau' => null, 'position' => null];
    }
}
