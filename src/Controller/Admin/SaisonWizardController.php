<?php

namespace App\Controller\Admin;

use App\Competition\Classement\CritereDepartage;
use App\Competition\CodesCompetition;
use App\Competition\CopieSaison;
use App\Competition\EtapePassages;
use App\Competition\VerificationSaison;
use App\Competition\Modele\Modeles;
use App\Competition\Sport\Sports;
use App\Entity\Phase;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Enum\PhaseType;
use App\Form\Wizard\DepartType;
use App\Form\Wizard\PhasesWizardType;
use App\Form\Wizard\PhaseWizardType;
use App\Form\Wizard\SaisonWizardType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
    public function __construct(private Sports $sports)
    {
    }

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
            // Saison créée : son organisation se modifie dans le wizard (étapes 2 à 6) tant qu'aucun match n'est joué (D29)
            if ($saisons->aDesMatchsJoues($saison)) {
                $this->addFlash('error', sprintf('Des matchs de la saison %s sont déjà joués : seuls le nom, les dates et les périodes de fermeture se modifient.', $saison->getNom()));

                return $this->redirectToRoute('admin_saison_edit', ['id' => $saison->getId()]);
            }
            if ($etape === 1) {
                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 2]);
            }
        } elseif ($etape > max(2, (int) $saison->getEtapeWizard())) {
            return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => max(2, (int) $saison->getEtapeWizard())]);
        }

        // Vérification déjà atteinte (ou saison créée) : les étapes à corriger sont signalées dans le stepper
        $verification = !$saison->isBrouillon() || (int) $saison->getEtapeWizard() >= 6 ? VerificationSaison::verifier($saison) : null;
        $aCorriger = array_values(array_unique(array_column($verification['erreurs'] ?? [], 'etape')));
        $vue = ['saison' => $saison, 'etape' => $etape, 'etapes' => self::etapes($saison, $etape, $aCorriger)];

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

        if ($etape === 3) {
            return $this->etapePhases($saison, $request, $em, $vue);
        }
        if ($etape === 4) {
            return $this->etapePassages($saison, $request, $em, $vue);
        }
        if ($etape === 5) {
            return $this->etapeClassement($saison, $request, $em, $vue);
        }
        if ($etape === 6) {
            return $this->etapeVerification($saison, $request, $em, $cache, $vue);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wizard'.$saison->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => $etape]);
            }

            return $this->naviguer($request, $saison, $etape, $em);
        }

        return $this->render('admin/saisonwizard/depart_resume.html.twig', $vue);
    }

    /**
     * Étape 3 : les phases et leurs poules. Les boutons « action » (ajouter, supprimer, monter, descendre une phase ;
     * ajouter, supprimer une poule) enregistrent la saisie en cours, modifient la structure et réaffichent l'étape.
     */
    private function etapePhases(Saison $saison, Request $request, EntityManagerInterface $em, array $vue): Response
    {
        $phases = self::phasesDansLOrdre($saison);
        $form = $this->createForm(PhasesWizardType::class, ['phases' => $phases]);
        $form->handleRequest($request);
        $ouverte = $request->query->getInt('phase', 0);

        if ($form->isSubmitted()) {
            self::verifierCodes($form, $phases);
            if ($form->isValid()) {
                $action = $request->getPayload()->getString('action');
                if ($action !== '') {
                    $ouverte = $this->appliquerAction($action, $saison, $phases, $em);
                }
                CodesCompetition::attribuer($saison);
                $em->flush();
                if ($action !== '') {
                    return $this->redirect($this->generateUrl('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 3, 'phase' => $ouverte]).'#phase-'.$ouverte);
                }

                return $this->naviguer($request, $saison, 3, $em);
            }
        }

        return $this->render('admin/saisonwizard/phases.html.twig', $vue + [
            'form' => $form,
            'ouverte' => $ouverte,
            'libelles' => PhaseWizardType::LIBELLES,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    /**
     * Étape 4 : passages d'une phase à l'autre (EtapePassages). Les poules passées en mode détaillé sont mémorisées
     * en session ; les boutons « action » enregistrent la saisie puis réaffichent l'étape.
     */
    private function etapePassages(Saison $saison, Request $request, EntityManagerInterface $em, array $vue): Response
    {
        $cleDetail = 'wizard.passages.detail.'.$saison->getId();
        $detail = $request->getSession()->get($cleDetail, []);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wizard'.$saison->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 4]);
            }
            $action = $request->getPayload()->getString('action');
            $saisie = $request->getPayload()->all('p');
            foreach (EtapePassages::appliquer($saison, $saisie, $action, $detail) as $probleme) {
                $this->addFlash('warning', $probleme);
            }
            $request->getSession()->set($cleDetail, $detail);
            $em->flush();
            if ($action !== '') {
                $ancre = preg_match('/(\d+)/', $action, $m) ? '#poule-'.$m[1] : '';

                return $this->redirect($this->generateUrl('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 4]).$ancre);
            }

            return $this->naviguer($request, $saison, 4, $em);
        }

        return $this->render('admin/saisonwizard/passages.html.twig', $vue + [
            'transitions' => EtapePassages::transitions($saison, $detail),
        ]);
    }

    /**
     * Étape 6 : vérification, schéma de la saison, récapitulatif ; « Valider la saison » (aller = valider) n'aboutit
     * que sans erreur : la saison cesse d'être un brouillon (D29).
     */
    private function etapeVerification(Saison $saison, Request $request, EntityManagerInterface $em, CacheInterface $cache, array $vue): Response
    {
        $verification = VerificationSaison::verifier($saison);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wizard'.$saison->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 6]);
            }
            if ($request->getPayload()->getString('aller') !== 'valider') {
                return $this->naviguer($request, $saison, 6, $em);
            }
            if ($verification['erreurs'] === [] && !$saison->isBrouillon()) {
                $em->flush();
                $this->addFlash('success', sprintf('Les modifications de la saison %s sont enregistrées.', $saison->getNom()));

                return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
            }
            if ($verification['erreurs'] === []) {
                $saison->setBrouillon(false)->setEtapeWizard(null);
                $em->flush();
                $cache->delete('saisons_all');
                $premiere = self::phasesDansLOrdre($saison)[0] ?? null;
                $this->addFlash('success', sprintf('Organisation de la saison %s enregistrée.%s', $saison->getNom(),
                    $premiere ? sprintf(' Prochaine étape : composer les poules de la %s.', $premiere->getNom()) : ''));

                return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
            }
        }

        $phases = self::phasesDansLOrdre($saison);
        $transitions = [];
        foreach ($phases as $i => $phase) {
            if (isset($phases[$i + 1])) {
                $transitions[] = ['phase' => $phase, 'suivante' => $phases[$i + 1], 'regles' => $phase->getReglesDePassage()->toArray()];
            }
        }

        return $this->render('admin/saisonwizard/verification.html.twig', $vue + [
            'verification' => $verification,
            'schema' => VerificationSaison::schema($saison),
            'phases' => $phases,
            'transitions' => $transitions,
            'libelles' => PhaseWizardType::LIBELLES,
            'sport' => $this->sports->pourSaison($saison)->libelle(),
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
    }

    /** Catégories du barème saisies (volley-ball) : clé enregistrée → [setter de la saison, libellé, aide]. */
    private const BAREME = [
        'victoire_forte' => ['setPointsVictoireForte', 'Victoire 3-0 ou 3-1', null],
        'victoire_faible' => ['setPointsVictoireFaible', 'Victoire 3-2', null],
        'defaite_forte' => ['setPointsDefaiteForte', 'Défaite 2-3', null],
        'defaite_faible' => ['setPointsDefaiteFaible', 'Défaite 0-3 ou 1-3', null],
        'forfait' => ['setPointsForfait', 'Équipe forfait', 'L\'adversaire reçoit les points d\'une victoire 3-0.'],
    ];

    /**
     * Étape 5 : barème de la saison, barème propre à certaines phases, ordre du départage (liste réordonnée par les
     * boutons « action » : monter, descendre, retirer, ajouter un critère).
     */
    private function etapeClassement(Saison $saison, Request $request, EntityManagerInterface $em, array $vue): Response
    {
        $phases = self::phasesDansLOrdre($saison);
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('wizard'.$saison->getId(), $request->getPayload()->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été enregistré.');

                return $this->redirectToRoute('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 5]);
            }
            $payload = $request->getPayload();
            $entier = static fn ($v) => \is_string($v) && preg_match('/^-?\d{1,2}$/', trim($v)) ? (int) trim($v) : null;

            $bareme = $payload->all('bareme');
            $valeurs = [];
            foreach (self::BAREME as $cle => [, $libelle]) {
                $valeurs[$cle] = $entier($bareme[$cle] ?? null);
                if ($valeurs[$cle] === null) {
                    $erreurs[] = sprintf('%s : indiquez un nombre de points (de -99 à 99).', $libelle);
                }
            }
            $baremesPhases = [];
            foreach ($phases as $phase) {
                $champs = $payload->all('phase')[$phase->getId()] ?? [];
                if (empty($champs['propre'])) {
                    $baremesPhases[$phase->getId()] = null;
                } elseif (($champs['mode'] ?? 'aucun') === 'aucun') {
                    $baremesPhases[$phase->getId()] = array_fill_keys(array_keys(self::BAREME), 0);
                } else {
                    $propre = [];
                    foreach (array_keys(self::BAREME) as $cle) {
                        if (($champs[$cle] ?? '') !== '') {
                            $propre[$cle] = $entier($champs[$cle]);
                            if ($propre[$cle] === null) {
                                $erreurs[] = sprintf('%s, %s : indiquez un nombre de points (de -99 à 99), ou laissez vide pour reprendre celui de la saison.', $phase->getNom(), self::BAREME[$cle][1]);
                            }
                        }
                    }
                    $baremesPhases[$phase->getId()] = array_filter($propre, fn ($v) => $v !== null) ?: null;
                }
            }

            if ($erreurs === []) {
                foreach (self::BAREME as $cle => [$setter]) {
                    $saison->$setter($valeurs[$cle]);
                }
                foreach ($phases as $phase) {
                    $phase->setBareme($baremesPhases[$phase->getId()]);
                }
                $saison->setDepartage(self::departage($payload->all('departage'), $payload->getString('action'), $payload->getString('nouveau_critere')));
                $em->flush();
                if ($payload->getString('action') !== '') {
                    return $this->redirect($this->generateUrl('admin_saison_wizard_etape', ['id' => $saison->getId(), 'etape' => 5]).'#departage');
                }

                return $this->naviguer($request, $saison, 5, $em);
            }
        }

        $criteres = array_values(array_filter($saison->getDepartage(), fn (CritereDepartage $c) => $c !== CritereDepartage::ALPHABETIQUE));

        return $this->render('admin/saisonwizard/classement.html.twig', $vue + [
            'erreurs' => $erreurs,
            'bareme' => self::BAREME,
            'phases' => $phases,
            'criteres' => $criteres,
            'disponibles' => array_values(array_filter(CritereDepartage::cases(), fn (CritereDepartage $c) => $c !== CritereDepartage::ALPHABETIQUE && !\in_array($c, $criteres, true))),
            'saisie' => $request->isMethod('POST') ? $request->getPayload()->all() : null,
        ], new Response(status: $erreurs === [] ? 200 : 422));
    }

    /**
     * Départage envoyé (codes dans l'ordre), modifié par l'action : monter-critere-i, descendre-critere-i,
     * retirer-critere-i, ajouter-critere (critère choisi dans nouveau_critere).
     *
     * @return list<CritereDepartage>
     */
    private static function departage(array $codes, string $action, string $nouveau): array
    {
        $criteres = array_values(array_filter(array_map(fn ($c) => \is_string($c) ? CritereDepartage::tryFrom($c) : null, $codes)));
        if ($action === 'ajouter-critere' && ($critere = CritereDepartage::tryFrom($nouveau)) !== null) {
            $criteres[] = $critere;
        } elseif (preg_match('/^(monter|descendre|retirer)-critere-(\d+)$/', $action, $m) && isset($criteres[(int) $m[2]])) {
            $i = (int) $m[2];
            if ($m[1] === 'retirer') {
                array_splice($criteres, $i, 1);
            } else {
                $j = $m[1] === 'monter' ? $i - 1 : $i + 1;
                if (isset($criteres[$j])) {
                    [$criteres[$i], $criteres[$j]] = [$criteres[$j], $criteres[$i]];
                }
            }
        }

        return $criteres;
    }

    /** Applique un bouton de structure de l'étape 3 ; renvoie l'index de la phase à garder ouverte. */
    private function appliquerAction(string $action, Saison $saison, array $phases, EntityManagerInterface $em): int
    {
        if ($action === 'ajouter-phase') {
            $derniere = end($phases) ?: null;
            $phase = (new Phase())
                ->setNom('Phase '.(\count($phases) + 1))
                ->setType(PhaseType::CHAMPIONNAT)
                ->setOrdre(($derniere?->getOrdre() ?? -1) + 1)
                ->setClose(0)
                ->setDateDebut($derniere?->getDateFin() ?? $saison->getDateDebut())
                ->setDateFin($saison->getDateFin());
            $saison->addPhase($phase);
            $phase->addPoule((new Poule())->setNom('Poule A')->setNiveau(1));
            $em->persist($phase);
            foreach ($phase->getPoules() as $poule) {
                $em->persist($poule);
            }

            return \count($phases);
        }

        if (preg_match('/^(supprimer|monter|descendre)-phase-(\d+)$/', $action, $m) && isset($phases[(int) $m[2]])) {
            $i = (int) $m[2];
            $phase = $phases[$i];
            if ($m[1] === 'supprimer') {
                $saison->removePhase($phase);
                $em->remove($phase);

                return max(0, $i - 1);
            }
            $j = $m[1] === 'monter' ? $i - 1 : $i + 1;
            if (isset($phases[$j])) {
                [$a, $b] = [$phase->getOrdre(), $phases[$j]->getOrdre()];
                $phase->setOrdre($b);
                $phases[$j]->setOrdre($a);

                return $j;
            }

            return $i;
        }

        if (preg_match('/^ajouter-poule-(\d+)$/', $action, $m) && isset($phases[(int) $m[1]])) {
            $phase = $phases[(int) $m[1]];
            $niveaux = array_map(fn (Poule $p) => (int) $p->getNiveau(), $phase->getPoules()->toArray());
            $poule = (new Poule())
                ->setNom('Poule '.\chr(64 + min(26, $phase->getPoules()->count() + 1)))
                ->setNiveau(($niveaux === [] ? 0 : max($niveaux)) + 1);
            $phase->addPoule($poule);
            $em->persist($poule);

            return (int) $m[1];
        }

        if (preg_match('/^supprimer-poule-(\d+)-(\d+)$/', $action, $m) && isset($phases[(int) $m[1]])) {
            $phase = $phases[(int) $m[1]];
            $poule = array_values($phase->getPoules()->toArray())[(int) $m[2]] ?? null;
            if ($poule !== null) {
                $phase->removePoule($poule);
                $em->remove($poule);
            }

            return (int) $m[1];
        }

        return 0;
    }

    /** Codes saisis : uniques dans la saison (phases) et dans la phase (poules), sinon erreur sur le champ. */
    private static function verifierCodes(FormInterface $form, array $phases): void
    {
        $codesPhases = [];
        foreach ($form->get('phases') as $i => $phaseForm) {
            $code = (string) $phaseForm->get('code')->getData();
            if ($code !== '' && isset($codesPhases[$code])) {
                $phaseForm->get('code')->addError(new FormError(sprintf('Le code %s est déjà utilisé par une autre phase.', $code)));
            }
            $codesPhases[$code] = true;
            $codesPoules = [];
            foreach ($phaseForm->get('poules') as $pouleForm) {
                $codePoule = (string) $pouleForm->get('code')->getData();
                if ($codePoule !== '' && isset($codesPoules[$codePoule])) {
                    $pouleForm->get('code')->addError(new FormError(sprintf('Le code %s est déjà utilisé par une autre poule de cette phase.', $codePoule)));
                }
                $codesPoules[$codePoule] = true;
            }
        }
    }

    /** @return list<Phase> */
    private static function phasesDansLOrdre(Saison $saison): array
    {
        $phases = $saison->getPhases()->toArray();
        usort($phases, fn (Phase $a, Phase $b) => [$a->getOrdre(), $a->getId()] <=> [$b->getOrdre(), $b->getId()]);

        return array_values($phases);
    }

    /**
     * Après enregistrement d'une étape : bouton « aller » = precedent, suivant, verification, quitter ou un numéro
     * d'étape déjà vue. L'étape atteinte est mémorisée pour reprendre le brouillon.
     */
    private function naviguer(Request $request, Saison $saison, int $etape, EntityManagerInterface $em): Response
    {
        $aller = $request->getPayload()->getString('aller', 'suivant');
        if ($aller === 'quitter') {
            if (!$saison->isBrouillon()) {
                $this->addFlash('success', sprintf('Les modifications de la saison %s sont enregistrées.', $saison->getNom()));

                return $this->redirectToRoute('admin_saison_show', ['id' => $saison->getId()]);
            }
            $this->addFlash('success', sprintf('Brouillon « %s » enregistré. Reprenez-le depuis la liste des saisons.', $saison->getNom()));

            return $this->redirectToRoute('admin_saison_index');
        }
        $cible = match (true) {
            $aller === 'precedent' => $etape - 1,
            $aller === 'verification' => 6,
            ctype_digit($aller) => (int) $aller,
            default => $etape + 1,
        };
        $cible = max($saison->isBrouillon() ? 1 : 2, min(6, $cible));
        if ($cible > $etape && $saison->isBrouillon()) {
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
    private static function etapes(?Saison $saison, int $courante, array $aCorriger = []): array
    {
        // Saison déjà créée (modification) : toutes les étapes sauf le départ
        $edition = $saison !== null && !$saison->isBrouillon();
        $atteinte = $edition ? 6 : max($courante, (int) $saison?->getEtapeWizard());
        $etapes = [];
        foreach (self::ETAPES as $numero => $libelle) {
            if ($edition && $numero === 1) {
                continue;
            }
            $etapes[] = [
                'numero' => $numero,
                'libelle' => $libelle,
                'etat' => $numero === $courante ? 'en cours' : (\in_array($numero, $aCorriger, true) ? 'à corriger' : ($numero <= $atteinte ? 'faite' : 'à venir')),
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
