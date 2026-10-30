<?php

namespace App\Controller\Admin;

use App\Entity\ReglementVersion;
use App\Entity\Saison;
use App\Entity\User;
use App\Reglement\BlocInapplicable;
use App\Reglement\BlocsReglement;
use App\Reglement\EditionReglement;
use App\Reglement\ImagesReglement;
use App\Reglement\RenduReglement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page d'édition du règlement d'une saison (docs/wizard-saison/decisions.md, D55 à D59 ; maquettes
 * archive/reglement-maquettes-r4.md) : éditeur visuel (assets/controllers/reglement_editeur_controller.js), aperçu du texte non
 * enregistré, éléments calculés, images, historique et restauration. Admins seulement.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison/{id}/reglement', name: 'admin_reglement_', requirements: ['id' => '\d+'])]
final class ReglementController extends AbstractController
{
    public function __construct(
        private readonly EditionReglement $edition,
        private readonly RenduReglement $rendu,
        private readonly BlocsReglement $blocs,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET'])]
    public function edit(Saison $saison): Response
    {
        $reglement = $this->edition->reglementDe($saison);

        return $this->render('admin/reglement/edit.html.twig', [
            'saison' => $saison,
            'reglement' => $reglement,
            // Nettoyé de nouveau comme à l'affichage public : un contenu repris ou importé ne sort jamais du <template>
            'contenu' => $this->edition->nettoyer($reglement->getContenu()),
            'jeton' => $this->edition->jeton($reglement),
            'inapplicables' => $this->rendu->rendre($saison, $reglement->getContenu(), true)['inapplicables'],
            'blocs' => array_map(fn ($bloc) => ['bloc' => $bloc, 'parametres' => $bloc->parametres($saison)], $this->blocs->tous()),
        ]);
    }

    /**
     * Enregistre (et publie, saison visible) le texte de l'éditeur. Si une autre version a été publiée depuis l'ouverture
     * de la page, rien n'est enregistré : 409 avec l'auteur et l'heure, sauf « forcer » (D59).
     */
    #[Route('/enregistrer', name: 'enregistrer', methods: ['POST'])]
    public function enregistrer(Saison $saison, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('reglement'.$saison->getId(), $request->getPayload()->getString('_token'))) {
            return new JsonResponse(['message' => 'La page a expiré : rechargez-la avant d\'enregistrer (vos modifications seront perdues, copiez-les d\'abord).'], 403);
        }
        if ($trop = $this->tropLong($request->getPayload()->getString('contenu'))) {
            return $trop;
        }
        $reglement = $this->edition->reglementDe($saison);
        if ($this->edition->jeton($reglement) !== $request->getPayload()->getString('jeton') && !$request->getPayload()->getBoolean('forcer')) {
            return new JsonResponse([
                'conflit' => true,
                'message' => sprintf('%s a %s une autre version à %s.', $this->auteur($reglement->getModifiePar()),
                    $saison->isBrouillon() ? 'enregistré' : 'publié', $reglement->getModifieLe()?->format('G \h i') ?? '?'),
            ], 409);
        }
        $user = $this->getUser();
        $this->edition->enregistrer($saison, $request->getPayload()->getString('contenu'), $user instanceof User ? $user : null);
        $reglement = $this->edition->reglementDe($saison);
        $inapplicables = $this->rendu->rendre($saison, $reglement->getContenu(), true)['inapplicables'];

        return new JsonResponse([
            'jeton' => $this->edition->jeton($reglement),
            'heure' => $reglement->getModifieLe()?->format('G \h i'),
            'inapplicables' => $this->inapplicables($inapplicables),
            // Codes et raisons, pour les signaler dans l'éditeur (style « à vérifier »)
            'codes' => $inapplicables,
        ]);
    }

    /** Aperçu du texte NON enregistré, tel que le verra le public (avec les encarts des codes inapplicables) */
    #[Route('/apercu', name: 'apercu', methods: ['POST'])]
    public function apercu(Saison $saison, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('reglement'.$saison->getId(), $request->getPayload()->getString('_token'))) {
            return new JsonResponse(['message' => 'La page a expiré : rechargez-la.'], 403);
        }
        if ($trop = $this->tropLong($request->getPayload()->getString('contenu'))) {
            return $trop;
        }
        $rendu = $this->rendu->rendre($saison, $this->edition->nettoyer($request->getPayload()->getString('contenu')), true);

        return new JsonResponse(['html' => $rendu['html'], 'inapplicables' => $this->inapplicables($rendu['inapplicables']), 'codes' => $rendu['inapplicables']]);
    }

    /** Résultat d'un élément calculé pour la saison (« Voir le résultat » du panneau) */
    #[Route('/element/{code}', name: 'element', requirements: ['code' => '[a-z_]+'], methods: ['GET'])]
    public function element(Saison $saison, string $code, Request $request): Response
    {
        $bloc = $this->blocs->bloc($code) ?? throw $this->createNotFoundException();
        $phase = $request->query->getString('phase');
        try {
            if ($phase !== '') {
                $valeurs = [];
                foreach ($bloc->parametres($saison) as $parametre) {
                    $valeurs += $parametre->nom === 'phase' ? $parametre->valeurs : [];
                }
                if (!isset($valeurs[$phase])) {
                    throw new BlocInapplicable(sprintf('aucune phase « %s » dans cette saison.', $phase));
                }
            }
            $html = $bloc->rendre($saison, $phase !== '' ? ['phase' => $phase] : []);
        } catch (BlocInapplicable $e) {
            $html = $this->renderView('reglement/_inapplicable.html.twig', ['code' => '['.$code.']', 'raison' => $e->getMessage(), 'saison' => $saison]);
        }

        return new Response($html);
    }

    /** Image envoyée depuis l'éditeur : adresse à insérer dans le texte */
    #[Route('/image', name: 'image', methods: ['POST'])]
    public function image(Saison $saison, Request $request, ImagesReglement $images, Packages $packages): JsonResponse
    {
        if (!$this->isCsrfTokenValid('reglement'.$saison->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['message' => 'La page a expiré : rechargez-la.'], 403);
        }
        $fichier = $request->files->get('image');
        if (!$fichier instanceof UploadedFile) {
            return new JsonResponse(['message' => 'L\'envoi a échoué, réessayez.'], 400);
        }
        try {
            $nom = $images->enregistrer($fichier);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(['url' => $packages->getUrl('uploads/reglement/'.$nom)]);
    }

    #[Route('/historique', name: 'historique', methods: ['GET'])]
    public function historique(Saison $saison): Response
    {
        return $this->render('admin/reglement/historique.html.twig', [
            'saison' => $saison,
            'reglement' => $this->edition->reglementDe($saison),
        ]);
    }

    #[Route('/version/{version}', name: 'version', requirements: ['version' => '\d+'], methods: ['GET'])]
    public function version(Saison $saison, ReglementVersion $version): Response
    {
        $this->verifierVersion($saison, $version);

        return $this->render('admin/reglement/version.html.twig', [
            'saison' => $saison,
            'version' => $version,
            'contenu' => $this->rendu->rendre($saison, $this->edition->nettoyer($version->getContenu()), true)['html'],
        ]);
    }

    #[Route('/version/{version}/restaurer', name: 'restaurer', requirements: ['version' => '\d+'], methods: ['POST'])]
    public function restaurer(Saison $saison, ReglementVersion $version, Request $request): Response
    {
        $this->verifierVersion($saison, $version);
        if (!$this->isCsrfTokenValid('restaurer'.$version->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $user = $this->getUser();
        $this->edition->restaurer($version, $user instanceof User ? $user : null);
        $this->addFlash('success', sprintf('Version du %s restaurée%s.', $this->dateLongue($version->getEnregistreLe()), $saison->isBrouillon() ? '' : ' et publiée'));

        return $this->redirectToRoute('admin_reglement_edit', ['id' => $saison->getId()]);
    }

    /** @param list<array{code: string, raison: string}> $inapplicables encart de la page d'édition, déjà rendu */
    private function inapplicables(array $inapplicables): string
    {
        return $this->renderView('admin/reglement/_inapplicables.html.twig', ['inapplicables' => $inapplicables]);
    }

    /** Le sanitizer tronque sans prévenir au-delà de sa longueur maximale : refusé avec un message */
    private function tropLong(string $contenu): ?JsonResponse
    {
        // En octets, comme le sanitizer
        if (\strlen($contenu) <= EditionReglement::TAILLE_MAX) {
            return null;
        }

        return new JsonResponse(['message' => sprintf('Le texte est trop long : %s caractères au plus, mise en forme comprise. Raccourcissez-le, vos modifications sont toujours dans l\'éditeur.',
            number_format(EditionReglement::TAILLE_MAX, 0, ',', "\u{202F}"))], 422);
    }

    /** « 12 octobre 2026 à 9 h 10 », comme l'historique */
    private function dateLongue(?\DateTimeInterface $date): string
    {
        if ($date === null) {
            return '?';
        }

        return (new \IntlDateFormatter('fr', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'd MMMM yyyy'))->format($date)
            .' à '.$date->format('G \h i');
    }

    private function verifierVersion(Saison $saison, ReglementVersion $version): void
    {
        if ($version->getReglement()?->getSaison() !== $saison) {
            throw $this->createNotFoundException();
        }
    }

    /** « A. Moulin », sinon « Un autre administrateur » (compte supprimé) */
    private function auteur(?User $user): string
    {
        if ($user === null) {
            return 'Un autre administrateur';
        }
        $prenom = (string) $user->getPrenom();

        return trim(($prenom !== '' ? mb_substr($prenom, 0, 1).'. ' : '').$user->getNom()) ?: (string) $user->getEmail();
    }
}
