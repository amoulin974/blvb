<?php

namespace App\Controller\Front;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Repository\FeuilleMatchRepository;
use App\Security\FeuilleMatchVoter;
use App\VolleyStats\MatchEquipe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function __construct(private FeuilleMatchRepository $feuilles)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function page(Equipe $equipe, Partie $partie): Response
    {
        $match = $this->match($equipe, $partie, FeuilleMatchVoter::VOIR);

        return $this->prive($this->render('front/feuille_match/page.html.twig', [
            'match' => $match,
            'feuille' => $match->feuille,
            'modifiable' => $this->isGranted(FeuilleMatchVoter::MODIFIER, $match),
        ]));
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
