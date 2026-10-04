<?php

namespace App\Command;

use App\Entity\Equipe;
use App\Entity\Joueur;
use App\Entity\MembreEquipe;
use App\Repository\EquipeRepository;
use App\Repository\JoueurRepository;
use App\Repository\MembreEquipeRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Migre l'ancien capitaine global d'une équipe (colonne equipe.capitaine_id) vers la
// nouvelle composition par saison (MembreEquipe::capitaine). À lancer une seule fois,
// sur chaque environnement (dev, prod...), après avoir déployé le code qui ne lit/écrit
// plus Equipe::capitaine, et avant la migration Doctrine qui supprime la colonne
// capitaine_id.
//
// Lit volontairement la colonne capitaine_id en SQL brut plutôt que via l'entité Equipe :
// le champ Doctrine correspondant a déjà été retiré de App\Entity\Equipe dans ce même
// déploiement, cette commande doit donc rester fonctionnelle sans lui.
#[AsCommand(name: 'app:migrate-capitaines-vers-membres', description: "Migre les anciens capitaines (colonne equipe.capitaine_id) vers la composition par saison (MembreEquipe)")]
class MigrateCapitaineToMembreCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipeRepository $equipeRepository,
        private readonly UserRepository $userRepository,
        private readonly JoueurRepository $joueurRepository,
        private readonly MembreEquipeRepository $membreEquipeRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, "Affiche ce qui serait fait sans rien écrire en base");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $rows = $this->em->getConnection()->fetchAllAssociative(
                'SELECT id, capitaine_id FROM equipe WHERE capitaine_id IS NOT NULL'
            );
        } catch (DbalException $e) {
            $io->error("Impossible de lire la colonne equipe.capitaine_id (déjà supprimée ?) : ".$e->getMessage());

            return Command::FAILURE;
        }

        if ($rows === []) {
            $io->success('Aucune équipe avec un capitaine à migrer.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d équipe(s) avec un capitaine à migrer%s', count($rows), $dryRun ? ' (dry-run)' : ''));

        $nbCrees = 0;
        $nbDejaPresents = 0;
        $equipesSansSaison = [];

        foreach ($rows as $row) {
            $equipe = $this->equipeRepository->find($row['id']);
            $capitaineUser = $this->userRepository->find($row['capitaine_id']);

            if (!$equipe || !$capitaineUser) {
                continue;
            }

            // Saisons déduites des poules actuelles de l'équipe : on ne peut pas deviner
            // la saison d'un capitaine pour une équipe qui n'est rattachée à aucune poule.
            $saisons = [];
            foreach ($equipe->getPoules() as $poule) {
                $saison = $poule->getPhase()->getSaison();
                $saisons[$saison->getId()] = $saison;
            }

            if ($saisons === []) {
                $equipesSansSaison[] = [$equipe, $capitaineUser];
                continue;
            }

            $joueur = $this->joueurRepository->findOneBy(['user' => $capitaineUser]);
            if (!$joueur) {
                $joueur = (new Joueur())
                    ->setNom($capitaineUser->getNom() ?? '')
                    ->setPrenom($capitaineUser->getPrenom() ?? '')
                    ->setEmail($capitaineUser->getEmail())
                    ->setTelephone($capitaineUser->getTelephone())
                    ->setUser($capitaineUser);

                $io->writeln(sprintf('  Création de la fiche joueur %s %s (compte %s)', $joueur->getPrenom(), $joueur->getNom(), $capitaineUser->getEmail()));

                if (!$dryRun) {
                    $this->em->persist($joueur);
                }
            }

            foreach ($saisons as $saison) {
                if ($this->membreEquipeRepository->findOneBy(['equipe' => $equipe, 'saison' => $saison, 'joueur' => $joueur])) {
                    $nbDejaPresents++;
                    continue;
                }

                $io->writeln(sprintf('  %s : capitaine %s %s pour %s', $equipe->getNom(), $capitaineUser->getPrenom(), $capitaineUser->getNom(), $saison->getNom()));

                if (!$dryRun) {
                    $membre = (new MembreEquipe())
                        ->setEquipe($equipe)
                        ->setSaison($saison)
                        ->setJoueur($joueur)
                        ->setCapitaine(true);
                    $this->em->persist($membre);
                }

                $nbCrees++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        if ($equipesSansSaison !== []) {
            $io->warning(sprintf(
                "%d équipe(s) ont un capitaine mais ne sont rattachées à aucune saison (aucune poule) : impossible de déduire la saison, à traiter manuellement :\n- %s",
                count($equipesSansSaison),
                implode("\n- ", array_map(
                    static fn(array $pair) => $pair[0]->getNom().' (id '.$pair[0]->getId().', capitaine : '.$pair[1]->getEmail().')',
                    $equipesSansSaison
                ))
            ));
        }

        $io->success(sprintf(
            '%s%d ligne(s) de composition créée(s), %d déjà existante(s) ignorée(s).',
            $dryRun ? '[dry-run] ' : '',
            $nbCrees,
            $nbDejaPresents
        ));

        return Command::SUCCESS;
    }
}
