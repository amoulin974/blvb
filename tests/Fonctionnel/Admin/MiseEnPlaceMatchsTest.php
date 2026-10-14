<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Entity\Partie;
use App\Entity\Poule;
use App\Enum\PhaseType;
use App\Factory\PhaseFactory;
use App\Factory\PouleFactory;
use App\Factory\SaisonFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * MEP-16 à MEP-20 : mise en place d'une saison validée, étape « Matchs » (D35 à D40). Saison de l'an prochain, une
 * phase de championnat de 12 semaines : Poule A (A1 à A4, 6 matchs prévus ; A4 n'a pas de gymnase) et Poule B
 * (B1 à B3, 3 matchs prévus).
 */
final class MiseEnPlaceMatchsTest extends TestFonctionnel
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }

    /** @return array{saison: int, phase: int, poules: array<string, int>, url: string, journees: string} */
    private function saison(): array
    {
        $debut = (new \DateTimeImmutable('+1 year'))->modify('monday this week');
        $saison = SaisonFactory::createOne([
            'nom' => 'Saison suivante', 'brouillon' => false, 'favori' => 0,
            'date_debut' => $debut, 'date_fin' => $debut->modify('+1 year'),
        ]);
        $phase = PhaseFactory::createOne(['saison' => $saison, 'nom' => 'Phase 1', 'ordre' => 1, 'type' => PhaseType::CHAMPIONNAT,
            'datedebut' => $debut, 'datefin' => $debut->modify('+83 days')]);
        $e = fn (string $code) => ChampionnatDeTest::get('equipe.'.$code);
        $a = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'equipes' => [$e('A1'), $e('A2'), $e('A3'), $e('A4')]]);
        $b = PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule B', 'niveau' => 2, 'equipes' => [$e('B1'), $e('B2'), $e('B3')]]);
        $base = '/admin/saison/'.$saison->getId().'/mise-en-place/';

        return [
            'saison' => $saison->getId(), 'phase' => $phase->getId(),
            'poules' => ['A' => $a->getId(), 'B' => $b->getId()],
            'url' => $base.'matchs', 'journees' => $base.'journees',
        ];
    }

    /** Crée les journées par l'étape précédente, comme le ferait l'administrateur. */
    private function creerJournees(array $s): void
    {
        $crawler = $this->client->request('GET', $s['journees']);
        $this->client->submit($crawler->filter('button[value="generer-toutes"]')->form());
    }

    private function jeton(string $url): string
    {
        return $this->client->request('GET', $url)->filter('input[name="_token"]')->attr('value');
    }

    private function envoyer(array $s, string $action): void
    {
        $this->client->request('POST', $s['url'].'?phase='.$s['phase'], ['_token' => $this->jeton($s['url']), 'action' => $action]);
    }

    /** @return int nombre de matchs de la poule */
    private function nbMatchs(int $pouleId): int
    {
        $this->em()->clear();

        return $this->em()->getRepository(Partie::class)->count(['poule' => $pouleId]);
    }

    #[TestDox('MEP-16 Sans journées : erreur avec lien vers l\'étape Journées, aucun bouton de génération, « Terminer » désactivé avec sa raison')]
    public function test_sans_journees(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->client->request('GET', $s['url']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#titre-etape', 'Matchs de Phase 1');
        self::assertSelectorTextContains('body', '0 match créé sur 9 prévus.');
        self::assertSelectorTextContains('body', 'Des poules n\'ont pas toutes leurs journées : créez-les avant de générer les matchs.');
        self::assertSelectorExists('.admin-alerte a[href="'.$s['journees'].'?phase='.$s['phase'].'"]');
        self::assertSelectorNotExists('[data-confirmation-action-param]');
        self::assertSelectorExists('button[value="terminer"][aria-disabled="true"]');
        self::assertSelectorTextContains('#raison-validation', 'La Poule A n\'a pas toutes ses journées.');
    }

    #[TestDox('MEP-17 Générer les matchs de la phase : 6 + 3 matchs, message de succès, bilan, déplacements, équipe sans gymnase signalée, « Terminer » possible')]
    public function test_generer_phase(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->creerJournees($s);

        $crawler = $this->client->request('GET', $s['url']);
        self::assertSelectorTextContains('body', 'Les matchs ne sont pas encore créés.');
        $declencheur = $crawler->filter('[data-confirmation-action-param*="/matchs"]');
        self::assertCount(1, $declencheur);
        self::assertSame('Calcul en cours', $declencheur->attr('data-confirmation-attente-param'));

        $this->envoyer($s, 'generer-phase');
        self::assertResponseRedirects($s['url'].'?phase='.$s['phase']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.admin-alerte', '9 matchs créés pour 2 poules.');
        self::assertSelectorTextContains('body', '9 matchs créés sur 9 prévus.');
        self::assertSelectorTextContains('#poule-'.$s['poules']['A'], '6 matchs sur 6 prévus');
        self::assertSelectorTextContains('#poule-'.$s['poules']['B'], '3 matchs sur 3 prévus');
        self::assertSelectorTextContains('#poule-'.$s['poules']['A'], 'de suite');
        self::assertSelectorTextContains('body', 'sans gymnase utilisable : EQUIPE A4');
        self::assertSelectorTextContains('body', 'seront joués au gymnase par défaut');
        self::assertSelectorNotExists('button[value="terminer"][aria-disabled="true"]');
        self::assertSame(6, $this->nbMatchs($s['poules']['A']));
        self::assertSame(3, $this->nbMatchs($s['poules']['B']));

        $this->cliquerTerminer($s);
        self::assertResponseRedirects('/admin/saison/'.$s['saison']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'Mise en place terminée pour Phase 1');
    }

    private function cliquerTerminer(array $s): void
    {
        $crawler = $this->client->request('GET', $s['url']);
        $this->client->submit($crawler->filter('button[value="terminer"]')->form());
    }

    #[TestDox('MEP-18 Poule par poule : régénérer remplace les matchs de la poule sans toucher aux autres ; « Générer » refusé si la poule a des matchs, accepté si elle n\'en a pas')]
    public function test_par_poule(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->creerJournees($s);
        $this->envoyer($s, 'generer-phase');
        $this->em()->clear();
        $avantB = array_map(fn (Partie $p) => $p->getId(), $this->em()->getRepository(Partie::class)->findBy(['poule' => $s['poules']['B']]));
        $avantA = array_map(fn (Partie $p) => $p->getId(), $this->em()->getRepository(Partie::class)->findBy(['poule' => $s['poules']['A']]));

        $this->envoyer($s, 'regenerer-'.$s['poules']['A']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '6 matchs créés pour Poule A.');
        $this->em()->clear();
        $apresB = array_map(fn (Partie $p) => $p->getId(), $this->em()->getRepository(Partie::class)->findBy(['poule' => $s['poules']['B']]));
        $apresA = array_map(fn (Partie $p) => $p->getId(), $this->em()->getRepository(Partie::class)->findBy(['poule' => $s['poules']['A']]));
        self::assertSame($avantB, $apresB, 'la poule B n\'est pas touchée');
        self::assertSame([], array_intersect($avantA, $apresA), 'les matchs de la poule A sont recréés');
        self::assertCount(6, $apresA);

        $this->envoyer($s, 'generer-'.$s['poules']['A']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'a déjà ses matchs');

        // Poule B vidée de ses matchs : « Générer les matchs de la poule » apparaît et fonctionne
        foreach ($this->em()->getRepository(Partie::class)->findBy(['poule' => $s['poules']['B']]) as $partie) {
            $this->em()->remove($partie);
        }
        $this->em()->flush();
        $crawler = $this->client->request('GET', $s['url']);
        // La génération d'une poule passe par la fenêtre de confirmation : son déclencheur porte l'action
        $declencheur = $crawler->filter('[data-confirmation-champs-param*="generer-'.$s['poules']['B'].'"]');
        self::assertCount(1, $declencheur);
        self::assertSame('Calcul en cours', $declencheur->attr('data-confirmation-attente-param'));
        $this->envoyer($s, 'generer-'.$s['poules']['B']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '3 matchs créés pour Poule B.');
        self::assertSame(3, $this->nbMatchs($s['poules']['B']));
    }

    #[TestDox('MEP-19 Poules à moins de deux équipes ou journées manquantes : génération refusée avant toute suppression, matchs conservés')]
    public function test_prerequis(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->envoyer($s, 'generer-phase'); // aucune journée
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'n\'a pas toutes ses journées');
        self::assertSame(0, $this->nbMatchs($s['poules']['A']));

        $this->creerJournees($s);
        $this->envoyer($s, 'generer-phase');
        $this->em()->clear();
        $a = $this->em()->find(Poule::class, $s['poules']['A']);
        $a->removeEquipe(ChampionnatDeTest::get('equipe.A1'));   // effectif 3 : 3 journées prévues mais 3 journées créées
        $a->removeEquipe(ChampionnatDeTest::get('equipe.A2'));   // effectif 2 : 1 journée prévue, 3 créées
        $this->em()->flush();

        $this->envoyer($s, 'regenerer-'.$s['poules']['A']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'n\'a pas toutes ses journées');
        self::assertSame(6, $this->nbMatchs($s['poules']['A']), 'les matchs existants sont conservés');
    }

    #[TestDox('MEP-21 Replacer les matchs à venir d\'une poule : refusé sans la confirmation « aucun match joué sans score » ; les matchs joués sont conservés, les autres recréés, aucune rencontre dupliquée')]
    public function test_replacer_poule(): void
    {
        $saison = ChampionnatDeTest::get('saison');
        $phase = ChampionnatDeTest::get('phase.1');
        $poule = ChampionnatDeTest::get('poule.A')->getId();
        $url = '/admin/saison/'.$saison->getId().'/mise-en-place/matchs';
        $this->connecter('admin');
        $cle = fn (Partie $p) => implode('-', [min($p->getIdEquipeRecoit()->getId(), $p->getIdEquipeDeplace()->getId()), max($p->getIdEquipeRecoit()->getId(), $p->getIdEquipeDeplace()->getId())]);

        $this->em()->clear();
        $avant = $this->em()->getRepository(Partie::class)->findBy(['poule' => $poule]);
        $joues = array_map(fn (Partie $p) => $p->getId(), array_filter($avant, fn (Partie $p) => $p->getNbSetGagnantReception() !== null));
        $rencontres = array_map($cle, $avant);
        sort($rencontres);
        self::assertCount(6, $avant);
        self::assertCount(3, $joues);

        // Écran : bloc de confirmation, avertissement sur le match sans score à date passée
        $this->client->request('GET', $url.'?phase='.$phase->getId());
        self::assertSelectorTextContains('.admin-alerte', 'les matchs joués sont figés');
        self::assertSelectorExists('input[name="confirme-'.$poule.'"]');
        self::assertSelectorTextContains('#poule-'.$poule, 'a une date déjà passée');
        self::assertSelectorNotExists('[data-confirmation-champs-param*="regenerer-'.$poule.'"]');

        // Sans la case cochée : rien n'est modifié
        $token = $this->jeton($url.'?phase='.$phase->getId());
        $this->client->request('POST', $url.'?phase='.$phase->getId(), ['_token' => $token, 'action' => 'replacer-'.$poule]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'Cochez la case de confirmation');
        self::assertCount(6, $this->em()->getRepository(Partie::class)->findBy(['poule' => $poule]));
        self::assertSelectorExists('#poule-'.$poule.' details[open] input[name="confirme-'.$poule.'"]', 'le bloc reste ouvert après un refus');

        // Avec la case cochée
        $this->client->request('POST', $url.'?phase='.$phase->getId(), ['_token' => $token, 'action' => 'replacer-'.$poule, 'confirme-'.$poule => '1']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '3 matchs à venir replacés ; 3 matchs joués conservés.');

        $this->em()->clear();
        $apres = $this->em()->getRepository(Partie::class)->findBy(['poule' => $poule]);
        self::assertCount(6, $apres);
        $idsApres = array_map(fn (Partie $p) => $p->getId(), $apres);
        self::assertSame([], array_diff($joues, $idsApres), 'les matchs joués sont conservés');
        $rencontresApres = array_map($cle, $apres);
        sort($rencontresApres);
        self::assertSame($rencontres, $rencontresApres, 'qui rencontre qui ne change pas');
        $nouveaux = array_filter($apres, fn (Partie $p) => !in_array($p->getId(), $joues, true));
        self::assertCount(3, $nouveaux);
        foreach ($nouveaux as $partie) {
            self::assertNull($partie->getNbSetGagnantReception());
        }
    }

    #[TestDox('MEP-22 Poule sans match joué dans une phase lancée : régénérée en entier ; poule avec matchs joués et phase entière : régénération refusée, replacer accepté')]
    public function test_phase_lancee_poule_par_poule(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->creerJournees($s);
        $this->envoyer($s, 'generer-phase');

        // Un score saisi dans la poule A : la phase est lancée, la poule A aussi, pas la poule B
        $this->em()->clear();
        $partie = $this->em()->getRepository(Partie::class)->findOneBy(['poule' => $s['poules']['A']]);
        $partie->setNbSetGagnantReception(3);
        $partie->setNbSetGagnantDeplacement(1);
        $joue = $partie->getId();
        $this->em()->flush();
        $this->em()->clear();

        $this->envoyer($s, 'generer-phase');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'ses matchs joués sont figés');

        $this->envoyer($s, 'regenerer-'.$s['poules']['A']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'La Poule A a des matchs joués');

        $this->envoyer($s, 'regenerer-'.$s['poules']['B']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '3 matchs créés pour Poule B.');

        // Replacer toute la phase : avec la case, le match joué est conservé
        $token = $this->jeton($s['url']);
        $this->client->request('POST', $s['url'].'?phase='.$s['phase'], ['_token' => $token, 'action' => 'replacer-phase', 'confirme-phase' => '1']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', '1 match joué conservé');
        $this->em()->clear();
        self::assertNotNull($this->em()->find(Partie::class, $joue));
        self::assertSame(6, $this->nbMatchs($s['poules']['A']));
        self::assertSame(3, $this->nbMatchs($s['poules']['B']));
    }

    #[TestDox('MEP-23 Un score saisi seulement côté extérieur compte comme match joué : la phase est lancée, la génération complète refusée')]
    public function test_score_exterieur_seul(): void
    {
        $s = $this->saison();
        $this->connecter('admin');
        $this->creerJournees($s);
        $this->envoyer($s, 'generer-phase');
        $this->client->followRedirect(); // consomme le message de succès

        $this->em()->clear();
        $partie = $this->em()->getRepository(Partie::class)->findOneBy(['poule' => $s['poules']['A']]);
        $partie->setNbSetGagnantDeplacement(3);
        $partie->setNbSetGagnantReception(null);
        $this->em()->flush();
        $this->em()->clear();

        $this->client->request('GET', $s['url']);
        self::assertSelectorTextContains('.admin-alerte', 'les matchs joués sont figés');
        $this->envoyer($s, 'generer-phase');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'ses matchs joués sont figés');
        self::assertSame(6, $this->nbMatchs($s['poules']['A']));
    }

    #[TestDox('MEP-20 Sécurité et verrou : jeton absent, poule d\'une autre phase, action inconnue, phase avec scores saisis, brouillon, non-administrateur')]
    public function test_securite(): void
    {
        $s = $this->saison();
        $autre = ChampionnatDeTest::get('poule.B')->getId();
        $this->connecter('admin');
        $this->creerJournees($s);
        $token = $this->jeton($s['url']);

        foreach ([
            ['action' => 'generer-phase'],                                           // sans jeton
            ['_token' => $token, 'action' => 'generer-'.$autre],                     // poule d'une autre phase
            ['_token' => $token, 'action' => 'regenerer-999999'],
            ['_token' => $token, 'action' => 'tout-supprimer'],
        ] as $envoi) {
            $this->client->request('POST', $s['url'], $envoi);
            self::assertResponseRedirects();
            $this->client->followRedirect();
        }
        self::assertSame(0, $this->nbMatchs($s['poules']['A']));

        // Phase avec des scores saisis : lecture seule, génération refusée sans rien supprimer
        $saison = ChampionnatDeTest::get('saison');
        $phase = ChampionnatDeTest::get('phase.1');
        $url = '/admin/saison/'.$saison->getId().'/mise-en-place/matchs?phase='.$phase->getId();
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorTextContains('.admin-alerte', 'Cette phase est lancée');
        self::assertSelectorNotExists('[data-confirmation-action-param]');
        $avant = $this->em()->getRepository(Partie::class)->count([]);
        $this->client->request('POST', $url, ['_token' => $crawler->filter('input[name="_token"]')->attr('value'), 'action' => 'generer-phase']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'ses matchs joués sont figés');
        self::assertSame($avant, $this->em()->getRepository(Partie::class)->count([]));

        // L'ancienne page d'optimisation respecte le même verrou : rien n'est supprimé
        $optimiser = $this->client->request('GET', '/admin/phase/'.$phase->getId().'/optimiser');
        $jeton = $optimiser->filter('[data-confirmation-action-param$="/optimisertout"]')->attr('data-confirmation-token-param');
        $this->client->request('POST', '/admin/phase/'.$phase->getId().'/optimisertout', ['_token' => $jeton]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.admin-alerte', 'ses matchs ne peuvent plus être refaits');
        self::assertSame($avant, $this->em()->getRepository(Partie::class)->count([]));

        $brouillon = SaisonFactory::createOne(['nom' => 'Brouillon', 'brouillon' => true, 'etapeWizard' => 3, 'favori' => 0,
            'date_debut' => new \DateTimeImmutable('+3 years'), 'date_fin' => new \DateTimeImmutable('+4 years')]);
        $this->client->request('GET', '/admin/saison/'.$brouillon->getId().'/mise-en-place/matchs');
        self::assertResponseRedirects('/admin/saison/'.$brouillon->getId().'/wizard/3');

        $this->connecter('cap.a1');
        $this->client->request('GET', $s['url']);
        $this->assertAccesRefuse('un capitaine ne crée pas les matchs');
    }
}
