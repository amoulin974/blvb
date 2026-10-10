<?php

namespace App\Command;

use App\Repository\PouleRepository;
use App\Repository\SaisonRepository;
use App\Service\ClassementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recalcule les classements à partir des scores enregistrés, par exemple après un changement
 * de barème (points de forfait mis en conformité avec le règlement : migration Version20261010201556).
 * Idempotent : peut être relancée sans risque.
 */
#[AsCommand(
    name: 'app:classement:recalculer',
    description: 'Recalcule les classements de toutes les poules (ou d\'une saison) à partir des scores enregistrés',
)]
class RecalculerClassementsCommand extends Command
{
    public function __construct(
        private readonly PouleRepository $pouleRepository,
        private readonly SaisonRepository $saisonRepository,
        private readonly ClassementService $classementService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('saison', null, InputOption::VALUE_REQUIRED, 'Identifiant de la saison à recalculer (par défaut : toutes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $poules = $this->pouleRepository->findAll();
        if ($input->getOption('saison') !== null) {
            $saison = $this->saisonRepository->find($input->getOption('saison'));
            if (!$saison) {
                $io->error('Saison introuvable.');

                return Command::FAILURE;
            }
            $poules = array_filter($poules, static fn ($poule) => $poule->getPhase()->getSaison() === $saison);
        }

        foreach ($poules as $poule) {
            $this->classementService->mettreAJourClassementPoule($poule);
        }

        $io->success(sprintf('%d poule(s) recalculée(s).', count($poules)));

        return Command::SUCCESS;
    }
}
