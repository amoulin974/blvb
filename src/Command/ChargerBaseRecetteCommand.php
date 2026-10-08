<?php

namespace App\Command;

use App\Entity\Creneau;
use App\Entity\Equipe;
use App\Entity\Indisponibilite;
use App\Entity\Joueur;
use App\Entity\Lieu;
use App\Entity\MembreEquipe;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Entity\User;
use App\Enum\PhaseType;
use App\EventListener\SaisonListener;
use App\Service\ClassementService;
use App\Service\JourneeService;
use App\Service\PartieService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Charge dans une base de RECETTE une « Saison 2026-2027 test » (et une « Saison 2025-2026 test » archivée)
 * avec des données qui permettent de dérouler tous les tests fonctionnels : phases en cours, matchs passés,
 * à venir et sans score, classements, gymnases saturés / hors créneau / en vacances, capitaines avec ou sans
 * compte ou téléphone, simples joueurs, joueurs qui changent d'équipe d'une saison à l'autre, imports Excel…
 *
 * À lancer via bin/recette (qui recrée d'abord la base de recette depuis la base de développement).
 * Garde-fou : refuse de tourner si la base ne s'appelle pas « …recette… ».
 */
#[AsCommand(
    name: 'app:recette:charger',
    description: 'Charge la « Saison 2026-2027 test » dans la base de recette (voir bin/recette)'
)]
class ChargerBaseRecetteCommand extends Command
{
    public const MOT_DE_PASSE = 'Recette-2026!';
    private const SAISON = 'Saison 2026-2027 test';
    private const ARCHIVE = 'Saison 2025-2026 test';
    private const DOMAINE = 'blvb.test';

    /** nom => [adresse, créneaux [jour (1 = lundi), heure, capacité, priorité]] */
    private const LIEUX = [
        'Test Anglet' => ['Complexe Test, 64600 Anglet', [[3, '20:30', 3, 1]]],
        'Test Biarritz' => ['Gymnase Test, 64200 Biarritz', [[2, '19:30', 3, 2], [3, '20:30', 2, 1]]],
        'Test Capbreton' => ['Salle Test, 40130 Capbreton', [[1, '19:45', 3, 1]]],
        'Test St-Jean-de-Luz' => ['Halle Test, 64500 Saint-Jean-de-Luz', [[3, '20:00', 2, 1]]],
        'Test Ondres' => ['Gymnase Test, 40440 Ondres', [[4, '20:45', 1, 1]]],
        'Test Bayonne' => ['Complexe Test, 64100 Bayonne', [[1, '20:00', 2, 1], [4, '20:00', 2, 1]]],
        'Test Sans Créneau' => ['Salle Test, 64100 Bayonne', []],
    ];

    /** code => [nom, gymnase (null = équipe sans gymnase), poule de la Phase 1] */
    private const EQUIPES = [
        'A1' => ['ARRATS (ARR)', 'Test Anglet', 'A'],
        'A2' => ['BIDART VB (BID)', 'Test Anglet', 'A'],
        'A3' => ['CAPBRETON LOISIR (CAP)', 'Test Anglet', 'A'],
        'A4' => ['ETXEA (ETX)', 'Test Biarritz', 'A'],
        'A5' => ['GOIZ ARGI (GOI)', 'Test Capbreton', 'A'],
        'A6' => ['HOSSEGOR (HOS)', 'Test St-Jean-de-Luz', 'A'],
        'A7' => ['IRATI (IRA)', 'Test Ondres', 'A'],
        'A8' => ["LES VOLLEYEURS DE LA CÔTE D'ARGENT (LVCA)", 'Test Bayonne', 'A'],
        'B1' => ['KOSKO ALAI (KOS)', 'Test Anglet', 'B'],
        'B2' => ['LAPURDI VOLLEY (LAP)', 'Test Biarritz', 'B'],
        'B3' => ['MENDI (MEN)', 'Test Capbreton', 'B'],
        'B4' => ['NIVE (NIV)', 'Test St-Jean-de-Luz', 'B'],
        'B5' => ['OIHANA (OIH)', 'Test Ondres', 'B'],
        'B6' => ['PIKA PIKA (PIK)', 'Test Bayonne', 'B'],
        'C1' => ['SOKOA (SOK)', 'Test Anglet', 'C'],
        'C2' => ['TXORI (TXO)', 'Test Capbreton', 'C'],
        'C3' => ['URDAZURI (URD)', 'Test St-Jean-de-Luz', 'C'],
        'C4' => ['XIBERO (XIB)', 'Test Bayonne', 'C'],
        'C5' => ['ZEZEN (ZEZ)', 'Test Sans Créneau', 'C'],
        'D1' => ['ADOUR (ADO)', 'Test Biarritz', 'D'],
        'D2' => ['BAIGORRI (BAI)', 'Test Capbreton', 'D'],
        'D3' => ['CHALOSSE (CHA)', 'Test Bayonne', 'D'],
        'D4' => ['DUNES (DUN)', null, 'D'], // équipe sans gymnase
    ];

    /**
     * Capitaines de la saison 2026-2027 test. Clés : compte (identifiant avant @), fiche = coordonnées portées par la
     * fiche joueur, userTel = téléphone porté par le compte seulement. Les équipes absentes de la liste reçoivent un
     * capitaine « fiche seule » (sans compte), sauf A8 (aucun capitaine) et B6 (aucun joueur).
     */
    private const CAPITAINES = [
        'A1' => ['compte' => 'cap.a1', 'prenom' => 'Amaia', 'nom' => 'Etcheverry', 'tel' => '06 11 11 11 11'],
        'A2' => ['compte' => 'cap.a2', 'prenom' => 'Beñat', 'nom' => 'Larralde', 'tel' => null], // compte, sans téléphone
        'A3' => ['compte' => null, 'prenom' => 'Céline', 'nom' => 'Iribarne', 'tel' => '06 33 33 33 33', 'email' => 'cap.a3.fiche@blvb.test'], // fiche seule
        'A4' => ['compte' => 'cap.a4', 'prenom' => 'Dani', 'nom' => 'Hiriart', 'tel' => '06 44 44 44 44'],
        'A5' => ['compte' => 'cap.a5', 'prenom' => 'Eneko', 'nom' => 'Dufau', 'tel' => null, 'email' => null, 'userTel' => '06 55 55 55 55'], // coordonnées seulement sur le compte
        'A6' => ['compte' => 'cap.a6', 'prenom' => 'Florence', 'nom' => 'Lassalle', 'tel' => '06 66 66 66 66'],
        'A7' => ['compte' => 'cap.a7', 'prenom' => 'Gorka', 'nom' => 'Darrigrand', 'tel' => '06 77 77 77 77'],
        'B1' => ['compte' => 'cap.b1', 'prenom' => 'Hélène', 'nom' => 'Harispe', 'tel' => '06 21 21 21 21'],
        'B2' => ['compte' => 'cap.double', 'prenom' => 'Iker', 'nom' => 'Casabonne', 'tel' => '06 22 22 22 22'], // capitaine de B2 et D2
        'C1' => ['compte' => 'cap.c1', 'prenom' => 'Julie', 'nom' => 'Lafitte', 'tel' => '06 31 31 31 31'],
        'D1' => ['compte' => 'cap.d1', 'prenom' => 'Kepa', 'nom' => 'Sarthou', 'tel' => '06 41 41 41 41'],
        'D2' => ['partage' => 'B2'],
    ];

    /** Effectif (capitaine compris) par poule ; A8 : sans capitaine ; B6 : aucun joueur. */
    private const EFFECTIF = ['A' => 7, 'B' => 6, 'C' => 5, 'D' => 5];

    private const PRENOMS = ['Laura', 'Mikel', 'Nathalie', 'Oihan', 'Pauline', 'Quentin', 'Rémi', 'Sophie', 'Txomin', 'Ugo', 'Vanessa', 'Xabi', 'Yann', 'Zoé', 'Aitor', 'Béatrice', 'Cédric', 'Denis', 'Élodie', 'Fabien', 'Gaëlle', 'Hugo', 'Inès', 'Jon', 'Karine', 'Lucas', 'Maëlle', 'Nicolas', 'Océane', 'Pierre'];
    private const NOMS = ['Mendiboure', 'Ithurralde', 'Capdeville', 'Duhalde', 'Lasserre', 'Pouyanne', 'Barbier', 'Laborde', 'Camou', 'Dubroca', 'Saint-Martin', 'Bidegain', 'Haramboure', 'Lacoste', 'Dargaignaratz', 'Lamarque', 'Cazaux', 'Poydessus', 'Etchegaray', 'Larrouy', 'Garat', 'Minvielle', 'Oçafrain', 'Pédebernade', 'Rey', 'Salha', 'Tauzin', 'Urruty', 'Vergez', 'Zabalo'];

    private const SCORES = [[3, 0], [3, 1], [3, 2], [2, 3], [1, 3], [0, 3]];

    private int $compteurJoueurs = 0;
    private AsciiSlugger $slugger;
    /** @var string[] lignes du récapitulatif des cas préparés */
    private array $cas = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly JourneeService $journeeService,
        private readonly PartieService $partieService,
        private readonly ClassementService $classementService,
        private readonly CacheInterface $cache,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
        $this->slugger = new AsciiSlugger();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED,
                "en-cours (défaut) : phase 1 en cours, matchs passés saisis, à venir ; "
                .'phase1-terminee : tous les matchs de la phase 1 saisis, phase non clôturée (pour tester la clôture) ; '
                .'vierge : structure, équipes et journées, aucun match (pour tester la génération)', 'en-cours')
            ->addOption('fichier-import', null, InputOption::VALUE_REQUIRED,
                "Chemin du fichier Excel d'exemple pour l'import des utilisateurs", 'var/recette/import_utilisateurs.xlsx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $scenario = (string) $input->getOption('scenario');
        if (!in_array($scenario, ['en-cours', 'phase1-terminee', 'vierge'], true)) {
            $io->error('Scénario inconnu : '.$scenario);

            return Command::INVALID;
        }

        $base = $this->em->getConnection()->getDatabase();
        if (!str_contains((string) $base, 'recette')) {
            $io->error(sprintf('Refus : la base « %s » n\'est pas une base de recette (son nom doit contenir « recette »). Utilisez bin/recette reinitialiser.', $base));

            return Command::FAILURE;
        }
        if ($this->em->getRepository(Saison::class)->findOneBy(['nom' => self::SAISON])) {
            $io->error('La saison de test existe déjà dans cette base : relancez bin/recette reinitialiser.');

            return Command::FAILURE;
        }
        $noms = array_column(self::EQUIPES, 0);
        foreach ($noms as $nom) {
            if ($this->em->getRepository(Equipe::class)->findOneBy(['nom' => $nom])) {
                $io->error(sprintf('Une équipe « %s » existe déjà : relancez bin/recette reinitialiser.', $nom));

                return Command::FAILURE;
            }
        }

        $maintenant = new \DateTimeImmutable();
        // La saison démarre 2 semaines avant aujourd'hui : il y a toujours des journées passées, une journée en cours
        // et des journées à venir dans les poules de 5 à 8 équipes (la poule D de 4 équipes est presque terminée)
        $debut = $maintenant->modify('monday this week')->modify('-2 weeks')->setTime(0, 0);

        SaisonListener::$enabled = false; // les phases et poules sont créées ici, pas par l'écouteur
        $io->title(sprintf('Chargement de « %s » dans %s (scénario : %s)', self::SAISON, $base, $scenario));

        $io->section('Structure : gymnases, saisons, phases, poules, équipes');
        $lieux = $this->creerLieux();
        $saison = $this->creerSaison($debut);
        $archive = $this->creerArchive($debut);
        $equipes = $this->creerEquipes($lieux, $saison, $archive);
        $this->em->flush();

        $io->section('Joueurs, comptes et compositions');
        $this->creerEffectifs($equipes, $saison, $archive);
        $this->em->flush();
        $this->em->clear();

        $io->section('Journées et matchs (services de l\'application)');
        $saison = $this->em->getRepository(Saison::class)->findOneBy(['nom' => self::SAISON]);
        $archive = $this->em->getRepository(Saison::class)->findOneBy(['nom' => self::ARCHIVE]);
        $phase1 = $saison->getPhases()[0];
        $phase2 = $saison->getPhases()[1];
        foreach ($phase1->getPoules() as $poule) {
            $this->journeeService->creerJournees($poule);
        }
        $this->journeeService->creerJournees($phase2->getPoules()[0]); // phase 2 : journées créées, aucun match
        foreach ($archive->getPhases()[0]->getPoules() as $poule) {
            $this->journeeService->creerJournees($poule);
        }
        $this->em->flush();
        if ($scenario !== 'vierge') {
            $this->partieService->creerCalendrierOptimise($phase1);
            $this->partieService->creerCalendrierOptimise($archive->getPhases()[0]);
        }
        $this->em->flush();
        $this->em->clear();

        if ($scenario !== 'vierge') {
            $io->section('Scénarios particuliers, scores et classements');
            $saison = $this->em->getRepository(Saison::class)->findOneBy(['nom' => self::SAISON]);
            $archive = $this->em->getRepository(Saison::class)->findOneBy(['nom' => self::ARCHIVE]);
            $this->preparerCasParticuliers($saison, $maintenant, $scenario);
            $this->em->flush();
            $this->saisirScoresArchive($archive);
            $this->em->flush();
            foreach ([$saison->getPhases()[0], $archive->getPhases()[0]] as $phase) {
                foreach ($phase->getPoules() as $poule) {
                    $this->classementService->mettreAJourClassementPoule($poule);
                }
            }
            $archive->getPhases()[0]->setClose(1);
            $this->em->flush();
        }

        $fichier = $this->ecrireFichierImport((string) $input->getOption('fichier-import'));
        $this->cache->delete('saisons_all');

        $this->afficherRecapitulatif($io, $scenario, $fichier);

        return Command::SUCCESS;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Structure
    // ---------------------------------------------------------------------------------------------------------

    /** @return array<string, Lieu> */
    private function creerLieux(): array
    {
        $lieux = [];
        foreach (self::LIEUX as $nom => [$adresse, $creneaux]) {
            $lieu = (new Lieu())->setNom($nom)->setAdresse($adresse);
            foreach ($creneaux as [$jour, $heure, $capacite, $priorite]) {
                $debut = new \DateTimeImmutable($heure);
                $lieu->addCreneau((new Creneau())
                    ->setJourSemaine($jour)->setHeureDebut($debut)->setHeureFin($debut->modify('+2 hours'))
                    ->setCapacite($capacite)->setPrioritaire($priorite));
            }
            $this->em->persist($lieu);
            $lieux[$nom] = $lieu;
        }

        return $lieux;
    }

    private function creerSaison(\DateTimeImmutable $debut): Saison
    {
        // La saison de test devient la saison affichée par défaut dans cette base de recette
        $this->em->getConnection()->executeStatement('UPDATE saison SET favori = 0');

        $fin = $debut->modify('+43 weeks');
        $saison = (new Saison())->setNom(self::SAISON)->setFavori(1)->setDateDebut($debut)->setDateFin($fin);
        $this->em->persist($saison);

        // Vacances scolaires 2026-2027 : les semaines concernées sont sautées à la création des journées
        foreach ([
            ['Vacances de Toussaint', '2026-10-19', '2026-11-01'],
            ['Vacances de Noël', '2026-12-21', '2027-01-03'],
            ['Vacances d\'hiver', '2027-02-15', '2027-02-28'],
            ['Vacances de Pâques', '2027-04-12', '2027-04-25'],
        ] as [$nom, $du, $au]) {
            $indispo = (new Indisponibilite())->setNom($nom)->setDateDebut(new \DateTimeImmutable($du))->setDateFin(new \DateTimeImmutable($au));
            $saison->addIndisponibilite($indispo);
            $this->em->persist($indispo);
        }

        $p1Fin = $debut->modify('+22 weeks')->modify('-1 day');
        $p2Debut = $p1Fin->modify('+1 day');
        $p2Fin = $p2Debut->modify('+16 weeks')->modify('-1 day');
        $phases = [
            ['Phase 1', PhaseType::CHAMPIONNAT, $debut, $p1Fin],
            ['Phase 2', PhaseType::CHAMPIONNAT, $p2Debut, $p2Fin],
            ['Phase Finale', PhaseType::FINALE, $p2Fin->modify('+1 day'), $fin],
        ];
        foreach ($phases as $ordre => [$nom, $type, $du, $au]) {
            $phase = (new Phase())->setNom($nom)->setType($type)->setOrdre($ordre)->setClose(0)->setDatedebut($du)->setDatefin($au);
            $saison->addPhase($phase);
            $this->em->persist($phase);
            foreach (['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4] as $lettre => $niveau) {
                $poule = (new Poule())->setNom("Poule $lettre")->setNiveau($niveau)->setNbMonteeDefaut(2)->setNbDescenteDefaut(2);
                $phase->addPoule($poule);
                $this->em->persist($poule);
            }
        }

        return $saison;
    }

    /** Saison précédente, terminée et clôturée : mêmes équipes de la poule D, compositions différentes. */
    private function creerArchive(\DateTimeImmutable $debut): Saison
    {
        $annee = $debut->modify('-1 year')->modify('monday this week');
        $archive = (new Saison())->setNom(self::ARCHIVE)->setFavori(0)->setDateDebut($annee)->setDateFin($annee->modify('+42 weeks'));
        $this->em->persist($archive);
        $phase = (new Phase())->setNom('Phase 1')->setType(PhaseType::CHAMPIONNAT)->setOrdre(0)->setClose(0)
            ->setDatedebut($annee)->setDatefin($annee->modify('+20 weeks'));
        $archive->addPhase($phase);
        $this->em->persist($phase);
        $poule = (new Poule())->setNom('Poule A')->setNiveau(1)->setNbMonteeDefaut(1)->setNbDescenteDefaut(1);
        $phase->addPoule($poule);
        $this->em->persist($poule);

        return $archive;
    }

    /** @return array<string, Equipe> code => équipe */
    private function creerEquipes(array $lieux, Saison $saison, Saison $archive): array
    {
        $poules = [];
        foreach ($saison->getPhases() as $phase) {
            foreach ($phase->getPoules() as $poule) {
                $poules[$phase->getOrdre()][substr($poule->getNom(), -1)] = $poule;
            }
        }

        $equipes = [];
        foreach (self::EQUIPES as $code => [$nom, $gymnase, $lettre]) {
            $equipe = (new Equipe())->setNom($nom)->setLieu($gymnase === null ? null : $lieux[$gymnase]);
            $this->em->persist($equipe);
            $poules[0][$lettre]->addEquipe($equipe);
            $equipes[$code] = $equipe;
        }
        // Phase 2 : seule la poule A reçoit des équipes (les mêmes), les autres poules restent vides
        foreach (array_filter(array_keys(self::EQUIPES), fn ($c) => $c[0] === 'A') as $code) {
            $poules[1]['A']->addEquipe($equipes[$code]);
        }

        // Archive : la poule A de l'an dernier réunit les équipes D1 à D4
        foreach (['D1', 'D2', 'D3', 'D4'] as $code) {
            $archive->getPhases()[0]->getPoules()[0]->addEquipe($equipes[$code]);
        }

        return $equipes;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Joueurs, comptes, compositions
    // ---------------------------------------------------------------------------------------------------------

    /** @var array<string, Joueur> */
    private array $joueurs = [];

    private function creerEffectifs(array $equipes, Saison $saison, Saison $archive): void
    {
        // --- Comptes sans lien avec une équipe -------------------------------------------------------------
        $this->creerUtilisateur('admin', 'Recette', 'Admin', ['ROLE_ADMIN']);
        $this->creerUtilisateur('user.sansfiche', 'Sans', 'Fiche');
        $this->creerUtilisateur('user.nonverifie', 'Non', 'Vérifié', ['ROLE_USER'], false);
        $this->creerUtilisateur('autre.compte', 'Autre', 'Compte');

        // --- Joueurs particuliers --------------------------------------------------------------------------
        $this->joueurs['a1'] = $this->joueurAvecCompte('joueur.a1', 'Jean', 'Premier', '06 10 10 10 10');
        $this->joueurs['a2'] = $this->joueurAvecCompte('joueur.a2', 'Marie', 'Seconde', null);
        $this->joueurs['mobile'] = $this->joueurAvecCompte('mobile', 'Mobile', 'Joueur', '06 99 00 99 00'); // change d'équipe entre les saisons
        // Pour l'import Excel d'utilisateurs (cf. fichier d'exemple)
        $this->joueurs['import.existant'] = $this->fiche('Existant', 'Import', 'import.existant@'.self::DOMAINE, null);
        $this->joueurs['import.casse'] = $this->fiche('Casse', 'Import', 'Import.Casse@BLVB.test', null); // e-mail en majuscules
        $this->joueurs['import.lie'] = $this->fiche('Lié', 'Import', 'import.lie@'.self::DOMAINE, null);
        $this->joueurs['import.lie']->setUser($this->utilisateurs['autre.compte']); // déjà lié à un autre compte

        // --- Compositions 2026-2027 test -------------------------------------------------------------------
        $supplements = [
            'A1' => ['a1'], 'A2' => ['a2'], 'A3' => ['mobile'],
            'C2' => ['import.lie'], 'C3' => ['import.existant'], 'C4' => ['import.casse'],
        ];
        $capitainesCrees = [];
        foreach (self::EQUIPES as $code => [$nom, $gymnase, $lettre]) {
            if ($code === 'B6') {
                continue; // équipe sans aucun joueur
            }
            $membres = [];
            if ($code !== 'A8') { // A8 : joueurs mais aucun capitaine désigné
                $spec = self::CAPITAINES[$code] ?? null;
                $cle = $spec['partage'] ?? $code;
                $spec = self::CAPITAINES[$cle] ?? null;
                $capitainesCrees[$cle] ??= $spec !== null ? $this->capitaine($spec) : $this->ficheAleatoire(true);
                $membres[] = [$capitainesCrees[$cle], true];
            }
            foreach ($supplements[$code] ?? [] as $cleJoueur) {
                $membres[] = [$this->joueurs[$cleJoueur], false];
            }
            while (count($membres) < self::EFFECTIF[$lettre]) {
                $membres[] = [$this->ficheAleatoire(false), false];
            }
            foreach ($membres as [$joueur, $estCapitaine]) {
                $this->em->persist((new MembreEquipe())->setEquipe($equipes[$code])->setSaison($saison)->setJoueur($joueur)->setCapitaine($estCapitaine));
            }
        }

        // --- Compositions 2025-2026 test : les joueurs ont changé d'équipe -------------------------------------
        // cap.a1 était capitaine de D1, cap.d1 simple joueur de D2, « Mobile Joueur » joueur de D3
        $archiveMembres = [
            'D1' => [[$capitainesCrees['A1'], true]],
            'D2' => [[$this->ficheAleatoire(true), true], [$capitainesCrees['D1'], false]],
            'D3' => [[$this->ficheAleatoire(true), true], [$this->joueurs['mobile'], false]],
            'D4' => [[$this->ficheAleatoire(true), true]],
        ];
        foreach ($archiveMembres as $code => $membres) {
            while (count($membres) < 4) {
                $membres[] = [$this->ficheAleatoire(false), false];
            }
            foreach ($membres as [$joueur, $estCapitaine]) {
                $this->em->persist((new MembreEquipe())->setEquipe($equipes[$code])->setSaison($archive)->setJoueur($joueur)->setCapitaine($estCapitaine));
            }
        }
    }

    /** @var array<string, User> */
    private array $utilisateurs = [];

    private function creerUtilisateur(string $compte, string $prenom, string $nom, array $roles = ['ROLE_USER'], bool $verifie = true, ?string $tel = null): User
    {
        $user = (new User())->setEmail($compte.'@'.self::DOMAINE)->setRoles($roles)->setNom($nom)->setPrenom($prenom)->setIsVerified($verifie)->setTelephone($tel);
        $user->setPassword($this->hasher->hashPassword($user, self::MOT_DE_PASSE));
        $this->em->persist($user);

        return $this->utilisateurs[$compte] = $user;
    }

    private function joueurAvecCompte(string $compte, string $prenom, string $nom, ?string $tel): Joueur
    {
        $user = $this->creerUtilisateur($compte, $prenom, $nom, ['ROLE_USER'], true, $tel);
        $joueur = (new Joueur())->setPrenom($prenom)->setNom($nom)->setEmail($user->getEmail())->setTelephone($tel)->setUser($user);
        $this->em->persist($joueur);

        return $joueur;
    }

    private function fiche(string $prenom, string $nom, ?string $email, ?string $tel): Joueur
    {
        $joueur = (new Joueur())->setPrenom($prenom)->setNom($nom)->setEmail($email)->setTelephone($tel);
        $this->em->persist($joueur);

        return $joueur;
    }

    /** Joueur sans compte, nom et prénom tirés de listes ; la moitié ont un e-mail, la moitié un téléphone. */
    private function ficheAleatoire(bool $capitaine): Joueur
    {
        $i = $this->compteurJoueurs++;
        $prenom = self::PRENOMS[$i % count(self::PRENOMS)];
        $nom = self::NOMS[($i * 7 + 3) % count(self::NOMS)];
        $slug = (string) $this->slugger->slug("$prenom.$nom", '.')->lower();
        $email = ($capitaine || $i % 2 === 0) ? "$slug@".self::DOMAINE : null;
        $tel = ($capitaine || $i % 2 === 1) ? sprintf('06 %02d %02d %02d %02d', 50 + $i % 40, 10 + $i % 80, 20 + $i % 70, 30 + $i % 60) : null;

        return $this->fiche($prenom, $nom, $email, $tel);
    }

    private function capitaine(array $spec): Joueur
    {
        $email = array_key_exists('email', $spec) ? $spec['email'] : ($spec['compte'] ? $spec['compte'].'@'.self::DOMAINE : null);
        if ($spec['compte'] === null) {
            return $this->fiche($spec['prenom'], $spec['nom'], $email, $spec['tel']);
        }
        $user = $this->creerUtilisateur($spec['compte'], $spec['prenom'], $spec['nom'], ['ROLE_USER'], true, $spec['userTel'] ?? $spec['tel']);
        $joueur = (new Joueur())->setPrenom($spec['prenom'])->setNom($spec['nom'])->setEmail($email)->setTelephone($spec['tel'])->setUser($user);
        $this->em->persist($joueur);

        return $joueur;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Matchs : cas particuliers, scores
    // ---------------------------------------------------------------------------------------------------------

    private function preparerCasParticuliers(Saison $saison, \DateTimeImmutable $maintenant, string $scenario): void
    {
        $phase1 = $saison->getPhases()[0];
        $parties = [];
        foreach ($phase1->getPoules() as $poule) {
            foreach ($poule->getParties() as $partie) {
                $parties[] = $partie;
            }
        }
        usort($parties, fn (Partie $a, Partie $b) => [$a->getDate(), $a->getId()] <=> [$b->getDate(), $b->getId()]);
        $lettre = fn (Partie $p) => substr($p->getPoule()->getNom(), -1);

        // --- Anomalies de planning (pour la page « calendrier par gymnase ») -----------------------------------
        $aVenir = array_values(array_filter($parties, fn (Partie $p) => $p->getDate() > $maintenant->modify('+8 days')));
        $utilises = [];
        $prendre = function (callable $filtre) use (&$aVenir, &$utilises): ?Partie {
            foreach ($aVenir as $p) {
                if (!isset($utilises[$p->getId()]) && $filtre($p)) {
                    return $utilises[$p->getId()] = $p;
                }
            }

            return null;
        };
        $lieu = fn (string $nom) => $this->em->getRepository(Lieu::class)->findOneBy(['nom' => $nom]);
        $jour = fn (Partie $p, int $decalage, string $heure) => $p->getJournee()->getDateDebut()->modify("+$decalage days")->setTime((int) substr($heure, 0, 2), (int) substr($heure, 3, 2));
        $decrire = fn (Partie $p) => sprintf('%s contre %s, %s, %s', $p->getIdEquipeRecoit()->getNom(), $p->getIdEquipeDeplace()->getNom(), $p->getPoule()->getNom(), $p->getDate()->format('d/m/Y H:i'));

        $m1 = $prendre(fn ($p) => $lettre($p) === 'A');
        $m2 = $m1 ? $prendre(fn ($p) => $lettre($p) === 'B' && $p->getJournee()->getNumero() === $m1->getJournee()->getNumero()) : null;
        if ($m1 && $m2) {
            foreach ([$m1, $m2] as $m) {
                $m->setLieu($lieu('Test Ondres'))->setDate($jour($m1, 3, '20:45'));
            }
            // L'optimiseur a pu placer lui-même d'autres matchs ce jour-là à Ondres : on compte les matchs réels
            $jourSurcharge = $m1->getDate()->format('Y-m-d');
            $nbSurcharge = count(array_filter($parties, fn (Partie $p) => $p->getLieu() === $m1->getLieu() && $p->getDate()->format('Y-m-d') === $jourSurcharge));
            $this->cas[] = [sprintf('Surcharge : %d matchs pour 1 terrain (Test Ondres, le %s)', $nbSurcharge, $m1->getDate()->format('d/m/Y')), $decrire($m1).' — et '.$decrire($m2)];
        }
        if ($m3 = $prendre(fn ($p) => $lettre($p) === 'C')) {
            $m3->setLieu($lieu('Test Capbreton'))->setDate($jour($m3, 2, '20:00'));
            $this->cas[] = ['Match hors créneau (Test Capbreton n\'a de créneau que le lundi)', $decrire($m3)];
        }
        if ($m4 = $prendre(fn ($p) => $lettre($p) === 'B')) {
            $m4->setLieu($lieu('Test Biarritz'))->setDate($jour($m4, 2, '20:30'));
            $this->cas[] = ['Créneau non prioritaire (Test Biarritz : le mardi est prioritaire, le mercredi non)', $decrire($m4)];
        }
        $vacances = null;
        foreach ($saison->getIndisponibilites() as $indispo) {
            if ($indispo->getDateDebut() > $maintenant && ($vacances === null || $indispo->getDateDebut() < $vacances->getDateDebut())) {
                $vacances = $indispo;
            }
        }
        if ($vacances && ($m5 = $prendre(fn ($p) => $lettre($p) === 'A'))) {
            $m5->setLieu($lieu('Test Anglet'))->setDate($vacances->getDateDebut()->modify('wednesday this week')->setTime(20, 30));
            $this->cas[] = ['Match placé pendant les vacances ('.$vacances->getNom().')', $decrire($m5)];
        }
        if ($m6 = $prendre(fn ($p) => $lettre($p) === 'C')) {
            $m6->setLieu(null);
            $this->cas[] = ['Match sans gymnase', $decrire($m6)];
        }

        // --- Scores ---------------------------------------------------------------------------------------------
        $termines = array_values(array_filter($parties, fn (Partie $p) => $scenario === 'phase1-terminee' || $p->getDate() < $maintenant->modify('-2 hours')));
        // Le match sans score doit être saisissable par le capitaine de l'équipe qui reçoit : on prend une équipe dont le capitaine a un compte
        $equipesAvecCapitaineConnectable = array_map(fn (string $code) => self::EQUIPES[$code][0], array_keys(array_filter(self::CAPITAINES, fn ($c) => ($c['compte'] ?? null) !== null)));
        $sansScore = null;
        if ($scenario === 'en-cours') {
            foreach (array_reverse($termines) as $p) {
                if ($lettre($p) === 'A' && in_array($p->getIdEquipeRecoit()->getNom(), $equipesAvecCapitaineConnectable, true)) {
                    $sansScore = $p;
                    break;
                }
            }
        }
        $forfait = null;
        foreach ($termines as $p) {
            if ($lettre($p) === 'B' && $p !== $sansScore) {
                $forfait = $p;
                break;
            }
        }
        foreach ($termines as $p) {
            if ($p === $sansScore) {
                continue;
            }
            [$a, $b] = $p === $forfait ? [-1, 3] : self::SCORES[($p->getId() * 7) % count(self::SCORES)];
            $p->setNbSetGagnantReception($a)->setNbSetGagnantDeplacement($b);
        }
        $this->cas[] = [sprintf('%d matchs de la phase 1 déjà joués, scores saisis', count($termines) - ($sansScore ? 1 : 0)), 'accueil : « Derniers résultats », classements calculés'];
        if ($sansScore) {
            $this->cas[] = ['Match joué SANS score saisi (bouton « Saisir » pour son capitaine et pour l\'admin)', $decrire($sansScore)];
        }
        if ($forfait) {
            $this->cas[] = ['Forfait (score enregistré -1 / 3)', $decrire($forfait)];
        }
    }

    private function saisirScoresArchive(Saison $archive): void
    {
        foreach ($archive->getPhases()[0]->getPoules() as $poule) {
            foreach ($poule->getParties() as $partie) {
                [$a, $b] = self::SCORES[($partie->getId() * 5) % count(self::SCORES)];
                $partie->setNbSetGagnantReception($a)->setNbSetGagnantDeplacement($b);
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // Fichier d'import Excel, récapitulatif
    // ---------------------------------------------------------------------------------------------------------

    private function ecrireFichierImport(string $chemin): string
    {
        if (!str_starts_with($chemin, '/')) {
            $chemin = rtrim($this->projectDir, '/').'/'.$chemin;
        }
        @mkdir(dirname($chemin), 0777, true);

        $feuille = new Spreadsheet();
        $feuille->getActiveSheet()->fromArray([
            ['email', 'nom', 'prenom', 'telephone'],
            ['nouveau.joueur@'.self::DOMAINE, 'Nouveau', 'Joueur', '06 12 34 56 78'],      // compte créé + fiche créée
            ['import.existant@'.self::DOMAINE, 'Existant', 'Import', ''],                    // fiche sans compte : reliée
            ['IMPORT.CASSE@'.self::DOMAINE, 'Casse', 'Import', ''],                          // e-mail en majuscules : fiche reliée
            ['import.lie@'.self::DOMAINE, 'Lié', 'Import', ''],                              // fiche déjà liée à un autre compte : avertissement
            ['user.sansfiche@'.self::DOMAINE, 'Sans', 'Fiche', ''],                          // compte existant : rejeté
            ['pas-un-email', 'Invalide', 'Ligne', ''],                                       // donnée invalide : rejetée
            ['', 'Vide', 'Ligne', ''],                                                       // e-mail vide : rejetée
        ]);
        (new Xlsx($feuille))->save($chemin);

        return $chemin;
    }

    private function afficherRecapitulatif(SymfonyStyle $io, string $scenario, string $fichier): void
    {
        $io->success('Base de recette chargée.');

        $io->section('Comptes (mot de passe commun : '.self::MOT_DE_PASSE.')');
        $io->table(['Compte', 'Rôle / situation'], [
            ['admin@blvb.test', 'Administrateur'],
            ['cap.a1@blvb.test', 'Capitaine de ARRATS (poule A, gymnase Anglet) — téléphone renseigné'],
            ['cap.a2@blvb.test', 'Capitaine de BIDART VB (poule A) — SANS téléphone'],
            ['cap.a4 / cap.a6 / cap.a7', 'Capitaines des équipes A4, A6, A7 (poule A)'],
            ['cap.a5@blvb.test', 'Capitaine de GOIZ ARGI — coordonnées seulement sur le compte (repli fiche → compte)'],
            ['cap.b1@blvb.test', 'Capitaine de KOSKO ALAI (poule B)'],
            ['cap.double@blvb.test', 'Capitaine de DEUX équipes : LAPURDI VOLLEY (B) et BAIGORRI (D)'],
            ['cap.c1@blvb.test', 'Capitaine de SOKOA (poule C) — autre poule que A'],
            ['cap.d1@blvb.test', 'Capitaine de ADOUR (poule D) — était simple joueur en 2025-2026'],
            ['joueur.a1@blvb.test', 'Simple joueur de ARRATS (non capitaine), avec compte'],
            ['joueur.a2@blvb.test', 'Simple joueur de BIDART VB, sans téléphone'],
            ['mobile@blvb.test', 'Joueur de CAPBRETON LOISIR en 2026-27 ; il jouait à CHALOSSE en 2025-26'],
            ['user.sansfiche@blvb.test', 'Compte sans fiche joueur'],
            ['user.nonverifie@blvb.test', 'Compte dont l\'e-mail n\'est pas vérifié'],
        ]);
        $io->text('Équipes particulières : A3 (capitaine sans compte, fiche seule), A8 (aucun capitaine), B6 (aucun joueur), C5 (gymnase sans créneau), D4 (aucun gymnase).');
        $io->text('Poules : A = 8 équipes, B = 6, C = 5 (nombre impair), D = 4. Phase 2 : poule A seule remplie, journées sans match. Phase finale vide.');

        if ($this->cas !== []) {
            $io->section('Cas préparés dans la phase 1');
            $io->table(['Cas', 'Détail'], $this->cas);
        }
        $io->text('Fichier Excel d\'exemple pour l\'import des utilisateurs : '.$fichier);
        $io->text('Archive : « '.self::ARCHIVE.' » (terminée, clôturée) pour tester le changement de saison et les compositions qui diffèrent.');
        $io->text('Pour naviguer sur cette base : bin/recette utiliser   (retour à la base de développement : bin/recette dev)');
    }
}
