<?php

namespace App\Reglement;

use App\Entity\Reglement;
use App\Entity\ReglementVersion;
use App\Entity\Saison;
use App\Entity\User;
use App\Repository\ReglementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Enregistrement du règlement d'une saison (docs/wizard-saison/decisions.md, D55, D56) : le HTML de l'éditeur est
 * nettoyé (sanitizer `app.reglement`), l'enregistrement publie aussitôt et garde le contenu précédent comme version,
 * restaurable. Un enregistrement sans changement ne crée pas de version.
 */
final class EditionReglement
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReglementRepository $reglementRepository,
        #[Target('app.reglement')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /** Règlement de la saison, créé vide (non enregistré) s'il n'existe pas encore. */
    public function reglementDe(Saison $saison): Reglement
    {
        return $this->reglementRepository->findDeSaison($saison) ?? (new Reglement())->setSaison($saison);
    }

    /**
     * Jeton de la version publiée, pour repérer qu'un autre admin a enregistré entre l'ouverture de la page et
     * l'enregistrement (D59) ; vide tant que le règlement n'existe pas
     */
    public function jeton(Reglement $reglement): string
    {
        return $reglement->getId() === null ? '' : sprintf('%s-%d', $reglement->getModifieLe()?->format('U') ?? '0', $reglement->getVersions()->count());
    }

    public function nettoyer(string $html): string
    {
        // Le sanitizer encode « = » dans le texte : rétabli pour garder les codes courts lisibles (« [bareme phase=P2] ») ;
        // sans risque hors d'une balise, et les valeurs d'attributs restent entre guillemets
        // Les caractères privés U+E000 et U+E001 servent de marqueurs au rendu (RenduReglement) : retirés du texte
        return trim(str_replace(['&#61;', "\u{E000}", "\u{E001}"], ['=', '', ''], $this->sanitizer->sanitize($html)));
    }

    /** Enregistre le contenu (nettoyé) ; rend false si rien n'a changé. */
    public function enregistrer(Saison $saison, string $html, ?User $auteur): bool
    {
        $reglement = $this->reglementDe($saison);
        $contenu = $this->nettoyer($html);
        if ($reglement->getId() !== null && $contenu === $reglement->getContenu()) {
            return false;
        }
        if ($reglement->getId() !== null && $reglement->getModifieLe() !== null) {
            $reglement->addVersion((new ReglementVersion())
                ->setContenu($reglement->getContenu())
                ->setEnregistreLe($reglement->getModifieLe())
                ->setAuteur($reglement->getModifiePar()));
        }
        $reglement->setContenu($contenu)->setModifieLe(new \DateTimeImmutable())->setModifiePar($auteur);
        $this->em->persist($reglement);
        $this->em->flush();

        return true;
    }

    /** Remet en place une version précédente, comme un nouvel enregistrement (la version courante est gardée). */
    public function restaurer(ReglementVersion $version, ?User $auteur): bool
    {
        return $this->enregistrer($version->getReglement()->getSaison(), $version->getContenu(), $auteur);
    }

    /** Copie du texte vers une nouvelle saison (CopieSaison, D56), sans l'historique ; à enregistrer par l'appelant. */
    public function copier(Saison $source, Saison $cible): ?Reglement
    {
        $reglement = $this->reglementRepository->findDeSaison($source);
        if ($reglement === null || $reglement->getContenu() === '') {
            return null;
        }

        // Daté de la copie : la page affiche « Mis à jour le … », et la première modification garde ce texte comme version
        return (new Reglement())->setSaison($cible)->setContenu($reglement->getContenu())->setModifieLe(new \DateTimeImmutable());
    }
}
