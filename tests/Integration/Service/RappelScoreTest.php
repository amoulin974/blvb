<?php

namespace App\Tests\Integration\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Factory\PartieFactory;
use App\Service\RappelScoreService;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\Persistence\save;

/**
 * MAI-02 : rappel par e-mail des scores non saisis au bout de 2 jours (RappelScoreService, app:scores:rappeler).
 *
 * Championnat de référence : le match de référence (A1 reçoit A2) date d'hier, sans score : trop récent.
 * Chaque test ajoute un match « A1 reçoit A2 » vieux de 3 jours. Destinataires attendus : capitaine de A1 (cap.a1),
 * capitaine de A2 (cap.a2) et responsable du club de A1 (resp.club).
 */
final class RappelScoreTest extends TestIntegration
{
    private RappelScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        ChampionnatDeTest::load();
        $this->service = self::service(RappelScoreService::class);
    }

    private function match(string $equipeRecoit, string $equipeDeplace, string $date, ?array $score = null): Partie
    {
        /** @var Equipe $recoit */
        $recoit = ChampionnatDeTest::get('equipe.'.$equipeRecoit);

        return PartieFactory::createOne([
            'journee' => ChampionnatDeTest::get('match.reference')->getJournee(),
            'poule' => ChampionnatDeTest::get('poule.A'),
            'idEquipeRecoit' => $recoit,
            'idEquipeDeplace' => ChampionnatDeTest::get('equipe.'.$equipeDeplace),
            'lieu' => $recoit->getLieu(),
            'date' => new \DateTimeImmutable($date),
            'nbSetGagnantReception' => $score[0] ?? null,
            'nbSetGagnantDeplacement' => $score[1] ?? null,
        ]);
    }

    /** @return string[] */
    private function destinataires(): array
    {
        return array_keys($this->service->preparer(new \DateTimeImmutable()));
    }

    #[TestDox('MAI-02 Deux jours après le match sans score : capitaines des deux équipes et responsable du club sont prévenus, une seule fois chacun')]
    public function test_rappel_aux_capitaines_et_responsables_du_club(): void
    {
        $this->match('A1', 'A2', '-3 days 20:30');

        self::assertSame(['cap.a1@blvb.test', 'cap.a2@blvb.test', 'resp.club@blvb.test'], $this->destinataires());
    }

    #[TestDox('MAI-02 Pas de rappel : match de moins de 2 jours, match à venir, score déjà saisi (même partiel), match sans date')]
    public function test_pas_de_rappel_quand_il_n_y_a_rien_a_rappeler(): void
    {
        // Le match de référence (hier, sans score) et les matchs à venir du championnat de référence sont déjà là
        $this->match('A1', 'A2', '-3 days 20:30', [3, 1]);
        $this->match('A1', 'A2', '-3 days 21:30', [0, 0]);
        $this->match('A1', 'A2', '-1 day 12:00');
        $sansDate = $this->match('A1', 'A2', '-3 days 22:30');
        self::service(EntityManagerInterface::class)->getConnection()->executeStatement('UPDATE partie SET date = NULL WHERE id = ?', [$sansDate->getId()]);

        self::assertSame([], $this->destinataires());
    }

    #[TestDox('MAI-02 Un destinataire reçoit un seul e-mail qui récapitule tous ses matchs ; le délai se règle (--jours)')]
    public function test_un_seul_mail_par_destinataire(): void
    {
        $this->match('A1', 'A2', '-3 days 20:30');
        $this->match('A2', 'A1', '-5 days 20:30');

        $resultat = $this->service->envoyer(new \DateTimeImmutable());

        self::assertSame(3, $resultat['mails'], 'cap.a1, cap.a2, resp.club');
        self::assertSame(2, $resultat['matchs']);
        self::assertSame([], $resultat['echecs']);
        self::assertEmailCount(3);
        foreach (self::getMailerMessages() as $email) {
            self::assertSame('BLVB : 2 scores à saisir', $email->getSubject());
            self::assertEmailAddressContains($email, 'From', 'blvb@vblc.fr');
        }
    }

    #[TestDox('MAI-02 Contenu de l\'e-mail : équipes, date, gymnase, poule et lien vers le calendrier')]
    public function test_contenu_du_mail(): void
    {
        $this->match('A1', 'A2', '-3 days 20:30');

        $this->service->envoyer(new \DateTimeImmutable());

        $capitaine = self::getMailerMessages()[0];
        self::assertSame('BLVB : un score à saisir', $capitaine->getSubject());
        $html = $capitaine->getHtmlBody();
        self::assertStringContainsString('EQUIPE A1 (CTA)', $html);
        self::assertStringContainsString('EQUIPE A2', $html);
        self::assertStringContainsString((new \DateTimeImmutable('-3 days'))->format('d/m/Y').' à 20h30', $html);
        self::assertStringContainsString('Gymnase G1', $html);
        self::assertStringContainsString('Poule A', $html);
        self::assertStringContainsString('/calendrier', $html);
    }

    #[TestDox('MAI-02 Un match n\'est rappelé qu\'une fois : relancer l\'envoi ne renvoie rien')]
    public function test_un_seul_rappel_par_match(): void
    {
        $match = $this->match('A1', 'A2', '-3 days 20:30');
        $maintenant = new \DateTimeImmutable();

        $premier = $this->service->envoyer($maintenant);
        $second = $this->service->envoyer($maintenant);

        self::assertSame(['mails' => 3, 'matchs' => 1], ['mails' => $premier['mails'], 'matchs' => $premier['matchs']]);
        self::assertSame(['mails' => 0, 'matchs' => 0], ['mails' => $second['mails'], 'matchs' => $second['matchs']]);
        self::assertEmailCount(3);
        self::assertNotNull(self::service(EntityManagerInterface::class)->find(Partie::class, $match->getId())->getRappelScoreEnvoyeLe());
    }

    #[TestDox('MAI-02 Une même personne capitaine et responsable de club ne reçoit qu\'un e-mail')]
    public function test_pas_de_doublon_capitaine_et_responsable(): void
    {
        $this->match('A1', 'A2', '-3 days 20:30');
        save(ChampionnatDeTest::get('club')->setResponsables(ChampionnatDeTest::get('saison'), [
            ChampionnatDeTest::compte('resp.club'),
            ChampionnatDeTest::compte('cap.a1'),
        ]));

        self::assertSame(['cap.a1@blvb.test', 'cap.a2@blvb.test', 'resp.club@blvb.test'], $this->destinataires());
    }

    #[TestDox('MAI-02 Match sans capitaine ni responsable : aucun e-mail, match non marqué (rappelé dès qu\'un destinataire existe)')]
    public function test_match_sans_destinataire_non_marque(): void
    {
        // A3 est du club voisin (sans responsable), A4 n'a pas de club ; aucun des deux n'a de capitaine
        $match = $this->match('A3', 'A4', '-3 days 20:30');

        $resultat = $this->service->envoyer(new \DateTimeImmutable());

        self::assertSame(0, $resultat['mails']);
        self::assertSame(0, $resultat['matchs']);
        self::assertEmailCount(0);
        self::assertNull(self::service(EntityManagerInterface::class)->find(Partie::class, $match->getId())->getRappelScoreEnvoyeLe());
    }

    #[TestDox('MAI-02 --jours=0 inclut les matchs d\'hier ; app:scores:rappeler --simulation n\'envoie ni ne marque rien')]
    public function test_commande_simulation_puis_envoi(): void
    {
        $commande = (new Application(self::bootKernel()))->find('app:scores:rappeler');
        $testeur = new CommandTester($commande);

        $testeur->execute(['--simulation' => true, '--jours' => '0']);
        self::assertSame(0, $testeur->getStatusCode());
        self::assertStringContainsString('cap.a1@blvb.test', $testeur->getDisplay());
        self::assertStringContainsString('Simulation', $testeur->getDisplay());
        self::assertEmailCount(0);
        self::assertNull(ChampionnatDeTest::get('match.reference')->getRappelScoreEnvoyeLe());

        $testeur->execute(['--jours' => '0']);
        self::assertSame(0, $testeur->getStatusCode());
        self::assertStringContainsString('3 e-mail(s) envoyé(s) pour 1 match(s).', $testeur->getDisplay());
        self::assertEmailCount(3);

        $testeur->execute(['--jours' => 'abc']);
        self::assertSame(2, $testeur->getStatusCode(), 'délai invalide refusé');
    }
}
