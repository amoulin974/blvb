<?php

namespace App\Command;

use App\Entity\Equipe;
use App\Repository\EquipeRepository;
use App\Repository\LieuRepository;
use App\Service\ClubDepuisNomEquipeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reprise des équipes existantes, à lancer une fois au déploiement des clubs :
 *  1. crée les clubs du référentiel ci-dessous (nom court, nom long, gymnases) ;
 *  2. rattache chaque équipe au club désigné par le sigle en fin de nom (« EROAK (AOVB) »), ancien sigle
 *     traduit au besoin (« (SJDL) » → VBLC), et retire ce sigle du nom enregistré ;
 *  3. rattache les équipes sans sigle listées dans le référentiel.
 * L'affichage ajoute le nom court du club : « ADISKIDEAK (VBLC) » (Equipe::getNom).
 *
 * Sans --force : simulation, rien n'est enregistré. Relançable : une équipe traitée n'a plus de sigle,
 * un club existant (même nom court) est réutilisé sans changer son nom long.
 */
#[AsCommand(
    name: 'app:clubs:depuis-noms-equipes',
    description: 'Crée les clubs et y rattache les équipes à partir du sigle en fin de nom, puis retire ce sigle du nom enregistré',
)]
final class ClubsDepuisNomsEquipesCommand extends Command
{
    /**
     * Référentiel des clubs (validé par le bureau du championnat le 11/10/2026).
     * sigles : anciens sigles écrits dans le nom des équipes ; equipes : équipes sans sigle (nom exact, casse ignorée).
     */
    public const CLUBS = [
        ['court' => 'AOVB', 'nom' => 'Anglet Olympique Volley Ball', 'gymnases' => ['Anglet'], 'sigles' => ['AOVB']],
        ['court' => 'ASPTT', 'nom' => "Section Volley de l'ASPTT", 'gymnases' => ['Biarritz'], 'sigles' => ['ASPTT']],
        ['court' => 'VBLC', 'nom' => 'Volley Ball Luzien et Cibourien', 'gymnases' => ['St-Jean-de-Luz'], 'sigles' => ['SJDL']],
        ['court' => 'JSAVB', 'nom' => 'Jeanne & Serge Angresse Volley Ball', 'gymnases' => ['Angresse'], 'sigles' => ['ANGR']],
        ['court' => 'USCVL', 'nom' => 'UNION SPORTIVE CAPBRETONNAISE VOLLEY LOISIR', 'gymnases' => ['Capbreton'], 'sigles' => ['CAP']],
        ['court' => 'Ondres', 'nom' => 'Ondres Volley Ball', 'gymnases' => ['Ondres'], 'sigles' => ['OND']],
        ['court' => 'BVB', 'nom' => 'Bayonne Volley Ball', 'gymnases' => ['Bayonne'], 'sigles' => ['BAY']],
        ['court' => 'Léo', 'nom' => 'Léo Lagrange Bayonne', 'gymnases' => ['Bayonne'], 'equipes' => ['LEO LAGRANGE']],
        ['court' => 'Tosse', 'nom' => 'Tosse Volley Club', 'gymnases' => ['Tosse'], 'equipes' => ['les Tamalous', 'TOSSE 2', 'SAMOUSSAS']],
    ];

    public function __construct(
        private readonly EquipeRepository $equipeRepository,
        private readonly LieuRepository $lieuRepository,
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

        // 1. Clubs du référentiel
        $alias = $equipesSansSigle = $lignesClubs = $alertes = [];
        foreach (self::CLUBS as $def) {
            ['club' => $club, 'cree' => $cree] = $this->service->club($def['court'], $def['nom']);
            $gymnases = [];
            foreach ($def['gymnases'] as $nomGymnase) {
                $lieu = $this->lieuRepository->findOneBy(['nom' => $nomGymnase]);
                if ($lieu === null) {
                    $alertes[] = sprintf('Gymnase « %s » introuvable : non rattaché au club %s.', $nomGymnase, $def['court']);
                    continue;
                }
                $club->addLieu($lieu);
                $gymnases[] = $nomGymnase;
            }
            foreach ($def['sigles'] ?? [] as $sigle) {
                $alias[$sigle] = $def['court'];
            }
            foreach ($def['equipes'] ?? [] as $nomEquipe) {
                $equipesSansSigle[mb_strtolower($nomEquipe)] = $def['court'];
            }
            $lignesClubs[] = [$club->getNomCourt(), $club->getNom(), implode(', ', $gymnases), $cree ? 'créé' : 'existant'];
        }

        // 2 et 3. Équipes
        $rattachees = $sansClub = $conflits = [];
        $trouvees = [];
        foreach ($this->equipeRepository->findBy([], ['nom' => 'ASC']) as $equipe) {
            $avant = (string) $equipe->getNomSansClub();
            $resultat = $this->service->rattacher($equipe, $alias);

            if ($resultat['statut'] === 'sans_club' && isset($equipesSansSigle[mb_strtolower($resultat['nom'])]) && $equipe->getClub() === null) {
                $nomCourt = $equipesSansSigle[mb_strtolower($resultat['nom'])];
                $trouvees[mb_strtolower($resultat['nom'])] = true;
                $this->service->club($nomCourt)['club']->addEquipe($equipe);
                $resultat['statut'] = 'rattachee';
            }

            match ($resultat['statut']) {
                'rattachee' => $rattachees[] = [$avant, $equipe->getNomSansClub(), $equipe->getClub()->getNomCourt(), $equipe->getNom()],
                'conflit' => $conflits[] = [$avant, $resultat['club']->getNomCourt()],
                'sans_club' => $equipe->getClub() === null ? $sansClub[] = [$avant, $avant !== $resultat['nom'] ? 'espaces retirés' : 'inchangé'] : null,
            };
        }
        foreach (array_diff_key($equipesSansSigle, $trouvees) as $nomEquipe => $nomCourt) {
            $alertes[] = sprintf('Équipe « %s » du référentiel (club %s) introuvable ou déjà rattachée.', $nomEquipe, $nomCourt);
        }

        $io->section('Clubs');
        $io->table(['Nom court', 'Nom long', 'Gymnases', 'État'], $lignesClubs);

        $io->section(sprintf('Équipes rattachées à un club (%d)', \count($rattachees)));
        $rattachees ? $io->table(['Nom en base avant', 'Nom en base après', 'Club', 'Nom affiché'], $rattachees) : $io->text('Aucune.');

        if ($sansClub) {
            $io->section(sprintf('Équipes sans club (%d) : à rattacher à la main dans l\'admin', \count($sansClub)));
            $io->table(['Nom', 'Traitement'], $sansClub);
        }
        if ($conflits) {
            $io->section(sprintf('Équipes non modifiées : déjà rattachées à un autre club (%d)', \count($conflits)));
            $io->table(['Nom', 'Club actuel'], $conflits);
        }
        foreach ($alertes as $alerte) {
            $io->warning($alerte);
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
