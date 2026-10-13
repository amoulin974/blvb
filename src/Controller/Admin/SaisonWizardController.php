<?php

namespace App\Controller\Admin;

use App\Competition\CopieSaison;
use App\Competition\Modele\Modeles;
use App\Competition\Sport\Sports;
use App\Entity\Saison;
use App\Form\Wizard\DepartType;
use App\Form\Wizard\SaisonWizardType;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Wizard de création d'une saison (docs/wizard-saison/ecrans.md, D29) : 1 Départ, 2 Saison, 3 Phases, 4 Passages,
 * 5 Classement, 6 Vérification. La saison est un brouillon dès l'étape 1 ; chaque changement d'étape enregistre.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison', name: 'admin_saison_wizard_')]
class SaisonWizardController extends AbstractController
{
    public const ETAPES = [1 => 'Départ', 2 => 'Saison', 3 => 'Phases', 4 => 'Passages', 5 => 'Classement', 6 => 'Vérification'];

    /** Étape 1 : copier une saison ou partir d'un modèle ; crée le brouillon. */
    #[Route('/wizard/nouvelle', name: 'nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request, Modeles $modeles, CopieSaison $copie, EntityManagerInterface $em, SaisonRepository $saisons, CacheInterface $cache): Response
    {
        $choixModeles = [];
        foreach ($modeles->tous() as $code => $modele) {
            $choixModeles[$modele->libelle()] = $code;
        }
        $form = $this->createForm(DepartType::class, null, ['modeles' => $choixModeles]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $donnees = $form->getData();
            $source = $donnees['saison'] ?? null;
            if ($donnees['depart'] === 'copie' && $source instanceof Saison) {
                $debut = $source->getDateDebut()->modify('+1 year');
                $resultat = $copie->copier($source, self::nomPropose($debut, $saisons), $debut, $source->getDateFin()->modify('+1 year'));
                $saison = $resultat['saison'];
                foreach ($resultat['avertissements'] as $avertissement) {
                    $this->addFlash('warning', $avertissement);
                }
            } elseif ($donnees['depart'] === 'modele' && ($modele = $modeles->parCode((string) ($donnees['modele'] ?? ''))) !== null) {
                $annee = (int) date('Y') + ((int) date('n') >= 7 ? 1 : 0);
                $saison = (new Saison())
                    ->setDateDebut(new \DateTimeImmutable("$annee-09-01"))
                    ->setDateFin(new \DateTimeImmutable(($annee + 1).'-07-31'));
                $saison->setNom(self::nomPropose($saison->getDateDebut(), $saisons));
                $modele->appliquer($saison);
                $em->persist($saison);
                foreach ($saison->getPhases() as $phase) {
                    $em->persist($phase);
                    foreach ($phase->getPoules() as $poule) {
                        $em->persist($poule);
                    }
                }
            } else {
                $this->addFlash('error', 'Choisissez la saison à copier ou le modèle.');

                return $this->redirectToRoute('admin_saison_wizard_nouvelle');
            }

            $saison->setBrouillon(true)->setEtapeWizard(2)->setFavori(0);
            $em->flush();
            $cache->delete('saisons_all');

            return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 2]);
        }

        return $this->render('admin/saisonwizard/depart.html.twig', [
            'form' => $form,
            'modeles' => $modeles->tous(),
            'etapes' => self::etapes(null, 1),
        ]);
    }

    /** Étapes 2 à 6 d'un brouillon. */
    #[Route('/{id}/wizard/{etape}', name: 'etape', requirements: ['id' => '\d+', 'etape' => '[1-6]'], methods: ['GET', 'POST'])]
    public function etape(Saison $saison, int $etape, Request $request, EntityManagerInterface $em, SaisonRepository $saisons, Sports $sports, CacheInterface $cache): Response
    {
        if (!$saison->isBrouillon()) {
            $this->addFlash('error', sprintf('La saison %s est déjà créée : modifiez-la depuis sa page.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
        }
        if ($etape > max(2, (int) $saison->getEtapeWizard())) {
            return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => max(2, (int) $saison->getEtapeWizard())]);
        }

        $vue = ['saison' => $saison, 'etape' => $etape, 'etapes' => self::etapes($saison, $etape)];

        if ($etape === 2) {
            $form = $this->createForm(SaisonWizardType::class, $saison);
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $homonyme = $saisons->findOneBy(['nom' => $saison->getNom()]);
                if ($homonyme !== null && $homonyme !== $saison) {
                    $form->get('nom')->addError(new \Symfony\Component\Form\FormError('Une saison porte déjà ce nom.'));
                }
                if ($form->isValid()) {
                    $em->flush();
                    $cache->delete('saisons_all');

                    return $this->naviguer($request, $saison, $etape, $em);
                }
            }

            return $this->render('admin/saisonwizard/saison.html.twig', $vue + ['form' => $form, 'sport' => $sports->pourSaison($saison)->libelle()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wizard'.$saison->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => $etape]);
            }

            return $this->naviguer($request, $saison, $etape, $em);
        }

        return $this->render('admin/saisonwizard/a_venir.html.twig', $vue);
    }

    /**
     * Après enregistrement d'une étape : bouton « aller » = precedent, suivant, verification, quitter ou un numéro
     * d'étape déjà vue. L'étape atteinte est mémorisée pour reprendre le brouillon.
     */
    private function naviguer(Request $request, Saison $saison, int $etape, EntityManagerInterface $em): Response
    {
        $aller = $request->getPayload()->getString('aller', 'suivant');
        if ($aller === 'quitter') {
            $this->addFlash('success', sprintf('Brouillon « %s » enregistré. Reprenez-le depuis la liste des saisons.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_index');
        }
        $cible = match (true) {
            $aller === 'precedent' => $etape - 1,
            $aller === 'verification' => 6,
            ctype_digit($aller) => (int) $aller,
            default => $etape + 1,
        };
        $cible = max(1, min(6, $cible));
        if ($cible > $etape) {
            $saison->setEtapeWizard(max((int) $saison->getEtapeWizard(), $cible));
            $em->flush();
        }
        if ($cible === 1) {
            return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 1]);
        }

        return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => $cible]);
    }

    /**
     * Étapes pour le stepper : numéro, libellé, état (faite, en cours, à venir) et si elle est accessible (déjà vue).
     *
     * @return list<array{numero: int, libelle: string, etat: string, accessible: bool}>
     */
    private static function etapes(?Saison $saison, int $courante): array
    {
        $atteinte = max($courante, (int) $saison?->getEtapeWizard());
        $etapes = [];
        foreach (self::ETAPES as $numero => $libelle) {
            $etapes[] = [
                'numero' => $numero,
                'libelle' => $libelle,
                'etat' => $numero === $courante ? 'en cours' : ($numero <= $atteinte ? 'faite' : 'à venir'),
                // L'étape 1 d'un brouillon n'est qu'un rappel (le point de départ est appliqué) ; pas de lien sans brouillon
                'accessible' => $saison !== null && $numero <= $atteinte,
            ];
        }

        return $etapes;
    }

    /** « Saison 2027-2028 » d'après la date de début, rendu unique si besoin (« Saison 2027-2028 (2) »). */
    private static function nomPropose(\DateTimeImmutable $debut, SaisonRepository $saisons): string
    {
        $annee = (int) $debut->format('Y');
        $nom = sprintf('Saison %d-%d', $annee, $annee + 1);
        $candidat = $nom;
        for ($i = 2; $saisons->findOneBy(['nom' => $candidat]) !== null; ++$i) {
            $candidat = "$nom ($i)";
        }

        return $candidat;
    }
}
