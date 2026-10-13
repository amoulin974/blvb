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
4. **Relectures** par les agents du projet (`.claude/agents/`) :
   - **dès qu'une interface graphique est réalisée ou modifiée** (écran, formulaire, gabarit Twig,
     libellés affichés), l'`ergonome` **et** le `webdesigner` interviennent avant le commit ;
     consigne de l'utilisateur, sans exception ;
   - `securite` pour les formulaires, les API et les droits.
5. **Fin de session** : mettre à jour l'état du lot dans `lots.md` et ajouter une entrée au journal
   ci-dessous (ce qui est fait, ce qui reste, points bloquants).

### Conventions du projet

- Tout est en français : code métier, libellés, messages de commit.
- Messages de commit sur le modèle de l'historique : `domaine : description en minuscules`
  (ex. `tests : lot 0, socle des tests et intégration continue`).
- Symfony 7.4, Doctrine, Twig, Tailwind/daisyUI ; une migration Doctrine par changement de schéma.

### Environnement

- Le dépôt est dans WSL (`~/projects/blvb`). Depuis Windows, git refuse le chemin
  `\\wsl.localhost\…` (« dubious ownership ») : lancer git **dans WSL**, par exemple
  `wsl.exe -d Ubuntu -- bash -c 'cd ~/projects/blvb && git status'`.
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

Tests automatisés, dans le conteneur `php` (300 tests verts au 2026-10-13) :

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
  309 tests verts, tests lents compris (`composer tests:tout`).
- Lot 1 terminé, sans interface graphique (pas de relecture ergonome / webdesigner nécessaire).
- **Suite** : fusionner `formats-competition-lot1` dans `formats-competition`, puis lot 2
  (classement paramétrable) sur `formats-competition-lot2`.
