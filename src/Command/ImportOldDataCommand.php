<?php
// src/Command/ImportOldDataCommand.php

namespace App\Command;

use App\Entity\Saison;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\Creneau;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-old-data', description: 'Importe les données de blvb_old')]
class ImportOldDataCommand extends Command
{
    public function __construct(private ManagerRegistry $doctrine)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $oldConn = $this->doctrine->getConnection('old'); // Connexion DBAL vers blvb_old [cite: 18]
        $em = $this->doctrine->getManager('default');   // EntityManager vers la nouvelle base [cite: 18]

        $io->title('Démarrage de la migration BLVB');

        // --- 1. Import des Saisons ---
        $oldSaisons = $oldConn->fetchAllAssociative('SELECT * FROM tvbasaison');
        $saisonMap = []; // Pour faire le lien avec les équipes plus tard

        foreach ($oldSaisons as $oldS) {
            $io->text("Migration saison : " . $oldS['nom']);
            $saison = new Saison();
            $saison->setNom($oldS['nom']);
            $saison->setFavori($oldS['active'] == 1);

            $em->persist($saison);
            $saisonMap[$oldS['nom']] = $saison;
        }
        $em->flush();

        // --- 2. Import des Equipes, Lieux et Créneaux ---
        $oldEquipes = $oldConn->fetchAllAssociative('SELECT * FROM tvbaequipe');
        $lieuxMap = [];

        foreach ($oldEquipes as $oldE) {
            $io->text("Migration équipe : " . $oldE['nom']);

            // Gestion du Lieu (Dédoublonnement par nom)
            $nomLieu = $oldE['lieu'] ?: 'Lieu inconnu';
            if (!isset($lieuxMap[$nomLieu])) {
                $lieu = new Lieu();
                $lieu->setNom($nomLieu);

                // Création du créneau si infos présentes
                if ($oldE['jour'] && $oldE['heure']) {
                    $creneau = new Creneau();
                    $creneau->setJourSemaine($this->mapJour($oldE['jour']));

                    $heureStr = str_replace('h', ':', strtolower($oldE['heure']));
                    $creneau->setHeureDebut(new \DateTime($heureStr));

                    $lieu->addCreneau($creneau);
                    $em->persist($creneau);
                }
                $em->persist($lieu);
                $lieuxMap[$nomLieu] = $lieu;
            }

            // Création de l'Equipe
            $equipe = new Equipe();
            $equipe->setNom($oldE['nom']);
            $equipe->setLieu($lieuxMap[$nomLieu]);

            $em->persist($equipe);
        }

        $em->flush();
        $io->success('Migration terminée avec succès !');

        return Command::SUCCESS;
    }

    private function mapJour(?string $jour): int
    {
        $map = [
            'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4,
            'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7
        ];
        return $map[strtolower($jour)] ?? 1;
    }
}
