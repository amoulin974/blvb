<?php

namespace App\Controller\Admin;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Form\PouleType;
use App\Competition\Sport\Sports;
use App\Form\Wizard\SaisonWizardType;
use Symfony\Component\Form\FormError;
use App\Repository\SaisonRepository;
use App\Repository\PouleRepository;
use App\Service\PhaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison', name: 'admin_saison_')]
final class SaisonController extends AbstractController
{
    use SuppressionTrait;

    #[Route('/saison', name: 'index', methods: ['GET'])]
    public function index(SaisonRepository $saisonRepository): Response
    {
        return $this->render('admin/saison/index.html.twig', [
            'saisons' => $saisonRepository->findBy([], ['date_debut' => 'DESC']),
        ]);
    }



    // Une saison se crée désormais avec le wizard (docs/wizard-saison/decisions.md, D6 et D29) : ancienne adresse conservée
    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->redirectToRoute('admin_saison_wizard_nouvelle');
    }


    //Supprime une saison
    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Saison $saison, EntityManagerInterface $entityManager, CacheInterface $cache): Response
    {
        // La saison affichée sur le site ne peut pas être supprimée (l'interface le cache, le serveur le refuse aussi)
        if ($saison->getFavori()) {
            $this->addFlash('error', sprintf('La saison %s est celle affichée sur le site : choisissez d\'abord une autre saison favorite. Rien n\'a été supprimé.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_index', [], Response::HTTP_SEE_OTHER);
        }
        if ($this->supprimerEntite($request, $entityManager, $saison, 'delete'.$saison->getId(), 'la saison '.$saison->getNom(), true)) {
            $cache->delete('saisons_all');
        }

        return $this->redirectToRoute('admin_saison_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    /**
     * Nom, dates et périodes de fermeture ; tant que la saison n'est pas lancée, lien vers le wizard pour l'organisation
     * (phases, passages, classement) (docs/wizard-saison/decisions.md, D29).
     */
    public function edit(Request $request, Saison $saison, EntityManagerInterface $entityManager, CacheInterface $cache, SaisonRepository $saisons, Sports $sports): Response
    {
        $form = $this->createForm(SaisonWizardType::class, $saison);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $homonyme = $saisons->findOneBy(['nom' => $saison->getNom()]);
            if ($homonyme !== null && $homonyme !== $saison) {
                $form->get('nom')->addError(new FormError('Une saison porte déjà ce nom.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $cache->delete('saisons_all');
            $this->addFlash('success', sprintf('La saison %s a été modifiée.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/saison/edit.html.twig', [
            'saison' => $saison,
            'form' => $form,
            'sport' => $sports->pourSaison($saison)->libelle(),
            'lancee' => $saisons->estLancee($saison),
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    //défini une saison comme favorite
    #[Route('/{id}/favorite/', name: 'favorite', methods: ['POST'])]
    public function favorite(Request $request, EntityManagerInterface $em, $id, CacheInterface $cache): Response
    {
        if (!$this->isCsrfTokenValid('favorite'.$id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez.');

            return $this->redirectToRoute('admin_saison_index');
        }
        $saisons=$em->getRepository(Saison::class)->findAll();
        $choisie = $em->getRepository(Saison::class)->find($id);
        if ($choisie?->isBrouillon()) {
            $this->addFlash('error', sprintf('La saison %s est un brouillon : terminez sa création avant de l\'afficher sur le site. Rien n\'a été modifié.', $choisie->getNom()));

            return $this->redirectToRoute('admin_saison_index');
        }
        foreach ($saisons as $saison) {
            if ($saison->getId() == $id) {
                $this->addFlash('success', sprintf('La saison %s est maintenant affichée sur le site.', $saison->getNom()));
            }
            if ($saison->getId()==$id){
                $saison->setFavori(1);
                }else{
                $saison->setFavori(0);
            }
            $em->persist($saison);
            $em->flush();
            $cache->delete('saisons_all');
        }
        return $this->redirectToRoute('admin_saison_index');
    }




    //Affiche une saison

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Saison $saison, Request $request, PouleRepository $pouleRepository, PhaseService $phaseService): Response
    {

        // 1. Déterminer la poule à ouvrir (via paramètre d'URL si présent)
        $openPouleId = $request->query->get('openPoule');
        $pouleOuverte = $openPouleId ? $pouleRepository->find($openPouleId) : null;
        if ($pouleOuverte !== null && $pouleOuverte->getPhase()->getSaison() !== $saison) {
            $pouleOuverte = null; // poule d'une autre saison : ignorée
        }

        // 2. Déterminer la phase à ouvrir
        // Si une poule est spécifiée, on ouvre sa phase parente
        // Sinon, on utilise la logique de "phase actuelle"
        if ($pouleOuverte) {
            $phaseOuverteId = $pouleOuverte->getPhase()->getId();
            $pouleOuverteId = $pouleOuverte->getId();
        } else {
            $phaseActuelle = $this->getPhaseActuelle($saison); 
            $phaseOuverteId = $phaseActuelle ? $phaseActuelle->getId() : null;
            $pouleOuverteId = null; // Par défaut, on ouvrira la première poule de la phase
        }

        // Une phase est cloturable si elle a au moins une poule et que TOUTES ses poules
        // ont déjà des journées (sinon leur classement n'a pas de sens et basculer les
        // équipes vers la phase suivante serait prématuré).
        foreach($saison->getPhases() as $phase){
            $phase->cloturable = $phaseService->estCloturable($phase);
        }

        // Problèmes des règles de passage, montrés dans la fenêtre « Clôturer la phase » avant le clic (D37)
        $avertissementsCloture = [];
        foreach ($saison->getPhases() as $phase) {
            if (!$phase->getClose() && $phase->cloturable) {
                $avertissementsCloture[$phase->getId()] = $phaseService->problemesAvantCloture($phase);
            }
        }

        return $this->render('admin/saison/show.html.twig', [
            'saison' => $saison,
            'avertissementsCloture' => $avertissementsCloture,
            'phaseOuverteId' => $phaseOuverteId,
            'pouleOuverteId' => $pouleOuverteId,
        ]);
    }

    // Recopiez cette méthode du FrontController ou placez-la dans un Service
    private function getPhaseActuelle(Saison $saison) {
        $dateActuelle = new \DateTime();
        foreach ($saison->getPhases() as $phase) {
            if ($dateActuelle >= $phase->getDateDebut() && $dateActuelle <= $phase->getDateFin()) {
                return $phase;
            }
        }
        return $saison->getPhases()->first();
    }
}
