<?php

namespace App\Controller\Admin;

use App\Entity\ParametresSite;
use App\Entity\User;
use App\Form\LogoSiteType;
use App\Form\ParametresContactType;
use App\Repository\ParametresSiteRepository;
use App\Security\LimiteurConnexion;
use App\Security\VerificationConnexion;
use App\Service\LogoSite;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Paramètres du site (D62) : connexion limitée, adresse de contact, logo. Admins seulement. */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/parametres', name: 'admin_parametres_')]
final class ParametresSiteController extends AbstractController
{
    /** Résultat de la dernière vérification, gardé une heure : l'admin compare son IP ou le transmet à l'hébergeur */
    private const SESSION_CONTROLES = 'parametres_site.controles';

    public function __construct(
        private readonly ParametresSiteRepository $depot,
        private readonly EntityManagerInterface $entityManager,
        private readonly VerificationConnexion $verification,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, LogoSite $logoSite): Response
    {
        $parametres = $this->depot->courants();

        $formContact = $this->createForm(ParametresContactType::class, $parametres);
        $formContact->handleRequest($request);
        if ($formContact->isSubmitted() && $formContact->isValid()) {
            $this->enregistrer($parametres);
            $this->addFlash('success', $parametres->getAdresseContact() !== null ? 'L\'adresse de contact est enregistrée.' : 'L\'adresse de contact est retirée.');

            return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
        }

        $formLogo = $this->createForm(LogoSiteType::class);
        $formLogo->handleRequest($request);
        if ($formLogo->isSubmitted() && $formLogo->isValid()) {
            try {
                $ancien = $parametres->getLogo();
                $parametres->setLogo($logoSite->ajouter($formLogo->get('fichier')->getData()));
                $this->enregistrer($parametres);
                $logoSite->supprimer($ancien);
                $this->addFlash('success', 'Le logo est enregistré.');
            } catch (FileException|IOException) {
                $this->addFlash('error', 'Le logo n\'a pas pu être enregistré sur le serveur. Réessayez, ou prévenez l\'hébergeur si cela persiste.');
            }

            return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
        }

        $invalide = ($formContact->isSubmitted() && !$formContact->isValid()) || ($formLogo->isSubmitted() && !$formLogo->isValid());
        return $this->render('admin/parametres/index.html.twig', [
            'parametres' => $parametres,
            'formContact' => $formContact,
            'formLogo' => $formLogo,
            'controles' => $this->derniersControles($request),
            'verificationValide' => $this->verificationValide($parametres),
            'finValidite' => $parametres->getConnexionVerifieeLe()?->modify(sprintf('+%d seconds', VerificationConnexion::VALIDITE)),
            'ipForgee' => VerificationConnexion::IP_FORGEE,
        ], new Response(status: $invalide ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Appelé par verification_connexion_controller.js, avec un en-tête X-Forwarded-For forgé */
    #[Route('/verifier-connexion', name: 'verifier_connexion', methods: ['POST'])]
    public function verifierConnexion(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('verifier-connexion', $request->request->getString('_token'))) {
            return $this->json(['erreur' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $controles = $this->verification->controler($request);
        $reussie = VerificationConnexion::reussie($controles);
        $parametres = $this->depot->courants();
        $parametres->setConnexionVerifieeLe($reussie ? $this->clock->now() : null);
        $this->enregistrer($parametres);
        $request->getSession()->set(self::SESSION_CONTROLES, ['le' => $this->clock->now()->getTimestamp(), 'controles' => $controles]);

        return $this->json(['reussie' => $reussie]);
    }

    #[Route('/connexion-limitee/activer', name: 'activer_connexion', methods: ['POST'])]
    public function activerConnexion(Request $request, LimiteurConnexion $limiteur): Response
    {
        if (!$this->isCsrfTokenValid('activer-connexion', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $parametres = $this->depot->courants();
        if ($parametres->isConnexionLimitee()) {
            // Onglet resté ouvert : ne pas effacer les compteurs en cours
            return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
        }
        if (!$this->verificationValide($parametres) || !$this->verification->serveurConforme($request)) {
            $this->addFlash('error', 'Activation refusée : relancez la vérification, elle doit avoir réussi depuis moins d\'une heure.');
        } elseif (!$request->request->getBoolean('confirmation')) {
            $this->addFlash('error', 'Activation refusée : cochez la case pour confirmer le point que le site ne peut pas contrôler seul.');
        } else {
            $limiteur->toutEffacer();
            $parametres->setConnexionLimitee(true);
            $this->enregistrer($parametres);
            $request->getSession()->remove(self::SESSION_CONTROLES);
            $this->logger->notice('Limitation des essais de connexion activée', ['utilisateur' => $this->idUtilisateur()]);
            $this->addFlash('success', 'La limitation des essais de connexion est activée. Faites maintenant l\'essai décrit plus bas.');
        }

        return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/connexion-limitee/desactiver', name: 'desactiver_connexion', methods: ['POST'])]
    public function desactiverConnexion(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('desactiver-connexion', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $parametres = $this->depot->courants();
        $parametres->setConnexionLimitee(false);
        $this->enregistrer($parametres);
        $this->logger->notice('Limitation des essais de connexion désactivée', ['utilisateur' => $this->idUtilisateur()]);
        $this->addFlash('success', 'La limitation des essais de connexion est désactivée.');

        return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/logo/retirer', name: 'retirer_logo', methods: ['POST'])]
    public function retirerLogo(Request $request, LogoSite $logoSite): Response
    {
        if (!$this->isCsrfTokenValid('retirer-logo', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $parametres = $this->depot->courants();
        $ancien = $parametres->getLogo();
        $parametres->setLogo(null);
        $this->enregistrer($parametres);
        $logoSite->supprimer($ancien);
        $this->addFlash('success', 'Le logo d\'origine est rétabli.');

        return $this->redirectToRoute('admin_parametres_index', status: Response::HTTP_SEE_OTHER);
    }

    /** @return list<array{ok: bool, libelle: string, detail: string}>|null */
    private function derniersControles(Request $request): ?array
    {
        $resultat = $request->getSession()->get(self::SESSION_CONTROLES);
        if ($resultat === null || $this->clock->now()->getTimestamp() - $resultat['le'] > VerificationConnexion::VALIDITE) {
            return null;
        }

        return $resultat['controles'];
    }

    private function idUtilisateur(): ?int
    {
        $utilisateur = $this->getUser();

        return $utilisateur instanceof User ? $utilisateur->getId() : null;
    }

    private function verificationValide(ParametresSite $parametres): bool
    {
        $le = $parametres->getConnexionVerifieeLe();

        return $le !== null && $this->clock->now()->getTimestamp() - $le->getTimestamp() <= VerificationConnexion::VALIDITE;
    }

    /** Persiste aussi la ligne si elle manquait (ParametresSiteRepository::courants) */
    private function enregistrer(ParametresSite $parametres): void
    {
        $this->entityManager->persist($parametres);
        $this->entityManager->flush();
    }
}
