<?php

namespace App\Command;

use App\Entity\FeuilleMatch;
use App\Entity\Saison;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Conservation des pages du match (Volley Stats, D66) : celles d'une saison sont supprimées, avec leurs sets, touches et
 * statistiques, une fois la saison suivante terminée. À planifier (cron), par exemple une fois par mois.
 */
#[AsCommand(
    name: 'app:volley-stats:purger',
    description: 'Supprime les pages du match des saisons dont la saison suivante est terminée (Volley Stats)',
)]
final class PurgerPagesMatchCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('simulation', null, InputOption::VALUE_NONE, 'Compte sans rien supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $saisons = $this->aPurger(new \DateTimeImmutable('today'));
        if ($saisons === []) {
            $io->success('Aucune page du match à supprimer.');

            return Command::SUCCESS;
        }
        $total = 0;
        foreach ($saisons as $saison) {
            $feuilles = $this->em->createQuery('SELECT f FROM '.FeuilleMatch::class.' f JOIN f.partie p JOIN p.poule po JOIN po.phase ph WHERE ph.saison = :saison')
                ->setParameter('saison', $saison)->getResult();
            $total += \count($feuilles);
            $io->text(sprintf('%s : %d page%s du match', $saison->getNom(), \count($feuilles), \count($feuilles) > 1 ? 's' : ''));
            if (!$input->getOption('simulation')) {
                array_walk($feuilles, fn (FeuilleMatch $f) => $this->em->remove($f));
            }
        }
        if ($input->getOption('simulation')) {
            $io->note(sprintf('Simulation : %d page%s du match seraient supprimées.', $total, $total > 1 ? 's' : ''));

            return Command::SUCCESS;
        }
        $this->em->flush();
        $io->success(sprintf('%d page%s du match supprimée%s.', $total, $total > 1 ? 's' : '', $total > 1 ? 's' : ''));

        return Command::SUCCESS;
    }

    /**
     * Saisons dont une saison suivante (commencée après leur fin) est terminée à cette date.
     *
     * @return list<Saison>
     */
    public function aPurger(\DateTimeImmutable $aujourdhui): array
    {
        $resultat = [];
        $saisons = $this->em->getRepository(Saison::class)->findBy(['brouillon' => false]);
        foreach ($saisons as $saison) {
            foreach ($saisons as $suivante) {
                if ($saison->getDateFin() !== null && $suivante->getDateDebut() !== null && $suivante->getDateFin() !== null
                    && $suivante->getDateDebut() >= $saison->getDateFin() && $suivante->getDateFin() < $aujourdhui) {
                    $resultat[] = $saison;
                    break;
                }
            }
        }

        return $resultat;
    }
}
