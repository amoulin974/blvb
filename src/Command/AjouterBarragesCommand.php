<?php

namespace App\Command;

use App\Competition\AjoutBarrages;
use App\Entity\Saison;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ajoute à une saison déjà créée les barrages du modèle « BLVB avec barrages » (App\Competition\AjoutBarrages) : entre
 * les deux phases de championnat, et après la phase finale. Sans --force : simulation, rien n'est enregistré.
 * Ensuite : vérifier les phases et passages dans « Modifier la saison » → « Ajouter des phases » (mode ajout, D51), puis
 * mettre en place les barrages (Poules, Journées, Matchs) à la clôture de la phase qui les alimente.
 */
#[AsCommand(
    name: 'app:saison:ajouter-barrages',
    description: 'Ajoute à une saison les barrages du modèle « BLVB avec barrages » (entre les phases et après la phase finale)',
)]
final class AjouterBarragesCommand extends Command
{
    public function __construct(
        private readonly SaisonRepository $saisonRepository,
        private readonly AjoutBarrages $ajout,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('saison', null, InputOption::VALUE_REQUIRED, 'Identifiant de la saison (par défaut : la saison en cours, celle affichée sur le site)')
            ->addOption('seulement', null, InputOption::VALUE_REQUIRED, '« entre » (barrages entre les phases) ou « fin » (barrages de fin de saison) ; par défaut : les deux')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Enregistrer (sans cette option : simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $seulement = $input->getOption('seulement');
        if ($seulement !== null && !\in_array($seulement, ['entre', 'fin'], true)) {
            $io->error('--seulement : « entre » ou « fin ».');

            return Command::INVALID;
        }
        $id = $input->getOption('saison');
        $saison = $id !== null ? $this->saisonRepository->find($id) : $this->saisonRepository->findCourante();
        if (!$saison instanceof Saison) {
            $io->error($id !== null ? sprintf('Aucune saison d\'identifiant %s.', $id) : 'Aucune saison en cours (saison affichée sur le site).');

            return Command::FAILURE;
        }
        $io->title(sprintf('%s : %s', $force ? 'Ajout des barrages' : 'Simulation, rien ne sera enregistré (ajoutez --force pour appliquer)', $saison->getNom()));

        $resultat = $this->ajout->ajouter($saison, $seulement !== 'fin', $seulement !== 'entre');
        foreach ($resultat['refus'] as $refus) {
            $io->warning($refus);
        }
        if ($resultat['actions'] === []) {
            $io->note('Rien à ajouter.');

            return $resultat['refus'] === [] ? Command::SUCCESS : Command::FAILURE;
        }
        $io->listing($resultat['actions']);
        foreach ($resultat['avertissements'] as $avertissement) {
            $io->caution($avertissement);
        }
        if (!$force) {
            $io->note('Simulation : relancez avec --force pour enregistrer.');

            return Command::SUCCESS;
        }
        $this->em->flush();
        $io->success('Barrages ajoutés. Vérifiez-les dans « Modifier la saison » → « Ajouter des phases » (étapes Phases et Passages).');

        return Command::SUCCESS;
    }
}
