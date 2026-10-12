<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Journee;
use App\Entity\Partie;
use App\Entity\Phase;
use App\Factory\PouleFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/** PHA-03 à PHA-05 : bouton « Clôturer la phase » de la fiche saison. */
final class CloturePhaseTest extends TestFonctionnel
{
    private function phase(string $nom): Phase
    {
        return self::getContainer()->get('doctrine')->getManager()->getRepository(Phase::class)->findOneBy(['nom' => $nom]);
    }

    /**
     * Jeton CSRF de clôture. Le bouton n'est affiché que pour une phase clôturable : sinon (dernière phase, phase déjà
     * close), le jeton est celui qu'enverrait une page restée ouverte ou une requête forgée par un administrateur.
     */
    private function jeton(Phase $phase): string
    {
        $crawler = $this->client->request('GET', '/admin/saison/'.ChampionnatDeTest::get('saison')->getId());
        $bouton = $crawler->filter('[data-confirmation-action-param$="/admin/phase/'.$phase->getId().'/cloturer"]');
        if ($bouton->count()) {
            return $bouton->attr('data-confirmation-token-param');
        }
        $requete = $this->client->getRequest();
        $pile = $this->client->getContainer()->get('request_stack');
        $pile->push($requete);
        $jeton = $this->client->getContainer()->get('security.csrf.token_manager')->getToken('cloturer'.$phase->getId())->getValue();
        $requete->getSession()->save();
        $pile->pop();

        return $jeton;
    }

    /** Clôture la phase et renvoie le texte de la page d'arrivée (fiche saison). */
    private function cloturer(Phase $phase, ?string $jeton = null): string
    {
        $jeton ??= $this->jeton($phase);
        $this->client->request('POST', '/admin/phase/'.$phase->getId().'/cloturer', ['_token' => $jeton]);
        self::assertResponseRedirects('/admin/saison/'.ChampionnatDeTest::get('saison')->getId());

        return $this->client->followRedirect()->filter('body')->text();
    }

    private function preparerPhase2(): Phase
    {
        $phase2 = $this->phase('Phase 2');
        PouleFactory::createOne(['phase' => $phase2, 'nom' => 'Poule haute', 'niveau' => 1, 'nb_montee_defaut' => 1, 'nb_descente_defaut' => 1]);
        PouleFactory::createOne(['phase' => $phase2, 'nom' => 'Poule basse', 'niveau' => 2, 'nb_montee_defaut' => 1, 'nb_descente_defaut' => 1]);

        return $phase2;
    }

    #[TestDox('PHA-03 Après clôture : phase close, équipes réparties, journées et matchs de la phase suivante créés')]
    public function test_cloture(): void
    {
        $phase2 = $this->preparerPhase2();
        $this->connecter('admin');

        $texte = $this->cloturer($this->phase('Phase 1'));
        self::assertStringContainsString('Équipes basculées avec succès', $texte);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertSame(1, $this->phase('Phase 1')->getClose());
        $phase2 = $em->find(Phase::class, $phase2->getId());
        $nbEquipes = array_sum(array_map(fn ($p) => $p->getEquipes()->count(), $phase2->getPoules()->toArray()));
        self::assertSame(7, $nbEquipes, 'les 7 équipes de la phase 1 sont réparties');
        foreach ($phase2->getPoules() as $poule) {
            self::assertNotSame(0, $em->getRepository(Journee::class)->count(['poule' => $poule]), $poule->getNom().' : journées');
            self::assertNotSame(0, $em->getRepository(Partie::class)->count(['poule' => $poule]), $poule->getNom().' : matchs');
        }
    }

    #[TestDox('PHA-04 Dernière phase de la saison : message d\'erreur, la phase n\'est pas clôturée')]
    public function test_derniere_phase(): void
    {
        $this->connecter('admin');

        $texte = $this->cloturer($this->phase('Phase 2'));
        self::assertStringContainsString('Il n’y a pas de phase suivante', $texte);

        self::getContainer()->get('doctrine')->getManager()->clear();
        self::assertSame(0, $this->phase('Phase 2')->getClose());
    }

    #[TestDox('PHA-05 Clôturer une seconde fois est refusé : les matchs (et scores) de la phase suivante sont conservés')]
    public function test_double_cloture(): void
    {
        $this->preparerPhase2();
        $this->connecter('admin');
        $jeton = $this->jeton($this->phase('Phase 1')); // page restée ouverte dans un second onglet
        $this->cloturer($this->phase('Phase 1'));

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $avant = array_map(fn (Partie $p) => $p->getId(), $em->getRepository(Partie::class)->findAll());

        $texte = $this->cloturer($this->phase('Phase 1'), $jeton);
        self::assertStringContainsString('est déjà clôturée. Rien n\'a été modifié.', $texte);
        $em->clear();
        self::assertSame($avant, array_map(fn (Partie $p) => $p->getId(), $em->getRepository(Partie::class)->findAll()));
    }
}
