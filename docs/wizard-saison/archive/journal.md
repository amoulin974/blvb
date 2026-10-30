# Journal du chantier (archive)

Entrées du 13/10/2026 au 22/10/2026, sorties de `methode.md` pour alléger la lecture de reprise. À ne lire
que pour retrouver l'historique d'un choix ou d'un piège.

### 2026-10-13 : lancement

- Branche `formats-competition` créée depuis `main` (`12b4fd4`).
- Choix de méthode : pas de Spec Kit, mais sa démarche en version légère (spécification →
  décisions → plan → lots), dans `docs/wizard-saison/`.
- État des lieux du code fait (voir `plan.md`, partie 1).
- `decisions.md`, `plan.md` et `lots.md` créés, puis complétés avec les réponses de l'utilisateur
  (D7 à D15) : formats BLVB d'abord, stockage mixte, migration des saisons existantes, départ par
  copie ou modèle fixe, wizard couvrant toute la création, pas de places provisoires, choix du
  sport (volley d'abord, abstraction `RegleSport` dès le socle), règlement généré en dernier,
  une branche par lot.
- Lot 0 terminé. Aucun code modifié.
- **Suite** : lot 1 (socle) sur la branche `formats-competition-lot1`, voir `lots.md`.

### 2026-10-13 : lot 1, notation des places

- Cadrage commité sur `formats-competition` (`2a3937d`) ; branche `formats-competition-lot1` créée.
- Notation des places écrite dans `src/Competition/` : `NotationPlace::analyser` (chaîne → `Place`),
  `Place::rangsPour` (rangs négatifs, plages), `ResolveurPlace` (place → équipes) via l'interface
  `ResultatsPhases`. Tests : `tests/Unitaire/Competition/` (41 tests), suite unitaire verte.
- Point sur les tests : le `vendor` du conteneur était en retard sur `composer.lock` (avertissement
  `DAMA\DoctrineTestBundle`), réglé par `composer install` ; les tests d'intégration et fonctionnels
  ne tournaient pas en local faute de droits de `symfony` sur `blvb_new_test`, réglé par le `GRANT`
  de la partie « Tests ». Résultat : 300 tests verts (85 unitaires, 50 intégration, 163
  fonctionnels, 2 lents).
- `RegleSport` + `Volley` : `ClassementService` (points, sets) et l'API de saisie
  (`FrontController::api_score_update` : validation, forfait) passent par le sport de la saison ;
  `RegleScore` supprimé. Aucun changement de comportement : 306 tests verts.
- `GenerateurFormat` : `Championnat` et `Elimination` reprennent à l'identique le code de
  `JourneeService` et `PartieService`, qui passent par `Formats` et ne testent plus `PhaseType`.
  311 tests verts, tests lents compris (`composer tests:tout`).
- Lot 1 terminé, sans interface graphique (pas de relecture ergonome / webdesigner nécessaire).
- **Suite** : fusionner `formats-competition-lot1` dans `formats-competition`, puis lot 2
  (classement paramétrable) sur `formats-competition-lot2`.

### 2026-10-13 : lot 1 fusionné, lot 2

- `tests/SPECIFICATIONS.md` mis à jour (§4.16), lot 1 fusionné dans `formats-competition` et poussé ;
  CI verte. `gh` connecté dans WSL (`gh auth setup-git`) : push et suivi de la CI depuis WSL.
- Règle ajoutée à la méthode : doc des tests à jour à chaque modification de test.
- Lot 2 : décisions D16 à D19 ; départage par saison (`CritereDepartage`, quotient de sets,
  mini-classement), barème surchargeable par phase, migration `Version20261013100000` (à appliquer
  au déploiement seulement). Sans écran. CLA-12 à CLA-16 ajoutés : 319 tests verts + 2 lents,
  `composer qualite` et `doctrine:schema:validate` (test) OK.

### 2026-10-13 : lot 3, règles de passage

- Décisions D20 à D28. `bin/base` créé (base locale alignée sur la branche) ; `blvb_new` alignée.
- Codes des phases et poules ; entité `RegleDePassage` ; règles par défaut du schéma 2025 ; clôture
  par les règles, avec vérification et avertissements ; message relu par l'ergonome et le webdesigner
  (agents lancés comme agents généraux suivant leur fiche). Migrations `Version20261013120000` et
  `Version20261013130000` vérifiées sur une copie de la base de développement. 351 tests verts.
- Reste du lot 3 : voir `lots.md`.

### 2026-10-13 : lot 3 fusionné, contraste sur main et prod, début du lot 4

- Contraste des alertes de l'admin corrigé sur `main` et `prod` (gabarit `admin/_alerte.html.twig`,
  relu par l'ergonome, le webdesigner et l'agent `deploiement`) ; `main` ramené dans
  `formats-competition` (conflit résolu : alerte commune + message à liste).
- Lot 3 fusionné et poussé. Lot 4 commencé : sport de la saison, modèle « Format BLVB actuel » (fin du
  YAML), copie d'une saison ; 361 tests verts.
- Propositions d'écrans de l'ergonome et du webdesigner synthétisées dans `ecrans.md`, avec
  19 questions à trancher par l'utilisateur. **Suite** : réponses aux questions, puis écrans.

### 2026-10-13 : lot 4, wizard réalisé et relu

- Wireframe cliquable validé (D29 à D33), puis les 6 étapes du wizard, le brouillon et la
  modification d'une saison créée ; `SaisonListener` supprimé.
- Relectures ergonome, webdesigner et sécurité des écrans réalisés : retours intégrés (D34, WIZ-14,
  WIZ-15, détail dans `lots.md`) ; quelques points reportés au lot 5. 383 tests verts + 2 lents.
  **Suite** : fusion de `formats-competition-lot4` dans `formats-competition`, puis lot 5 (mise en
  place des poules, journées et matchs).

### 2026-10-13 : lot 4 fusionné, reprise sur un autre ordinateur

- Lot 4 fusionné dans `formats-competition` et poussé. Derniers ajouts après les relectures : thème
  d'erreurs de formulaire (`templates/admin/form/erreurs.html.twig`), ordre des phases contrôlé par les
  dates de début (WIZ-17), boutons Monter / Descendre explicites (`_deplacer.html.twig`), période de
  fermeture vide refusée (WIZ-16). 385 tests verts + 2 lents.
- **Suite** : lot 5 (mise en place : composition des poules, journées, génération des matchs,
  enchaînement des phases), voir `lots.md`. Reporté au lot 5 par les relectures : carte « Point de
  départ » du récapitulatif, « Brouillon enregistré à 14 h 32 », écarts d'effectif dans le schéma,
  aide courte sous chaque critère de départage, liens de l'alerte d'erreur vers les champs. Hors lots :
  avertissements avant « Clôturer », messages joints par « ; » (voir `lots.md`).

### 2026-10-14 : lot 5, mise en place

- Branche `formats-competition-lot5` ; décisions D35 à D41, écrans dessinés puis relus par l'ergonome et le
  webdesigner avant réalisation (`ecrans-mise-en-place.md`, §1 à §22).
- **Trois étapes de la mise en place** (stepper « Mise en place », après la validation de la saison) : Poules
  (`CompositionPoules`), Journées (`MiseEnPlaceJournees`, `Semaines`, `GenerateurFormat::nombreJournees`) et Matchs
  (`MiseEnPlaceMatchs`, `GenerateurFormat::nombreMatchs`), toutes dans `SaisonMiseEnPlaceController`. Chaque action
  enregistre tout de suite ; « Enregistrer et quitter » sur les trois.
- **Matchs** : génération de la phase (optimiseur, poules calculées ensemble) ou d'une poule ; régénération confirmée ;
  « Replacer les matchs à venir » (phase ou poule) qui conserve les matchs joués et exige la case « J'ai saisi tous les
  scores des matchs déjà joués » ; transaction (un échec ne fait perdre aucun match, MEP-24) ; priorités fixes
  expliquées, pas de pondération (D41) ; verrou par poule et par phase (score saisi d'un côté ou de l'autre).
- **Clôture d'une phase (D37, D40)** : applique les règles de passage puis renvoie à l'étape Poules de la phase suivante ;
  ne crée plus journées ni matchs ; avertissements des règles dans la fenêtre « Clôturer » ; conditions vérifiées côté
  serveur (saison validée, phases précédentes clôturées, journées présentes).
- **Fenêtre de confirmation partagée** : ton `primary`, attente annoncée (bouton, « Annuler » et Échap bloqués,
  `role="status"`), complément lu par les lecteurs d'écran.
- **Reports du lot 4 réalisés** : point de départ enregistré (`Saison.depart_libelle`) et rappelé au récapitulatif,
  « Brouillon enregistré à H h mm » (`Saison.modifie_le`), écarts d'effectif dans le schéma, aide sous chaque critère de
  départage, alerte de l'étape 3 avec liens vers les champs. Migration `Version20261014180000` (**à appliquer au
  déploiement seulement**, comme les précédentes du chantier).
- Relectures ergonome, webdesigner et sécurité de chaque écran, retours intégrés (verrou contourné par l'ancienne page
  « Optimiser », classements à zéro verrouillant la phase suivante, clôture sans condition côté serveur…). 418 tests
  verts + 2 lents.
- **Suite** : lot 6 (formats BLVB : aller-retour, format de match par phase, élimination complète avec barrages,
  petite finale, journée des finales, barrages de montée et descente), voir `lots.md`. Points ouverts à reprendre (`decisions.md`, PO1 à PO3) :
  recentrer la page d'une saison hors wizard sur le suivi (avis de l'ergonome du 14/10/2026, 5 décisions à prendre,
  dont vérifier où se saisissent les scores) ; équipe qui se retire et forfait général (règle de forfait, D41) ;
  page « Optimiser » (tableaux colorés, hors motif `table-admin`).

### 2026-10-15 : corrections sur main, lot 6 (barrages, tableau complet)

- **Sur `main`, déployées en `prod`** (`2c67e9d`) : pas de zone de montée / descente sans poule voisine
  (CLA-17) ; les capitaines des deux équipes peuvent saisir le score (SCO-03 : sans journal ni verrou, choix de
  l'utilisateur) ; `main` ramenée dans `formats-competition-lot5`, lot 5 fusionné dans `formats-competition`.
- **Lot 6** (`formats-competition-lot6`, décisions D42 à D44) :
  - 6a : `PhaseType::BARRAGE`, `Phase.options`, générateur `Barrage` (une poule de deux équipes par match),
    vainqueur / perdant (`ResultatsSaison::issue`), codes `BAR` (migration `Version20261015100000`) ;
  - 6b : modèle « BLVB avec barrages » (1 montée directe, barrage avant-dernier / 2e du dessous, 1 descente) ;
  - 6c : barrages dans le wizard (étapes Phases, Passages, vérification), relu ergonome, webdesigner, sécurité ;
  - 6d : tableau complet : têtes de série selon le classement, placement standard, exemptions, petite finale
    (migration `Version20261015110000`, `partie.parent_perdants`).
  444 tests verts + 2 lents.
- **Reste du lot 6** : 6e journée des finales (C3), 6f aller-retour (A2) et format de match par phase ; vérifier
  dans le navigateur la mise en place (Poules, Journées, Matchs) d'une phase de barrages ; barrages aussi en
  fin de saison (point 6 de F2, à décider). Les deux migrations du lot ne vont en production qu'au déploiement.

### 2026-10-16 : e-mails, import Excel et panne en production (hors chantier)

- **Expéditeur des e-mails** (sur `main` `ad55678`, fusionné dans `prod` `41ba275`) : Francis a constaté que le
  SMTP d'OVH n'accepte que le compte authentifié, `blvb@vblc.fr` ; le code envoyait « mot de passe oublié » avec
  `admin@blvb.fr` et la confirmation d'inscription (inscription fermée, code conservé) avec `tech@vblc.fr`. Un seul
  expéditeur, en-tête From et enveloppe, dans `config/packages/mailer.yaml`, réglable par `MAILER_FROM` (par défaut
  `blvb@vblc.fr` dans `services.yaml`) ; objets et texte des messages en français ; test MAI-01. Fusion dans `prod` :
  seul conflit `.env`, résolu en gardant celui de `prod` (mot de passe SMTP de Francis) et en y ajoutant `MAILER_FROM`.
- **Import Excel des utilisateurs** : erreur 500 en production, fonctionne 10 lignes par 10 lignes. Cause probable :
  hachage d'un mot de passe aléatoire par compte (≈ 0,3 s) qui dépasse les 30 s de PHP sur le serveur. Décision de
  l'utilisateur : générer ces mots de passe plus simplement, l'utilisateur passant par « J'ai oublié mon mot de
  passe ». À faire sur `main` (voir `lots.md`, hors lots).
- **Site en panne en production** : la branche `prod` (`b62d0b1`, puis `41ba275`) se construit et fonctionne sur le
  poste de développement ; cause côté serveur, diagnostic en attente (Francis).
- **Rappels** : répondre à l'utilisateur en français ; le `.env` de `prod` contient des secrets, ne pas l'afficher
  (le contrôle automatique bloque sa lecture) : le résoudre par `git checkout --ours .env` puis ajouter les lignes
  nouvelles.
- **Faille de sécurité** CVE-2026-109449 (`symfony/expression-language`, signalée par le job « Sécurité » de la CI) :
  passage en 7.4.21 sur `main` (`4a13ab8`), fusionné dans `prod` (`1b609d4`) et dans `formats-competition-lot6`
  (`03c063e`). **Correction (soir du 16/10)** : le serveur tourne avec `.:/app` monté (compose.yaml, cible
  `frankenphp_dev`), donc le `vendor` est celui du dossier du serveur, **pas** celui de l'image : il faut y lancer
  `composer install` (versions exactes de `composer.lock`), vider le cache et **redémarrer** le conteneur (le mode worker de
  FrankenPHP garde les anciennes classes en mémoire).
- **Cause de la panne en production** (logs de Francis) : `vendor` du serveur en retard sur `composer.lock` ; Twig trop ancien
  pour l'opérateur `?.` introduit le 15/10 (`d79bdfa`, `_score_partie.html.twig` ligne 16) ; après la mise à jour de
  `twig/twig` seul, erreur `CallExpression::needsIsSandboxed()` : anciennes classes Twig encore chargées par le worker.
  Remède : `composer install`, `cache:clear`, `docker compose restart php` ; annuler la retouche manuelle du gabarit.
- Le chantier n'a pas avancé : reste du lot 6 inchangé (6c bis, 6e, 6f).

### 2026-10-16 (soir) : lot 6e, journée des finales

- Décisions D45 à D48 prises avec l'utilisateur : journée des finales (tours regroupés au choix par phase), planning
  calculé, aller-retour en miroir, 2 sets gagnants avec barème adapté.
- **6e réalisé** : option `journee_finales` de la phase (`Phase::getJourneeFinales`), service `PlanningFinales` (créneaux
  d'autant de matchs que de terrains, demi-finales puis finales, du niveau le plus bas au niveau 1 ; une régénération de
  tableau ne replace que ce tableau ; un match avec score n'est jamais déplacé ; toute la journée recalculée après la
  génération de la phase), journées datées par `Elimination`, message « trop tôt » aussi à la vérification, date décalée
  à la copie. Écran à l'étape Phases (`JourneeFinalesType`), relu par l'ergonome, le webdesigner et la sécurité, retours
  intégrés (libellés, aide raccourcie, contrôles seulement si la case est cochée, texte en 16 px dans le fieldset avec
  `.fieldset-formulaire`, liste des gymnases par identifiant). Défaut corrigé : pas de petite finale dans un tableau de 4.
- **Piège** : ne pas déclarer un type de formulaire comme service. Dans ce projet, aucun ne l'est ; en déclarer un fait
  perdre aux autres le jeton CSRF commun `submit` (contrôle d'origine), détecté par `FicheClubTest::test_protection_csrf`.
  Passer les données par une option du formulaire.
- 455 tests verts + 2 lents. **Suite** : 6f (aller-retour en miroir, format de match en 2 sets gagnants ; durée proposée
  de 60 minutes pour la journée des finales en 2 sets, avis de l'ergonome), 6c bis (relecture navigateur des barrages).
  Reporté (webdesigner) : contraste des `.label` de daisyUI (≈ 4,4:1) et taille de texte des autres fieldsets de l'étape.

### 2026-10-19 : lot 6f-1, format de match (2 ou 3 sets gagnants)

- Reprise sur `formats-competition-lot6` après `git fetch` : le distant avait 11 commits d'avance (lot 6e, D45 à D48,
  rappel des scores) faits depuis un autre poste ; le seul commit local (méthode, branche `prod`) a été rejoué dessus
  (`git rebase`), conflit de `methode.md` résolu en gardant les deux versions.
- `docs/` et un `CLAUDE.md` (consignes, pointeur vers ce chantier) ont été ajoutés à `main`.
- **6f-1 fait** (D48) : `Phase::getSetsGagnants` (option `sets_gagnants`, 3 par défaut), `RegleSport` et `Volley`
  prennent `$setsGagnants` ; en 2 sets : 2-0 forte, 2-1 faible, 1-2 défaite forte, 0-2 défaite faible, forfait 2 contre -1,
  interrompu 1-1 et 1-0 sans point. `ClassementService` évalue chaque match selon la phase du match ; l'API de score
  et la fenêtre de saisie (`data-sets-gagnants`) suivent. Choix « Format des matchs » à l'étape Phases (toutes les phases).
- Relectures ergonome et webdesigner intégrées : règle 1R réduite à « 1–1 ou 1–0, sans point » en format court (la
  phrase « comptez ce set pour elle » n'est gardée qu'en 3 sets), format rappelé dans la fenêtre de saisie, aide
  raccourcie, `aria-describedby` sur le fieldset, `fieldset-formulaire`, règlement public complété. 521 tests verts.
- **À décider avec le comité** (signalé par l'ergonome) : valider que 1-1 et 1-0 valent zéro point en format court ;
  le règlement public ne le dit pas encore (seule la phrase sur les 2 sets gagnants a été ajoutée).
- **Durée d'un match (D49)** : réglable pour la journée des finales seulement (choix de l'utilisateur), proposée
  selon le format : 120 minutes en 3 sets gagnants, 75 en 2 sets (`Phase::dureeMatchParDefaut`, SET-05) ; l'agenda
  `.ics` (2 h) et le calendrier d'administration (1 h) gardent leur durée fixe.
- **6f-2 fait, aller-retour (D47)** : `Phase::estAllerRetour` (option `aller_retour`, championnat seulement) ;
  `Championnat` double journées et matchs, `genererMatchs` crée le retour inversé ; l'optimiseur de `PartieService`
  travaille sur 2R rondes dont la seconde moitié est le miroir de la première : la recherche locale bascule
  ensemble un match de l'aller et son retour (`groupesBasculables`, `jumeau`, `basculer`) et échange les rondes avec
  leurs retours, ce qui garde le miroir et l'équilibre domicile / extérieur ; le replacement des matchs à venir fige un
  groupe dès qu'un de ses matchs est joué. Case « Matchs aller et retour » à l'étape Phases, relue par l'ergonome et le
  webdesigner. 538 tests verts + 2 lents (l'aller simple est inchangé, les tests existants passent sans modification).
- **6g fait, barrages de fin de saison (D50)** : le modèle « BLVB avec barrages » compte 5 phases (Barrages de fin de
  saison, code BAR2, après la phase finale, mêmes dates). La phase 2 envoie toute sa poule dans le tableau de son niveau
  (`P2.A#1..-1`) et son avant-dernier / son 2e dans le barrage ; aucune règle vers la saison suivante (choix de
  l'utilisateur : poules de la saison suivante remplies à la main). Changements : `VerificationPassages` compte une
  destination par phase d'arrivée ; `PhaseService` place une équipe une fois par phase d'arrivée et traite une phase
  finale sans règle comme terminale (`estTerminale` : clôture sans répartition, ni avertissement, ni ligne de passages
  dans le wizard) ; un barrage ne propose plus toutes les équipes de la phase précédente comme « à placer » ; le
  barrage « mieux classé » ignore les tableaux d'une phase finale. 545 tests verts + 2 lents.
- **6h fait, mode ajout (D51)** : la saison de production a été créée sans barrages ; plutôt qu'un bouton dédié,
  le wizard rouvre sur une saison lancée en mode ajout (choix de l'utilisateur). `App\Competition\ModeAjout` fige les
  phases commencées (clôturée, match, classement même nul, équipes placées) et toutes celles qui les précèdent ; elles
  restent hors du formulaire de l'étape Phases. `EtapePassages::passagesFiges` : passages d'une phase clôturée ou vers une
  phase figée. Pour ajouter des barrages en production : « Modifier la saison » → « Ajouter des phases » → ajouter une
  phase, type Barrages, la monter entre la phase 1 et la phase 2 (si la phase 2 n'a pas commencé) ou la laisser après la
  phase finale, régler ses dates et ses matchs, puis les passages. 554 tests verts + 2 lents.
- **6i fait, barrages de fin de saison à l'étape Passages (D53)** : `EtapePassages::barragesFinDeSaison` (phase de
  barrages qui suit une phase finale, source = dernière phase de championnat avant elle), `sectionsFinDeSaison`
  (affichage, proposition du bureau, encart « À vérifier »), `appliquerFin` (ne remplace que les règles vers ces
  barrages ; places proposées seulement ; rang réel pour reconnaître la même équipe). La section source → phase finale
  conserve ces règles dans toutes ses branches (simple, détaillé, « Rétablir », « mode simple »). 569 tests verts + 2 lents.
- **Reste du lot 6** : vérifier dans le navigateur la fenêtre de saisie en format court et la mise en place d'une phase de barrages.

### 2026-10-20 : règlement par saison, étape R1

- Branche `reglement` créée depuis `formats-competition-lot6` (tient lieu de lot 11) ; spécifications recueillies auprès
  de l'utilisateur : D55 à D57 ; plan dans `reglement.md`.
- **R1 fait** : entités `Reglement` (une par saison, suppression en cascade avec la saison) et `ReglementVersion`,
  migration `Version20261020164942` ; `App\Reglement\EditionReglement` (nettoyage par `symfony/html-sanitizer`,
  nouvelle dépendance, configuration `config/packages/html_sanitizer.yaml` ; enregistrement, versions, restauration,
  copie) ; `CopieSaison` recopie le texte. Le sanitizer encode « = » : rétabli pour garder les codes courts lisibles.
  `CopieSaison` et `EditionReglement` rendus publics dans le conteneur de test. 574 tests verts.
- Maquettes des éléments calculés (webdesigner), relues par l'ergonome, validées par l'utilisateur : D58,
  `reglement-maquettes.md`.
- **R2 fait** : `App\Reglement\RenduReglement` (ancres, codes courts, codes inapplicables : encart admin, rien au public),
  registre `BlocsReglement` (tag `app.bloc_reglement`), blocs `SchemaPhases` (avec `TableauElimination`, déroulé d'un
  tableau sans rien enregistrer), `BlocBareme`, `BlocDepartage`, `BlocCalendrier` ; mots publics dans `Texte` ; gabarits
  `templates/reglement/` ; styles hors couche dans `app.css` ; `Volley::pointsInterrompus` ; `_alerte.html.twig` accepte
  `role`. Relu par l'ergonome et le webdesigner sur un aperçu des données locales, retours intégrés (voir « Points à
  trancher » de `reglement.md` pour les reports). Piège : un encart ou un élément rendu doit être mis de côté avant de
  chercher d'autres codes courts (sinon le `<code>[…]</code>` de l'encart est remplacé à son tour). 600 tests verts.
- « Phase Finale » écrit « Phase finale » dans les modèles, la recette et les fixtures (choix de l'utilisateur).
- **R3 fait** : `/reglement` rend le règlement de la saison choisie (nettoyé une seconde fois à l'affichage), titre,
  « Mis à jour le … », saison passée signalée, message sans règlement ; sommaire h2/h3 sans les titres des éléments
  calculés (`.js-toc-ignore`), refermé au choix d'un lien sur téléphone ; impression sans en-tête, menu ni pied (toutes les
  pages publiques) ; `POST /set-saison` revient à la page d'origine (champ `retour`, chemin interne seulement). Un règlement
  recopié avec la saison est daté de la copie. Relu par l'ergonome, le webdesigner et la sécurité (aucun constat critique
  ni important ; codes courts ignorés dans les attributs, marqueurs internes retirés, `retour` plus strict). 605 tests verts.
  Un règlement de démonstration est enregistré dans la base locale (saisons 1 et 3), à remplacer par l'import (R5).
- **Recette** : scénario `barrages` (`bin/recette reinitialiser --scenario=barrages`) : saison au modèle « BLVB avec
  barrages », phase 1 et barrages joués et clôturés, phase 2 en cours, phase finale et barrages de fin de saison à venir,
  règlement de démonstration (voir `tests/recette/README.md`).
- Retouches demandées par l'utilisateur : sorties des poules dans l'ordre du classement avec la phase d'arrivée et l'enjeu
  des barrages ; barème en trois colonnes (résultat, points du gagnant, points du perdant) avec une note ; tableaux finaux
  sur fond secondaire ; enchaînement des phases en tête de `[schema_phases]` (qui passe d'une étape à l'autre, calculé
  à partir des règles) ; R6 ajouté au plan (schémas dans un canvas). Piège : `tailwind:build` peut échouer (accolade
  orpheline) en n'affichant qu'une ligne d'erreur ; vérifier « Done » et la présence des nouvelles classes dans
  `var/tailwind/app.built.css`.
- **R4 écrit** (D59, maquettes `reglement-maquettes-r4.md`) : page d'édition, TipTap par l'importmap (chargé à la
  demande : `stimulusFetch: 'lazy'`), aperçu, panneau, images, historique, conflit ; `#article-reglement` devenu
  `.reglement-article` dans `app.css`. 613 tests verts. Relectures sécurité, webdesigner et ergonome **interrompues** (limite
  d'utilisation) : à relancer, ainsi qu'un essai dans un navigateur. Voir « Reste à faire » dans `reglement.md`.
- Tests lancés pendant que l'application est sur la base de recette : `docker compose exec -e DATABASE_URL=…blvb_new… php
  composer tests` (sinon la base de test `blvb_recette_test` n'existe pas).

### 2026-10-21 : règlement, relectures de R4

- Branche locale `reglement` créée depuis `origin/reglement` ; le `vendor` du conteneur n'avait pas
  `symfony/html-sanitizer` (application en erreur) : `composer install` à faire après tout pull de cette branche.
- **Sécurité** : aucun constat critique ni important ; trois mineurs corrigés : texte plus long que la limite du sanitizer
  refusé en 422 (`EditionReglement::TAILLE_MAX`, au lieu d'être tronqué sans prévenir), contenu renettoyé dans la page
  d'édition (comme la page publique), test de la version d'une autre saison (404) ; RGL-17 et RGL-21 complétés.
- **Ergonome** : messages d'erreur en français (réseau coupé, session expirée : plus de « Failed to fetch » ni d'erreur
  d'analyse JSON), raison « cet élément n'accepte pas de paramètre : écrivez [departage]. », conflit en brouillon
  (« enregistrée »), état avec la date et rétabli après un envoi d'image, libellés « Titre de partie / Titre de règle »
  (abrégés sur téléphone), cibles de 44 px, lien inséré sans sélection, focus des onglets, annonce unique, dates en
  toutes lettres, retour d'un brouillon vers l'étape Vérification du wizard.
- **Webdesigner** : listes numérotées, historique en cartes sur téléphone (date en `td`, sans toucher aux autres
  tableaux), lien « Aller aux éléments calculés » avant l'éditeur, état « activé » contrasté, chevrons du panneau, codes
  courts sans capitales, séparateurs verticaux, fenêtres fermées par un clic à l'extérieur, menu actif,
  `reglement_2025.html.twig` avec `.reglement-article`.
- Contre-relectures : ergonome « oui », webdesigner « conforme » ; leurs dernières retouches intégrées. 613 tests verts.
- **Piège** : un commentaire Twig `{# … #}` à l'intérieur d'une balise (`{% include … with { … } %}`) casse le lexer, avec
  une erreur « Unexpected character » signalée bien plus loin dans le fichier.
- **Suite** : essai de l'éditeur dans un navigateur (`reglement.md`, « Reste à faire » 2), puis R5.
- Retours de l'essai (utilisateur) : sommaire de `/reglement` entièrement déplié, barre de position aux couleurs de la charte,
  règles en retrait ; le lien cliqué reste marqué (en bas de page, tocbot marquait le titre précédent, qui ne pouvait
  pas monter en haut de l'écran) ; lien sur une image dans l'éditeur (attribut `href` de l'image, rendu
  `<a href><img></a>`, gardé par le nettoyage, RGL-01), aides « cliquez sur l'image puis sur Lien ». Une description
  d'image `"><script>…` reste du texte encodé (RGL-01). Relu par l'ergonome et le webdesigner.
- **Sommaire, vraie cause** : chaque clic dans le sommaire déclenchait une visite Turbo (page rechargée, sommaire
  reconstruit) ; selon le navigateur, le titre précédent restait marqué. `data-turbo="false"` sur le sommaire ; saut
  natif vers l'ancre (marge `scroll-mt-24`), `headingsOffset` 100. Constaté avec Playwright (Chromium, Firefox, WebKit),
  dans Docker (`mcr.microsoft.com/playwright`, `--network host`). Piège : `scrollIntoView` d'un lien du sommaire collant
  faisait défiler toute la page sous Firefox et Safari.
- **Éditeur, toutes les options de TipTap sauf la couleur** (demande de l'utilisateur) : annuler / rétablir, souligné,
  surligné, exposant, indice, citation, ligne de séparation, saut de ligne, alignements (gauche, centre, droite,
  justifié ; classes `aligne-…`, seules classes gardées par `App\Reglement\ClasseAlignement`), tableaux (barre « Tableau »
  contextuelle), onglet « HTML » (code modifiable, avertissement si des éléments sont retirés), clic sur un lien sans
  navigation. Extensions TipTap 2.11.5 ajoutées à l'importmap (`importmap:install` au déploiement ; Symfony avait
  rétrogradé `@tiptap/pm/state` et `/view` en 2.11.4, remis en 2.11.5). Relu par l'ergonome, le webdesigner et la
  sécurité (images externes et liens d'image filtrés à l'analyse). **Ouvert** : regrouper l'alignement dans un menu
  pour alléger la barre sur téléphone (proposé par l'ergonome).
- Alignements regroupés dans un menu (fait). Pièges : un `|raw` passé en argument d'une macro Twig ne la traverse pas
  (contenu réaffiché échappé) ; daisyUI rend un `summary` à `tabindex` insensible aux clics quand il a le focus
  (`pointer-events: none`), et la carte `overflow-clip` rognait le menu (sens d'ouverture calculé, `placerMenu`).
- Souhaits de l'ergonome faits : images de plus de 2 Mo réduites dans le navigateur avant l'envoi, barre « Image : »
  (description, retirer), codes courts inapplicables marqués « à vérifier » dans l'éditeur (`codes` rendus par l'aperçu
  et l'enregistrement, RGL-18).
- **R5** : `app:reglement:importer` (RGL-23) verse le règlement 2025 (`src/Reglement/Reprise/reglement-2025.html`,
  balisage corrigé, texte inchangé, images avec description) dans la saison en cours ; ancien gabarit supprimé. Essayée
  sur la base de recette : 11 parties, 15 règles, 2 tableaux, 6 images, ancre `#regle-1r`, rien perdu à l'ouverture dans
  l'éditeur. Les `<li><hr></li>` de la règle 1D, que l'éditeur ne garde pas, ont été retirés de la source. Piège : une
  image dans un `<p>` disparaît de l'éditeur (image en bloc). Reste en production : données 2025, puis la commande.
- Checklist de mise en production par l'agent `deploiement` : `deploiement-reglement.md` (composer install avant le
  redémarrage, migrations à l'entrypoint, sauvegarde, secret SMTP du `.env` de `prod`). `prosemirror-view` monté en
  1.42.3 (faille XSS au collage) ; reste une faille moyenne de `@tiptap/core`, corrigée seulement en TipTap 3.
- Mode d'emploi des commandes de test écrit : `tests/README.md`. 614 tests verts + 2 lents. Branche poussée.
- **Suite** (détail dans `reglement.md`, « Reste à faire », et `lots.md`) : R6 (schémas dessinés) ; essais restants dans
  un navigateur ; reste du lot 6 (6c bis, bilan) ; fusion selon D15 ; en production, données 2025 puis import.

### 2026-10-22 : règlement, TipTap 3

- **TipTap monté de 2.11.5 en 3.31.4** (demande de l'utilisateur ; faille moyenne GHSA-cp6q-959q-f8rh de `@tiptap/core`,
  corrigée à partir de 3.30.4) ; `importmap:audit` : aucune vulnérabilité. Changements : le lien et le souligné font
  partie de `StarterKit` (configurés par ses options `link`, `underline`), tableaux importés de `@tiptap/extension-table`
  (`{ Table, TableRow, TableHeader, TableCell }`), imports nommés, `setContent(html, { emitUpdate: true })`,
  `trailingNode: false` (TipTap 3 ajouterait un paragraphe vide en fin de texte).
- **Piège de l'importmap** : le résolveur de jsDelivr n'arrive pas à trouver seul les dépendances de ProseMirror
  (« Unable to find the latest version for package "orderedmap" ») : retirer toutes les entrées `@tiptap/*` et
  `prosemirror-*`, puis les redemander avec leurs versions explicites (dernières versions compatibles avec
  `@tiptap/pm` 3.31.4 et vieilles de deux semaines : `prosemirror-view` 1.42.6, `prosemirror-model` 1.25.12…).
- **Essai dans un navigateur** (Playwright 1.49 dans Docker, `--network host`, base de recette, scénario `barrages`) :
  Chromium, Firefox et WebKit : chargement sans erreur, gras, code court mis en évidence, tableau, annuler / rétablir, lien
  relatif `#regle-1r`, aller-retour par l'onglet HTML, aperçu, enregistrement, rechargement sans paragraphe vide final.
  Piège du test : cliquer un bouton de la barre puis taper aussitôt perd les premières lettres (le focus revient à
  l'éditeur au rafraîchissement suivant) ; attendre un instant. 614 tests verts.

- **Éditeur, trois points ouverts faits** : menu « Plus » sur téléphone (barre en 2 lignes à 375 px : espacement de 2 px,
  « Lien » et « Image » en icône seule, menus resserrés ; « Plus » marqué quand une de ses entrées est active) ; page
  d'édition toujours chargée entièrement (`turbo-visit-control` : une visite Turbo de retour n'est pas annulable, une
  vraie navigation déclenche `beforeunload`) ; « Historique (N) » compte la version publiée ; menus de la barre ouverts
  vers le haut et défilants s'ils manquent de place (`placerMenu`). Piège Safari : une commande choisie dans un menu
  (focus parti de l'éditeur) perdait la mise en forme en attente au retour du focus : focus rendu d'abord, commande à
  l'image suivante. Relu par l'ergonome et le webdesigner ; vérifié dans Chromium, Firefox et WebKit. 614 tests verts.

### 2026-10-22 : lot 6, 6c bis

- Parcours réel dans le navigateur (Playwright, base de recette, scénario `barrages`, scores de la phase 2 saisis en
  base) : clôture de la phase 2 depuis la page de la saison, puis mise en place des barrages de fin de saison (Poules,
  Journées, Matchs), captures ordinateur et téléphone, relues par l'ergonome et le webdesigner.
- **Défaut bloquant trouvé** : `Saison::phases` n'avait pas d'ordre ; la page de la saison affichait les phases dans le
  désordre et ne proposait pas « Clôturer » pour la dernière de la liste (ici la phase 2). `OrderBy(ordre, id)` ajouté.
- Autres corrections : « 0 journée créée » (le générateur de barrages crée les journées sans les ajouter à la collection
  de la poule : compte en base), origine « Vient de la poule A », textes propres à un barrage, article des noms de poule
  dans les messages (`Texte::lePoule`, fonction Twig `le_poule`), message groupé « 3 journées créées pour 3 barrages »,
  séparateurs `divide-base-300` jamais générés (daisyUI importé en CSS : `divide-(--color-base-300)`). BAR-10. 615 tests verts.
- **Ajouter des barrages à une saison existante** : aucune commande ; l'interface le permet (mode ajout, D51 : « Modifier
  la saison » → « Ajouter des phases ») tant que la phase 2 n'a pas commencé pour des barrages entre les phases 1 et 2.

### 2026-10-22 : lot 6, commande d'ajout des barrages et bilan du lot

- **6j** : `app:saison:ajouter-barrages` (service `AjoutBarrages`) ajoute à une saison créée sans eux les barrages du
  modèle (entre les phases et/ou de fin de saison), simulation puis `--force` ; refus expliqués (phase 1 clôturée, phase 2
  commencée, barrages déjà là) ; avertissements (dates qui chevauchent la phase 2, passages « saison suivante » gardés,
  vérification des passages). BAR-11 : résultat identique au modèle. Sur la copie locale de la saison 2026-2027 (phase 1
  clôturée) : barrages entre les phases refusés, barrages de fin de saison proposés.

#### Bilan du lot 6 (formats BLVB)

- **Livré** (6a à 6j) : phase de barrages et modèle « BLVB avec barrages » (D42 à D44), élimination complète avec
  exemptions et petite finale (B1, B2), journée des finales au planning calculé (C3, D45, D46), format de match en 2 ou
  3 sets gagnants et durée d'un match (D48, D49), championnat aller-retour en miroir (A2, D47), barrages de fin de saison
  (D50, D53), wizard en mode ajout pour une saison lancée (D51), mise en place des barrages relue dans le navigateur
  (6c bis), commande d'ajout des barrages (6j). Hors lot pendant le lot : page « Modifier la poule » (D52), menu
  « Supprimer » d'une poule refusé une fois la phase jouée (D54), expéditeur unique des e-mails, rappel des scores.
- **Migrations** du lot : `Version20261015100000`, `Version20261015110000` (options et codes des phases de barrages) ;
  avec `main` : `Version20261017145440` (rappel des scores) ; lot 11 : `Version20261020164942` (règlement). À appliquer
  en production au déploiement seulement.
- **Tests** : 617 verts + 2 lents (branche `reglement`, qui contient le lot 6) ; `composer qualite`, `composer audit` et
  `importmap:audit` propres. Chaque écran relu par l'ergonome et le webdesigner, les formulaires et API par la sécurité ;
  barrages et règlement vérifiés dans Chromium, Firefox et WebKit (Playwright).
- **Format BLVB actuel inchangé** : calendriers, classements et passages d'une saison au modèle « Format BLVB actuel »
  identiques (tests de non-régression des lots 1 à 5 verts sans modification).
- **Reste, hors lot** (`lots.md`, « À faire hors lots », et points ouverts PO1, PO2 de `decisions.md`) : page d'une saison
  recentrée sur le suivi (PO1), forfait général (PO2), contraste des libellés de formulaire, taille du texte des autres
  `fieldset` de l'étape Phases, messages joints par « ; », import Excel lent en production, validation par le comité de
  « 1-1 ou 1-0 sans point » en 2 sets gagnants.
- **Suite** : fusion de `formats-competition-lot6` et `reglement` dans `formats-competition` (D15), puis `main` ; mise en
  production par Francis avec `deploiement-reglement.md` (commande d'ajout des barrages en option). Lot 11 : R6 en
  attente (choix de l'utilisateur), essais restants dans un navigateur (conflit entre deux onglets, historique).

- **Fusion** (22/10/2026, demande de l'utilisateur) : `reglement` (lot 6 + lot 11, R1 à R5) fusionnée dans
  `formats-competition` en avance rapide, sans conflit. `main` n'est pas encore touchée.

