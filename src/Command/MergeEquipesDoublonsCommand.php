<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Fusionne les équipes en doublon (même nom exact) en une seule : l'import historique a
// créé plusieurs fois la même équipe (une fois par exécution de ImportOldDataCommand),
// ce qui a dupliqué des lignes `equipe` au lieu de réutiliser la même équipe à travers
// les saisons.
//
// Pour chaque groupe de doublons (équipes partageant le même nom exact), on garde
// l'équipe la plus "riche" (celle avec le plus de poules/matchs/classements/membres
// rattachés) comme équipe canonique, et on réassigne vers elle tout ce qui pointait sur
// les autres avant de les supprimer :
//   - equipe_poule (en évitant les doublons de paire poule+équipe)
//   - partie.id_equipe_recoit / id_equipe_deplace
//   - classement (en évitant les doublons de paire poule+équipe)
//   - membre_equipe (en évitant les doublons de triplet equipe+saison+joueur)
#[AsCommand(name: 'app:fusionner-equipes-doublons', description: "Fusionne les équipes en doublon (même nom) en une seule équipe canonique")]
class MergeEquipesDoublonsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
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
        $connection = $this->em->getConnection();

        $groupes = $connection->fetchAllAssociative(
            'SELECT nom, GROUP_CONCAT(id ORDER BY id) AS ids FROM equipe GROUP BY nom HAVING COUNT(*) > 1'
        );

        if ($groupes === []) {
            $io->success('Aucune équipe en doublon.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d nom(s) d\'équipe en doublon%s', count($groupes), $dryRun ? ' (dry-run)' : ''));

        $totalFusionnees = 0;

        foreach ($groupes as $groupe) {
            $ids = array_map('intval', explode(',', $groupe['ids']));
            $canonicalId = $this->choisirEquipeCanonique($connection, $ids);
            $doublonIds = array_values(array_filter($ids, static fn(int $id) => $id !== $canonicalId));

            $io->section(sprintf('%s : garder id=%d, fusionner id=%s', $groupe['nom'], $canonicalId, implode(',', $doublonIds)));

            foreach ($doublonIds as $doublonId) {
                $this->fusionnerEquipe($connection, $canonicalId, $doublonId, $dryRun, $io);
                $totalFusionnees++;
            }
        }

        $io->success(sprintf('%s%d équipe(s) en doublon fusionnée(s) et supprimée(s).', $dryRun ? '[dry-run] ' : '', $totalFusionnees));

        return Command::SUCCESS;
    }

    /**
     * Choisit l'équipe la plus "riche" (le plus de poules/matchs/classements/membres
     * rattachés) parmi un groupe de doublons, pour la garder comme équipe canonique.
     * En cas d'égalité, on garde l'id le plus petit (le plus ancien).
     *
     * @param int[] $ids
     */
    private function choisirEquipeCanonique(Connection $connection, array $ids): int
    {
        $meilleurId = null;
        $meilleurScore = -1;

        foreach ($ids as $id) {
            $score = (int) $connection->fetchOne('SELECT COUNT(*) FROM equipe_poule WHERE equipe_id = ?', [$id])
                + (int) $connection->fetchOne('SELECT COUNT(*) FROM partie WHERE id_equipe_recoit_id = ? OR id_equipe_deplace_id = ?', [$id, $id])
                + (int) $connection->fetchOne('SELECT COUNT(*) FROM classement WHERE equipe_id = ?', [$id])
                + (int) $connection->fetchOne('SELECT COUNT(*) FROM membre_equipe WHERE equipe_id = ?', [$id]);

            if ($score > $meilleurScore) {
                $meilleurScore = $score;
                $meilleurId = $id;
            }
        }

        return $meilleurId;
    }

    private function fusionnerEquipe(Connection $connection, int $canonicalId, int $doublonId, bool $dryRun, SymfonyStyle $io): void
    {
        // Lieu : si l'équipe canonique n'en a pas et que le doublon en a un, on le récupère.
        $lieuCanonical = $connection->fetchOne('SELECT lieu_id FROM equipe WHERE id = ?', [$canonicalId]);
        $lieuDoublon = $connection->fetchOne('SELECT lieu_id FROM equipe WHERE id = ?', [$doublonId]);
        if (!$lieuCanonical && $lieuDoublon) {
            $io->writeln(sprintf('  - reprise du lieu_id=%s depuis l\'équipe id=%d', $lieuDoublon, $doublonId));
            if (!$dryRun) {
                $connection->executeStatement('UPDATE equipe SET lieu_id = ? WHERE id = ?', [$lieuDoublon, $canonicalId]);
            }
        }

        // equipe_poule : réassigner chaque poule du doublon vers le canonique, sauf si le
        // canonique y est déjà (pour ne pas violer la clé primaire composite).
        $poules = $connection->fetchFirstColumn('SELECT poule_id FROM equipe_poule WHERE equipe_id = ?', [$doublonId]);
        foreach ($poules as $pouleId) {
            $dejaPresent = (int) $connection->fetchOne('SELECT COUNT(*) FROM equipe_poule WHERE poule_id = ? AND equipe_id = ?', [$pouleId, $canonicalId]);
            $io->writeln(sprintf('  - poule %d : %s', $pouleId, $dejaPresent ? 'canonique déjà présente, doublon ignoré' : 'rattachée au canonique'));
            if (!$dryRun && !$dejaPresent) {
                $connection->executeStatement('INSERT INTO equipe_poule (poule_id, equipe_id) VALUES (?, ?)', [$pouleId, $canonicalId]);
            }
        }
        if (!$dryRun) {
            $connection->executeStatement('DELETE FROM equipe_poule WHERE equipe_id = ?', [$doublonId]);
        }

        // Parties jouées : reporter sur le canonique.
        $nbParties = (int) $connection->fetchOne('SELECT COUNT(*) FROM partie WHERE id_equipe_recoit_id = ? OR id_equipe_deplace_id = ?', [$doublonId, $doublonId]);
        if ($nbParties > 0) {
            $io->writeln(sprintf('  - %d match(s) réassigné(s) au canonique', $nbParties));
            if (!$dryRun) {
                $connection->executeStatement('UPDATE partie SET id_equipe_recoit_id = ? WHERE id_equipe_recoit_id = ?', [$canonicalId, $doublonId]);
                $connection->executeStatement('UPDATE partie SET id_equipe_deplace_id = ? WHERE id_equipe_deplace_id = ?', [$canonicalId, $doublonId]);
            }
        }

        // Classements : réassigner, sauf conflit avec un classement déjà existant du
        // canonique pour la même poule (on garde alors celui du canonique).
        $classements = $connection->fetchAllAssociative('SELECT id, poule_id FROM classement WHERE equipe_id = ?', [$doublonId]);
        foreach ($classements as $classement) {
            $dejaPresent = (int) $connection->fetchOne('SELECT COUNT(*) FROM classement WHERE poule_id = ? AND equipe_id = ?', [$classement['poule_id'], $canonicalId]);
            if (!$dryRun) {
                if ($dejaPresent) {
                    $connection->executeStatement('DELETE FROM classement WHERE id = ?', [$classement['id']]);
                } else {
                    $connection->executeStatement('UPDATE classement SET equipe_id = ? WHERE id = ?', [$canonicalId, $classement['id']]);
                }
            }
        }
        if ($classements !== []) {
            $io->writeln(sprintf('  - %d ligne(s) de classement traitée(s)', count($classements)));
        }

        // Composition (membre_equipe) : réassigner, sauf conflit avec un membre déjà
        // présent côté canonique pour la même saison et le même joueur.
        $membres = $connection->fetchAllAssociative('SELECT id, saison_id, joueur_id FROM membre_equipe WHERE equipe_id = ?', [$doublonId]);
        foreach ($membres as $membre) {
            $dejaPresent = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM membre_equipe WHERE equipe_id = ? AND saison_id = ? AND joueur_id = ?',
                [$canonicalId, $membre['saison_id'], $membre['joueur_id']]
            );
            if (!$dryRun) {
                if ($dejaPresent) {
                    $connection->executeStatement('DELETE FROM membre_equipe WHERE id = ?', [$membre['id']]);
                } else {
                    $connection->executeStatement('UPDATE membre_equipe SET equipe_id = ? WHERE id = ?', [$canonicalId, $membre['id']]);
                }
            }
        }
        if ($membres !== []) {
            $io->writeln(sprintf('  - %d membre(s) de composition traité(s)', count($membres)));
        }

        if (!$dryRun) {
            $connection->executeStatement('DELETE FROM equipe WHERE id = ?', [$doublonId]);
        }
    }
}
