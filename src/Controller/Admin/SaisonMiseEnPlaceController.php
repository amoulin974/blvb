<?php

namespace App\Controller\Admin;

use App\Competition\CompositionPoules;
use App\Entity\Phase;
use App\Entity\Saison;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mise en place d'une saison validée (docs/wizard-saison/ecrans-mise-en-place.md, D35 à D38) : 7 Poules (ici),
 * puis Journées et Matchs. Chaque action enregistre tout de suite ; « aller » ne fait que naviguer.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison/{id}/mise-en-place', name: 'admin_saison_miseenplace_', requirements: ['id' => '\d+'])]
class SaisonMiseEnPlaceController extends AbstractController
{
    public function __construct(private CompositionPoules $composition)
    {
    }

    #[Route('/poules', name: 'poules', methods: ['GET', 'POST'])]
    public function poules(Saison $saison, Request $request): Response
    {
        if ($saison->isBrouillon()) {
            $this->addFlash('error', sprintf('La saison %s n\'est pas encore validée : terminez le wizard avant de composer les poules.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => max(2, (int) $saison->getEtapeWizard())]);
        }

        $phase = $this->phase($saison, $request->query->getInt('phase'));
        if ($phase === null) {
            $this->addFlash('warning', sprintf('Toutes les phases de la saison %s sont clôturées : il n\'y a plus de poules à composer.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }
        $adresse = fn (array $extra = []) => $this->generateUrl('admin_saison_miseenplace_poules', ['id' => $saison->getId(), 'phase' => $phase->getId()] + $extra);

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('miseenplace'.$saison->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirect($adresse());
            }
            $action = $payload->getString('action');
            if ($action !== '') {
                $resultat = $this->composition->appliquer($phase, $action, $payload->all('vers'), $payload->all('ajout'));
                $this->addFlash($resultat['ok'] ? 'success' : 'error', $resultat['message']);

                return $this->redirect($adresse().($resultat['ancre'] ? '#'.$resultat['ancre'] : ''));
            }

            return match ($payload->getString('aller')) {
                'precedent' => $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 6]),
                'terminer' => $this->terminer($saison, $phase, $adresse),
                default => $this->redirect($adresse()),
            };
        }

        $etat = $this->composition->etat($phase);

        return $this->render('admin/saisonwizard/miseenplace/poules.html.twig', [
            'saison' => $saison,
            'phase' => $phase,
            'phases' => CompositionPoules::phases($saison),
            'etat' => $etat,
        ]);
    }

    private function terminer(Saison $saison, Phase $phase, callable $adresse): Response
    {
        $etat = $this->composition->etat($phase);
        if ($etat['bloquant']) {
            $this->addFlash('error', (string) $etat['raison']);

            return $this->redirect($adresse());
        }
        $this->addFlash('success', sprintf('Composition des poules de « %s » enregistrée.', $phase->getNom()));

        return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
    }

    /** Phase demandée si elle appartient à la saison, sinon la première phase non clôturée. */
    private function phase(Saison $saison, int $id): ?Phase
    {
        if ($id > 0) {
            foreach ($saison->getPhases() as $phase) {
                if ($phase->getId() === $id) {
                    return $phase;
                }
            }
        }

        return CompositionPoules::phaseACompleter($saison);
    }
}
