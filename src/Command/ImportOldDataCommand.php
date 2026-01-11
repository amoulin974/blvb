<?php
// src/Command/ImportOldDataCommand.php

namespace App\Command;

use App\Entity\Indisponibilite;
use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\Creneau;
use App\Entity\User;
use App\Enum\PhaseType;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;
use function Zenstruck\Foundry\faker;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\ClassementService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import-old-data', description: 'Importe les données de blvb_old')]
class ImportOldDataCommand extends Command
{
    public function __construct(
        private ManagerRegistry   $doctrine,
        private ClassementService $classementService,
        private UserPasswordHasherInterface $passwordHasher
    )
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
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('journee', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('indisponibilite', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('partie', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('classement', true));
        $em->getConnection()->executeStatement($platform->getTruncateTableSQL('user', true));

        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');

        //Création de l'administrateur
        // 2. Création de l'Administrateur
        // La factory gère le hachage grâce au UserPasswordHasherInterface injecté
        UserFactory::createOne([
            'email' => 'admin@blvb.fr',
            'roles' => ['ROLE_ADMIN'],
            'password' => 'fakepassword123#',
            'nom' => 'Administrateur',
            'prenom' => 'Principal'
        ]);

        //Récupération des dates de la saison
        $oldIndisponibilites = $oldConn->fetchAssociative('SELECT * FROM `tvbadatesaison` WHERE saison like "2025/2026-GroupeA-Phase1"');

        //Création de la saison
        $saison = new Saison();
        $saison->setNom('Saison 2025-2026');
        $saison->setFavori(1);
        $dateDebut = new \DateTimeImmutable($oldIndisponibilites['date_init']);
        $saison->setDateDebut($dateDebut);
        $dateFin = $dateDebut->modify('+10 month');
        $saison->setDateDebut($dateFin);
        $saison->setPointsDefaiteFaible(0);
        $saison->setPointsDefaiteForte(1);
        $saison->setPointsVictoireFaible(2);
        $saison->setPointsVictoireForte(3);
        $saison->setPointsForfait(3);
        $saison->setPointsNul(1);

        //Création des indisponibilités
        //vacance de la toussain
        $indisp1 = new Indisponibilite();
        $indisp1->setNom("Vacance de la toussaint");
        $indisp1->setSaison($saison);
        $dateDebut = new \DateTimeImmutable($oldIndisponibilites['date_ts1']);
        $indisp1->setDateDebut($dateDebut);
        $date2semaine = new \DateTimeImmutable($oldIndisponibilites['date_ts2']);
        $dateFin = $date2semaine->modify('+6 day');
        $indisp1->setDateFin($dateFin);
        $em->persist($indisp1);


        //Vacance de noel
        $indisp2 = new Indisponibilite();
        $indisp2->setNom("Vacance de noel");
        $indisp2->setSaison($saison);
        $dateDebut = new \DateTimeImmutable($oldIndisponibilites['date_no1']);
        $indisp2->setDateDebut($dateDebut);
        $date2semaine = new \DateTimeImmutable($oldIndisponibilites['date_no2']);
        $dateFin = $date2semaine->modify('+6 day');
        $indisp2->setDateFin($dateFin);
        $em->persist($indisp2);

        //Vacance d'hivers
        $indisp3 = new Indisponibilite();
        $indisp3->setNom("Vacance d'hiver");
        $indisp3->setSaison($saison);
        $dateDebut = new \DateTimeImmutable($oldIndisponibilites['date_ca1']);
        $indisp3->setDateDebut($dateDebut);
        $date2semaine = new \DateTimeImmutable($oldIndisponibilites['date_ca2']);
        $dateFin = $date2semaine->modify('+6 day');
        $indisp3->setDateFin($dateFin);
        $em->persist($indisp3);

        //Vacance de Paques
        $indisp4 = new Indisponibilite();
        $indisp4->setNom("Vacance de Paques");
        $indisp4->setSaison($saison);
        $dateDebut = new \DateTimeImmutable($oldIndisponibilites['date_ca1']);
        $indisp4->setDateDebut($dateDebut);
        $date2semaine = new \DateTimeImmutable($oldIndisponibilites['date_pa2']);
        $dateFin = $date2semaine->modify('+6 day');
        $indisp4->setDateFin($dateFin);
        $em->persist($indisp4);


        $saison->addIndisponibilite($indisp1);
        $saison->addIndisponibilite($indisp2);
        $saison->addIndisponibilite($indisp3);
        $saison->addIndisponibilite($indisp4);

        $em->persist($saison);
        //Création des phases
        $phase1 = new Phase();
        $phase1->setNom("Phase 1");
        $phase1->setType(PhaseType::CHAMPIONNAT);
        $phase1->setOrdre(0);
        $dateDebut = new \DateTimeImmutable('2025-09-01');
        $dateFin = new \DateTimeImmutable('2026-01-11');
        $phase1->setDatedebut($dateDebut);
        $phase1->setDatefin($dateFin);
        $phase1->setClose(0);

        $phase2 = new Phase();
        $phase2->setNom("Phase 2");
        $phase2->setType(PhaseType::CHAMPIONNAT);
        $phase2->setOrdre(1);
        $dateDebut = new \DateTimeImmutable('2026-01-12');
        $dateFin = new \DateTimeImmutable('2026-05-31');
        $phase2->setDatedebut($dateDebut);
        $phase2->setDatefin($dateFin);
        $phase2->setClose(0);

        $phase3 = new Phase();
        $phase3->setNom("Phase 2");
        $phase3->setType(PhaseType::FINALE);
        $phase3->setOrdre(2);
        $dateDebut = new \DateTimeImmutable('2026-06-01');
        $dateFin = new \DateTimeImmutable('2026-06-30');
        $phase3->setDatedebut($dateDebut);
        $phase3->setDatefin($dateFin);
        $phase3->setClose(0);

        $phase1->setSaison($saison);
        $phase2->setSaison($saison);
        $phase3->setSaison($saison);
        $em->persist($phase1);
        $em->persist($phase2);
        $em->persist($phase3);

        $saison->addPhase($phase1);
        $saison->addPhase($phase2);
        $saison->addPhase($phase3);

        $tabPhase = [$phase1, $phase2, $phase3];
        $tabNomPoules = ['Poule A', 'Poule B', 'Poule C', 'Poule D'];

        for ($i = 0; $i < 3; $i++) {
            for ($y = 0; $y < 4; $y++) {
                $poule = new Poule();
                $poule->setNom($tabNomPoules[$y]);
                switch ($y) {
                    case 0:
                        $poule->setNbMonteeDefaut(0);
                        $poule->setNbDescenteDefaut(2);
                        break;
                    case 1:
                    case 2:
                        $poule->setNbMonteeDefaut(2);
                        $poule->setNbDescenteDefaut(2);
                        break;

                    case 3:
                        $poule->setNbMonteeDefaut(2);
                        $poule->setNbDescenteDefaut(0);
                        break;
                }

                $poule->setNiveau($y);
                $poule->setPhase($tabPhase[$i]);
                $tabPhase[$i]->addPoule($poule);

                $em->persist($poule);

            }
        }


        //Création des lieux et des équipes

        $oldEquipes = $oldConn->fetchAllAssociative('SELECT * FROM `tvbaequipe` WHERE saison like "2025/2026%"');
        $tabNewLieu = [];
        $tabNewEquipe = [];
        foreach ($oldEquipes as $oldEquipe) {
            $nomOriginalLieu = $oldEquipe['lieu'];
            //Si le lieu n'existe pas déjà
            if (!isset($tabNewLieu[$nomOriginalLieu])) {

                $newLieu = new Lieu();

                $posVirgule = strpos($nomOriginalLieu, ",");
                $nomCourt = ($posVirgule !== false) ? substr($nomOriginalLieu, 0, $posVirgule) : $nomOriginalLieu;
                $newLieu->setNom($nomCourt);
                $newLieu->setAdresse($nomOriginalLieu);


                $creneau = new Creneau();
                $creneau->setJourSemaine($this->mapJour($oldEquipe['jour']));
                $heureStr = str_replace('h', ':', strtolower($oldEquipe['heure']));
                $creneau->setHeureDebut(new \DateTimeImmutable($heureStr ?: '20:00'));

                $creneau->setCapacite(2);
                $creneau->setPrioritaire(1);
                $heureFin = $creneau->getHeureDebut()->modify('+2 hours');
                $creneau->setHeureFin($heureFin);
                $newLieu->addCreneau($creneau);
                $tabNewLieu[$nomOriginalLieu] = $newLieu;
                $em->persist($newLieu);
                $em->persist($creneau);
            }

            $equipe = new Equipe();
            $equipe->setNom($oldEquipe['nom']);
            $equipe->setLieu($tabNewLieu[$nomOriginalLieu]);

            //Récupération des capitaines
            $oldCapitaine = $oldConn->fetchAssociative(
                'SELECT * FROM `tvbacontact` WHERE codeequipe = :codeEquipe',
                ['codeEquipe' => $oldEquipe['code']]
            );

            if ($oldCapitaine && !empty($oldCapitaine['email'])) {
                //Vérifier qu'un user n'existe pas déjà avec l'adresse du capitaine
                $userActuel = $em->getRepository(User::class)->findOneBy(['email' => $oldCapitaine['email']]);
                if ($userActuel) {
                    $equipe->setCapitaine($userActuel);
                } else {
                    $user = new User();
                    $user->setPrenom($oldCapitaine['nom']);
                    $user->setEmail($oldCapitaine['email']);
                    $user->setRoles(['ROLE_USER']);
                    // Génération d'un mot de passe aléatoire haché
                    $fakePassword = bin2hex(random_bytes(10));
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $fakePassword);
                    $user->setPassword($hashedPassword);
                    $user->setIsVerified(1);
                    $equipe->setCapitaine($user);
                    $em->persist($user);
                }
            }


            //Affectation de l'équipe à une poule
            $poule = $this->findPouleByOldSaison($oldEquipe['saison'], $tabPhase);
            if ($poule) {
                $poule->addEquipe($equipe);
            }

            $tabNewEquipe[$oldEquipe['code']] = $equipe;
            $em->persist($equipe);


            $tabNewLieu[$nomOriginalLieu]->addEquipe($equipe);


        }

        //Création des journée pour les phases championnat
        $tabPouleAncienne = ['2025/2026-GroupeA-Phase1', '2025/2026-GroupeB-Phase1', '2025/2026-GroupeC-Phase1', '2025/2026-GroupeD-Phase1'];
        $journeeMapping = []; // Pour lier les matchs plus tard
        foreach ($tabPouleAncienne as $nomPouleAncienne) {
            $poule = $this->findPouleByOldSaison($nomPouleAncienne, $tabPhase);
            if (!$poule) continue;


            $oldJournees = $oldConn->fetchAllAssociative(
                'SELECT numjournee, MIN(datepartie) as date_min
                         FROM tvbaresultat
                         WHERE saison = :pouleAncienne
                         GROUP BY numjournee
                         ORDER BY numjournee ASC',
                ['pouleAncienne' => $nomPouleAncienne]
            );


            foreach ($oldJournees as $oldJ) {
                $num = (int)$oldJ['numjournee'];
                $dateMin = new \DateTimeImmutable($oldJ['date_min']); //

                // Normalisation : on se cale sur le lundi de la semaine de ce match
                $dateLundi = $dateMin->modify('monday this week');
                $dateDimanche = $dateLundi->modify('+6 days');

                $journee = new Journee();
                $journee->setNumero($num);
                $journee->setNom("Semaine du " . $dateLundi->format('d/m'));
                $journee->setDatedebut($dateLundi);
                $journee->setDatefin($dateDimanche);

                // On lie la journée à la poule Symfony actuelle
                $poule->addJournee($journee);
                $em->persist($journee);

                // On stocke l'objet pour l'import des matchs (Partie)
                $journeeMapping[$nomPouleAncienne][$num] = $journee;
            }
        }


        //Récupération des matchs
        // Récupération de tous les matchs de la saison
        $oldMatchs = $oldConn->fetchAllAssociative('SELECT * FROM `tvbaresultat` WHERE saison like "2025/2026%"');

        $io->section('Importation des matchs');
        $progressBar = $io->createProgressBar(count($oldMatchs));

        foreach ($oldMatchs as $oldM) {
            $partie = new Partie();

            // 1. Liaison avec l'équipe Reçoit et Déplace
            // On utilise le tableau de mapping créé lors de l'import des équipes
            $equipeRecoit = $tabNewEquipe[$oldM['equipe1']] ?? null;
            $equipeDeplace = $tabNewEquipe[$oldM['equipe2']] ?? null;

            if (!$equipeRecoit || !$equipeDeplace) {
                // Optionnel : logger l'erreur si une équipe est introuvable
                $progressBar->advance();
                continue;
            }

            $partie->setIdEquipeRecoit($equipeRecoit);
            $partie->setIdEquipeDeplace($equipeDeplace);

            // 2. Liaison avec le Lieu
            // L'ancienne base utilise 'lieupartie' pour le gymnase du match
            if (isset($tabNewLieu[$oldM['lieupartie']])) {
                $partie->setLieu($tabNewLieu[$oldM['lieupartie']]);
            } else {
                // Par défaut, on peut mettre le lieu de l'équipe qui reçoit
                $partie->setLieu($equipeRecoit->getLieu());
            }

            // 3. Gestion de la Date et de l'Heure
            $dateBrute = $oldM['datepartie'];   // Format YYYY-MM-DD
            $heureBrute = $oldM['heurepartie']; // Format "20h30" ou "20:30"

            // Nettoyage de l'heure : on remplace 'h' par ':' pour rendre la chaîne lisible par PHP
            $heureNettoyee = str_replace('h', ':', strtolower($heureBrute));

            try {
                // On concatène la date et l'heure pour créer un objet complet
                // On prévoit une heure par défaut (20:00) si la colonne est vide
                $dateComplete = new \DateTimeImmutable($dateBrute . ' ' . ($heureNettoyee ?: '20:00'));
                $partie->setDate($dateComplete);
            } catch (\Exception $e) {
                // En cas d'erreur de format, on enregistre au moins la date sans l'heure
                $partie->setDate(new \DateTimeImmutable($dateBrute));
            }

            // 4. Scores

            if ($oldM['set1'] == 0 && $oldM['set2'] == 0) {
                //match non joué
                $partie->setNbSetGagnantReception(null);
                $partie->setNbSetGagnantDeplacement(null);
            } else {
                $partie->setNbSetGagnantReception((int)$oldM['set1']);
                $partie->setNbSetGagnantDeplacement((int)$oldM['set2']);
            }


            // 5. Liaison avec la Journée
            // On utilise le mapping multidimensionnel [$nomPouleAncienne][$numJournee]
            if (isset($journeeMapping[$oldM['saison']][$oldM['numjournee']])) {
                $partie->setJournee($journeeMapping[$oldM['saison']][$oldM['numjournee']]);
            } else {
                dump($oldM);
            }

            //liaison avec la poule
            $poule = $this->findPouleByOldSaison($oldM['saison'], $tabPhase);
            $partie->setPoule($poule);
            $poule->addParty($partie);
            $em->persist($partie);
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine();


        $em->flush();

        $io->section('Mise à jour des classements');

        // On boucle sur toutes les phases et toutes les poules créées
        foreach ($tabPhase as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $io->text("Calcul du classement pour : " . $phase->getNom() . " - " . $poule->getNom());

                // Le service gère le calcul des points, des sets et le tri (points, ratio, confrontation directe)
                $this->classementService->mettreAJourClassementPoule($poule);
            }
        }

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

    private function findPouleByOldSaison(string $oldSaison, array $tabPhase): ?Poule
    {
        $parts = explode('-', $oldSaison);
        if (count($parts) < 3) return null;

        $groupePart = $parts[1]; // "GroupeD"
        $phasePart = $parts[2]; // "Phase1"

        // Extraction de l'index de la phase (Phase1 -> 0, Phase2 -> 1)
        $phaseNum = (int)filter_var($phasePart, FILTER_SANITIZE_NUMBER_INT);
        $phaseIndex = $phaseNum - 1;

        if (isset($tabPhase[$phaseIndex])) {
            $currentPhase = $tabPhase[$phaseIndex];
            $nomPouleCherche = "Poule " . str_replace('Groupe', '', $groupePart);

            foreach ($currentPhase->getPoules() as $poule) {
                if ($poule->getNom() === $nomPouleCherche) {
                    return $poule;
                }
            }
        }

        return null;
    }
}
