<?php

namespace App\Controller\Front;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Repository\FeuilleMatchRepository;
use App\Security\FeuilleMatchVoter;
use App\VolleyStats\FeuilleMatchService;
use App\VolleyStats\MatchEquipe;
use App\VolleyStats\NomsCourts;
use App\VolleyStats\SaisieConcurrente;
use App\VolleyStats\SaisieInvalide;
use App\VolleyStats\VueFeuille;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page du match d'une équipe (Volley Stats, D66, D67 ; docs/volley-stats/specification-page-match.md) : visible de
 * l'équipe et du responsable de son club seulement. Ordre des contrôles : équipe qui ne joue pas ce match → 404 pour
 * tous ; visiteur → connexion ; connecté sans droit → 404 (la page ne se révèle pas). Rien n'en sort vers le public.
 */
#[Route('/equipe/{equipe}/match/{partie}', name: 'front_feuille_match', requirements: ['equipe' => '\d+', 'partie' => '\d+'])]
final class FeuilleMatchController extends AbstractController
{
    public function __construct(private FeuilleMatchRepository $feuilles, private FeuilleMatchService $service)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function page(Equipe $equipe, Partie $partie): Response
    {
        $match = $this->match($equipe, $partie, FeuilleMatchVoter::VOIR);

        $membres = $this->service->membres($match);

        return $this->prive($this->render('front/feuille_match/page.html.twig', [
            'match' => $match,
            'feuille' => $match->feuille,
            'modifiable' => $this->isGranted(FeuilleMatchVoter::MODIFIER, $match),
            'membres' => $membres,
            'noms' => NomsCourts::pour($membres),
            'presents' => $match->feuille ? array_map(fn ($j) => $j->getMembre()->getId(), $match->feuille->getJoueurs()->toArray()) : $this->service->presentsProposes($match),
            'jeton' => self::jeton($match),
        ]));
    }

    /** « Préparer le match » : crée la page (POST seulement : rien n'est créé par une simple visite) */
    #[Route('/preparer', name: '_preparer', methods: ['POST'])]
    public function preparer(Equipe $equipe, Partie $partie, Request $request): Response
    {
        $match = $this->match($equipe, $partie, FeuilleMatchVoter::MODIFIER);
        if ($this->jetonValide($request, $match)) {
            $this->executer(fn () => $this->service->preparer($match));
        }

        return $this->retour($match);
    }

    /** Joueurs présents (formulaire de cases à cocher, utilisable sans JavaScript) ; crée la page si besoin */
    #[Route('/presents', name: '_presents', methods: ['POST'])]
    public function presents(Equipe $equipe, Partie $partie, Request $request): Response
    {
        $match = $this->match($equipe, $partie, FeuilleMatchVoter::MODIFIER);
        if ($this->jetonValide($request, $match)) {
            $this->executer(function () use ($match, $request): void {
                $feuille = $this->service->preparer($match);
                $this->service->presents($feuille, new MatchEquipe($match->equipe, $match->partie, $feuille), $request->getPayload()->all('presents'));
                $this->addFlash('success', 'Joueurs présents enregistrés.');
            });
        }

        return $this->retour($match);
    }

    /**
     * Écritures d'un set, en JSON (feuille_match_controller.js) : composition, commencer, événement, précédent, suivant,
     * terminer, rouvrir. Réponse : l'état complet de la page ; 400 saisie refusée, 409 saisie concurrente (recharger).
     */
    #[Route('/set/{numero}/{action}', name: '_set', requirements: ['numero' => '[1-5]', 'action' => 'composition|commencer|evenement|precedent|suivant|terminer|rouvrir'], methods: ['POST'])]
    public function set(Equipe $equipe, Partie $partie, int $numero, string $action, Request $request): JsonResponse
    {
        $match = $this->match($equipe, $partie, FeuilleMatchVoter::MODIFIER);
        $jeton = $request->headers->get('X-CSRF-TOKEN');
        if (!$this->isCsrfTokenValid(self::jeton($match), $jeton)) {
            return $this->json(['message' => 'La page a expiré : rechargez-la.'], 403);
        }
        $donnees = json_decode($request->getContent(), true);
        if (!\is_array($donnees)) {
            return $this->json(['message' => 'Saisie non reconnue : rechargez la page.'], 400);
        }
        try {
            $feuille = $match->feuille ?? throw new SaisieInvalide('Préparez d\'abord le match : cochez les joueurs présents.');
            $set = $action === 'composition' ? null : ($feuille->set($numero) ?? throw new SaisieInvalide('Ce set n\'existe pas encore.'));
            $chaine = fn (string $cle) => \is_string($donnees[$cle] ?? null) ? $donnees[$cle] : '';
            match ($action) {
                'composition' => $this->service->composition($feuille, $numero, \is_array($donnees['composition'] ?? null) ? $donnees['composition'] : []),
                'commencer' => $this->service->commencer($set, ($donnees['aCinq'] ?? false) === true),
                'evenement' => $this->service->evenement($set, $chaine('uuid'), $chaine('type'), \is_array($donnees['donnees'] ?? null) ? $donnees['donnees'] : [],
                    \is_int($donnees['dernierOrdre'] ?? null) ? $donnees['dernierOrdre'] : null, $this->getUser() instanceof User ? $this->getUser() : null),
                'precedent' => $this->service->precedent($set, $chaine('uuid')),
                'suivant' => $this->service->suivant($set, $chaine('uuid')),
                'terminer' => $this->service->terminerSet($set),
                'rouvrir' => $this->service->rouvrirSet($set),
            };
            $statut = 200;
        } catch (SaisieConcurrente $e) {
            $statut = 409;
            $message = $e->getMessage();
        } catch (SaisieInvalide $e) {
            $statut = 400;
            $message = $e->getMessage();
        }
        $reponse = $this->json(['message' => $message ?? null, 'feuille' => $match->feuille ? $this->vue($match) : null], $statut);
        $reponse->headers->set('Cache-Control', 'private, no-store');

        return $reponse;
    }

    /** @return array<string, mixed> */
    private function vue(MatchEquipe $match): array
    {
        $this->feuilles->getEntityManager()->refresh($match->feuille);

        return VueFeuille::tableau($match->feuille, NomsCourts::pour($this->service->membres($match)), $match->equipe->getNom(), (string) $match->adversaire()?->getNom());
    }

    private static function jeton(MatchEquipe $match): string
    {
        return 'feuille_match_'.$match->equipe->getId().'_'.$match->partie->getId();
    }

    private function jetonValide(Request $request, MatchEquipe $match): bool
    {
        $jeton = $request->headers->get('X-CSRF-TOKEN') ?? $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid(self::jeton($match), $jeton)) {
            return true;
        }
        $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

        return false;
    }

    /** Saisie refusée : message pour l'utilisateur ; rien d'autre ne sort (pas de message technique) */
    private function executer(callable $action): void
    {
        try {
            $action();
        } catch (SaisieInvalide $e) {
            $this->addFlash('error', $e->getMessage());
        }
    }

    private function retour(MatchEquipe $match): Response
    {
        return $this->redirectToRoute('front_feuille_match', ['equipe' => $match->equipe->getId(), 'partie' => $match->partie->getId()], Response::HTTP_SEE_OTHER);
    }

    /** Contrôles communs, dans l'ordre de la spécification (§4) ; renvoie le match avec sa page si elle existe */
    private function match(Equipe $equipe, Partie $partie, string $droit): MatchEquipe
    {
        $match = new MatchEquipe($equipe, $partie, $this->feuilles->pour($partie, $equipe));
        if (!$match->estValide()) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        if (!$this->isGranted(FeuilleMatchVoter::VOIR, $match) || !$this->isGranted($droit, $match)) {
            throw $this->createNotFoundException();
        }

        return $match;
    }

    /** Téléphones partagés : rien en cache */
    private function prive(Response $reponse): Response
    {
        $reponse->headers->set('Cache-Control', 'private, no-store');

        return $reponse;
    }
}
