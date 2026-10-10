<?php

namespace App\Tests\Support\Story;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Entity\User;
use App\Enum\PhaseType;
use App\Factory\CreneauFactory;
use App\Factory\EquipeFactory;
use App\Factory\JoueurFactory;
use App\Factory\JourneeFactory;
use App\Factory\LieuFactory;
use App\Factory\MembreEquipeFactory;
use App\Factory\PartieFactory;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

/**
 * Championnat de référence des tests (tests/SPECIFICATIONS.md §3.3) : le plus petit jeu de données
 * qui permet de tester accès, scores, classement et coordonnées. Construit par les tests, jamais copié
 * d'une vraie base. Les dates sont relatives à maintenant.
 *
 * Accès : ChampionnatDeTest::get('saison'), ::get('equipe.A1'), ::get('compte.cap.a1'), ::get('match.reference')…
 *
 *  Saison test (favorite)      barème volontairement tout différent : 5 / 4 / 2 / 1 / forfait -3
 *  Saison test précédente      non favorite
 *  Phase 1 (championnat)       Poule A : A1 A2 A3 A4     Poule B : B1 B2 B3 (impair)
 *  Phase 2 (championnat)       vide, ordre 2 (pour la clôture)
 *  Gymnases                    G1 : mercredi 20:30, 1 terrain · G2 : lundi et jeudi, 2 terrains · G3 : aucun créneau
 *                              A4 n'a pas de gymnase
 *  Journée 1 (semaine passée)  A1 reçoit A2, hier, SANS score  ← match de référence
 *                              A3 reçoit A4, hier, 3-1          B1 reçoit B2, hier, 3-2
 *  Journée 2 (semaine à venir) A2 reçoit A3, A4 reçoit A1 (sans score)
 *
 *  Comptes (mot de passe non utilisable : se connecter avec KernelBrowser::loginUser) :
 *    admin               administrateur
 *    cap.a1              capitaine de A1 (équipe qui reçoit le match de référence), avec téléphone
 *    cap.a2              capitaine de A2 (équipe qui se déplace)
 *    cap.b1              capitaine de B1 (autre poule)
 *    joueur.a1           simple joueur de A1          joueur.a2   simple joueur de A2
 *    cap.a1.precedente   capitaine de A1 la saison précédente seulement, simple joueur cette saison
 *    sans.equipe         compte sans fiche joueur
 */
final class ChampionnatDeTest extends Story
{
    public function build(): void
    {
        $saison = SaisonFactory::createOne([
            'nom' => 'Saison test',
            'favori' => 1,
            'date_debut' => new \DateTimeImmutable('-2 months'),
            'date_fin' => new \DateTimeImmutable('+8 months'),
            'points_victoire_forte' => 5,
            'points_victoire_faible' => 4,
            'points_defaite_forte' => 2,
            'points_defaite_faible' => 1,
            'points_forfait' => -3,
        ]);
        $precedente = SaisonFactory::createOne([
            'nom' => 'Saison test précédente',
            'favori' => 0,
            'date_debut' => new \DateTimeImmutable('-14 months'),
            'date_fin' => new \DateTimeImmutable('-3 months'),
        ]);
        $this->addState('saison', $saison);
        $this->addState('saison.precedente', $precedente);

        // Gymnases
        $g1 = LieuFactory::createOne(['nom' => 'Gymnase G1']);
        CreneauFactory::createOne(['lieu' => $g1, 'jourSemaine' => 3, 'capacite' => 1, 'prioritaire' => 1]);
        $g2 = LieuFactory::createOne(['nom' => 'Gymnase G2']);
        CreneauFactory::createOne(['lieu' => $g2, 'jourSemaine' => 1, 'capacite' => 2, 'prioritaire' => 1]);
        CreneauFactory::createOne(['lieu' => $g2, 'jourSemaine' => 4, 'capacite' => 2, 'prioritaire' => 2]);
        $g3 = LieuFactory::createOne(['nom' => 'Gymnase G3']);
        foreach (['G1' => $g1, 'G2' => $g2, 'G3' => $g3] as $nom => $lieu) {
            $this->addState('gymnase.'.$nom, $lieu);
        }

        // Équipes
        $lieux = ['A1' => $g1, 'A2' => $g2, 'A3' => $g1, 'A4' => null, 'B1' => $g2, 'B2' => $g3, 'B3' => $g1];
        $equipes = [];
        foreach ($lieux as $code => $lieu) {
            $equipes[$code] = EquipeFactory::createOne(['nom' => 'EQUIPE '.$code, 'lieu' => $lieu]);
            $this->addState('equipe.'.$code, $equipes[$code]);
        }

        // Phases et poules
        $phase1 = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => new \DateTimeImmutable('-1 month'), 'datefin' => new \DateTimeImmutable('+2 months')]);
        PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 2', 'ordre' => 2, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => new \DateTimeImmutable('+2 months'), 'datefin' => new \DateTimeImmutable('+7 months')]);
        $pouleA = PouleFactory::createOne(['phase' => $phase1, 'nom' => 'Poule A', 'niveau' => 1,
            'equipes' => [$equipes['A1'], $equipes['A2'], $equipes['A3'], $equipes['A4']]]);
        $pouleB = PouleFactory::createOne(['phase' => $phase1, 'nom' => 'Poule B', 'niveau' => 2,
            'equipes' => [$equipes['B1'], $equipes['B2'], $equipes['B3']]]);
        $this->addState('phase.1', $phase1);
        $this->addState('poule.A', $pouleA);
        $this->addState('poule.B', $pouleB);

        // Journées et matchs
        $j1A = JourneeFactory::createOne(['poule' => $pouleA, 'numero' => 1,
            'date_debut' => new \DateTimeImmutable('monday last week'), 'date_fin' => new \DateTimeImmutable('sunday last week')]);
        $j2A = JourneeFactory::createOne(['poule' => $pouleA, 'numero' => 2,
            'date_debut' => new \DateTimeImmutable('monday next week'), 'date_fin' => new \DateTimeImmutable('sunday next week')]);
        $j1B = JourneeFactory::createOne(['poule' => $pouleB, 'numero' => 1,
            'date_debut' => new \DateTimeImmutable('monday last week'), 'date_fin' => new \DateTimeImmutable('sunday last week')]);

        $hier = new \DateTimeImmutable('yesterday 20:30');
        $dansUneSemaine = new \DateTimeImmutable('+7 days 20:30');
        $this->addState('match.reference', $this->match($j1A, $equipes['A1'], $equipes['A2'], $hier));
        $this->addState('match.A3-A4', $this->match($j1A, $equipes['A3'], $equipes['A4'], $hier, [3, 1]));
        $this->addState('match.B1-B2', $this->match($j1B, $equipes['B1'], $equipes['B2'], $hier, [3, 2]));
        $this->addState('match.futur', $this->match($j2A, $equipes['A2'], $equipes['A3'], $dansUneSemaine));
        $this->match($j2A, $equipes['A4'], $equipes['A1'], $dansUneSemaine);

        // Comptes et compositions
        $this->addState('compte.admin', UserFactory::createOne(['email' => 'admin@blvb.test', 'roles' => ['ROLE_ADMIN'], 'prenom' => 'Ada', 'nom' => 'Admin']));
        $this->membre('cap.a1', 'Camille', 'Arrieta', $equipes['A1'], $saison, true, '06 11 11 11 11');
        $this->membre('cap.a2', 'Claude', 'Bidart', $equipes['A2'], $saison, true);
        $this->membre('cap.b1', 'Dominique', 'Biarritz', $equipes['B1'], $saison, true);
        $this->membre('joueur.a1', 'Jean', 'Premier', $equipes['A1'], $saison, false, '06 10 10 10 10');
        $this->membre('joueur.a2', 'Jeanne', 'Second', $equipes['A2'], $saison, false);
        $precedent = $this->membre('cap.a1.precedente', 'Pat', 'Ancien', $equipes['A1'], $precedente, true);
        MembreEquipeFactory::createOne(['equipe' => $equipes['A1'], 'saison' => $saison, 'joueur' => $precedent, 'capitaine' => false]);
        $this->addState('compte.sans.equipe', UserFactory::createOne(['email' => 'sans.equipe@blvb.test', 'prenom' => 'Sam', 'nom' => 'Seul']));
    }

    /** @param array{int, int}|null $score sets de l'équipe qui reçoit, sets de l'équipe qui se déplace */
    private function match(object $journee, Equipe $recoit, Equipe $deplace, \DateTimeImmutable $date, ?array $score = null): Partie
    {
        return PartieFactory::createOne([
            'journee' => $journee,
            'poule' => $journee->getPoule(),
            'idEquipeRecoit' => $recoit,
            'idEquipeDeplace' => $deplace,
            'lieu' => $recoit->getLieu(),
            'date' => $date,
            'nbSetGagnantReception' => $score[0] ?? null,
            'nbSetGagnantDeplacement' => $score[1] ?? null,
        ]);
    }

    /** Crée un compte, sa fiche joueur liée et son appartenance à l'équipe ; renvoie la fiche joueur. */
    private function membre(string $compte, string $prenom, string $nom, Equipe $equipe, Saison $saison, bool $capitaine, ?string $telephone = null): object
    {
        $user = UserFactory::createOne(['email' => $compte.'@blvb.test', 'prenom' => $prenom, 'nom' => $nom, 'telephone' => $telephone]);
        $joueur = JoueurFactory::createOne(['prenom' => $prenom, 'nom' => $nom, 'email' => $compte.'@blvb.test', 'telephone' => $telephone, 'user' => $user]);
        MembreEquipeFactory::createOne(['equipe' => $equipe, 'saison' => $saison, 'joueur' => $joueur, 'capitaine' => $capitaine]);
        $this->addState('compte.'.$compte, $user);
        $this->addState('joueur.'.$compte, $joueur);

        return $joueur;
    }

    public static function compte(string $nom): User
    {
        return self::get('compte.'.$nom);
    }
}
