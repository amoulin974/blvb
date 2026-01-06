<?php
// src/Command/ImportOldDataCommand.php

namespace App\Command;

use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\Creneau;
use App\Enum\PhaseType;
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
        $platform = $em->getConnection()->getDatabasePlatform();
        $io->title('Démarrage de la migration BLVB');

        //Suppression des données existante
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');

        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('lieu', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('creneau', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('saison', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('phase', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('poule', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('equipe', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('equipe_poule', true));

        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');

        //Création de la saison
        $saison=new Saison();
        $saison->setNom('Saison 2025-2026');
        $saison->setFavori(1);
        $dateDebut = new \DateTimeImmutable('2025-09-01');
        $saison->setDateDebut($dateDebut);
        $dateFin = new \DateTimeImmutable('2026-07-01');
        $saison->setDateDebut($dateFin);
        $saison->setPointsDefaiteFaible(0);
        $saison->setPointsDefaiteForte(1);
        $saison->setPointsVictoireFaible(2);
        $saison->setPointsVictoireForte(3);
        $saison->setPointsForfait(3);
        $saison->setPointsNul(1);

        $em->persist($saison);
        //Création des phases
        $phase1=new Phase();
        $phase1->setNom("Phase 1");
        $phase1->setType(PhaseType::CHAMPIONNAT);
        $phase1->setOrdre(0);
        $dateDebut=new \DateTimeImmutable('2025-09-01');
        $dateFin=new \DateTimeImmutable('2026-01-11');
        $phase1->setDatedebut($dateDebut);
        $phase1->setDatefin($dateFin);

        $phase2=new Phase();
        $phase2->setNom("Phase 2");
        $phase2->setType(PhaseType::CHAMPIONNAT);
        $phase2->setOrdre(1);
        $dateDebut=new \DateTimeImmutable('2026-01-12');
        $dateFin=new \DateTimeImmutable('2026-05-31');
        $phase2->setDatedebut($dateDebut);
        $phase2->setDatefin($dateFin);

        $phase3=new Phase();
        $phase3->setNom("Phase 2");
        $phase3->setType(PhaseType::FINALE);
        $phase3->setOrdre(2);
        $dateDebut=new \DateTimeImmutable('2026-06-01');
        $dateFin=new \DateTimeImmutable('2026-06-30');
        $phase3->setDatedebut($dateDebut);
        $phase3->setDatefin($dateFin);


        $phase1->setSaison($saison);
        $phase2->setSaison($saison);
        $phase3->setSaison($saison);
        $em->persist($phase1);
        $em->persist($phase2);
        $em->persist($phase3);

        $saison->addPhase($phase1);
        $saison->addPhase($phase2);
        $saison->addPhase($phase3);

        $tabPhase=[$phase1, $phase2, $phase3];
        $tabNomPoules = ['Poule A', 'Poule B', 'Poule C', 'Poule D'];

        for($i=0; $i<3; $i++){
            for ($y=0; $y<4; $y++ ){
                $poule=new Poule();
                $poule->setNom($tabNomPoules[$y]);
                if ($y != 3){
                    $poule->setNbDescenteDefaut(2);
                }else{
                    $poule->setNbDescenteDefaut(0);
                }
                if ($y != 0){
                    $poule->setNbDescenteDefaut(0);
                }else{
                    $poule->setNbDescenteDefaut(2);
                }
                $poule->setNiveau($y);
                $poule->setPhase($tabPhase[$i]);
                $tabPhase[$i]->addPoule($poule);

                $em->persist($poule);

            }
        }



        //Création des lieux et des équipes

        $oldEquipes = $oldConn->fetchAllAssociative('SELECT * FROM `tvbaequipe` WHERE saison like "2025/2026%"');
        $tabNewLieu=[];
        $tabNewEquipe=[];
        foreach ($oldEquipes as $oldEquipe){
            $nomOriginalLieu = $oldEquipe['lieu'];
            //Si le lieu n'existe pas déjà
            if (! isset($tabNewLieu[$nomOriginalLieu])){

                $newLieu=new Lieu();

                $posVirgule = strpos($nomOriginalLieu, ",");
                $nomCourt = ($posVirgule !== false) ? substr($nomOriginalLieu, 0, $posVirgule) : $nomOriginalLieu;
                $newLieu->setNom($nomCourt);
                $newLieu->setAdresse($nomOriginalLieu);


                $creneau=new Creneau();
                $creneau->setJourSemaine($this->mapJour($oldEquipe['jour']));
                $heureStr = str_replace('h', ':', strtolower($oldEquipe['heure']));
                $creneau->setHeureDebut(new \DateTimeImmutable($heureStr ?: '20:00'));

                $creneau->setCapacite(2);
                $creneau->setPrioritaire(1);
                $heureFin = $creneau->getHeureDebut()->modify('+2 hours');
                $creneau->setHeureFin($heureFin);
                $newLieu->addCreneau($creneau);
                $tabNewLieu[$nomOriginalLieu]=$newLieu;
                $em->persist($newLieu);
                $em->persist($creneau);
            }

            $equipe = new Equipe();
            $equipe->setNom($oldEquipe['nom']);
            $equipe->setLieu($tabNewLieu[$nomOriginalLieu]);

            //Affectation de l'équipe à une poule
                // 1. Découpage de la chaîne : "2025/2026-GroupeD-Phase1"
            $parts = explode('-', $oldEquipe['saison']);
            if (count($parts) < 3) continue; // Sécurité si le format est incorrect

            $groupePart = $parts[1]; // "GroupeD"
            $phasePart  = $parts[2]; // "Phase1"

                // 2. Identification de la Phase (Phase1 -> index 0, Phase2 -> index 1...)
                // On extrait le chiffre et on soustrait 1 pour l'index du tableau
            $phaseNum = (int) filter_var($phasePart, FILTER_SANITIZE_NUMBER_INT);
            $phaseIndex = $phaseNum - 1;

            if (isset($tabPhase[$phaseIndex])) {
                $currentPhase = $tabPhase[$phaseIndex];

                    // 3. Identification de la Poule dans cette phase
                    // On transforme "GroupeD" en "Poule D"
                $nomPouleCherche = "Poule " . str_replace('Groupe', '', $groupePart);

                foreach ($currentPhase->getPoules() as $poule) {
                    if ($poule->getNom() === $nomPouleCherche) {

                        $poule->addEquipe($equipe);


                        break;
                    }
                }
            }
            $tabNewEquipe[$oldEquipe['code']]=$equipe;

            $em->persist($equipe);
            $tabNewLieu[$nomOriginalLieu]->addEquipe($equipe);




        }
        //TODO créer le bon nombre de journées en fonction du nombre déquipe
        //Création des journée
        foreach ($tabPhase as $phase){
            foreach ($phase->getPoules() as $poule){
                for ($i=0; $i<count($poule->getEquipes()); $i++){
                    $journee=new Journee();
                    $journee->setDateDebut(new \DateTimeImmutable('2025-09-01'));
                }
            }
        }

        //Récupération des matchs
        $oldMatch = $oldConn->fetchAllAssociative('SELECT * FROM `tvbaresultat` WHERE saison like "2025/2026%"');
        $partie=new Partie();
        if (!isset($tabNewLieu[$oldMatch['lieupartie']])){
            dump("erreur lieu match". $oldMatch['code']);
        }else{
            $partie->setLieu($tabNewLieu[$oldMatch['lieupartie']]);
        }
        $partie->setDate(new \DateTimeImmutable($oldMatch['datepartie']));
        $partie->setIdEquipeRecoit($tabNewEquipe[$oldMatch['equipe1']]);
        $partie->setIdEquipeDeplace($tabNewEquipe[$oldMatch['equipe2']]);
        $partie->setNbSetGagnantReception($oldMatch['set1']);
        $partie->setNbSetGagnantDeplacement($oldMatch['set2']);
        $em->flush();
        $io->success('Migration terminée avec succès !');



        //------------------------------------------------------------------------
        /**
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
*/
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
