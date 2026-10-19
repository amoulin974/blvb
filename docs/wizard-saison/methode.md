# Wizard de création de saison : méthode de travail

Ce fichier permet à une nouvelle session (humaine ou Claude) de reprendre le chantier sans contexte
préalable. **À lire en premier**, puis dans l'ordre : `decisions.md`, `plan.md`, `lots.md`.

---

## 1. Objectif

Permettre à l'administrateur de **créer une nouvelle saison avec n'importe quel format de compétition**
décrit dans [`docs/formats-competition.md`](../formats-competition.md) (parties A à E), au moyen d'un
wizard dans le back-office.

Aujourd'hui, la structure d'une saison est figée :

- les phases et poules viennent de `config/app/saison.yaml`, créées automatiquement par
  `SaisonListener` → `CompetitionCreator` à l'enregistrement d'une saison ;
- seuls deux types de phase existent (`PhaseType::CHAMPIONNAT`, `PhaseType::FINALE`) ;
- les montées et descentes sont calculées en dur (niveau ± 1, `setNbMonteeDefaut(2)`) dans
  `PhaseService::cloturerEtBasculer` ;
- le départage du classement est codé dans `ClassementService::mettreAJourClassementPoule`.

La cible : **une saison est un ensemble de paramètres** (phases, formats, règles de passage, barème,
départage), saisis dans le wizard et appliqués par l'application. Le règlement change chaque année :
ces règles doivent être des données, pas du code.

L'outil sert à **créer** une saison : ce n'est pas au bureau de trancher entre les formats ici,
l'application doit savoir les proposer tous, et l'administrateur choisit au moment de la création.

## 2. Documents du chantier

| Fichier | Rôle |
|---|---|
| [`../formats-competition.md`](../formats-competition.md) | Catalogue des formats (spécification fonctionnelle). Partie G : esquisse du paramétrage. |
| [`decisions.md`](decisions.md) | Choix de conception, avec leur justification. Une décision prise n'est pas rediscutée sans raison nouvelle. |
| [`plan.md`](plan.md) | État des lieux du code, modèle de données cible, impacts sur les services et le wizard. |
| [`lots.md`](lots.md) | Découpage en lots livrables, avec leur état d'avancement. |
| `methode.md` (ce fichier) | Objectif, méthode, journal de ce qui a été fait. |

## 3. Méthode

1. **Pas de code sans décision.** Si un point de conception n'est pas dans `decisions.md`, le poser
   à l'utilisateur, puis l'y consigner (« Décision », « Pourquoi », date).
2. **Un lot à la fois**, dans l'ordre de `lots.md`. Chaque lot est livrable seul : l'application reste
   utilisable et les saisons existantes continuent de fonctionner.
3. **Tests d'abord pour la logique** (notation des places, règles de passage, départage, générateurs
   de calendrier) : tests unitaires dans `tests/Unitaire`, tests d'intégration dans
   `tests/Integration`. Les tests existants doivent rester verts.
4. **Documentation des tests à jour à chaque modification de test** (ajout, suppression,
   déplacement, renommage, changement de ce qui est vérifié) : dans le même commit, mettre à jour
   `tests/SPECIFICATIONS.md` (arborescence §3.1, catalogue §4 avec ses identifiants, plan §7 avec
   le nombre de tests, vérifié en relançant la suite) et, si les données ou scénarios de recette
   changent, `tests/recette/README.md`. Consigne de l'utilisateur, sans exception.
5. **Relectures** par les agents du projet (`.claude/agents/`) :
   - **dès qu'une interface graphique est réalisée ou modifiée** (écran, formulaire, gabarit Twig,
     libellés affichés), l'`ergonome` **et** le `webdesigner` interviennent avant le commit ;
     consigne de l'utilisateur, sans exception ;
   - `securite` pour les formulaires, les API et les droits.
   - Si les agents du projet ne sont pas chargés dans la session (cas d'une session ouverte sur le
     chemin `\\wsl.localhost\…`), lancer un agent général en lui demandant de lire et suivre
     strictement la fiche `.claude/agents/<agent>.md`.
6. **Fin de session** : mettre à jour l'état du lot dans `lots.md` et ajouter une entrée au journal
   ci-dessous (ce qui est fait, ce qui reste, points bloquants).

### Conventions du projet

- Tout est en français : code métier, libellés, messages de commit.
- Messages de commit sur le modèle de l'historique : `domaine : description en minuscules`
  (ex. `tests : lot 0, socle des tests et intégration continue`).
- Symfony 7.4, Doctrine, Twig, Tailwind/daisyUI ; une migration Doctrine par changement de schéma.

### Environnement

- Le dépôt est dans WSL (`~/projects/blvb`). Depuis Windows, git refuse le chemin
  `\\wsl.localhost\…` (« dubious ownership ») : lancer git **dans WSL**, par exemple
  `wsl.exe -d Ubuntu -- bash -c 'cd ~/projects/blvb && git status'`. L'identité git de WSL est
  configurée (`~/.gitconfig` : `amoulin974`) ; `gh` est installé dans WSL : après
  `gh auth login` et `gh auth setup-git`, il sert au push et à suivre la CI (`gh run list`).
- **Base locale et branches** : après un changement de branche, `bin/base etat` puis `bin/base aligner`
  (sauvegarde, annulation des migrations d'une autre branche, application de celles de la branche ;
  voir README, « Changer de branche »). `bin/base rappel` installe un rappel git. Les migrations des
  lots ne s'appliquent en production qu'au déploiement.
- Les commandes `docker compose exec` lancées depuis un script lisent l'entrée standard : ajouter
  `-T` et `< /dev/null`, sinon elles avalent la suite du script.
- Branche de travail : `formats-competition`, créée depuis `main` au commit `12b4fd4` ; une
  branche par lot, `formats-competition-lotN`, fusionnée dans `formats-competition` (D15).

### Tests

Deux bases, deux usages, toutes deux séparées de la base de développement `blvb_new` :

| | Tests automatisés (PHPUnit) | Recette (tests manuels) |
|---|---|---|
| Base | `blvb_new_test`, recréée par Foundry au lancement | `blvb_recette`, copie de `blvb_new` + saisons de test |
| Données | fabriquées par les tests (factories, stories) | `app:recette:charger`, scénarios `en-cours`, `phase1-terminee`, `vierge` |
| Référence | `tests/SPECIFICATIONS.md` | `tests/recette/README.md` |

Tests automatisés, dans le conteneur `php` (455 tests verts + 2 lents au 2026-10-16, lot 6e ; toujours par `composer tests`) :

```
docker compose exec php composer tests          # tout, hors tests lents (≈ 1 min)
docker compose exec php composer tests:tout     # avec les tests lents (groupe « lent »)
docker compose exec php php bin/phpunit --testsuite unitaire   # rapide, pendant le développement
```

(`bin/phpunit` n'est pas exécutable dans le conteneur : toujours le lancer par `php bin/phpunit`.)

Prérequis, une fois par environnement : après un pull qui change `composer.lock`,
`docker compose exec php composer install` ; l'utilisateur MySQL `symfony` doit avoir les droits
sur les bases de test (la CI, elle, utilise `root`) :

```
docker compose exec -T database mysql -uroot -prootpassword \
  -e "GRANT ALL PRIVILEGES ON \`blvb\_new\_test%\`.* TO 'symfony'@'%';"
```

Recette : `bin/recette reinitialiser [--scenario=…]`, puis `bin/recette utiliser` ; retour avec
`bin/recette dev`. `app:recette:charger` (`ChargerBaseRecetteCommand`) construit la structure de
saison en dur : elle suivra le nouveau modèle, avec des scénarios aux formats variés (lots 3 à 6).

## 4. Journal

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
- **Reste du lot 6** : 6f-2 aller-retour en miroir (D47) ; durée de match proposée à 60 min en 2 sets pour la journée
  des finales ; vérifier dans le navigateur la fenêtre de saisie en format court et la mise en place d'une phase de barrages.

## 5. Reprendre le travail sur un autre ordinateur

1. **Récupérer le code** : `git fetch && git checkout formats-competition-lot6 && git pull` (lot en cours, pas
   encore fusionné dans `formats-competition`). Pour un nouveau lot, créer `formats-competition-lotN` depuis
   `formats-competition` une fois le lot précédent fusionné.
2. **Environnement** (voir §3, « Environnement » et « Tests ») : `docker compose up -d --build`,
   `docker compose exec php composer install`, droits MySQL des bases de test, `gh auth login` puis
   `gh auth setup-git` pour pousser et suivre la CI (`gh run list`).
3. **Base locale** : `bin/base etat` puis `bin/base aligner` (applique les migrations de la branche ;
   les migrations du chantier, `Version202610131*`, ne vont en production qu'au déploiement).
4. **CSS et assets** : `docker compose exec php php bin/console tailwind:build` après chaque
   changement de `assets/styles/app.css` ou de classes Tailwind. **Ne pas lancer `asset-map:compile`
   en local** : le dossier `public/assets/` qu'il crée fige d'anciens fichiers (icônes énormes, styles
   manquants) ; s'il existe, le supprimer. En production, le déploiement le lance.
5. **Vérifier l'état** : `docker compose exec php composer tests` (455 tests verts attendus ; `php bin/phpunit` lancé seul dépasse les 128 Mo de PHP, passer par `composer tests`) et
   `docker compose exec php composer qualite`.
6. **Reprendre** : lire dans l'ordre `methode.md` (ce fichier), `decisions.md` (D1 à D48), `lots.md`
   (lot 6) puis `plan.md`. Règles permanentes de l'utilisateur : voir §3 (documentation des tests à
   jour dans le même commit, ergonome et webdesigner avant tout commit d'interface, sécurité pour les
   formulaires, agent `deploiement` avant tout passage en production, pas de décision de conception
   sans l'avoir consignée). Les agents `.claude/agents/*.md` se lancent comme agents généraux qui lisent
   leur fiche si la session ne les charge pas.
7. **Déploiement** (quand le chantier est livré, pas avant) : **on ne touche jamais à la branche
   `prod`, Francis seul fusionne dedans.** Nous arrêtons à `main` ; l'agent `deploiement` relit et
   rend la checklist à transmettre à Francis : `git pull`, `docker compose up -d --build`,
   `importmap:install`, `tailwind:build --minify`, `asset-map:compile`, `cache:clear`, migrations.
   Le serveur monte le dossier dans le conteneur (`.:/app`) : `composer install` **dans le conteneur** à chaque
   changement de `composer.lock`, puis `cache:clear` et `docker compose restart php`.
   La branche `prod` garde son propre `.env` (secrets du serveur) : à chaque fusion, conflit attendu si `main` a
   changé `.env` ; garder celui de `prod` et y reporter les nouvelles variables (ex. `MAILER_FROM`).
