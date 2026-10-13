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
4. **Relectures** par les agents du projet (`.claude/agents/`) avant de clore un lot :
   `ergonome` pour les écrans du wizard et les libellés, `webdesigner` pour la charte,
   `securite` pour les formulaires et les droits.
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
- Branche de travail : `formats-competition`, créée depuis `main` au commit `12b4fd4` ; une
  branche par lot, `formats-competition-lotN`, fusionnée dans `formats-competition` (D15).

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
