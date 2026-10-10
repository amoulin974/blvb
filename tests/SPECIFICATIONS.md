# Spécifications des tests automatisés et de l'intégration continue — BLVB

> Statut : **proposition**, à valider avant implémentation.
> Périmètre : application Symfony 7.3 / PHP 8.4 / MySQL 8.4 (dépôt `amoulin974/blvb`, branche de départ `admin-charte`).
> Audience : développeurs du projet (mainteneur, Francis pour le déploiement).

---

## 1. Pourquoi, et ce que l'on protège

Le projet n'a aujourd'hui **aucun test automatisé** (`bin/phpunit` → « No tests executed »). Toutes les vérifications ont été manuelles ou ponctuelles (scripts Playwright jetables). Conséquence concrète : la règle `access_control` visait `^/Admin` au lieu de `^/admin` et le tableau de bord admin est resté accessible sans connexion en production, sans que rien ne le signale.

Objectif : qu'une régression sur une règle qui compte **casse la construction avant d'arriver sur `main`**.

Par ordre de gravité, ce que la suite doit protéger :

| Priorité | Domaine | Pourquoi |
|---|---|---|
| P0 | Accès et droits (admin, capitaine, joueur, anonyme) | Données personnelles, intégrité du championnat |
| P0 | Saisie des scores et calcul du classement | Cœur métier ; une erreur fausse le championnat et se voit publiquement |
| P0 | Visibilité des coordonnées des joueurs | Données personnelles (RGPD) |
| P1 | Composition des équipes, suppressions admin | Opérations destructrices ou structurantes |
| P1 | Génération des journées et des matchs, clôture de phase | Calculs complexes, rejoués à chaque saison |
| P2 | Agenda ICS, import Excel, calendrier par gymnase | Fonctions annexes mais utilisées par des tiers (agendas) |
| P2 | Rendu de toutes les pages (fumée) | Twig `strict_variables` : une variable manquante = page 500 |

### Principes

1. **Rapide** : la suite complète tourne en moins de 3 minutes en CI ; les tests lents (optimiseur) sont isolés dans un groupe `@group lent`.
2. **Déterministe** : aucune dépendance à l'ordre d'exécution, à l'heure réelle « au hasard », au réseau, ni à des données existantes. L'optimiseur de calendrier n'utilise pas de hasard (vérifié : aucun `rand`/`shuffle` dans `src/`), ses résultats sont donc comparables d'une exécution à l'autre.
3. **Isolé** : chaque test part d'une base connue et la laisse propre.
4. **Lisible** : un test = un comportement, nommé en français dans la langue du métier (`test_le_capitaine_visiteur_ne_peut_pas_saisir_le_score`). Structure *Étant donné / Quand / Alors* en commentaires si le test dépasse 10 lignes.
5. **Jamais de données réelles** : aucun test ne lit `blvb_new`, `blvb_recette` ni un dump de production. Toutes les données sont fabriquées par les tests (factories). Les adresses e-mail de test sont en `@blvb.test`.
6. **Tout correctif de bogue arrive avec le test qui le reproduit** (le test échoue avant le correctif, passe après).

---

## 2. État des lieux de l'outillage

| Élément | État | Action |
|---|---|---|
| PHPUnit 12 | Installé, `phpunit.dist.xml` présent, `failOnDeprecation/Notice/Warning` déjà activés (bon réglage) | Conserver ; ajouter les suites (§3.1) |
| `symfony/browser-kit`, `css-selector` | Installés | Pour les tests fonctionnels (`WebTestCase`) |
| `zenstruck/foundry` 2.8 | Installé, 10 factories dans `src/Factory`, 3 stories dans `src/Story` | Compléter (§3.3) ; vérifier qu'elles suivent le modèle actuel |
| `doctrine-fixtures-bundle` | Installé (`AppFixtures`) | Non utilisé par les tests (fixtures = jeu de démonstration) |
| Base de test | `blvb_new_test` existe ; suffixe `_test%env(TEST_TOKEN)%` configuré dans `doctrine.yaml` (prêt pour la parallélisation) | Schéma créé par les migrations |
| `dama/doctrine-test-bundle` | Absent | **À ajouter** : chaque test dans une transaction annulée → isolation et vitesse |
| Analyse statique (PHPStan) | Absente | **À ajouter** (§5) — elle aurait signalé `PartieController::apiPartiesAdd` (variable non définie, méthodes inexistantes), supprimé à la main le 10/10/2026 |
| Style de code (PHP-CS-Fixer) | Absent | À ajouter en mode vérification |
| Intégration continue | **Aucune** (pas de `.github/workflows`) | **À créer** (§6) |
| Horloge | La date du jour est lue en direct (`new \DateTime…` dans 3 fichiers de `src/`, `date()` dans 5 gabarits Twig) | Données de test **relatives à maintenant** (§3.4) ; refactorisation vers `ClockInterface` en option |

---

## 3. Architecture de la suite

### 3.1 Arborescence et types de tests

```
tests/
├── bootstrap.php
├── SPECIFICATIONS.md              ← ce document
├── Unitaire/                      ← classes isolées, sans noyau ni base (millisecondes)
│   ├── Service/                      CalendrierAnalyseService, calculs purs extraits…
│   └── Twig/
├── Integration/                   ← KernelTestCase + base de test (services réels, Doctrine réel)
│   ├── Service/                      ClassementService, JourneeService, PartieService, PhaseService,
│   │                                 CalendarIcsGenerator, ContactCapitaineService
│   ├── Repository/                   MembreEquipeRepository (estCapitaine, definirCapitaine…)
│   └── Import/                       import Excel des utilisateurs
├── Fonctionnel/                   ← WebTestCase : requêtes HTTP de bout en bout, sans navigateur
│   ├── Securite/                     matrice des accès, CSRF
│   ├── Front/                        scores, fiche équipe, composition, ICS
│   ├── Admin/                        suppressions, composition admin, saisons
│   └── Fumee/                        toutes les pages s'affichent
├── Support/
│   ├── Story/ChampionnatDeTest.php   ← jeu de données de référence (§3.3)
│   ├── Trait/ConnexionTrait.php      ← se connecter en tant que <profil>
│   └── Assertion/…                   ← assertions métier réutilisables
└── recette/README.md              ← (existant) base de recette manuelle, sans rapport avec la CI
```

`phpunit.dist.xml` déclare trois suites (`unitaire`, `integration`, `fonctionnel`) pour pouvoir lancer `bin/phpunit --testsuite unitaire` en quelques secondes pendant le développement.

| Type | Classe de base | Base de données | Durée cible |
|---|---|---|---|
| Unitaire | `PHPUnit\Framework\TestCase` | non | < 10 ms par test |
| Intégration | `KernelTestCase` + `Factories` + `ResetDatabase` | oui, transaction annulée | < 200 ms |
| Fonctionnel | `WebTestCase` + `Factories` + `ResetDatabase` | oui, transaction annulée | < 500 ms |
| Lent (`#[Group('lent')]`) | selon le cas | oui | exécuté en CI de nuit et avant fusion |

### 3.2 Base de données de test

- Nom : `blvb_new_test` (dérivé automatiquement de `DATABASE_URL` + suffixe). En parallèle (ParaTest), `TEST_TOKEN` donne une base par processus.
- Schéma : `php bin/console doctrine:database:create --env=test --if-not-exists` puis `doctrine:migrations:migrate --env=test -n`. **Les migrations sont la source de vérité** : la CI vérifie aussi `doctrine:schema:validate` (le mapping et le schéma migré concordent).
- Isolation : `dama/doctrine-test-bundle` ouvre une transaction avant chaque test et l'annule après. Foundry (`ResetDatabase`) ne recrée le schéma qu'une fois par exécution.
- Exception : les tests qui vérifient un **rollback** (ex. suppression refusée par une clé étrangère, §4.6) fonctionnent aussi sous transaction (savepoints DBAL) ; à confirmer au lot 0, sinon les marquer `#[Group('sans-transaction')]` et réinitialiser la base pour eux.
- `SaisonListener` (création automatique de phases et poules à la création d'une saison) est **désactivé par défaut** dans les tests (`SaisonListener::$enabled = false`, comme dans la commande de recette) et réactivé uniquement dans le test qui le couvre.

### 3.3 Données de test : factories et « championnat de référence »

**Factories à ajouter** (absentes aujourd'hui) : `JoueurFactory`, `MembreEquipeFactory`, `IndisponibiliteFactory`. Les factories existantes sont revues pour suivre le modèle actuel (ex. `MembreEquipe` a remplacé l'ancien champ capitaine).

**Story `ChampionnatDeTest`** — le plus petit jeu de données qui permet de tout tester. Construit en mémoire à chaque test (rapide grâce aux transactions), jamais copié d'une vraie base :

| Élément | Contenu |
|---|---|
| Saison courante | « Saison test », favorite, barème volontairement **tous différents** pour détecter les inversions : victoire 3–0/3–1 = **5**, victoire 3–2 = **4**, défaite 2–3 = **2**, défaite 1–3/0–3 = **1**, forfait = **−3** |
| Saison précédente | « Saison test précédente », non favorite (pour les règles « par saison ») |
| Phase 1 | championnat, poules A (4 équipes) et B (3 équipes, nombre impair → équipe exempte) |
| Phase 2 | vide, ordre 2 (pour la clôture) |
| Gymnases | G1 (1 créneau mercredi 20:30, 1 terrain, priorité 1), G2 (2 créneaux, 2 terrains), G3 (aucun créneau) |
| Équipes | A1–A4, B1–B3 ; A4 sans gymnase |
| Journées / matchs | générés par les services réels ; certains matchs **dans le passé** (J−7), d'autres **dans le futur** (J+7) |
| Comptes | `admin` (ROLE_ADMIN) · `cap.a1` capitaine de A1 (qui reçoit le match de référence) · `cap.a2` capitaine de A2 (qui se déplace) · `cap.b1` capitaine de B1 (autre poule) · `joueur.a1` simple joueur de A1 · `joueur.a2` simple joueur de A2 · `cap.a1.precedente` capitaine de A1 la saison précédente seulement · `sans.equipe` compte sans fiche joueur |
| Match de référence | `A1 reçoit A2`, joué hier, sans score |

Chaque test qui a besoin d'autre chose le crée lui-même avec les factories (pas de story « fourre-tout »).

### 3.4 Le temps

- Les dates des données sont **relatives à maintenant** (`new \DateTimeImmutable('-7 days')`), jamais en dur (`2026-10-14`). Un test écrit aujourd'hui doit passer dans deux ans.
- Les règles qui dépendent du jour (match passé/à venir, journée en cours, vacances) sont testées avec des écarts nets (±7 jours), jamais « aujourd'hui à minuit » (fuseau, heure d'été).
- Option recommandée à moyen terme : injecter `Symfony\Component\Clock\ClockInterface` dans `FrontController`, `SaisonController` et les services datés, et exposer une fonction Twig `maintenant()` à la place de `date()` ; les tests utiliseraient alors `MockClock`. Non bloquant pour démarrer.

### 3.5 Conventions d'écriture

- Méthodes nommées `test_<comportement_en_francais>` ; attribut `#[TestDox('…')]` pour un rapport lisible (`bin/phpunit --testdox`).
- Matrices (profils × routes, scores valides/invalides) en `#[DataProvider]` avec des clés lisibles (`'capitaine visiteur' => […]`).
- Assertions métier dans `tests/Support` : `assertAccesRefuse($client)`, `assertRedirigeVersConnexion($client)`, `assertScore($partie, 3, 1)`, `assertClassement($poule, ['A1', 'A3', 'A2', 'A4'])`.
- Interdits : `sleep()`, accès réseau, dépendance à l'ordre des tests, `markTestSkipped` sans ticket, tests qui ne vérifient rien (`assertTrue(true)`).
- Un test qui documente une **anomalie connue non encore corrigée** est marqué `#[Group('anomalie')]` et exclu de la CI bloquante tant que l'arbitrage (§8) n'est pas fait — il ne doit pas rester plus d'un lot dans cet état.

---

## 4. Catalogue des tests

Chaque cas a un identifiant stable (repris dans le nom de la classe ou en `#[TestDox]`) pour pouvoir en discuter et le tracer. **Attendu** = comportement exigé. Les écarts constatés dans le code actuel sont signalés par ⚠ et récapitulés en §8.

### 4.1 Sécurité et droits d'accès — `Fonctionnel/Securite` (P0)

**SEC-01 — Garde-fou automatique sur tout `/admin`** *(le test le plus important de la suite)*
Le test lit la table de routage (`router->getRouteCollection()`), retient **toutes** les routes dont le chemin commence par `/admin`, et pour chacune (paramètres remplis avec des identifiants de la story) :
- anonyme → redirection vers `/login` (302) ;
- `joueur.a1` (ROLE_USER) → 403 ;
- `admin` → tout sauf 403/302-vers-login (200, 302 interne, 404 si la ressource n'existe pas, 405 si la méthode ne correspond pas).
Ainsi, une nouvelle route admin oubliée est testée sans écrire de test. Une liste blanche explicite (vide aujourd'hui) documente les éventuelles exceptions.

**SEC-02 — Règle `access_control`** : `config/packages/security.yaml` contient une règle `^/admin` exigeant `ROLE_ADMIN` (test qui lit la configuration chargée, pour empêcher le retour de `^/Admin`).

**SEC-03 — Pages publiques** : `/`, `/equipes`, `/equipe/{id}`, `/calendrier`, `/calendrierlieu`, `/classement`, `/reglement`, `/qui-sommes-nous`, ICS → 200 pour anonyme, joueur, capitaine, admin.

**SEC-04 — Pages réservées aux connectés** : `/mon-profil` → anonyme redirigé vers la connexion ; connecté → 200.

**SEC-05 — Composition côté site** `/equipe/{e}/saison/{s}/composition` (GET et les 4 POST) :

| Profil | Attendu |
|---|---|
| anonyme | refus (redirection connexion ou 403) |
| `joueur.a1` (membre, non capitaine) | 403 |
| `cap.a2` (capitaine d'une autre équipe) | 403 |
| `cap.a1.precedente` (capitaine de A1 la saison précédente seulement) | 403 pour la saison courante, 200 pour la précédente |
| `cap.a1` | 200 |
| `admin` | 200 |

**SEC-06 — Composition côté admin** `/admin/equipe/{e}/saison/{s}/composition` : `cap.a1` → 403 (même s'il a les droits côté site) ; `admin` → 200.

**SEC-07 — Manipulation d'identifiants** : retirer / nommer capitaine un `MembreEquipe` d'une **autre** équipe ou saison en changeant l'identifiant dans l'URL → 404, aucune modification en base.

**SEC-08 — CSRF sur les actions qui modifient** : pour chaque formulaire POST de l'admin (suppressions, clôture, favori, composition), un envoi **sans jeton** ou avec un jeton faux ne modifie rien et affiche « La page a expiré… ». Pour l'API de score : voir SCO-01.

**SEC-09 — Connexion** : identifiants faux → message en français, pas de fuite (« identifiants invalides » sans préciser lequel) ; après connexion admin, le lien « Administration » est présent ; pour un joueur, absent.

**SEC-10 — Déconnexion** : `/logout` termine la session ; une page admin redemande la connexion.

### 4.2 Saisie des scores — `Fonctionnel/Front/SaisieScoreTest` (P0)

Cible : `PUT /front/partie/{id}/api/update`, corps JSON `{scoreReception, scoreDeplacement}`, en-tête `X-CSRF-TOKEN` (jeton `score_update`).

**SCO-01** — Sans en-tête CSRF ou jeton faux → 403, score inchangé.
**SCO-02** — Anonyme (même avec un jeton) → refus, score inchangé. ⚠ *Actuel : 400 « Modification interdite ». Attendu proposé : 403* (§8, A4).
**SCO-03** — Matrice des droits sur le match de référence (A1 reçoit A2) :

| Profil | Attendu |
|---|---|
| `cap.a1` (capitaine de l'équipe qui reçoit, saison du match) | 200 |
| `cap.a2` (capitaine de l'équipe qui se déplace) | refus |
| `cap.b1` (autre poule) | refus |
| `joueur.a1` (membre non capitaine de A1) | refus |
| `cap.a1.precedente` | refus |
| `admin` | 200 |

**SCO-04 — Scores valides** (data provider) : 3–0, 3–1, 3–2, 2–3, 1–3, 0–3 → 200, valeurs enregistrées, réponse `newScore` = « 3 - 1 ».
**SCO-05 — Scores invalides** → 400, score inchangé : 4–0, −1–3 (en chiffres), « a »–3, 2.5–3, champ absent.
**SCO-06 — Scores impossibles au volley** → 400 : 3–3, 0–0, 2–1, 1–1, 0–2 (aucune équipe à 3 sets). ⚠ *Actuel : acceptés* (§8, A2).
**SCO-07 — Forfait** : `F` + vide → réception −1 / déplacement 3, `newScore` = « F - 3 » ; vide + `F` → symétrique ; `F` + `F` → 400.
**SCO-08 — Effacement** : deux champs vides → les deux valeurs à `null`, `newScore` = null, le classement est recalculé (l'équipe perd les points de ce match).
**SCO-09 — Classement mis à jour** après chaque saisie (vérifie la ligne `Classement` des deux équipes).
**SCO-10 — Match à venir** : saisie par un capitaine sur un match futur → ⚠ *règle à arbitrer* (l'interface ne propose « Saisir » qu'après la date ; l'API accepte) (§8, A5).
**SCO-11** — Match inexistant → 404.
**SCO-12 — Interface** (rendu HTML du calendrier) : pour `cap.a1`, match passé sans score → bouton « Saisir » à la place du tiret ; match futur → tiret, pas de bouton ; match avec score → score + crayon ; pour `cap.a2` et anonyme → jamais de bouton ; la balise `<meta name="csrf-token">` n'est présente que pour un utilisateur connecté.

### 4.3 Calcul du classement — `Integration/Service/ClassementServiceTest` (P0)

Tous ces tests utilisent le barème « tous différents » de la story (5/4/2/1/−3) pour qu'une inversion de règle soit visible.

**CLA-01 — Points par résultat** (data provider, du point de vue de chaque équipe) :

| Score (sets gagnés–perdus) | Points attendus |
|---|---|
| 3–0, 3–1 | victoire forte (5) |
| 3–2 | victoire faible (4) |
| 2–3 | défaite forte (2) |
| 1–3, 0–3 | défaite faible (1) |
| match non joué (null) | 0 |
| vainqueur par forfait (3 contre −1) | victoire forte (5) ⚠ *actuel : tombe dans le cas par défaut → défaite faible (1)* (§8, A1) |
| équipe forfait (−1 contre 3) | points de forfait de la saison (−3) ⚠ *actuel : défaite faible (1) ; `points_forfait` n'est jamais utilisé* (§8, A1) |

**CLA-02 — Le barème vient de la saison** : modifier `pointsVictoireForte` de la saison change le total ; deux saisons au barème différent ne se mélangent pas.
**CLA-03 — Domicile et extérieur** : les points d'une équipe additionnent ses matchs à domicile et à l'extérieur.
**CLA-04 — Contexte de poule** : un match d'une autre poule (phase 2, autre poule de la même équipe) n'est pas compté.
**CLA-05 — Sets** : total gagnés / perdus correct ; un forfait (−1) compte pour 0 set, sans retirer de sets.
**CLA-06 — Ordre** : points décroissants ; à égalité, différence de sets décroissante.
**CLA-07 — Confrontation directe** (égalité de points et de différence de sets) : l'équipe qui a **gagné** le match direct est classée **devant**. ⚠ *Le comparateur renvoie +1 quand A a gagné ; avec `usort`, +1 place A **après** B : le vainqueur serait classé derrière* (§8, A3). Le test tranche.
**CLA-08 — Confrontation non jouée** : si le match direct n'a pas de score, aucune préférence (ordre stable). ⚠ *Actuel : `null > null` est faux → compté comme une victoire de l'équipe qui se déplace* (§8, A3).
**CLA-09 — Aller-retour** : si les deux équipes se sont rencontrées deux fois, la règle retenue (cumul des deux matchs, ou sets) est appliquée — ⚠ *à arbitrer ; actuellement seul le premier match trouvé compte.*
**CLA-10 — Positions** : 1 à n sans trou ni doublon ; recalcul **idempotent** (deux appels successifs → même résultat, pas de doublon de lignes `Classement`).
**CLA-11 — Nouvelle équipe ajoutée** à la poule → apparaît au classement avec 0 point au prochain recalcul.

### 4.4 Visibilité des coordonnées — `Integration/Service/ContactCapitaineServiceTest` + `Fonctionnel/Front/FicheEquipeTest` (P0, RGPD)

Règle validée : *on ne voit que les coordonnées du capitaine adverse ; seuls les membres d'une même équipe voient les coordonnées des joueurs non capitaines.*

**CON-01 — Matrice sur la fiche équipe de A1** (rendu HTML, on cherche l'e-mail/téléphone de chaque personne dans la page) :

| Spectateur | Capitaine de A1 | Joueurs non capitaines de A1 |
|---|---|---|
| anonyme | non visible | non visible |
| `joueur.a2` (autre équipe) | non visible | non visible |
| `cap.a2` (capitaine adverse, même poule) | **visible** | non visible |
| `cap.b1` (capitaine d'une autre poule) | ⚠ selon règle `canViewCapitaine` actuelle — à figer par le test | non visible |
| `joueur.a1` (coéquipier) | visible | **visible** |
| `admin` | visible | visible |

**CON-02** — Sur l'accueil et le calendrier, la ligne « Capitaine : … » d'un match n'apparaît que pour le capitaine de l'équipe adverse de ce match (`contact_capitaine_adverse`).
**CON-03** — Coordonnées prises sur la fiche joueur, à défaut sur le compte ; aucune coordonnée → mention neutre, pas de ligne vide.
**CON-04 — Saison** : le capitaine de la saison précédente n'est pas présenté comme capitaine de la saison courante.
**CON-05 — Pas de fuite par l'API ou l'ICS** : l'agenda ICS et les réponses JSON ne contiennent aucune coordonnée personnelle.

### 4.5 Composition des équipes — `Fonctionnel/Front/CompositionTest` et `Fonctionnel/Admin/CompositionAdminTest` (P1)

**COM-01** — Ajouter un joueur existant → membre créé (équipe, saison) ; message nommant l'action.
**COM-02** — Ajouter un joueur déjà présent → refus « fait déjà partie », pas de doublon.
**COM-03** — Créer une fiche avec un e-mail **inconnu** → nouvelle fiche + membre.
**COM-04** — Créer une fiche avec l'e-mail d'un **compte existant** (casse différente : `Cap.A1@BLVB.test`) → fiche liée à ce compte, ou réutilisation de la fiche déjà liée ; pas de seconde fiche.
**COM-05** — Retirer → le membre disparaît, la fiche joueur reste ; message nommant le joueur.
**COM-06** — Désigner capitaine → **exactement un** capitaine pour (équipe, saison) après l'opération ; l'ancien redevient membre.
**COM-07** — Une même personne peut être capitaine de deux équipes différentes (cas `cap.double` de la recette) sans interférence.
**COM-08 — Côté de retour** : chaque action lancée depuis `/admin/…` redirige vers `/admin/…` ; depuis le site, vers le site.
**COM-09** — Le sélecteur « Ajouter un joueur existant » ne propose pas les joueurs déjà dans la composition.
**COM-10 — Admin** : la page admin liste les saisons de l'équipe ; le lien « Gérer la composition » de la fiche équipe admin pointe vers la version admin.

### 4.6 Suppressions dans l'admin — `Fonctionnel/Admin/SuppressionTest` (P1)

Pour chaque entité : la suppression n'est plus proposée dans les listes ; elle se fait depuis la page de modification, via la fenêtre de confirmation (`data-action="confirmation#ouvrir"`).

**SUP-01 — Équipe sans match** → supprimée, message « L'équipe X a été supprimée. », sa composition supprimée, fiches joueurs conservées, retirée des poules.
**SUP-02 — Équipe avec matchs ou classement** → la page de modification affiche le **blocage** (pas de bouton de confirmation) ; un POST forcé avec un jeton valide → message « n'a pas pu être supprimée… Rien n'a été modifié », **pas de 500**, l'équipe et ses matchs existent toujours.
**SUP-03 — Gymnase utilisé** (équipe ou match) → même comportement que SUP-02 ; gymnase libre → supprimé avec ses créneaux.
**SUP-04 — Fiche joueur membre de compositions** → retirée des compositions puis supprimée ; le compte lié éventuel est conservé.
**SUP-05 — Compte utilisateur lié à une fiche** → compte supprimé, fiche conservée avec `user = null` ; demandes de réinitialisation de mot de passe du compte supprimées.
**SUP-06 — Son propre compte** → refusé côté serveur (message), compte intact.
**SUP-07 — Saison** : saison non affichée → supprimée avec phases/poules/journées/matchs. Saison **affichée sur le site** → ⚠ *bloquée seulement dans l'interface ; un POST direct la supprime* (§8, A6).
**SUP-08 — Phase / poule** → supprimées avec leur contenu ; redirection vers la page de la saison.
**SUP-09 — Match** → supprimé ; redirection vers la saison, poule ouverte.
**SUP-10 — Suppressions en masse** (matchs d'une poule, d'une journée ; journées d'une poule) → n'affectent que la cible, jeton exigé.
**SUP-11 — Clôture de phase** → jeton `cloturer<id>` exigé ; sans jeton : message, phase non clôturée.

### 4.7 Génération des journées et des matchs — `Integration/Service` (P1, en partie `lent`)

**JOU-01** — `JourneeService::creerJournees` sur une poule de n équipes : n−1 journées si n pair, n si n impair (équipe exempte) ; numéros 1..k consécutifs.
**JOU-02** — Les journées tombent dans les dates de la phase et **sautent les semaines indisponibles** (indisponibilités de la saison).
**JOU-03** — Phase de finales : journées nommées selon `getNomJournee` (demi-finales, finale, barrage le cas échéant).
**MAT-01 — Round-robin** (`createCalendar`) : chaque paire d'équipes se rencontre **exactement une fois** par phase ; aucune équipe ne joue deux fois la même journée ; avec n impair, une équipe exempte par journée, chacune exactement une fois.
**MAT-02 — Domicile/extérieur** : écart domicile − extérieur ≤ 1 par équipe ; nombre de « breaks » (deux matchs consécutifs au même endroit) ≤ seuil documenté.
**MAT-03 — Date et lieu** : un match a lieu dans le gymnase de l'équipe qui reçoit, le jour de son créneau prioritaire, dans la semaine de la journée (`calculerDateMatch` — testable en **unitaire** : « mercredi » + semaine du 14/09 → mercredi 16/09 20:30).
**MAT-04 — Équipe sans gymnase** : pas d'erreur ; match créé sans lieu (« Gymnase à définir »).
**MAT-05 — Optimiseur** (`creerCalendrierOptimise`, `#[Group('lent')]`) : à capacité suffisante, aucune surcharge de gymnase ; à capacité insuffisante, les surcharges sont **rapportées** dans le rapport (pas d'exception) ; résultat identique sur deux exécutions (déterminisme) ; mémoire < 512 Mo sur 4 poules de 8 (la commande de recette a dû monter à 1 Go : à mesurer).
**MAT-06** — Régénérer une poule (« Optimiser cette poule ») supprime puis recrée ses matchs, sans toucher aux autres poules.

### 4.8 Clôture de phase — `Integration/Service/PhaseServiceTest` (P1)

**PHA-01** — Montées/descentes : les `nbMontee` premiers de la poule de niveau k passent au niveau k−1, les `nbDescente` derniers au niveau k+1 ; les autres restent au même niveau.
**PHA-02** — Poule de niveau le plus haut : pas de montée ; le plus bas : pas de descente.
**PHA-03** — Après clôture : phase marquée close, journées et matchs de la phase suivante créés.
**PHA-04** — Dernière phase (pas de suivante) → message d'erreur, rien ne change.
**PHA-05** — Clôturer deux fois → refusé ou sans effet (à figer).

### 4.9 Agenda ICS — `Integration/Service/CalendarIcsGeneratorTest` + `Fonctionnel/Front/IcsTest` (P2)

**ICS-01** — `GET /calendar/saison/{s}/equipe/{e}.ics` → 200, `Content-Type: text/calendar; charset=utf-8`, nom de fichier en `.ics`.
**ICS-02** — Un `VEVENT` par match **de cette équipe et de cette saison** uniquement (pas la saison précédente).
**ICS-03 — UID stables** : deux générations successives → mêmes UID (sinon les agendas dupliquent les événements).
**ICS-04** — Fuseau `Europe/Paris` déclaré, heure de début correcte en été comme en hiver.
**ICS-05** — Description : phase, poule, journée ; score au format « 3–1 » quand il existe.
**ICS-06 — Cache** : `ETag` identique entre deux appels sans changement ; requête avec `If-None-Match` égal → 304 ; après saisie d'un score → nouvel `ETag`.
**ICS-07** — Lignes pliées à 75 octets (RFC 5545), fins de ligne CRLF.
**ICS-08** — Ancienne route `/calendar/{poule}/{equipe}.ics` toujours servie (abonnements existants).

### 4.10 Import Excel des utilisateurs — `Integration/Import/ImportUtilisateursTest` (P2)

Fichier `.xlsx` généré par le test (PhpSpreadsheet), jamais un fichier réel.
**IMP-01** — Lignes valides → comptes créés (rôle utilisateur), fiches joueurs créées et liées.
**IMP-02** — E-mail d'une fiche existante (casse différente) → fiche liée au nouveau compte, pas de doublon.
**IMP-03** — Fiche déjà liée à un **autre** compte → avertissement dans le rapport, pas de modification de la fiche.
**IMP-04** — E-mail invalide → ligne rejetée avec le motif « Adresse e-mail invalide », les autres lignes importées.
**IMP-05** — Compte déjà existant → non dupliqué.

### 4.11 Calendrier par gymnase — `Unitaire/Service/CalendrierAnalyseServiceTest` (P2)

**GYM-01** — Date sans créneau avec matchs → alerte « hors créneau ».
**GYM-02** — Plus de matchs que de terrains → surcharge ; autant → complet ; moins → « N terrains libres ».
**GYM-03** — Créneau non prioritaire alors qu'un autre jour l'est → alerte priorité.
**GYM-04** — Date pendant des vacances scolaires (indisponibilité) avec match → alerte vacances ; sans match → simple mention.
**GYM-05 (fonctionnel)** — La page `/calendrierlieu` affiche aussi les dates de créneau **libres** de la phase, et le filtre « Seulement les dates à problème » s'appuie sur `data-alerte`.

### 4.12 « Mon équipe » et fonctions Twig — `Integration/Twig` (P2)

**MEQ-01** — `mon_equipe_defaut()` : capitaine connecté → son équipe de la saison courante ; joueur non capitaine, anonyme → `null` (rendu `{'id': null}`, jamais `[]`, qui casse le contrôleur Stimulus).
**MEQ-02** — Capitaine de deux équipes → règle de choix déterministe.
**MEQ-03** — `mon_equipe_choix()` : équipes de la saison, triées alphabétiquement.
**ADM-01** — `admin_saison_courante()` : saison favorite, sinon la plus récente.

### 4.13 Fumée : toutes les pages s'affichent — `Fonctionnel/Fumee` (P2, très rentable)

**FUM-01** — Pour chaque route **GET** de l'application (lue dans le routeur, paramètres remplis depuis la story), avec le profil adéquat (anonyme pour le site, admin pour `/admin`) : statut 200 (ou 302 attendu), **aucune erreur Twig**, aucune « Notice/Deprecation ».
Couvre d'un coup les erreurs de gabarit (variables manquantes en `strict_variables`, gymnase absent → page 500 corrigée en octobre 2026).
**FUM-02** — Même parcours sur une saison **vide** (sans phase) et sur une base **sans aucune saison** (`/aucune-saison`).

### 4.14 Bout en bout dans un navigateur — `tests/e2e` (Playwright, lot 5, facultatif)

Réservé à ce que le PHP ne peut pas voir (JavaScript Stimulus) ; 5 à 8 scénarios maximum, exécutés en CI de nuit :
**E2E-01** saisie d'un score dans la fenêtre (mobile et ordinateur) ; **E2E-02** « Mon équipe » (choix, surlignage, onglet ouvert) ; **E2E-03** fenêtre de confirmation admin (Annuler a le focus, Échap ferme, rien n'est supprimé) ; **E2E-04** listes admin (recherche, compteur, tri clavier) ; **E2E-05** filtre gymnase pré-sélectionné par « Mon équipe ».

---

## 5. Analyse statique et qualité du code

| Outil | Réglage de départ | Cible | Pourquoi |
|---|---|---|---|
| **PHPStan** + `phpstan-symfony` + `phpstan-doctrine` | niveau 5, **baseline** générée pour l'existant | niveau 6 puis 8, baseline vidée lot par lot | Aurait détecté `apiPartiesAdd` (variable non définie, méthodes inexistantes) ; détecte les `null` non gérés (gymnase absent) |
| **PHP-CS-Fixer** (règles `@Symfony`) | mode vérification (`--dry-run --diff`) sur les fichiers modifiés seulement | tout le dépôt | Homogénéité ; ne pas reformater tout le dépôt d'un coup (diffs illisibles) |
| `bin/console lint:twig templates` | bloquant | — | Syntaxe des gabarits |
| `bin/console lint:yaml config translations` | bloquant | — | |
| `bin/console lint:container` | bloquant | — | Injection de dépendances |
| `doctrine:schema:validate` | bloquant | — | Mapping et migrations concordent |
| `composer validate --strict`, `composer audit` | bloquant | — | Dépendances vulnérables |
| `bin/console importmap:audit` | avertissement | bloquant | Paquets JS vulnérables |
| Couverture (pcov) | publiée, non bloquante | seuils par fichier critique (§6.3) | |
| Infection (tests de mutation) | — | sur `ClassementService` et l'API de score | Vérifie que les tests détectent vraiment les changements de règle |

Raccourcis Composer (exécutables à l'identique en local et en CI) :

```
composer qualite   → lint:twig, lint:yaml, lint:container, phpstan, php-cs-fixer --dry-run
composer tests     → bin/phpunit (hors groupe lent)
composer tests:tout→ bin/phpunit (avec groupe lent)
```

---

## 6. Intégration continue (GitHub Actions)

### 6.1 Déclencheurs

- Chaque **push** sur toute branche et chaque **pull request** vers `main` : jobs `qualite` et `tests`.
- **Nuit** (cron) sur `main` : `tests:tout` (groupe lent) + `composer audit` + E2E éventuels.
- Manuel (`workflow_dispatch`).

### 6.2 Jobs

```
qualite (≈ 1 min)                      tests (≈ 3 min)
├─ PHP 8.4 (shivammathur/setup-php)    ├─ service mysql:8.4 (healthcheck)
├─ cache Composer                      ├─ PHP 8.4 + extensions (pdo_mysql, intl, zip, gd) + pcov
├─ composer install --no-progress      ├─ composer install
├─ composer validate / audit           ├─ doctrine:database:create --env=test
├─ lint:twig / yaml / container        ├─ doctrine:migrations:migrate --env=test -n
├─ phpstan (baseline)                  ├─ doctrine:schema:validate --env=test
└─ php-cs-fixer --dry-run              ├─ tailwind:build (les gabarits référencent le CSS compilé)
                                       ├─ bin/phpunit --testsuite unitaire,integration,fonctionnel
                                       │      --log-junit + couverture clover
                                       └─ artefacts : rapport JUnit, couverture, var/log/test.log en cas d'échec
```

- Versions **identiques à la production** : PHP 8.4 (image `dunglas/frankenphp:1-php8.4`), MySQL 8.4. Variante possible plus tard : exécuter les tests **dans l'image Docker du projet** (cible `frankenphp_dev`) pour supprimer tout écart.
- Secrets : aucun nécessaire (pas d'envoi d'e-mail réel : `MAILER_DSN=null://null` en test).
- Temps cible total < 5 min ; au-delà, paralléliser avec ParaTest (`TEST_TOKEN` déjà prévu).

### 6.3 Règles de blocage

- **Protection de `main`** (réglage GitHub) : fusion uniquement via pull request, jobs `qualite` et `tests` verts obligatoires, branche à jour.
- Pas de seuil de couverture global au départ (il pousse à écrire des tests inutiles). Seuils **par fichier critique** à partir du lot 3 : `ClassementService`, `ContactCapitaineService`, `EquipeCompositionController`, `SuppressionTrait`, l'API de score → ≥ 90 % des lignes.
- Un test instable (*flaky*) est corrigé ou supprimé dans la semaine ; jamais relancé « jusqu'à ce que ça passe ».

### 6.4 Déploiement (hors périmètre immédiat, à discuter avec Francis)

La CI produit un statut ; le déploiement reste manuel. Étape suivante possible : un job qui construit l'image Docker de production sur `main` et vérifie qu'elle démarre (`/` répond 200) — sans déployer.

---

## 7. Plan de mise en œuvre

| Lot | Contenu | Livrable vérifiable | Charge estimée |
|---|---|---|---|
| **0 — Socle** | `dama/doctrine-test-bundle`, suites PHPUnit, factories manquantes, story `ChampionnatDeTest`, traits de connexion, workflow GitHub Actions (jobs `qualite` minimal + `tests`), raccourcis Composer | CI verte sur un premier test trivial ; `composer tests` fonctionne en local dans Docker | 0,5 j |
| **1 — Accès** | SEC-01 à SEC-10, FUM-01/02 | La faille `^/Admin` réintroduite volontairement fait échouer la CI | 0,5 j |
| **2 — Scores et classement** | SCO-*, CLA-* ; arbitrage des anomalies A1–A5 puis corrections | Barème testé ; anomalies corrigées ou explicitement acceptées | 1 j (+ corrections) |
| **3 — Données personnelles et admin** | CON-*, COM-*, SUP-* ; seuils de couverture par fichier | Matrice de visibilité figée par les tests | 1 j |
| **4 — Calculs et fonctions annexes** | JOU-*, MAT-*, PHA-*, ICS-*, IMP-*, GYM-*, MEQ-* ; job de nuit | Génération de calendrier couverte | 1,5 j |
| **5 — Durcissement** | PHPStan niveau 6+, PHP-CS-Fixer étendu, Infection sur le classement, E2E Playwright | Baseline PHPStan réduite | 1 j |

Ordre conseillé : 0 → 1 → 2 (les lots 1 et 2 couvrent les risques P0). Chaque lot = une pull request relue, CI verte.

---

## 8. Anomalies relevées pendant l'analyse — à arbitrer avant le lot 2

Ces points ont été trouvés en lisant le code pour écrire ces spécifications. **Aucun n'a été modifié.** Le test correspondant fixera la règle choisie.

| # | Où | Constat | Proposition |
|---|---|---|---|
| A1 | `ClassementService::pointsPourMatch` | Forfait : le vainqueur (3 contre −1) tombe dans le cas par défaut et reçoit les points d'une **défaite faible** ; l'équipe forfait reçoit aussi une défaite faible ; `Saison::points_forfait` (−3 par défaut) n'est **jamais utilisé** | Vainqueur = victoire forte ; forfait = `points_forfait`. À confirmer avec le règlement |
| A2 | `FrontController::api_score_update` | Scores impossibles au volley acceptés (3–3, 0–0, 2–1…) : seule la plage 0–3 est contrôlée | Exiger exactement une équipe à 3 sets et l'autre entre 0 et 2 |
| A3 | `ClassementService::confrontationDirecte` | Convention de signe vraisemblablement inversée pour `usort` (le vainqueur du match direct serait classé derrière) ; un match direct non joué est compté comme une victoire de l'équipe qui se déplace ; en aller-retour, seul le premier match compte | Vérifier par CLA-07/08 puis corriger ; préciser la règle aller-retour selon le règlement |
| A4 | API de score | Refus de droits renvoyé en **400** (au lieu de 403) | 403 pour un refus de droits, 400 pour une donnée invalide |
| A5 | API de score | L'API accepte un score sur un match **à venir** (l'interface ne le propose qu'après la date) | Refuser pour un capitaine, autoriser pour un admin (correction d'erreur de date) |
| A6 | `SaisonController::delete` | La saison affichée sur le site n'est protégée que dans l'interface ; un POST direct la supprime | Refuser aussi côté serveur |
| A7 | `/register` | L'inscription libre est active (route accessible) alors que les liens sont commentés dans les menus | Décider : fermer la route ou l'assumer (avec vérification d'e-mail) |

---

## 9. Définition de « terminé » pour une modification

Une pull request est fusionnable quand :
1. la CI est verte (qualité + tests) ;
2. tout nouveau comportement a au moins un test, tout correctif de bogue a le test qui le reproduisait ;
3. aucun test n'est ignoré sans justification ;
4. la baseline PHPStan n'a pas grossi ;
5. les données de test sont fabriquées par le test (aucune donnée réelle, aucune adresse hors `@blvb.test`).
