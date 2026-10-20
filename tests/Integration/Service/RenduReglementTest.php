<?php

namespace App\Tests\Integration\Service;

use App\Competition\Modele\ModeleBlvbAvecBarrages;
use App\Entity\Journee;
use App\Entity\Phase;
use App\Entity\Saison;
use App\Factory\EquipeFactory;
use App\Factory\IndisponibiliteFactory;
use App\Factory\LieuFactory;
use App\Factory\SaisonFactory;
use App\Reglement\RenduReglement;
use App\Service\CompetitionCreator;
use App\Tests\Support\TestIntegration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * RGL-04 à RGL-09 : affichage du règlement d'une saison (App\Reglement\RenduReglement, D55 à D58) : ancres, codes
 * courts, codes inapplicables, et les quatre éléments calculés sur le modèle « BLVB avec barrages » (phase 1 de quatre
 * poules de 8, barrages, phase 2, phase finale, barrages de fin de saison).
 */
final class RenduReglementTest extends TestIntegration
{
    private int $saisonId;

    protected function setUp(): void
    {
        parent::setUp();
        $saison = SaisonFactory::createOne(['nom' => 'Saison règlement', 'date_debut' => new \DateTimeImmutable('2026-09-01'),
            'date_fin' => new \DateTimeImmutable('2027-07-31')]);
        $this->saisonId = $saison->getId();
        $em = $this->em();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($em->find(Saison::class, $this->saisonId), false, new ModeleBlvbAvecBarrages());
        $em->clear();
        foreach ($this->phase('P1')->getPoules() as $poule) {
            foreach (range(1, 8) as $n) {
                $poule->addEquipe(EquipeFactory::createOne(['nom' => $poule->getCode().$n, 'lieu' => null]));
            }
        }
        // Phase 2 : seule la poule A a un effectif prévu (tableau A de la phase finale calculable)
        foreach ($this->phase('P2')->getPoules() as $poule) {
            if ($poule->getCode() === 'A') {
                $poule->setNbEquipesPrevu(8);
            }
        }
        $em->flush();
        $em->clear();
    }

    private function em(): EntityManagerInterface
    {
        return self::service('doctrine')->getManager();
    }

    private function phase(string $code): Phase
    {
        return $this->em()->getRepository(Phase::class)->findOneBy(['saison' => $this->saisonId, 'code' => $code]);
    }

    /** @return array{html: string, inapplicables: list<array{code: string, raison: string}>} */
    private function rendre(string $contenu, bool $admin = false): array
    {
        return self::service(RenduReglement::class)->rendre($this->em()->find(Saison::class, $this->saisonId), $contenu, $admin);
    }

    /** Texte affiché, espaces regroupés */
    private static function texte(string $html): string
    {
        $html = preg_replace('#<(?!/?(a|strong|em|code)\b)#', ' <', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    #[TestDox('RGL-04 Ancres sur les titres : code de la règle (#regle-1r, lien de la fenêtre de score), sinon titre sans accents ; doublons numérotés')]
    public function test_ancres(): void
    {
        $html = $this->rendre('<h2>RÈGLE 1R : Partie interrompue</h2><h3>Décompte des <strong>points</strong></h3><h3>Décompte des points</h3>')['html'];

        self::assertStringContainsString('<h2 id="regle-1r" class="scroll-mt-24">RÈGLE 1R : Partie interrompue</h2>', $html);
        self::assertStringContainsString('<h3 id="decompte-des-points" class="scroll-mt-24">', $html);
        self::assertStringContainsString('<h3 id="decompte-des-points-2" class="scroll-mt-24">', $html);
    }

    #[TestDox('RGL-05 Code court seul dans son paragraphe : l\'élément remplace le paragraphe ; au fil du texte : remplacé sur place ; dans <code> : laissé tel quel')]
    public function test_codes_courts(): void
    {
        $html = $this->rendre('<p>[departage]</p><p>Voir <code>[bareme]</code> et [departage] ici.</p>')['html'];

        self::assertStringStartsWith('<section class="reglement-bloc" data-bloc="departage"', $html);
        self::assertSame(2, substr_count($html, 'data-bloc="departage"'));
        self::assertStringContainsString('<code>[bareme]</code>', $html);
        self::assertStringNotContainsString('data-bloc="bareme"', $html);
    }

    #[TestDox('RGL-05 Un code court dans un attribut (title, alt) n\'est pas remplacé ; les marqueurs internes saisis dans le texte sont ignorés')]
    public function test_code_court_dans_un_attribut(): void
    {
        $html = $this->rendre('<p><a href="https://ex.org/" title="[departage]">lien</a> <img src="/a.png" alt="[bareme]" /> '."\u{E000}0\u{E001}".'</p>', true)['html'];

        self::assertStringContainsString('<a href="https://ex.org/" title="[departage]">lien</a>', $html);
        self::assertStringContainsString('alt="[bareme]"', $html);
        self::assertStringNotContainsString('data-bloc', $html);
        self::assertStringNotContainsString("\u{E000}", $html);
    }

    #[TestDox('RGL-06 Code inapplicable : rien pour le public ; pour un admin, un encart qui en donne la raison ; liste des codes inapplicables')]
    public function test_inapplicables(): void
    {
        $contenu = '<p>[inconnu]</p><p>[bareme saison=1]</p><p>[calendrier phase=FIN]</p><p>[departage phase=P1]</p>';

        $public = $this->rendre($contenu);
        self::assertSame('', self::texte($public['html']));
        self::assertSame(['[inconnu]', '[bareme saison=1]', '[calendrier phase=FIN]', '[departage phase=P1]'], array_column($public['inapplicables'], 'code'));
        [$inconnu, $parametre, $valeur, $sansParametre] = array_column($public['inapplicables'], 'raison');
        self::assertStringStartsWith('aucun élément calculé ne s\'appelle « inconnu ». Codes possibles : schema_phases, bareme, departage, calendrier.', $inconnu);
        self::assertSame('paramètre « saison » inconnu. Paramètres possibles : phase.', $parametre);
        self::assertStringStartsWith('« FIN » n\'est pas une valeur possible pour « phase » dans cette saison. Valeurs possibles : P1 (Phase 1), BAR (Barrages), P2 (Phase 2), F (Phase finale)', $valeur);
        self::assertSame('« departage » n\'accepte aucun paramètre.', $sansParametre);

        $admin = self::texte($this->rendre($contenu, true)['html']);
        self::assertSame(4, substr_count($admin, 'Élément calculé non affiché au public'));
        self::assertStringContainsString('[calendrier phase=FIN] : « FIN » n\'est pas une valeur possible', $admin);
        self::assertStringContainsString('Cet encart n\'est visible que des administrateurs.', $admin);
    }

    #[TestDox('RGL-07 Schéma des phases : sorties de chaque poule en mots publics (rangs voisins fusionnés), barrages par leur composition, tableau final calculé, phase finale terminale')]
    public function test_schema_phases(): void
    {
        $p1 = self::texte($this->rendre('<p>[schema_phases phase=P1]</p>')['html']);
        self::assertStringContainsString('Phase 1 Championnat aller simple · du 01/09/2026 au 01/02/2027 · 3 sets gagnants', $p1);
        self::assertStringContainsString('Poule A · 8 équipes Barrages 7e : joue le barrage A / B Phase 2 Du 1er au 6e : restent en poule A 8e : descend en poule B', $p1);
        self::assertStringContainsString('Poule B · 8 équipes Barrages 2e : joue le barrage A / B 7e : joue le barrage B / C Phase 2 1er : monte en poule A Du 3e au 6e : restent en poule B 8e : descend en poule C', $p1);
        self::assertStringNotContainsString('antépénultième', $p1);
        self::assertStringNotContainsString('P1.A', $p1);

        $barrages = self::texte($this->rendre('<p>[schema_phases phase=BAR]</p>')['html']);
        self::assertStringContainsString('L\'équipe de la poule la plus haute reçoit.', $barrages);
        self::assertStringContainsString('Barrage A / B 7e de la poule A (Phase 1) contre 2e de la poule B (Phase 1) Vainqueur : va en poule A (Phase 2) Perdant : va en poule B (Phase 2)', $barrages);

        $finale = self::texte($this->rendre('<p>[schema_phases phase=F]</p>')['html']);
        self::assertStringContainsString('La phase finale ne compte pas pour les montées et descentes.', $finale);
        self::assertStringContainsString('Poule A 8 équipes de la poule A (Phase 2), placées selon leur classement.', $finale);
        self::assertStringContainsString('Quarts de finale Quart de finale 1 : 1er contre 8e Quart de finale 2 : 4e contre 5e', $finale);
        self::assertStringContainsString('Le tableau sera établi à la fin de la phase précédente.', $finale);

        $fin = self::texte($this->rendre('<p>[schema_phases phase=BAR2]</p>')['html']);
        self::assertStringContainsString('7e de la poule A (Phase 2) contre 2e de la poule B (Phase 2)', $fin);
    }

    #[TestDox('RGL-07 Schéma d\'une saison sans équipes : rangs voisins fusionnés sans effectif (« tous sauf les 2 derniers »), tableau établi plus tard')]
    public function test_schema_sans_equipes(): void
    {
        $saison = SaisonFactory::createOne(['nom' => 'Brouillon', 'date_debut' => new \DateTimeImmutable('2027-09-01'), 'date_fin' => new \DateTimeImmutable('2028-07-31')]);
        $em = $this->em();
        self::service(CompetitionCreator::class)->creerPhasesEtPoules($em->find(Saison::class, $saison->getId()), false, new ModeleBlvbAvecBarrages());
        $em->clear();

        $texte = self::texte(self::service(RenduReglement::class)->rendre($em->find(Saison::class, $saison->getId()), '<p>[schema_phases]</p>')['html']);
        self::assertStringContainsString('Poule A Barrages Avant-dernier : joue le barrage A / B Phase 2 Tous sauf les 2 derniers : restent en poule A Dernier : descend en poule B', $texte);
        self::assertStringContainsString('Poule D Barrages 2e : joue le barrage C / D Phase 2 1er : monte en poule C Du 3e au dernier : restent en poule D', $texte);
        self::assertStringContainsString('Le tableau sera établi à la fin de la phase précédente.', $texte);
    }

    #[TestDox('RGL-08 Barème : un tableau par format de match, victoire par forfait et forfait, match interrompu (règle 1R) ; phases sans classement en une phrase')]
    public function test_bareme(): void
    {
        $this->phase('P2')->setSetsGagnants(Phase::SETS_GAGNANTS_COURT);
        $this->em()->flush();
        $this->em()->clear();

        $html = $this->rendre('<p>[bareme]</p>')['html'];
        $texte = self::texte($html);
        self::assertStringContainsString('Phase 1 · 3 sets gagnants Résultat Points Victoire 3-0 ou 3-1 3 Victoire 3-2 2 Défaite 2-3 1 Défaite 1-3 ou 0-3 0 Victoire par forfait 3 Forfait (équipe absente) -1', $texte);
        self::assertStringContainsString('Forfait (équipe absente) -1 Match interrompu à 2-1 (règle 1R) 2 Match interrompu à 1-2 ou 2-2 (règle 1R) 1 Match interrompu à 1-1, 1-0 ou 0-1 (règle 1R) 0', $texte);
        self::assertStringContainsString('Phase 2 · 2 sets gagnants Résultat Points Victoire 2-0 3 Victoire 2-1 2 Défaite 1-2 1 Défaite 0-2 0', $texte);
        self::assertStringContainsString('Barrages, Phase finale et Barrages de fin de saison : pas de points au classement, seul compte le vainqueur du match (3 sets gagnants).', $texte);
        self::assertStringContainsString('href="#regle-1r"', $html);
    }

    #[TestDox('RGL-09 Départage dans l\'ordre de la saison ; calendrier : journées par semaine, semaines sans match, journée des finales avec son gymnase, « Cette semaine »')]
    public function test_departage_et_calendrier(): void
    {
        $departage = self::texte($this->rendre('<p>[departage]</p>')['html']);
        self::assertMatchesRegularExpression('/Points Les points du barème.*Différence de sets.*Confrontation directe.*Ordre alphabétique/u', $departage);

        $em = $this->em();
        foreach ($this->phase('P1')->getPoules() as $poule) {
            $mixte = \in_array($poule->getCode(), ['C', 'D'], true);
            foreach ([['2026-09-14', 1], ['2026-09-21', $mixte ? 3 : 2]] as [$lundi, $numero]) {
                $em->persist((new Journee())->setPoule($poule)->setNumero($numero)
                    ->setDateDebut(new \DateTimeImmutable($lundi))->setDateFin((new \DateTimeImmutable($lundi))->modify('+6 days')));
            }
        }
        $lieu = LieuFactory::createOne(['nom' => 'Gymnase des finales']);
        $this->phase('F')->setOptions(['journee_finales' => ['tours' => Phase::TOURS_FINALES, 'lieu' => $lieu->getId(), 'date' => '2027-06-25', 'heure' => '19:00', 'terrains' => 2]]);
        IndisponibiliteFactory::createOne(['saison' => $em->find(Saison::class, $this->saisonId), 'nom' => 'Vacances de la Toussaint',
            'dateDebut' => new \DateTimeImmutable('2026-10-17'), 'dateFin' => new \DateTimeImmutable('2026-11-02')]);
        // Journée de la semaine en cours dans la phase 2
        $lundi = new \DateTimeImmutable('monday this week');
        foreach ($this->phase('P2')->getPoules() as $poule) {
            $em->persist((new Journee())->setPoule($poule)->setNumero(1)->setDateDebut($lundi)->setDateFin($lundi->modify('+6 days')));
        }
        $em->flush();
        $em->clear();

        $p1 = self::texte($this->rendre('<p>[calendrier phase=P1]</p>')['html']);
        self::assertStringContainsString('Semaine du Programme Phase 1 · du 01/09/2026 au 01/02/2027 14/09/2026 Journée 1, toutes les poules 21/09/2026 Poules A et B : journée 2 · poules C et D : journée 3', $p1);
        // « Cette semaine » si la suite tourne pendant ces vacances
        self::assertMatchesRegularExpression('#17/10/2026 (Cette semaine )?Pas de match : Vacances de la Toussaint du 17/10/2026 au 02/11/2026#u', $p1);

        $f = self::texte($this->rendre('<p>[calendrier phase=F]</p>')['html']);
        self::assertStringContainsString('25/06/2027 Journée des finales vendredi 25 juin 2027, 19 h, Gymnase des finales', $f);

        $p2 = self::texte($this->rendre('<p>[calendrier phase=P2]</p>')['html']);
        self::assertStringContainsString('Cette semaine Journée 1, toutes les poules', $p2);
    }
}
