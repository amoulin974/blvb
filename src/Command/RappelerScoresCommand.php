<?php

namespace App\Command;

use App\Service\RappelScoreService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappelle par e-mail aux capitaines et aux responsables de club les scores non saisis au bout de 2 jours.
 * À planifier une fois par jour sur le serveur (cron), par exemple à 9 h :
 *   0 9 * * * docker compose exec -T php php bin/console app:scores:rappeler
 * Un match n'est rappelé qu'une fois : relancer la commande ne renvoie rien de plus.
 */
#[AsCommand(
    name: 'app:scores:rappeler',
    description: 'Envoie un rappel aux capitaines et responsables de club des matchs de la saison courante sans score depuis 2 jours',
)]
class RappelerScoresCommand extends Command
{
    public function __construct(private readonly RappelScoreService $rappelScoreService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('jours', null, InputOption::VALUE_REQUIRED, 'Délai en jours après la date du match', (string) RappelScoreService::DELAI_PAR_DEFAUT_JOURS)
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Affiche les rappels qui partiraient, sans envoyer ni enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jours = filter_var($input->getOption('jours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($jours === false) {
            $io->error('--jours attend un nombre entier de jours (0 ou plus).');

            return Command::INVALID;
        }

        $maintenant = new \DateTimeImmutable();

        if ($input->getOption('simulation')) {
            $rappels = $this->rappelScoreService->preparer($maintenant, $jours);
            if ($rappels === []) {
                $io->success('Aucun rappel à envoyer.');

                return Command::SUCCESS;
            }
            $io->table(['Destinataire', 'Scores à saisir'], array_map(
                static fn (string $adresse, array $rappel): array => [$adresse, \count($rappel['matchs'])],
                array_keys($rappels),
                $rappels,
            ));
            $io->note('Simulation : aucun e-mail envoyé, aucun match marqué.');

            return Command::SUCCESS;
        }

        $resultat = $this->rappelScoreService->envoyer($maintenant, $jours);
        $io->success(\sprintf('%d e-mail(s) envoyé(s) pour %d match(s).', $resultat['mails'], $resultat['matchs']));
        if ($resultat['echecs'] !== []) {
            $io->warning('Envoi refusé pour : '.implode(', ', $resultat['echecs']).'. Ces rappels seront retentés au prochain passage si aucun autre destinataire du match n\'a été prévenu.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
