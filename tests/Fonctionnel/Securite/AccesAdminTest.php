<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * SEC-01 : garde-fou automatique sur TOUTES les routes /admin, lues dans le routeur. Une route admin ajoutée demain
 * est testée sans écrire de test. SEC-02 : règle access_control.
 */
final class AccesAdminTest extends TestFonctionnel
{
    /** Routes /admin volontairement ouvertes à d'autres que l'admin (aucune aujourd'hui). */
    private const EXCEPTIONS = [];

    /** @return array<string, Route> nom => route, pour toutes les routes dont le chemin commence par /admin */
    private function routesAdmin(): array
    {
        $routes = [];
        foreach (self::getContainer()->get('router')->getRouteCollection()->all() as $nom => $route) {
            if (str_starts_with($route->getPath(), '/admin') && !\in_array($nom, self::EXCEPTIONS, true)) {
                $routes[$nom] = $route;
            }
        }
        self::assertGreaterThan(50, \count($routes), 'le routeur doit lister les routes de l\'admin');

        return $routes;
    }

    /** URL de la route, paramètres remplis avec des identifiants du championnat de référence. */
    private function url(string $nom, Route $route): string
    {
        $parametres = [];
        foreach ($route->compile()->getPathVariables() as $variable) {
            $parametres[$variable] = match ($variable) {
                'equipe' => ChampionnatDeTest::get('equipe.A1')->getId(),
                'saison' => ChampionnatDeTest::get('saison')->getId(),
                'poule' => ChampionnatDeTest::get('poule.A')->getId(),
                'partie' => ChampionnatDeTest::get('match.reference')->getId(),
                'journee' => ChampionnatDeTest::get('match.reference')->getJournee()->getId(),
                'membre' => 1,
                'step' => 1,
                default => $this->identifiant($nom),
            };
        }

        return self::getContainer()->get('router')->generate($nom, $parametres);
    }

    /** {id} : un objet existant du type géré par le contrôleur (route admin_<type>_…). */
    private function identifiant(string $nom): int
    {
        return match (true) {
            str_starts_with($nom, 'admin_equipe_') => ChampionnatDeTest::get('equipe.A1')->getId(),
            str_starts_with($nom, 'admin_user_') => ChampionnatDeTest::compte('joueur.a1')->getId(),
            str_starts_with($nom, 'admin_joueur_') => ChampionnatDeTest::get('joueur.joueur.a1')->getId(),
            str_starts_with($nom, 'admin_club_') => ChampionnatDeTest::get('club')->getId(),
            str_starts_with($nom, 'admin_saison') => ChampionnatDeTest::get('saison')->getId(),
            str_starts_with($nom, 'admin_phase_') => ChampionnatDeTest::get('phase.1')->getId(),
            str_starts_with($nom, 'admin_poule_') => ChampionnatDeTest::get('poule.A')->getId(),
            str_starts_with($nom, 'admin_partie_') => ChampionnatDeTest::get('poule.A')->getId(), // {id} = poule pour les calendriers et les API
            str_starts_with($nom, 'admin_journee_') => ChampionnatDeTest::get('match.reference')->getJournee()->getId(),
            str_starts_with($nom, 'admin_lieu_') => ChampionnatDeTest::get('equipe.A1')->getLieu()->getId(),
            default => 1,
        };
    }

    /** Première méthode HTTP acceptée par la route (GET si elle est permise). */
    private function methode(Route $route): string
    {
        $methodes = $route->getMethods() ?: ['GET'];

        return \in_array('GET', $methodes, true) ? 'GET' : $methodes[0];
    }

    #[TestDox('SEC-01 Toutes les routes /admin renvoient un visiteur vers la connexion')]
    public function test_visiteur(): void
    {
        $echecs = [];
        foreach ($this->routesAdmin() as $nom => $route) {
            $this->client->request($this->methode($route), $this->url($nom, $route));
            $reponse = $this->client->getResponse();
            if (!$reponse->isRedirect() || !str_ends_with((string) $reponse->headers->get('Location'), '/login')) {
                $echecs[] = sprintf('%s %s → %d', $this->methode($route), $nom, $reponse->getStatusCode());
            }
        }

        self::assertSame([], $echecs, 'routes admin ouvertes à un visiteur');
    }

    /** @return array<string, array{string}> */
    public static function comptesNonAdmin(): array
    {
        return [
            'simple joueur' => ['joueur.a1'],
            'capitaine' => ['cap.a1'],
            'responsable de club' => ['resp.club'],
        ];
    }

    #[TestDox('SEC-01 Toutes les routes /admin sont refusées (403) à un compte non administrateur : $_dataName')]
    #[\PHPUnit\Framework\Attributes\DataProvider('comptesNonAdmin')]
    public function test_compte_non_admin(string $compte): void
    {
        $this->connecter($compte);
        $echecs = [];
        foreach ($this->routesAdmin() as $nom => $route) {
            $this->client->request($this->methode($route), $this->url($nom, $route));
            if ($this->client->getResponse()->getStatusCode() !== 403) {
                $echecs[] = sprintf('%s %s → %d', $this->methode($route), $nom, $this->client->getResponse()->getStatusCode());
            }
        }

        self::assertSame([], $echecs, 'routes admin ouvertes à '.$compte);
    }

    #[TestDox('SEC-01 Les pages /admin (GET) s\'ouvrent pour un administrateur : ni refus, ni retour à la connexion, ni erreur 500')]
    public function test_administrateur(): void
    {
        $this->connecter('admin');
        $echecs = [];
        foreach ($this->routesAdmin() as $nom => $route) {
            if ($this->methode($route) !== 'GET') {
                continue; // les actions (POST, PUT, DELETE) sont couvertes par SEC-08 et les tests de chaque écran
            }
            $this->client->request('GET', $this->url($nom, $route));
            $reponse = $this->client->getResponse();
            $versConnexion = $reponse->isRedirect() && str_ends_with((string) $reponse->headers->get('Location'), '/login');
            if ($reponse->getStatusCode() === 403 || $versConnexion || $reponse->getStatusCode() >= 500) {
                $echecs[] = sprintf('GET %s → %d', $nom, $reponse->getStatusCode());
            }
        }

        self::assertSame([], $echecs, 'pages admin inaccessibles ou en erreur pour l\'administrateur');
    }

    #[TestDox('SEC-02 La règle access_control exige ROLE_ADMIN pour /admin (et non /Admin)')]
    public function test_regle_access_control(): void
    {
        $config = Yaml::parseFile(self::getContainer()->getParameter('kernel.project_dir').'/config/packages/security.yaml');
        $regles = array_filter($config['security']['access_control'] ?? [], static fn (array $r) => ($r['roles'] ?? null) === 'ROLE_ADMIN' || \in_array('ROLE_ADMIN', (array) ($r['roles'] ?? []), true));

        $couvre = static fn (string $chemin) => array_filter($regles, static fn (array $r) => preg_match('{'.$r['path'].'}', $chemin) === 1) !== [];
        self::assertTrue($couvre('/admin/dashboard'), 'règle sur /admin');
        self::assertTrue($couvre('/admin/club/'), 'règle sur /admin/…');
    }
}
