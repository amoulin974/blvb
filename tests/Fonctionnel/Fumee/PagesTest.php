<?php

namespace App\Tests\Fonctionnel\Fumee;

use App\Entity\Saison;
use App\Factory\SaisonFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Routing\Route;

/**
 * FUM-01 : chaque page (route GET hors /admin, lue dans le routeur) s'affiche sans erreur, paramètres remplis
 * depuis le championnat de référence. Les pages /admin sont parcourues par SEC-01 (AccesAdminTest).
 * FUM-02 : les pages publiques tiennent avec une saison vide (sans phase).
 */
final class PagesTest extends TestFonctionnel
{
    /** Routes qui ne sont pas des pages à parcourir (lien reçu par e-mail, action, outils). */
    private const IGNOREES = ['app_reset_password', 'app_verify_email', 'app_logout'];

    /** @return array<string, string> nom => URL */
    private function pages(): array
    {
        $routeur = self::getContainer()->get('router');
        $valeurs = [
            'id' => ChampionnatDeTest::get('equipe.A1')->getId(),
            'equipe' => ChampionnatDeTest::get('equipe.A1')->getId(),
            'saison' => ChampionnatDeTest::get('saison')->getId(),
            'poule' => ChampionnatDeTest::get('poule.A')->getId(),
        ];
        $pages = [];
        foreach ($routeur->getRouteCollection()->all() as $nom => $route) {
            /** @var Route $route */
            if (str_starts_with($nom, '_') || str_starts_with($route->getPath(), '/admin') || \in_array($nom, self::IGNOREES, true)) {
                continue;
            }
            if ($route->getMethods() !== [] && !\in_array('GET', $route->getMethods(), true)) {
                continue;
            }
            $parametres = [];
            foreach ($route->compile()->getPathVariables() as $variable) {
                $parametres[$variable] = $valeurs[$variable] ?? 1;
            }
            if ($nom === 'front_club_show' || $nom === 'front_club_edit') {
                $parametres['id'] = ChampionnatDeTest::get('club')->getId();
            }
            $pages[$nom] = $routeur->generate($nom, $parametres);
        }
        self::assertGreaterThan(15, \count($pages));

        return $pages;
    }

    /** @param array<string, string> $pages @return list<string> pages en erreur */
    private function parcourir(array $pages): array
    {
        $echecs = [];
        foreach ($pages as $nom => $url) {
            $this->client->request('GET', $url);
            $statut = $this->client->getResponse()->getStatusCode();
            // 403 attendu : modifier la fiche d'un club ou la composition d'une équipe dont on n'est pas responsable / capitaine
            $refusAttendu = $statut === 403 && \in_array($nom, ['front_club_edit', 'front_equipe_composition_index'], true);
            if ($statut >= 400 && !$refusAttendu) {
                $echecs[] = sprintf('%s (%s) → %d', $nom, $url, $statut);
            }
        }

        return $echecs;
    }

    #[TestDox('FUM-01 Toutes les pages du site s\'affichent sans erreur : visiteur')]
    public function test_visiteur(): void
    {
        self::assertSame([], $this->parcourir($this->pages()));
    }

    #[TestDox('FUM-01 Toutes les pages du site s\'affichent sans erreur : capitaine (composition de son équipe comprise)')]
    public function test_capitaine(): void
    {
        $this->connecter('cap.a1');
        self::assertSame([], $this->parcourir($this->pages()));
    }

    #[TestDox('FUM-01 Toutes les pages du site s\'affichent sans erreur : responsable de club (modification de sa fiche comprise)')]
    public function test_responsable(): void
    {
        $this->connecter('resp.club');
        self::assertSame([], $this->parcourir($this->pages()));
    }

    #[TestDox('FUM-02 Les pages publiques s\'affichent avec une saison vide (sans phase) choisie comme saison courante')]
    public function test_saison_vide(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        foreach ($em->getRepository(Saison::class)->findAll() as $saison) {
            $saison->setFavori(0);
        }
        $em->flush();
        SaisonFactory::createOne(['nom' => 'Saison vide', 'favori' => 1, 'date_debut' => new \DateTimeImmutable('-1 month'), 'date_fin' => new \DateTimeImmutable('+10 months')]);
        self::getContainer()->get('cache.app')->clear();

        $echecs = [];
        foreach (['/', '/equipes', '/calendrier', '/calendrierlieu', '/classement', '/clubs', '/qui-sommes-nous'] as $page) {
            $this->client->request('GET', $page);
            if ($this->client->getResponse()->getStatusCode() >= 400) {
                $echecs[] = $page.' → '.$this->client->getResponse()->getStatusCode();
            }
        }
        self::assertSame([], $echecs);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('body', 'Saison vide', 'la saison vide est bien celle affichée');
    }
}
