<?php

namespace App\Tests\Fonctionnel\Front;

use App\Entity\Joueur;
use App\Entity\MembreEquipe;
use App\Factory\MembreEquipeFactory;
use App\Repository\MembreEquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * COM-01 à COM-10 : composition d'une équipe pour une saison (côté site par le capitaine, côté admin).
 * Équipe A1, saison courante : cap.a1 capitaine, joueur.a1 membre.
 */
final class CompositionTest extends TestFonctionnel
{
    private function chemin(string $cote = 'site', string $equipe = 'A1'): string
    {
        return sprintf('%s/equipe/%d/saison/%d/composition', $cote === 'admin' ? '/admin' : '',
            ChampionnatDeTest::get('equipe.'.$equipe)->getId(), ChampionnatDeTest::get('saison')->getId());
    }

    /** @return list<MembreEquipe> */
    private function membres(string $equipe = 'A1'): array
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();

        return self::getContainer()->get(MembreEquipeRepository::class)->findBy(['equipe' => ChampionnatDeTest::get('equipe.'.$equipe)->getId(), 'saison' => ChampionnatDeTest::get('saison')->getId()]);
    }

    private function membre(string $compte, string $equipe = 'A1'): ?MembreEquipe
    {
        foreach ($this->membres($equipe) as $m) {
            if ($m->getJoueur()->getId() === ChampionnatDeTest::get('joueur.'.$compte)->getId()) {
                return $m;
            }
        }

        return null;
    }

    private function message(): string
    {
        $this->client->followRedirect();

        return $this->client->getCrawler()->filter('main')->text();
    }

    /**
     * Action confirmée (retirer, capitaine). Admin : adresse et jeton portés par le bouton de la fenêtre de confirmation ;
     * site : formulaire avec confirmation du navigateur.
     */
    private function confirmer(string $cote, int $membre, string $action): void
    {
        $crawler = $this->client->request('GET', $this->chemin($cote));
        $suffixe = sprintf('/%d/%s', $membre, $action);
        $bouton = $crawler->filter(sprintf('[data-confirmation-action-param$="%s"]', $suffixe));
        if ($bouton->count() > 0) {
            $this->client->request('POST', $bouton->attr('data-confirmation-action-param'), ['_token' => $bouton->attr('data-confirmation-token-param')]);

            return;
        }
        $this->client->submit($crawler->filter(sprintf('form[action$="%s"]', $suffixe))->form());
    }

    private function ajouter(string $cote, int $joueur): void
    {
        $crawler = $this->client->request('GET', $this->chemin($cote));
        $form = $crawler->filter('form[action$="/ajouter"]')->form();
        $valeurs = $form->getPhpValues();
        $nom = array_key_first($valeurs);
        $valeurs[$nom]['joueur'] = (string) $joueur; // valeur forcée : le sélecteur n'offre pas les joueurs déjà présents
        $this->client->request('POST', $form->getUri(), $valeurs);
    }

    private function creer(string $cote, array $champs): void
    {
        $crawler = $this->client->request('GET', $this->chemin($cote));
        $form = $crawler->filter('form[action$="/creer"]')->form();
        $nom = array_key_first($form->getPhpValues());
        foreach ($champs as $champ => $valeur) {
            $form[$nom.'['.$champ.']'] = $valeur;
        }
        $this->client->submit($form);
    }

    #[TestDox('COM-01/09 Ajouter un joueur existant ; le sélecteur ne propose pas les joueurs déjà dans la composition')]
    public function test_ajouter(): void
    {
        $this->connecter('cap.a1');
        $crawler = $this->client->request('GET', $this->chemin());
        $options = $crawler->filter('form[action$="/ajouter"] select option')->each(fn ($o) => $o->attr('value'));
        self::assertNotContains((string) ChampionnatDeTest::get('joueur.joueur.a1')->getId(), $options, 'déjà membre');
        self::assertContains((string) ChampionnatDeTest::get('joueur.joueur.a2')->getId(), $options);

        $this->ajouter('site', ChampionnatDeTest::get('joueur.joueur.a2')->getId());
        self::assertStringContainsString('Joueur ajouté à la composition', $this->message());
        self::assertNotNull($this->membre('joueur.a2'));
        self::assertNotNull($this->membre('joueur.a2', 'A2'), 'il reste aussi dans son équipe');
    }

    #[TestDox('COM-02 Ajouter un joueur déjà présent : refus « fait déjà partie », pas de doublon')]
    public function test_ajouter_doublon(): void
    {
        $this->connecter('cap.a1');
        $avant = \count($this->membres());
        $this->ajouter('site', ChampionnatDeTest::get('joueur.joueur.a1')->getId());

        self::assertStringContainsString('fait déjà partie', $this->message());
        self::assertCount($avant, $this->membres());
    }

    #[TestDox('COM-03 Créer une fiche avec un e-mail inconnu : nouvelle fiche, ajoutée à la composition')]
    public function test_creer_fiche(): void
    {
        $this->connecter('cap.a1');
        $this->creer('site', ['prenom' => 'Nouvelle', 'nom' => 'Joueuse', 'email' => 'nouvelle.joueuse@blvb.test', 'telephone' => '06 12 34 56 78']);

        self::assertStringContainsString('Fiche joueur créée et ajoutée', $this->message());
        $fiche = self::getContainer()->get('doctrine')->getManager()->getRepository(Joueur::class)->findOneBy(['email' => 'nouvelle.joueuse@blvb.test']);
        self::assertNotNull($fiche);
        self::assertNull($fiche->getUser());
        self::assertCount(1, array_filter($this->membres(), fn ($m) => $m->getJoueur()->getId() === $fiche->getId()));
    }

    #[TestDox('COM-04 E-mail d\'un compte existant (casse différente) : fiche liée au compte, ou fiche déjà liée réutilisée ; jamais de seconde fiche')]
    public function test_creer_compte_existant(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $nbFiches = $em->getRepository(Joueur::class)->count([]);
        $this->connecter('cap.a1');

        // Compte sans fiche : une fiche est créée et liée au compte
        $this->creer('site', ['prenom' => 'Sam', 'nom' => 'Seul', 'email' => 'Sans.Equipe@BLVB.test']);
        self::assertStringContainsString('liée au compte existant', $this->message());
        $em->clear();
        $fiche = $em->getRepository(Joueur::class)->findOneBy(['user' => ChampionnatDeTest::compte('sans.equipe')->getId()]);
        self::assertNotNull($fiche, 'fiche liée au compte');
        self::assertSame($nbFiches + 1, $em->getRepository(Joueur::class)->count([]));

        // Compte qui a déjà une fiche (joueur.a2) : sa fiche est réutilisée
        $this->creer('site', ['prenom' => 'Jeanne', 'nom' => 'Second', 'email' => 'JOUEUR.A2@blvb.test']);
        self::assertStringContainsString('liée au compte existant', $this->message());
        self::assertNotNull($this->membre('joueur.a2'), 'fiche existante ajoutée');
        self::assertSame($nbFiches + 1, $em->getRepository(Joueur::class)->count([]), 'pas de seconde fiche');
    }

    #[TestDox('COM-05 Retirer un joueur : il quitte la composition, sa fiche reste ; message nommant le joueur')]
    public function test_retirer(): void
    {
        $this->connecter('cap.a1');
        $this->confirmer('site', $this->membre('joueur.a1')->getId(), 'retirer');

        self::assertStringContainsString('Jean Premier a été retiré(e)', $this->message());
        self::assertNull($this->membre('joueur.a1'));
        self::assertNotNull(self::getContainer()->get('doctrine')->getManager()->find(Joueur::class, ChampionnatDeTest::get('joueur.joueur.a1')->getId()));
    }

    #[TestDox('COM-06/07 Désigner capitaine : un seul capitaine pour l\'équipe et la saison ; le capitaine d\'une autre équipe n\'est pas touché')]
    public function test_designer_capitaine(): void
    {
        // cap.a1 est aussi capitaine de B2 (comme cap.double dans la base de recette)
        MembreEquipeFactory::createOne(['equipe' => ChampionnatDeTest::get('equipe.B2'), 'saison' => ChampionnatDeTest::get('saison'),
            'joueur' => ChampionnatDeTest::get('joueur.cap.a1'), 'capitaine' => true]);
        $this->connecter('cap.a1');
        $this->confirmer('site', $this->membre('joueur.a1')->getId(), 'capitaine');

        // L'ancien capitaine n'a plus accès à la composition : il revient sur la fiche de l'équipe, avec le message
        self::assertResponseRedirects('/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId());
        self::assertStringContainsString('Jean Premier est maintenant capitaine', $this->message());
        $capitaines = array_filter($this->membres(), fn ($m) => $m->isCapitaine());
        self::assertCount(1, $capitaines);
        self::assertSame(ChampionnatDeTest::get('joueur.joueur.a1')->getId(), array_values($capitaines)[0]->getJoueur()->getId());
        self::assertFalse($this->membre('cap.a1')->isCapitaine(), 'l\'ancien capitaine redevient membre');
        self::assertTrue($this->membre('cap.a1', 'B2')->isCapitaine(), 'toujours capitaine de B2');
    }

    #[TestDox('COM-08 Chaque action ramène du côté d\'où elle a été lancée (site ou admin)')]
    public function test_cote_de_retour(): void
    {
        $this->connecter('admin');
        $this->ajouter('admin', ChampionnatDeTest::get('joueur.joueur.a2')->getId());
        self::assertResponseRedirects($this->chemin('admin'));
        $this->confirmer('admin', $this->membre('joueur.a2')->getId(), 'retirer');
        self::assertResponseRedirects($this->chemin('admin'));

        $this->ajouter('site', ChampionnatDeTest::get('joueur.joueur.a2')->getId());
        self::assertResponseRedirects($this->chemin('site'));
    }

    #[TestDox('COM-10 Admin : la composition liste les saisons de l\'équipe ; « Gérer la composition » de la fiche équipe admin mène à la version admin')]
    public function test_admin(): void
    {
        // A1 joue aussi une poule de la saison précédente : la composition admin propose les deux saisons
        $phase = \App\Factory\PhaseFactory::createOne(['saison' => ChampionnatDeTest::get('saison.precedente'), 'nom' => 'Phase 1', 'ordre' => 1,
            'type' => \App\Enum\PhaseType::CHAMPIONNAT, 'datedebut' => new \DateTimeImmutable('-13 months'), 'datefin' => new \DateTimeImmutable('-10 months')]);
        \App\Factory\PouleFactory::createOne(['phase' => $phase, 'nom' => 'Poule A', 'niveau' => 1, 'equipes' => [ChampionnatDeTest::get('equipe.A1')]]);
        $this->connecter('admin');
        $crawler = $this->client->request('GET', $this->chemin('admin'));
        $saisons = $crawler->filter('nav[aria-label="Choisir la saison"] a')->each(fn ($a) => trim($a->text()));
        self::assertContains('Saison test', $saisons);
        self::assertContains('Saison test précédente', $saisons);

        $crawler = $this->client->request('GET', '/admin/equipe/'.ChampionnatDeTest::get('equipe.A1')->getId());
        $lien = $crawler->filter('a:contains("Gérer la composition")')->first();
        self::assertStringStartsWith('/admin/equipe/', $lien->attr('href'));
    }
}
