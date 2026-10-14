<?php

namespace App\Controller\Admin;

use App\Competition\CompositionPoules;
use App\Competition\MiseEnPlaceJournees;
use App\Competition\MiseEnPlaceMatchs;
use App\Entity\Phase;
use App\Entity\Saison;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mise en place d'une saison validée (docs/wizard-saison/ecrans-mise-en-place.md, D35 à D39) : 7 Poules, 8 Journées,
 * puis 9 Matchs. Chaque action enregistre tout de suite ; « aller » ne fait que naviguer.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison/{id}/mise-en-place', name: 'admin_saison_miseenplace_', requirements: ['id' => '\d+'])]
class SaisonMiseEnPlaceController extends AbstractController
{
    public function __construct(private CompositionPoules $composition, private MiseEnPlaceJournees $journees, private MiseEnPlaceMatchs $matchs)
    {
    }

    #[Route('/poules', name: 'poules', methods: ['GET', 'POST'])]
    public function poules(Saison $saison, Request $request): Response
    {
        if ($refus = $this->refuser($saison, 'composer les poules')) {
            return $refus;
        }
        $phase = $this->phase($saison, $request->query->getInt('phase'));
        if ($phase === null) {
            return $this->toutesCloturees($saison, 'de poules à composer');
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
                'quitter' => $this->quitter($saison, 'Poules'),
                'precedent' => $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 6]),
                'suivant' => $this->suivantPoules($saison, $phase, $adresse),
                default => $this->redirect($adresse()),
            };
        }

        return $this->render('admin/saisonwizard/miseenplace/poules.html.twig', [
            'saison' => $saison,
            'phase' => $phase,
            'phases' => CompositionPoules::phases($saison),
            'etat' => $this->composition->etat($phase),
        ]);
    }

    #[Route('/journees', name: 'journees', methods: ['GET', 'POST'])]
    public function journees(Saison $saison, Request $request): Response
    {
        if ($refus = $this->refuser($saison, 'créer les journées')) {
            return $refus;
        }
        $phase = $this->phase($saison, $request->query->getInt('phase'));
        if ($phase === null) {
            return $this->toutesCloturees($saison, 'de journées à créer');
        }
        $adresse = fn (array $extra = []) => $this->generateUrl('admin_saison_miseenplace_journees', ['id' => $saison->getId(), 'phase' => $phase->getId()] + $extra);

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('miseenplace'.$saison->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirect($adresse());
            }
            $action = $payload->getString('action');
            if ($action !== '') {
                $resultat = $this->journees->appliquer($phase, $action);
                $this->addFlash($resultat['ok'] ? 'success' : 'error', $resultat['message']);

                return $this->redirect($adresse().($resultat['ancre'] ? '#'.$resultat['ancre'] : ''));
            }

            return match ($payload->getString('aller')) {
                'quitter' => $this->quitter($saison, 'Journées'),
                'precedent' => $this->redirectToRoute('admin_saison_miseenplace_poules', ['id' => $saison->getId(), 'phase' => $phase->getId()]),
                'suivant' => $this->suivantJournees($saison, $phase, $adresse),
                default => $this->redirect($adresse()),
            };
        }

        return $this->render('admin/saisonwizard/miseenplace/journees.html.twig', [
            'saison' => $saison,
            'phase' => $phase,
            'phases' => CompositionPoules::phases($saison),
            'etat' => $this->journees->etat($phase),
        ]);
    }

    /** Chaque action est déjà enregistrée : « Enregistrer et quitter » ne fait que revenir à la saison. */
    private function quitter(Saison $saison, string $etape): Response
    {
        $this->addFlash('success', sprintf('Étape %s enregistrée. Reprenez la mise en place depuis la page de la saison.', $etape));

        return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
    }

    #[Route('/matchs', name: 'matchs', methods: ['GET', 'POST'])]
    public function matchs(Saison $saison, Request $request): Response
    {
        if ($refus = $this->refuser($saison, 'créer les matchs')) {
            return $refus;
        }
        $phase = $this->phase($saison, $request->query->getInt('phase'));
        if ($phase === null) {
            return $this->toutesCloturees($saison, 'de matchs à créer');
        }
        $adresse = fn (array $extra = []) => $this->generateUrl('admin_saison_miseenplace_matchs', ['id' => $saison->getId(), 'phase' => $phase->getId()] + $extra);

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            if (!$this->isCsrfTokenValid('miseenplace'.$saison->getId(), $payload->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été modifié.');

                return $this->redirect($adresse());
            }
            $action = $payload->getString('action');
            if ($action !== '') {
                $resultat = $this->matchs->appliquer($phase, $action);
                $this->addFlash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
                if ($resultat['avertissement'] !== null) {
                    $this->addFlash('warning', $resultat['avertissement']);
                }

                return $this->redirect($adresse().($resultat['ancre'] ? '#'.$resultat['ancre'] : ''));
            }

            return match ($payload->getString('aller')) {
                'quitter' => $this->quitter($saison, 'Matchs'),
                'precedent' => $this->redirectToRoute('admin_saison_miseenplace_journees', ['id' => $saison->getId(), 'phase' => $phase->getId()]),
                'terminer' => $this->terminerMatchs($saison, $phase, $adresse),
                default => $this->redirect($adresse()),
            };
        }

        return $this->render('admin/saisonwizard/miseenplace/matchs.html.twig', [
            'saison' => $saison,
            'phase' => $phase,
            'phases' => CompositionPoules::phases($saison),
            'etat' => $this->matchs->etat($phase),
        ]);
    }

    /** Une saison en brouillon n'a pas de mise en place : retour à son wizard. */
    private function refuser(Saison $saison, string $action): ?Response
    {
        if (!$saison->isBrouillon()) {
            return null;
        }
        $this->addFlash('error', sprintf('La saison %s n\'est pas encore validée : terminez le wizard avant de %s.', $saison->getNom(), $action));

        return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => max(2, (int) $saison->getEtapeWizard())]);
    }

    private function toutesCloturees(Saison $saison, string $objet): Response
    {
        $this->addFlash('warning', sprintf('Toutes les phases de la saison %s sont clôturées : il n\'y a plus %s.', $saison->getNom(), $objet));

        return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
    }

    private function suivantPoules(Saison $saison, Phase $phase, callable $adresse): Response
    {
        $etat = $this->composition->etat($phase);
        if ($etat['bloquant']) {
            $this->addFlash('error', (string) $etat['raison']);

            return $this->redirect($adresse());
        }

        return $this->redirectToRoute('admin_saison_miseenplace_journees', ['id' => $saison->getId(), 'phase' => $phase->getId()]);
    }

    private function suivantJournees(Saison $saison, Phase $phase, callable $adresse): Response
    {
        $etat = $this->journees->etat($phase);
        if ($etat['bloquant']) {
            $this->addFlash('error', (string) $etat['raison']);

            return $this->redirect($adresse());
        }

        return $this->redirectToRoute('admin_saison_miseenplace_matchs', ['id' => $saison->getId(), 'phase' => $phase->getId()]);
    }

    /** Dernière étape : la phase est prête, retour à la saison. */
    private function terminerMatchs(Saison $saison, Phase $phase, callable $adresse): Response
    {
        $etat = $this->matchs->etat($phase);
        if ($etat['bloquant']) {
            $this->addFlash('error', (string) $etat['raison']);

            return $this->redirect($adresse());
        }
        $this->addFlash('success', sprintf('Mise en place terminée pour %s : poules, journées et matchs sont en place.', $phase->getNom()));

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
