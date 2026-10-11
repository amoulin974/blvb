<?php

namespace App\Command;

use App\Repository\EquipeRepository;
use App\Service\ClubDepuisNomEquipeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reprise des équipes existantes : « EROAK (AOVB) » devient l'équipe « EROAK » du club AOVB (créé au besoin).
 * L'affichage reste « EROAK (AOVB) » (Equipe::getNom). Sans --force, la commande montre ce qu'elle ferait
 * sans rien enregistrer. Peut être relancée sans risque : une équipe déjà traitée n'a plus de nom court.
 */
#[AsCommand(
    name: 'app:clubs:depuis-noms-equipes',
    description: 'Crée les clubs à partir du nom court entre parenthèses à la fin du nom des équipes, et retire ce nom court du nom enregistré',
)]
final class ClubsDepuisNomsEquipesCommand extends Command
{
    public function __construct(
        private readonly EquipeRepository $equipeRepository,
        private readonly ClubDepuisNomEquipeService $service,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Enregistrer les changements (sans cette option : simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $io->title($force ? 'Clubs créés à partir du nom des équipes' : 'Simulation : rien ne sera enregistré (ajoutez --force pour appliquer)');

        $rattachees = $sansClub = $conflits = [];
        $clubsCrees = [];
        foreach ($this->equipeRepository->findBy([], ['nom' => 'ASC']) as $equipe) {
            $avant = (string) $equipe->getNomSansClub();
            $resultat = $this->service->rattacher($equipe);

            match ($resultat['statut']) {
                'rattachee' => $rattachees[] = [$avant, $resultat['nom'], $resultat['club']->getNomCourt(), $equipe->getNom()],
                'conflit' => $conflits[] = [$avant, $resultat['club']->getNomCourt()],
                'sans_club' => $avant !== $resultat['nom']
                    ? $sansClub[] = [$avant, $resultat['nom'].' (espaces retirés)']
                    : ($equipe->getClub() === null ? $sansClub[] = [$avant, 'inchangé'] : null),
            };
            if ($resultat['cree']) {
                $clubsCrees[] = $resultat['club']->getNomCourt();
            }
        }

        $io->section(sprintf('Clubs créés (%d)', \count($clubsCrees)));
        $clubsCrees ? $io->listing($clubsCrees) : $io->text('Aucun.');
        if ($clubsCrees) {
            $io->note('Le nom long de ces clubs vaut leur nom court : complétez-le dans l\'admin (Clubs).');
        }

        $io->section(sprintf('Équipes rattachées à un club (%d)', \count($rattachees)));
        $rattachees ? $io->table(['Nom en base avant', 'Nom en base après', 'Club', 'Nom affiché'], $rattachees) : $io->text('Aucune.');

        if ($sansClub) {
            $io->section(sprintf('Équipes sans nom court de club (%d) : à rattacher à la main si besoin', \count($sansClub)));
            $io->table(['Nom', 'Traitement'], $sansClub);
        }
        if ($conflits) {
            $io->section(sprintf('Équipes non modifiées : déjà rattachées à un autre club (%d)', \count($conflits)));
            $io->table(['Nom', 'Club actuel'], $conflits);
        }

        if (!$force) {
            $io->warning('Simulation terminée : rien n\'a été enregistré. Relancez avec --force pour appliquer.');

            return Command::SUCCESS;
        }

        $this->em->flush();
        $io->success('Changements enregistrés.');

        return Command::SUCCESS;
    }
}
