<?php

namespace App\Tests\Integration\Service;

use App\Entity\Saison;
use App\Entity\User;
use App\Factory\SaisonFactory;
use App\Factory\UserFactory;
use App\Reglement\EditionReglement;
use App\Repository\ReglementRepository;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/** RGL-01 à RGL-03 : enregistrement du règlement d'une saison, nettoyage et versions (App\Reglement\EditionReglement, D55, D56). */
final class EditionReglementTest extends TestIntegration
{
    private Saison $saison;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $em = self::service('doctrine')->getManager();
        $this->saison = $em->find(Saison::class, SaisonFactory::createOne()->getId());
        $this->admin = $em->find(User::class, UserFactory::createOne(['roles' => ['ROLE_ADMIN']])->getId());
    }

    private function edition(): EditionReglement
    {
        return self::service(EditionReglement::class);
    }

    #[TestDox('RGL-01 Nettoyage : scripts, attributs d\'événement, styles, cadres, images externes et liens javascript retirés ; mise en forme, images du site (cliquables ou non) et codes courts conservés ; description d\'image encodée')]
    public function test_nettoyage(): void
    {
        $propre = $this->edition()->nettoyer(
            '<h2 id="x" style="color:red">RÈGLE 1R : Partie interrompue</h2>'
            .'<p onclick="alert(1)">Texte <strong>gras</strong> [bareme phase=P2]</p>'
            .'<script>alert(1)</script><iframe src="https://exemple.org"></iframe>'
            .'<img src="/uploads/reglement/a.webp" alt="Filet"><img src="https://exemple.org/b.png" alt="externe">'
            .'<a href="javascript:alert(1)">piège</a><a href="https://www.ffvb.org">FFVB</a>'
            // Image cliquable (lien posé sur l'image dans l'éditeur) ; description qui tente de fermer l'attribut
            .'<a href="https://www.ffvb.org"><img src="/uploads/reglement/c.png" alt="Logo"></a>'
            .'<a href="javascript:alert(1)"><img src="/uploads/reglement/d.png" alt=""></a>'
            .'<img src="/uploads/reglement/e.png" alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">'
            // Centrage de l'éditeur gardé ; toute autre classe retirée (styles du site)
            .'<h3 class="aligne-centre">Centré</h3><p class="aligne-centre btn btn-error">Texte</p><p class="hidden">Caché</p>'
            .'<img class="aligne-centre" src="/uploads/reglement/f.png" alt="Centrée">'
            // Autres options de la barre d'outils : alignements, souligné, surligné, exposant, indice, citation, séparation,
            // tableau (largeurs en style et colgroup écrites par l'éditeur : retirées)
            .'<p class="aligne-droite">D</p><p class="aligne-justifie">J</p><img class="aligne-droite" src="/uploads/reglement/g.png" alt="D">'
            .'<p><u>s</u> <mark>m</mark> 1<sup>er</sup> H<sub>2</sub>O</p><blockquote><p>c</p></blockquote><hr>'
            .'<table style="min-width: 50px"><colgroup><col style="min-width: 25px"></colgroup><tbody><tr><th colspan="1" rowspan="1"><p>T</p></th></tr><tr><td colspan="1" rowspan="1"><p>V</p></td></tr></tbody></table>'
        );

        self::assertSame(
            '<h2>RÈGLE 1R : Partie interrompue</h2>'
            .'<p>Texte <strong>gras</strong> [bareme phase=P2]</p>'
            .'<img src="/uploads/reglement/a.webp" alt="Filet" /><img alt="externe" />'
            .'<a>piège</a><a href="https://www.ffvb.org">FFVB</a>'
            .'<a href="https://www.ffvb.org"><img src="/uploads/reglement/c.png" alt="Logo" /></a>'
            .'<a><img src="/uploads/reglement/d.png" alt /></a>'
            .'<img src="/uploads/reglement/e.png" alt="&#34;&gt;&lt;script&gt;alert(1)&lt;/script&gt;" />'
            .'<h3 class="aligne-centre">Centré</h3><p class="aligne-centre">Texte</p><p>Caché</p>'
            .'<img class="aligne-centre" src="/uploads/reglement/f.png" alt="Centrée" />'
            .'<p class="aligne-droite">D</p><p class="aligne-justifie">J</p><img class="aligne-droite" src="/uploads/reglement/g.png" alt="D" />'
            .'<p><u>s</u> <mark>m</mark> 1<sup>er</sup> H<sub>2</sub>O</p><blockquote><p>c</p></blockquote><hr />'
            .'<table><tbody><tr><th colspan="1" rowspan="1"><p>T</p></th></tr><tr><td colspan="1" rowspan="1"><p>V</p></td></tr></tbody></table>',
            $propre
        );
    }

    #[TestDox('RGL-02 Enregistrer publie le contenu (date, auteur) et garde le précédent comme version ; un enregistrement identique ne crée pas de version')]
    public function test_enregistrer_et_versions(): void
    {
        self::assertTrue($this->edition()->enregistrer($this->saison, '<p>V1</p>', $this->admin));
        self::assertTrue($this->edition()->enregistrer($this->saison, '<p>V2</p>', null));
        self::assertFalse($this->edition()->enregistrer($this->saison, '<p>V2</p>', $this->admin));

        self::service('doctrine')->getManager()->clear();
        $reglement = self::service(ReglementRepository::class)->findDeSaison($this->saison);
        self::assertSame('<p>V2</p>', $reglement->getContenu());
        self::assertNotNull($reglement->getModifieLe());
        self::assertNull($reglement->getModifiePar());
        self::assertCount(1, $reglement->getVersions());
        $version = $reglement->getVersions()->first();
        self::assertSame('<p>V1</p>', $version->getContenu());
        self::assertSame($this->admin->getId(), $version->getAuteur()?->getId());
    }

    #[TestDox('RGL-03 Restaurer une version la republie et garde le contenu remplacé comme version')]
    public function test_restaurer(): void
    {
        $this->edition()->enregistrer($this->saison, '<p>V1</p>', $this->admin);
        $this->edition()->enregistrer($this->saison, '<p>V2</p>', $this->admin);
        $v1 = self::service(ReglementRepository::class)->findDeSaison($this->saison)->getVersions()->first();

        self::assertTrue($this->edition()->restaurer($v1, $this->admin));

        self::service('doctrine')->getManager()->clear();
        $reglement = self::service(ReglementRepository::class)->findDeSaison($this->saison);
        self::assertSame('<p>V1</p>', $reglement->getContenu());
        self::assertSame(['<p>V2</p>', '<p>V1</p>'], $reglement->getVersions()->map(fn ($v) => $v->getContenu())->toArray());
    }
}
