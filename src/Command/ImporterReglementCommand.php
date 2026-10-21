<?php

namespace App\Command;

use App\Entity\Saison;
use App\Reglement\EditionReglement;
use App\Reglement\RenduReglement;
use App\Repository\ReglementRepository;
use App\Repository\SaisonRepository;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reprise du règlement 2025, à lancer une fois au déploiement du règlement par saison (docs/wizard-saison/reglement.md,
 * étape R5, D57) : verse le texte de l'ancienne page en dur (src/Reglement/Reprise/reglement-2025.html, ex-gabarit
 * front/reglement_2025.html.twig) dans le règlement de la saison en cours, images comprises (public/images/reglement).
 * Le texte passe par l'enregistrement normal (nettoyage, version, date) ; les ancres sont recalculées à l'affichage
 * (« RÈGLE 1R » → #regle-1r, lien de la fenêtre de saisie du score).
 *
 * Sans --force : simulation, rien n'est enregistré. Un règlement déjà rédigé n'est remplacé qu'avec --remplacer
 * (le texte actuel reste alors dans l'historique, restaurable).
 */
#[AsCommand(
    name: 'app:reglement:importer',
    description: 'Verse le règlement 2025 (texte et images) dans le règlement de la saison en cours',
)]
final class ImporterReglementCommand extends Command
{
    public const SOURCE = __DIR__.'/../Reglement/Reprise/reglement-2025.html';

    public function __construct(
        private readonly SaisonRepository $saisonRepository,
        private readonly ReglementRepository $reglementRepository,
        private readonly EditionReglement $edition,
        private readonly RenduReglement $rendu,
        private readonly Packages $packages,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $dossierPublic,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('saison', null, InputOption::VALUE_REQUIRED, 'Identifiant de la saison (par défaut : la saison en cours, celle affichée sur le site)')
            ->addOption('remplacer', null, InputOption::VALUE_NONE, 'Remplacer un règlement déjà rédigé (le texte actuel reste dans l\'historique)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Enregistrer (sans cette option : simulation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $io->title($force ? 'Reprise du règlement 2025' : 'Simulation : rien ne sera enregistré (ajoutez --force pour appliquer)');

        $id = $input->getOption('saison');
        $saison = $id !== null ? $this->saisonRepository->find($id) : $this->saisonRepository->findCourante();
        if (!$saison instanceof Saison) {
            $io->error($id !== null ? sprintf('Aucune saison d\'identifiant %s.', $id) : 'Aucune saison en cours (saison affichée sur le site).');

            return Command::FAILURE;
        }
        if ($saison->isBrouillon()) {
            $io->error(sprintf('« %s » est un brouillon du wizard : son règlement ne serait pas public.', $saison->getNom()));

            return Command::FAILURE;
        }

        // Images : adresse du site (préfixe /blvb en production), fichier présent dans public/
        $manquantes = [];
        $html = preg_replace_callback('#src="(images/reglement/[^"]+)"#', function (array $m) use (&$manquantes) {
            if (!is_file($this->dossierPublic.'/'.$m[1])) {
                $manquantes[] = $m[1];
            }

            return sprintf('src="%s"', $this->packages->getUrl($m[1]));
        }, (string) file_get_contents(self::SOURCE));

        $nettoye = $this->edition->nettoyer($html);
        $rendu = $this->rendu->rendre($saison, $nettoye, true)['html'];
        $existant = $this->reglementRepository->findDeSaison($saison);
        $dejaRedige = $existant !== null && trim($existant->getContenu()) !== '';

        $io->definitionList(
            ['Saison' => sprintf('%s (identifiant %d)', $saison->getNom(), $saison->getId())],
            ['Parties (h2)' => substr_count($nettoye, '<h2')],
            ['Règles (h3)' => substr_count($nettoye, '<h3')],
            ['Images' => substr_count($nettoye, '<img')],
            ['Ancre #regle-1r' => str_contains($rendu, 'id="regle-1r"') ? 'présente' : 'ABSENTE'],
            ['Règlement actuel' => $dejaRedige ? 'déjà rédigé, mis à jour le '.$existant->getModifieLe()?->format('d/m/Y à H:i') : 'pas encore rédigé'],
        );
        foreach ($manquantes as $image) {
            $io->warning(sprintf('Image absente du serveur : public/%s (elle ne s\'affichera pas).', $image));
        }
        if (!str_contains($rendu, 'id="regle-1r"')) {
            $io->warning('Le titre « RÈGLE 1R » est introuvable : le lien de la fenêtre de saisie du score ne mènera nulle part.');
        }

        if ($dejaRedige && !$input->getOption('remplacer')) {
            $io->error('Le règlement de cette saison est déjà rédigé : rien n\'est fait. Ajoutez --remplacer pour le remplacer (le texte actuel restera dans l\'historique).');

            return Command::FAILURE;
        }
        if (!$force) {
            $io->note('Simulation terminée. Relancez avec --force pour enregistrer.');

            return Command::SUCCESS;
        }

        $this->edition->enregistrer($saison, $html, null);
        $io->success(sprintf('Règlement 2025 versé dans « %s » et publié. À relire dans l\'administration : Règlement.', $saison->getNom()));

        return Command::SUCCESS;
    }
}
