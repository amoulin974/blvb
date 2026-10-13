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

Tests automatisés, dans le conteneur `php` (385 tests verts + 2 lents au 2026-10-13, fin du lot 4) :

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

## 5. Reprendre le travail sur un autre ordinateur

1. **Récupérer le code** : `git fetch && git checkout formats-competition && git pull`. Pour un nouveau
   lot, créer `git checkout -b formats-competition-lot5` depuis `formats-competition`.
2. **Environnement** (voir §3, « Environnement » et « Tests ») : `docker compose up -d --build`,
   `docker compose exec php composer install`, droits MySQL des bases de test, `gh auth login` puis
   `gh auth setup-git` pour pousser et suivre la CI (`gh run list`).
3. **Base locale** : `bin/base etat` puis `bin/base aligner` (applique les migrations de la branche ;
   les migrations du chantier, `Version202610131*`, ne vont en production qu'au déploiement).
4. **CSS et assets** : `docker compose exec php php bin/console tailwind:build` après chaque
   changement de `assets/styles/app.css` ou de classes Tailwind. **Ne pas lancer `asset-map:compile`
   en local** : le dossier `public/assets/` qu'il crée fige d'anciens fichiers (icônes énormes, styles
   manquants) ; s'il existe, le supprimer. En production, le déploiement le lance.
5. **Vérifier l'état** : `docker compose exec php composer tests` (385 tests verts attendus) et
   `docker compose exec php composer qualite`.
6. **Reprendre** : lire dans l'ordre `methode.md` (ce fichier), `decisions.md` (D1 à D34), `lots.md`
   (lot 5) puis `plan.md`. Règles permanentes de l'utilisateur : voir §3 (documentation des tests à
   jour dans le même commit, ergonome et webdesigner avant tout commit d'interface, sécurité pour les
   formulaires, agent `deploiement` avant tout passage en production, pas de décision de conception
   sans l'avoir consignée). Les agents `.claude/agents/*.md` se lancent comme agents généraux qui lisent
   leur fiche si la session ne les charge pas.
7. **Déploiement** (quand le chantier est livré, pas avant) : `git pull`, `docker compose up -d
   --build`, `importmap:install`, `tailwind:build --minify`, `asset-map:compile`, `cache:clear`,
   migrations ; l'agent `deploiement` relit avant la fusion dans `prod`.
