<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Joueur;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\MembreEquipe;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\MembreEquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SEC-08 : toute action de l'admin qui modifie des données exige un jeton CSRF. Sans jeton (ou avec un jeton faux),
 * rien n'est modifié et la page dit « La page a expiré… » ; l'API du calendrier répond 403.
 */
final class JetonsAdminTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    private function id(string $etat): int
    {
        return ChampionnatDeTest::get($etat)->getId();
    }

    /** Photographie de la base, relue après vidage du gestionnaire d'entités : sert à vérifier que rien n'a changé. */
    private function etat(): array
    {
        $em = $this->em();
        $em->clear();
        $compter = static fn (string $classe) => $em->getRepository($classe)->count([]);

        return [
            'équipes' => $compter(Equipe::class), 'joueurs' => $compter(Joueur::class), 'comptes' => $compter(User::class),
            'gymnases' => $compter(Lieu::class), 'clubs' => $compter(Club::class), 'saisons' => $compter(Saison::class),
            'phases' => $compter(Phase::class), 'poules' => $compter(Poule::class), 'journées' => $compter(Journee::class),
            'matchs' => $compter(Partie::class), 'membres' => $compter(MembreEquipe::class),
            'phase close' => $em->find(Phase::class, $this->id('phase.1'))->getClose(),
            'saison favorite' => $em->getRepository(Saison::class)->findOneBy(['favori' => 1])?->getId(),
            'capitaine de A1' => self::getContainer()->get(MembreEquipeRepository::class)->findOneBy(['equipe' => $this->id('equipe.A1'), 'saison' => $this->id('saison'), 'capitaine' => true])?->getId(),
        ];
    }

    /** @return array<string, string> action => URL (POST) */
    private function actions(): array
    {
        $routeur = self::getContainer()->get('router');
        $journee = ChampionnatDeTest::get('match.reference')->getJournee()->getId();
        $membre = self::getContainer()->get(MembreEquipeRepository::class)->findOneBy(['joueur' => $this->id('joueur.joueur.a1'), 'saison' => $this->id('saison')])->getId();
        $poule = $this->id('poule.A');
        $composition = ['equipe' => $this->id('equipe.A1'), 'saison' => $this->id('saison'), 'membre' => $membre];

        return [
            'supprimer une équipe' => $routeur->generate('admin_equipe_delete', ['id' => $this->id('equipe.B3')]),
            'supprimer un joueur' => $routeur->generate('admin_joueur_delete', ['id' => $this->id('joueur.joueur.a2')]),
            'supprimer un compte' => $routeur->generate('admin_user_delete', ['id' => $this->id('compte.sans.equipe')]),
            'supprimer un gymnase' => $routeur->generate('admin_lieu_delete', ['id' => $this->id('gymnase.G3')]),
            'supprimer un club' => $routeur->generate('admin_club_delete', ['id' => $this->id('club.voisin')]),
            'supprimer une saison' => $routeur->generate('admin_saison_delete', ['id' => $this->id('saison.precedente')]),
            'supprimer une phase' => $routeur->generate('admin_phase_delete', ['id' => $this->id('phase.1')]),
            'supprimer une poule' => $routeur->generate('admin_poule_delete', ['id' => $this->id('poule.B')]),
            'supprimer une journée' => $routeur->generate('admin_journee_delete', ['id' => $journee]),
            'supprimer un match' => $routeur->generate('admin_partie_delete', ['id' => $this->id('match.A3-A4')]),
            'retirer un joueur de la composition' => $routeur->generate('admin_equipe_composition_retirer', $composition),
            'nommer capitaine' => $routeur->generate('admin_equipe_composition_capitaine', $composition),
            'clôturer une phase' => $routeur->generate('admin_phase_cloturer', ['id' => $this->id('phase.1')]),
            'saison favorite' => $routeur->generate('admin_saison_favorite', ['id' => $this->id('saison.precedente')]),
            'supprimer tous les matchs d\'une poule' => $routeur->generate('admin_partie_deleteall', ['id' => $poule]),
            'supprimer les matchs d\'une journée' => $routeur->generate('admin_partie_deleteallbyjournee', ['id' => $poule, 'journee' => $journee]),
            'générer les matchs d\'une poule' => $routeur->generate('admin_partie_optimiser', ['id' => $this->id('poule.B')]),
            'supprimer toutes les journées' => $routeur->generate('admin_journee_deleteall', ['id' => $poule]),
            'créer les journées' => $routeur->generate('admin_journee_createjournee', ['id' => $this->id('poule.B')]),
            'générer tout le calendrier de la phase' => $routeur->generate('admin_phase_optimisertout', ['id' => $this->id('phase.1')]),
        ];
    }

    #[TestDox('SEC-08 Sans jeton ou avec un jeton faux, aucune action de l\'admin ne modifie rien et la page dit « La page a expiré »')]
    public function test_actions_sans_jeton(): void
    {
        $this->connecter('admin');
        $avant = $this->etat();
        $echecs = [];

        foreach ($this->actions() as $action => $url) {
            foreach (['sans jeton' => [], 'jeton faux' => ['_token' => 'faux']] as $cas => $donnees) {
                $this->client->request('POST', $url, $donnees);
                $reponse = $this->client->getResponse();
                if (!$reponse->isRedirect()) {
                    $echecs[] = sprintf('%s (%s) : réponse %d au lieu d\'une redirection', $action, $cas, $reponse->getStatusCode());
                    continue;
                }
                $this->client->followRedirect();
                if (!str_contains((string) $this->client->getResponse()->getContent(), 'La page a expiré')) {
                    $echecs[] = sprintf('%s (%s) : pas de message « La page a expiré »', $action, $cas);
                }
                if ($this->etat() !== $avant) {
                    $echecs[] = sprintf('%s (%s) : la base a changé', $action, $cas);
                    $avant = $this->etat();
                }
            }
        }

        self::assertSame([], $echecs);
    }

    #[TestDox('SEC-08 API du calendrier de l\'admin : sans jeton ou avec un jeton faux → 403, rien n\'est modifié ; avec le jeton de la page → accepté')]
    public function test_api_calendrier(): void
    {
        $this->connecter('admin');
        $poule = $this->id('poule.A');
        $journee = ChampionnatDeTest::get('match.reference')->getJournee()->getId();
        $match = $this->id('match.A3-A4');
        $appels = [
            'déplacer une journée' => ['PUT', "/admin/journee/$poule/api/$journee", ['datedebut' => '2030-01-07', 'datefin' => '2030-01-13']],
            'supprimer une journée' => ['DELETE', "/admin/journee/$poule/api/$journee", null],
            'ajouter une journée' => ['POST', "/admin/journee/$poule/api", ['datedebut' => '2030-02-04', 'datefin' => '2030-02-10']],
            'déplacer un match' => ['PUT', "/admin/partie/$poule/api/$match", ['datedebut' => '2030-01-08 20:30:00']],
            'supprimer un match' => ['DELETE', "/admin/partie/$poule/api/$match", null],
        ];
        $avant = $this->etat();
        $dates = fn () => [$this->em()->find(Journee::class, $journee)->getDateDebut(), $this->em()->find(Partie::class, $match)->getDate()];
        $datesAvant = $dates();

        foreach ($appels as $appel => [$methode, $url, $corps]) {
            foreach (['sans jeton' => [], 'jeton faux' => ['HTTP_X_CSRF_TOKEN' => 'faux']] as $cas => $entetes) {
                $this->client->request($methode, $url, server: ['CONTENT_TYPE' => 'application/json'] + $entetes, content: $corps === null ? null : json_encode($corps));
                self::assertResponseStatusCodeSame(403, "$appel ($cas)");
            }
        }
        self::assertSame($avant, $this->etat(), 'aucune journée ni aucun match ajouté ou supprimé');
        self::assertEquals($datesAvant, $dates(), 'aucune date déplacée');

        // Avec le jeton fourni par la page du calendrier, l'appel est accepté
        $crawler = $this->client->request('GET', '/admin/partie/'.$poule.'/getpartiecalendar');
        $jeton = $crawler->filter('[data-calendar-csrf-token-value]')->attr('data-calendar-csrf-token-value');
        $this->client->request('PUT', "/admin/partie/$poule/api/$match", server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $jeton], content: json_encode(['datedebut' => '2030-01-08 20:30:00']));
        self::assertResponseIsSuccessful('déplacement accepté avec le jeton');
        self::assertSame('2030-01-08 20:30', $this->em()->find(Partie::class, $match)->getDate()->format('Y-m-d H:i'));
    }
}
