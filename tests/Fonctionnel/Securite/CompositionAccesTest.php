<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Entity\MembreEquipe;
use App\Repository\MembreEquipeRepository;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * SEC-05 : composition d'une équipe côté site (/equipe/{e}/saison/{s}/composition, GET et 4 POST).
 * SEC-07 : un identifiant de membre d'une autre équipe ou d'une autre saison, glissé dans l'URL, n'est pas traité.
 * (SEC-06, composition côté admin refusée au capitaine, est couvert par SEC-01.)
 */
final class CompositionAccesTest extends TestFonctionnel
{
    private function membre(string $compte, string $equipe = 'A1', string $saison = 'saison'): MembreEquipe
    {
        return self::getContainer()->get(MembreEquipeRepository::class)->findOneBy([
            'joueur' => ChampionnatDeTest::get('joueur.'.$compte)->getId(),
            'equipe' => ChampionnatDeTest::get('equipe.'.$equipe)->getId(),
            'saison' => ChampionnatDeTest::get($saison)->getId(),
        ]);
    }

    private function base(string $saison = 'saison'): string
    {
        return sprintf('/equipe/%d/saison/%d/composition', ChampionnatDeTest::get('equipe.A1')->getId(), ChampionnatDeTest::get($saison)->getId());
    }

    /** @return array<string, array{string, string}> méthode, chemin relatif à la composition */
    private function actions(): array
    {
        $joueur = $this->membre('joueur.a1')->getId();

        return [
            'consulter' => ['GET', ''],
            'ajouter un joueur' => ['POST', '/ajouter'],
            'créer un joueur' => ['POST', '/creer'],
            'retirer un joueur' => ['POST', '/'.$joueur.'/retirer'],
            'nommer capitaine' => ['POST', '/'.$joueur.'/capitaine'],
        ];
    }

    /** @return array<string, array{?string}> */
    public static function profilsRefuses(): array
    {
        return [
            'visiteur' => [null],
            'simple joueur de l\'équipe' => ['joueur.a1'],
            'capitaine d\'une autre équipe' => ['cap.a2'],
            'capitaine de l\'équipe la saison précédente seulement' => ['cap.a1.precedente'],
            'responsable du club de l\'équipe' => ['resp.club'],
        ];
    }

    #[DataProvider('profilsRefuses')]
    #[TestDox('SEC-05 Composition de la saison courante refusée, rien n\'est modifié : $_dataName')]
    public function test_refus(?string $compte): void
    {
        if ($compte !== null) {
            $this->connecter($compte);
        }
        $avant = \count(self::getContainer()->get(MembreEquipeRepository::class)->findRosterBySaison(ChampionnatDeTest::get('equipe.A1'), ChampionnatDeTest::get('saison')));

        foreach ($this->actions() as $action => [$methode, $chemin]) {
            $this->client->request($methode, $this->base().$chemin, ['_token' => 'quelconque']);
            $reponse = $this->client->getResponse();
            $refus = $reponse->getStatusCode() === 403 || ($compte === null && $reponse->isRedirect() && str_ends_with((string) $reponse->headers->get('Location'), '/login'));
            self::assertTrue($refus, sprintf('%s : %s → %d', $compte ?? 'visiteur', $action, $reponse->getStatusCode()));
        }

        self::getContainer()->get('doctrine')->getManager()->clear();
        self::assertCount($avant, self::getContainer()->get(MembreEquipeRepository::class)->findRosterBySaison(ChampionnatDeTest::get('equipe.A1'), ChampionnatDeTest::get('saison')));
        self::assertTrue($this->membre('cap.a1')->isCapitaine(), 'capitaine inchangé');
    }

    #[TestDox('SEC-05 Le capitaine d\'une saison précédente accède à la composition de cette saison-là')]
    public function test_capitaine_saison_precedente(): void
    {
        $this->connecter('cap.a1.precedente');
        $this->client->request('GET', $this->base('saison.precedente'));

        self::assertResponseIsSuccessful();
    }

    /** @return array<string, array{string}> */
    public static function profilsAutorises(): array
    {
        return ['capitaine de l\'équipe' => ['cap.a1'], 'administrateur' => ['admin']];
    }

    #[DataProvider('profilsAutorises')]
    #[TestDox('SEC-05 Composition ouverte (consultation et actions acceptées) : $_dataName')]
    public function test_autorise(string $compte): void
    {
        $this->connecter($compte);
        $this->client->request('GET', $this->base());
        self::assertResponseIsSuccessful();

        foreach ($this->actions() as $action => [$methode, $chemin]) {
            if ($methode === 'GET') {
                continue;
            }
            // Jeton faux : l'action est acceptée (pas de refus d'accès) puis renvoie à la composition sans rien changer
            $this->client->request($methode, $this->base().$chemin, ['_token' => 'faux']);
            self::assertResponseRedirects($this->base(), message: $action);
        }
    }

    #[TestDox('SEC-07 Retirer ou nommer capitaine un membre d\'une autre équipe ou d\'une autre saison : 404, rien n\'est modifié')]
    public function test_identifiant_trafique(): void
    {
        $this->connecter('cap.a1');
        $autreEquipe = $this->membre('joueur.a2', 'A2');
        $autreSaison = $this->membre('cap.a1.precedente', 'A1', 'saison.precedente');

        foreach ([$autreEquipe, $autreSaison] as $membre) {
            foreach (['retirer', 'capitaine'] as $action) {
                $this->client->request('POST', $this->base().'/'.$membre->getId().'/'.$action, ['_token' => 'quelconque']);
                self::assertResponseStatusCodeSame(404, $action.' du membre '.$membre->getId());
            }
        }

        self::getContainer()->get('doctrine')->getManager()->clear();
        self::assertNotNull($this->membre('joueur.a2', 'A2'), 'membre de A2 toujours là');
        self::assertFalse($this->membre('joueur.a2', 'A2')->isCapitaine(), 'pas nommé capitaine');
        self::assertTrue($this->membre('cap.a1.precedente', 'A1', 'saison.precedente')->isCapitaine());
    }
}
