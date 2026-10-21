# Wizard de création de saison : méthode de travail

Ce fichier permet à une nouvelle session (humaine ou Claude) de reprendre le chantier sans contexte
préalable. **À lire en premier**, puis dans l'ordre : `decisions.md`, `archive/plan.md`, `lots.md`.

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
| [`lots.md`](lots.md) | Découpage en lots livrables, avec leur état d'avancement (détail des lots 0 à 6 en archive). |
| [`reglement.md`](reglement.md) | Règlement par saison (lot 11) : plan, état, reste à faire. |
| [`deploiement-reglement.md`](deploiement-reglement.md) | Checklist de mise en production (Francis). |
| `methode.md` (ce fichier) | Objectif, méthode, dernière entrée du journal. |
| [`archive/`](archive/) | Historique : journal complet, détail des lots 0 à 6, état des lieux initial (`plan.md`), maquettes d'écrans. À ne lire qu'au besoin. |

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

Mode d'emploi complet des commandes de test : [`tests/README.md`](../../tests/README.md).

Tests automatisés, dans le conteneur `php` (614 tests verts + 2 lents au 2026-10-21, branche `reglement` ; toujours par `composer tests`) :

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

Le journal complet (13/10 au 22/10/2026, lots 0 à 6 et règlement R1 à R5) est dans [`archive/journal.md`](archive/journal.md).
En résumé : lots 1 à 6 et lot 11 (R1 à R5) faits, relus, fusionnés dans `main` le 22/10/2026 ; R6 en attente ; mise en
production par Francis avec `deploiement-reglement.md`. Ajouter ici les nouvelles entrées (date, fait, reste, pièges).

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

## 5. Reprendre le travail sur un autre ordinateur

1. **Récupérer le code** : `git fetch && git checkout formats-competition && git pull`. Le lot 6 et le lot 11 (règlement,
   R1 à R5) y sont fusionnés depuis le 22/10/2026 ; R6 (schémas dessinés) est en attente sur la branche `reglement`.
   Prochaine étape : fusion de `formats-competition` dans `main` (D15), puis mise en production par Francis
   (`deploiement-reglement.md`). Pour un nouveau lot, créer `formats-competition-lotN` depuis `formats-competition` une
   fois le lot précédent fusionné. Après un pull qui change `composer.lock` : `composer install` dans le conteneur
   (sinon l'application ne démarre pas, par exemple sans `symfony/html-sanitizer`).
2. **Environnement** (voir §3, « Environnement » et « Tests ») : `docker compose up -d --build`,
   `docker compose exec php composer install`, droits MySQL des bases de test, `gh auth login` puis
   `gh auth setup-git` pour pousser et suivre la CI (`gh run list`).
3. **Base locale** : `bin/base etat` puis `bin/base aligner` (applique les migrations de la branche ;
   les migrations du chantier, `Version202610131*`, ne vont en production qu'au déploiement).
4. **CSS et assets** : `docker compose exec php php bin/console tailwind:build` après chaque
   changement de `assets/styles/app.css` ou de classes Tailwind. **Ne pas lancer `asset-map:compile`
   en local** : le dossier `public/assets/` qu'il crée fige d'anciens fichiers (icônes énormes, styles
   manquants) ; s'il existe, le supprimer. En production, le déploiement le lance.
5. **Vérifier l'état** : `docker compose exec php composer tests` (614 tests verts attendus ; `php bin/phpunit` lancé
   seul dépasse les 128 Mo de PHP, passer par `composer tests` ; mode d'emploi complet : `tests/README.md`) et
   `docker compose exec php composer qualite`.
6. **Reprendre** : lire `methode.md` (ce fichier), le sommaire de `decisions.md` puis les décisions du sujet traité,
   `lots.md` et `reglement.md` (« Reste à faire ») ; l'archive seulement au besoin. Règles permanentes de l'utilisateur : voir §3 (documentation des tests à
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
