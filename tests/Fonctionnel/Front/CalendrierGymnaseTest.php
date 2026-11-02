<?php

namespace App\Tests\Fonctionnel\Front;

use App\Factory\CreneauFactory;
use App\Factory\FermetureGymnaseFactory;
use App\Factory\IndisponibiliteFactory;
use App\Factory\LieuFactory;
use App\Factory\PartieFactory;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * GYM-02, GYM-04, GYM-05, GYM-06 : page « Calendrier par gymnase » (/calendrierlieu) sur un gymnase construit pour le test
 * (mercredi : 2 terrains, priorité 2 ; vendredi : 1 terrain, priorité 1), avec des matchs à des dates choisies.
 */
final class CalendrierGymnaseTest extends TestFonctionnel
{
    private \DateTimeImmutable $lundi;

    /** Date relative au lundi de référence (dans la phase 1, qui finit dans deux mois). */
    private function jour(int $decalage): \DateTimeImmutable
    {
        return $this->lundi->modify(sprintf('%+d days', $decalage))->setTime(20, 30);
    }

    private function match(int $decalage, string $recoit, string $deplace, object $lieu): void
    {
        PartieFactory::createOne(['journee' => ChampionnatDeTest::get('match.reference')->getJournee(), 'poule' => ChampionnatDeTest::get('poule.A'),
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.'.$recoit), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.'.$deplace),
            'lieu' => $lieu, 'date' => $this->jour($decalage)]);
    }

    /** Texte de la ligne (vue tableau) de cette date dans la section du gymnase, et son attribut data-alerte. */
    private function ligne(\Symfony\Component\DomCrawler\Crawler $page, int $decalage): array
    {
        $formatteur = new \IntlDateFormatter('fr', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
        $date = mb_convert_case(mb_substr($formatteur->format($this->jour($decalage)), 0, 1), MB_CASE_UPPER).mb_substr($formatteur->format($this->jour($decalage)), 1);
        $lignes = $page->filterXPath("//h3[contains(., 'Gymnase analyse')]/ancestor::*[.//table][1]//table//tbody/tr")
            ->reduce(fn ($tr) => trim($tr->filter('td')->first()->text()) === $date);
        self::assertCount(1, $lignes, "ligne du $date");

        return [preg_replace('/\s+/', ' ', $lignes->text()), $lignes->attr('data-alerte')];
    }

    #[TestDox('GYM-02/04/05 Calendrier par gymnase : surcharge, complet, terrains libres, priorité, hors créneau, vacances, dates libres')]
    public function test_page(): void
    {
        $this->lundi = new \DateTimeImmutable('monday next week');
        $lieu = LieuFactory::createOne(['nom' => 'Gymnase analyse']);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 3, 'capacite' => 2, 'prioritaire' => 2]);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 5, 'capacite' => 1, 'prioritaire' => 1]);
        IndisponibiliteFactory::createOne(['saison' => ChampionnatDeTest::get('saison'), 'nom' => 'Vacances de test',
            'dateDebut' => $this->lundi->modify('+21 days'), 'dateFin' => $this->lundi->modify('+34 days')]);

        foreach ([['A1', 'A2'], ['A3', 'A4'], ['A2', 'A3']] as [$r, $d]) {
            $this->match(2, $r, $d, $lieu);   // mercredi S1 : 3 matchs pour 2 terrains
        }
        $this->match(9, 'A1', 'A3', $lieu);   // mercredi S2 : 2 matchs, complet
        $this->match(9, 'A2', 'A4', $lieu);
        $this->match(16, 'A4', 'A1', $lieu);  // mercredi S3 : 1 match, 1 terrain libre
        $this->match(4, 'A3', 'A1', $lieu);   // vendredi S1 : créneau non prioritaire
        $this->match(1, 'A4', 'A2', $lieu);   // mardi S1 : hors créneau
        $this->match(23, 'A1', 'A4', $lieu);  // mercredi S4, pendant les vacances

        $page = $this->client->request('GET', '/calendrierlieu');
        self::assertResponseIsSuccessful();

        self::assertSame([true, '1'], [str_contains($this->ligne($page, 2)[0], 'Surcharge : 3 matchs pour 2 terrains'), $this->ligne($page, 2)[1]]);
        self::assertSame([true, '0'], [str_contains($this->ligne($page, 9)[0], 'Complet'), $this->ligne($page, 9)[1]]);
        self::assertSame([true, '0'], [str_contains($this->ligne($page, 16)[0], '1 terrain libre sur 2'), $this->ligne($page, 16)[1]]);
        [$vendredi, $alerte] = $this->ligne($page, 4);
        self::assertStringContainsString('Créneau non prioritaire', $vendredi);
        self::assertSame('1', $alerte);
        [$mardi, $alerte] = $this->ligne($page, 1);
        self::assertStringContainsString('hors créneau', $mardi);
        self::assertSame('1', $alerte);
        [$vacancesAvecMatch, $alerte] = $this->ligne($page, 23);
        self::assertStringContainsString('Match pendant les vacances scolaires (Vacances de test)', $vacancesAvecMatch);
        self::assertSame('1', $alerte);
        [$vacancesSansMatch, $alerte] = $this->ligne($page, 30);
        self::assertStringContainsString('Aucun match', $vacancesSansMatch, 'date de créneau libre listée');
        self::assertStringContainsString('Pendant les vacances scolaires (Vacances de test)', $vacancesSansMatch);
        self::assertSame('0', $alerte);
        [$libre, $alerte] = $this->ligne($page, 37);
        self::assertStringContainsString('2 terrains libres sur 2', $libre);
        self::assertSame('0', $alerte);
    }

    #[TestDox('GYM-06 Fermeture : date libre marquée « Gymnase fermé », jamais un terrain libre ; match à déplacer signalé ; motif échappé, retiré une fois la date passée')]
    public function test_fermetures(): void
    {
        $this->lundi = new \DateTimeImmutable('monday next week');
        $lieu = LieuFactory::createOne(['nom' => 'Gymnase analyse']);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 3, 'capacite' => 2, 'prioritaire' => 1]);
        CreneauFactory::createOne(['lieu' => $lieu, 'jourSemaine' => 5, 'capacite' => 2, 'prioritaire' => 2]);
        FermetureGymnaseFactory::createOne(['lieu' => $lieu, 'motif' => 'Toiture', 'dateDebut' => $this->lundi->modify('+7 days'), 'dateFin' => $this->lundi->modify('+9 days')]);
        FermetureGymnaseFactory::createOne(['lieu' => $lieu, 'motif' => '0', 'dateDebut' => $this->lundi->modify('+16 days'), 'dateFin' => $this->lundi->modify('+16 days')]);
        FermetureGymnaseFactory::createOne(['lieu' => $lieu, 'motif' => '<script>alert(1)</script>', 'dateDebut' => $this->lundi->modify('+23 days'), 'dateFin' => $this->lundi->modify('+23 days')]);
        // Mercredi d'au moins 5 jours avant aujourd'hui, quel que soit le jour du lancement (lundi de référence : 1 à 7 jours après)
        FermetureGymnaseFactory::createOne(['lieu' => $lieu, 'motif' => 'Motif ancien', 'dateDebut' => $this->lundi->modify('-12 days'), 'dateFin' => $this->lundi->modify('-12 days')]);
        FermetureGymnaseFactory::createOne(['lieu' => $lieu, 'motif' => null, 'dateDebut' => $this->lundi->modify('+30 days'), 'dateFin' => $this->lundi->modify('+30 days')]);
        FermetureGymnaseFactory::createOne(['motif' => 'Autre gymnase', 'dateDebut' => $this->lundi, 'dateFin' => $this->lundi->modify('+60 days')]);
        $this->match(9, 'A1', 'A3', $lieu);
        $this->match(2, 'A2', 'A4', $lieu);
        PartieFactory::createOne(['journee' => ChampionnatDeTest::get('match.reference')->getJournee(), 'poule' => ChampionnatDeTest::get('poule.A'),
            'idEquipeRecoit' => ChampionnatDeTest::get('equipe.A1'), 'idEquipeDeplace' => ChampionnatDeTest::get('equipe.A2'),
            'lieu' => $lieu, 'date' => $this->jour(-12), 'nb_set_gagnant_reception' => 3, 'nb_set_gagnant_deplacement' => 0]);

        $page = $this->client->request('GET', '/calendrierlieu');
        self::assertResponseIsSuccessful();

        [$matchDeplace, $alerte] = $this->ligne($page, 9);
        self::assertStringContainsString('Gymnase fermé : 1 match à déplacer', $matchDeplace);
        self::assertStringContainsString('Motif : Toiture', $matchDeplace);
        self::assertStringNotContainsString('terrain', $matchDeplace);
        self::assertSame('1', $alerte);

        [$fermee, $alerte] = $this->ligne($page, 16);
        self::assertStringContainsString('Gymnase fermé', $fermee);
        self::assertStringContainsString('Motif : 0', $fermee);
        self::assertStringNotContainsString('terrain', $fermee);
        self::assertSame('0', $alerte, 'même sur un créneau non prioritaire');

        [$matchJoue, $alerte] = $this->ligne($page, -12);
        self::assertStringContainsString('Gymnase fermé', $matchJoue);
        self::assertStringNotContainsString('déplacer', $matchJoue);
        self::assertStringNotContainsString('Motif ancien', $this->client->getResponse()->getContent());
        self::assertSame('0', $alerte);

        self::assertStringNotContainsString('<script>alert(1)', $this->client->getResponse()->getContent());
        self::assertStringContainsString('&lt;script&gt;alert(1)', $this->client->getResponse()->getContent());

        [$motifEfface, $alerte] = $this->ligne($page, 30);
        self::assertStringContainsString('Gymnase fermé', $motifEfface, 'motif effacé au changement de saison (FGY-17)');
        self::assertStringNotContainsString('Motif', $motifEfface);
        self::assertSame('0', $alerte);

        foreach ([2 => '1 terrain libre sur 2', 37 => '2 terrains libres sur 2'] as $jour => $attendu) {
            [$ouverte] = $this->ligne($page, $jour);
            self::assertStringContainsString($attendu, $ouverte, 'jours voisins des fermetures, ou fermeture d\'un autre gymnase');
            self::assertStringNotContainsString('fermé', $ouverte);
        }
    }
}
