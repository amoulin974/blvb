<?php

namespace App\Command;

use App\Repository\ParametresSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Secours (D62) : un admin bloqué par la limitation des essais de connexion ne peut plus atteindre la page des paramètres. */
#[AsCommand(
    name: 'app:connexion-limitee:desactiver',
    description: 'Désactive la limitation des essais de connexion, si un administrateur est bloqué',
)]
final class DesactiverConnexionLimiteeCommand extends Command
{
    public function __construct(
        private readonly ParametresSiteRepository $parametres,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $parametres = $this->parametres->courants();

        if (!$parametres->isConnexionLimitee()) {
            $io->success('La limitation des essais de connexion était déjà désactivée : rien à faire.');

            return Command::SUCCESS;
        }

        $parametres->setConnexionLimitee(false);
        $this->entityManager->persist($parametres);
        $this->entityManager->flush();
        $this->logger->notice('Limitation des essais de connexion désactivée par la commande de secours');
        $io->success('Limitation des essais de connexion désactivée. Pour la réactiver : page « Paramètres du site » de l\'administration.');

        return Command::SUCCESS;
    }
}
