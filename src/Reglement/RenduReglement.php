<?php

namespace App\Reglement;

use App\Entity\Saison;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

/**
 * Affichage du règlement d'une saison (docs/wizard-saison/decisions.md, D55 à D58) : le texte, déjà nettoyé à
 * l'enregistrement, reçoit une ancre sur chaque titre (« RÈGLE 1R » → #regle-1r), puis chaque code court est remplacé
 * par l'élément calculé. Un code inapplicable ne montre rien au public ; un admin voit à sa place un encart qui en
 * donne la raison. Un code court mis en forme « code » (<code>[bareme]</code>) est laissé tel quel.
 */
final class RenduReglement
{
    /** « [bareme] », « [bareme phase=P2] » ; nom en minuscules sans accent, paramètres « nom=valeur » */
    private const MOTIF = '\[([a-z_]+)((?:[ \x{00A0}]+[a-z_]+=[^\s\]<]+)*)[ \x{00A0}]*\]';

    public function __construct(
        private BlocsReglement $blocs,
        private Environment $twig,
    ) {
    }

    /**
     * @return array{html: string, inapplicables: list<array{code: string, raison: string}>}
     */
    public function rendre(Saison $saison, string $contenu, bool $admin = false): array
    {
        $inapplicables = [];
        // Marqueurs internes de mise de côté : jamais repris du texte saisi
        $html = $this->ancrer(str_replace(["\u{E000}", "\u{E001}"], '', $contenu));

        // Les codes cités dans <code> et les éléments déjà rendus sont mis de côté jusqu'à la fin : jamais relus
        $deCote = [];
        $mettreDeCote = function (string $morceau) use (&$deCote): string {
            $deCote[] = $morceau;

            return "\u{E000}".(\count($deCote) - 1)."\u{E001}";
        };
        $html = preg_replace_callback('#<code>.*?</code>#s', fn (array $m) => $mettreDeCote($m[0]), $html);

        $remplacer = function (string $codeCourt) use ($saison, $admin, &$inapplicables, $mettreDeCote): string {
            preg_match('/'.self::MOTIF.'/u', $codeCourt, $m);
            try {
                return $mettreDeCote($this->rendreCode($saison, $m[1], self::lireParametres($m[2] ?? '')));
            } catch (BlocInapplicable $e) {
                $code = trim(preg_replace('/[ \x{00A0}]+/u', ' ', $codeCourt));
                $inapplicables[] = ['code' => $code, 'raison' => $e->getMessage()];

                return $admin ? $mettreDeCote($this->twig->render('reglement/_inapplicable.html.twig', [
                    'code' => $code, 'raison' => $e->getMessage(), 'saison' => $saison,
                ])) : '';
            }
        };

        // Code seul dans son paragraphe : l'élément remplace le paragraphe (un bloc ne va pas dans un <p>)
        $html = preg_replace_callback('#<p>(?:\s|&nbsp;|<br />)*('.self::MOTIF.')(?:\s|&nbsp;|<br />)*</p>#u',
            fn (array $m) => $remplacer($m[1]), $html);
        // Au fil du texte seulement : les balises (et leurs attributs, ex. title="[bareme]") sont mises de côté
        $html = preg_replace_callback('#<[^>]+>#', fn (array $m) => $mettreDeCote($m[0]), $html);
        $html = preg_replace_callback('/'.self::MOTIF.'/u', fn (array $m) => $remplacer($m[0]), $html);

        // Restitution (les morceaux mis de côté peuvent en contenir d'autres)
        do {
            $html = preg_replace_callback("/\u{E000}(\\d+)\u{E001}/u", fn (array $m) => $deCote[(int) $m[1]] ?? '', $html, -1, $restitues);
        } while ($restitues > 0);

        return ['html' => $html, 'inapplicables' => $inapplicables];
    }

    /**
     * @param array<string, string> $parametres
     * @throws BlocInapplicable
     */
    private function rendreCode(Saison $saison, string $code, array $parametres): string
    {
        $bloc = $this->blocs->bloc($code);
        if ($bloc === null) {
            throw new BlocInapplicable(sprintf('aucun élément calculé ne s\'appelle « %s ». Codes possibles : %s.', $code,
                implode(', ', array_map(fn (BlocReglement $b) => $b->code(), $this->blocs->tous()))));
        }
        $declares = [];
        foreach ($bloc->parametres($saison) as $parametre) {
            $declares[$parametre->nom] = $parametre;
        }
        foreach ($parametres as $nom => $valeur) {
            if (!isset($declares[$nom])) {
                throw new BlocInapplicable($declares === []
                    ? sprintf('« %s » n\'accepte aucun paramètre.', $code)
                    : sprintf('paramètre « %s » inconnu. Paramètres possibles : %s.', $nom, implode(', ', array_keys($declares))));
            }
            if (!\array_key_exists($valeur, $declares[$nom]->valeurs)) {
                throw new BlocInapplicable($declares[$nom]->valeurs === []
                    ? sprintf('aucune valeur possible pour « %s » dans cette saison.', $nom)
                    : sprintf('« %s » n\'est pas une valeur possible pour « %s » dans cette saison. Valeurs possibles : %s.', $valeur, $nom, $declares[$nom]->valeursEnClair()));
            }
        }

        return $bloc->rendre($saison, $parametres);
    }

    /** @return array<string, string> « phase=P2 » → ['phase' => 'P2'] */
    private static function lireParametres(string $texte): array
    {
        $parametres = [];
        foreach (preg_split('/[ \x{00A0}]+/u', trim($texte, " \u{00A0}"), -1, PREG_SPLIT_NO_EMPTY) as $morceau) {
            [$nom, $valeur] = explode('=', $morceau, 2);
            $parametres[$nom] = $valeur;
        }

        return $parametres;
    }

    /**
     * Ancre sur chaque titre du texte : le code de la règle s'il commence par « RÈGLE xx » (#regle-1r, lien de la
     * fenêtre de saisie du score), sinon le titre en minuscules sans accents ; doublons numérotés.
     */
    private function ancrer(string $html): string
    {
        $slugger = new AsciiSlugger('fr');
        $pris = [];

        return preg_replace_callback('#<h([234])>(.*?)</h\1>#s', function (array $m) use ($slugger, &$pris) {
            $texte = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $ancre = preg_match('/^r[èeÈE]gle\s+([0-9a-z]+)\b/iu', $texte, $regle)
                ? 'regle-'.mb_strtolower($regle[1])
                : $slugger->slug($texte)->lower()->toString();
            $ancre = $ancre === '' ? 'section' : $ancre;
            $unique = $ancre;
            for ($i = 2; isset($pris[$unique]); ++$i) {
                $unique = $ancre.'-'.$i;
            }
            $pris[$unique] = true;

            return sprintf('<h%1$s id="%2$s" class="scroll-mt-24">%3$s</h%1$s>', $m[1], $unique, $m[2]);
        }, $html);
    }
}
