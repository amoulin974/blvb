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
| P0 | Accès et droits (admin, responsable de club, capitaine, joueur, anonyme) | Données personnelles, intégrité du championnat |
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
│   ├── Competition/                  notation des places, règles du sport et barème (Sport/), choix du format (Format/),
│   │                                 critères de départage (Classement/) — §4.3 et §4.16
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
│   ├── TestIntegration.php           ← base KernelTestCase (Factories, ResetDatabase)
│   └── TestFonctionnel.php           ← base WebTestCase : client, connecter('<compte>'), assertions d'accès
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
- En local (Docker), l'utilisateur MySQL `symfony` n'a de droits que sur `blvb_new` : Foundry ne peut pas créer la base de test et arrête la suite au premier test d'intégration (« could not reset the database »). À faire une fois par environnement (la CI, elle, utilise `root`) :
  `docker compose exec -T database mysql -uroot -prootpassword -e "GRANT ALL PRIVILEGES ON \`blvb\_new\_test%\`.* TO 'symfony'@'%';"`
- Après un `git pull` qui modifie `composer.lock` : `docker compose exec php composer install`, sinon le `vendor` du conteneur est en retard (symptôme : « Cannot bootstrap extension … DAMA\DoctrineTestBundle »).
- Isolation : `dama/doctrine-test-bundle` ouvre une transaction avant chaque test et l'annule après. Foundry (`ResetDatabase`) ne recrée le schéma qu'une fois par exécution.
- Exception : les tests qui vérifient un **rollback** (ex. suppression refusée par une clé étrangère, §4.6) fonctionnent aussi sous transaction (savepoints DBAL) ; à confirmer au lot 0, sinon les marquer `#[Group('sans-transaction')]` et réinitialiser la base pour eux.
- Les tests construisent eux-mêmes phases et poules. (`SaisonListener`, qui les créait automatiquement à l'enregistrement d'une saison et que les tests désactivaient, a été supprimé au lot 4 des formats de compétition : une saison se crée avec le wizard.)

### 3.3 Données de test : factories et « championnat de référence »

**Factories** (`src/Factory`, partagées avec les données de démonstration) : `JoueurFactory`, `MembreEquipeFactory` et `IndisponibiliteFactory` ajoutées au lot 0 ; les factories existantes ont reçu des valeurs par défaut valides et réalistes (e-mails `@blvb.test`, créneau mercredi 20:30, phase non close…).

**Story `ChampionnatDeTest`** — le plus petit jeu de données qui permet de tout tester. Construit en mémoire à chaque test (rapide grâce aux transactions), jamais copié d'une vraie base :

| Élément | Contenu |
|---|---|
| Saison courante | « Saison test », favorite, barème volontairement **tous différents** pour détecter les inversions : victoire 3–0/3–1 = **5**, victoire 3–2 = **4**, défaite 2–3 = **2**, défaite 1–3/0–3 = **1**, forfait = **−3** |
| Saison précédente | « Saison test précédente », non favorite (pour les règles « par saison ») |
| Phase 1 | championnat, poules A (4 équipes) et B (3 équipes, nombre impair → équipe exempte) |
| Phase 2 | vide, ordre 2 (pour la clôture) |
| Gymnases | G1 (1 créneau mercredi 20:30, 1 terrain, priorité 1), G2 (2 créneaux, 2 terrains), G3 (aucun créneau) |
| Équipes | A1–A4, B1–B3 ; A4 sans gymnase. Noms enregistrés sans club (« EQUIPE A1 »), affichés avec l'abréviation du club (« EQUIPE A1 (CTA) ») |
| Clubs | « Club test A1-B1 » (CTA) : A1 et B1 (deux poules), gymnases G1 et G2, responsable `resp.club` · « Club test voisin » (CTV) : A3, gymnase G1 (partagé) · A2, A4, B2, B3 sans club |
| Journées / matchs | créés explicitement (indépendants du générateur, testé à part au lot 4). Poule A : journée 1 il y a deux semaines (A2–A1 1–3 : **une équipe du club CTA à l'extérieur, score saisi** ; A4–A3 3–0), journée 2 la semaine passée (A1–A2 hier **sans score** = match de référence, A3–A4 3–1), journée 3 la semaine prochaine (A2–A3, A4–A1). Poule B : journée 1 (B1–B2 3–2) |
| Comptes | `admin` (ROLE_ADMIN) · `cap.a1` capitaine de A1 (qui reçoit le match de référence) · `cap.a2` capitaine de A2 (qui se déplace) · `cap.b1` capitaine de B1 (autre poule) · `joueur.a1` simple joueur de A1 · `joueur.a2` simple joueur de A2 · `cap.a1.precedente` capitaine de A1 la saison précédente seulement · `sans.equipe` compte sans fiche joueur · `resp.club` responsable du club CTA **pour la saison courante**, sans fiche joueur (rôle déduit ROLE_RESPONSABLE_CLUB) |
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

**SEC-01 — Garde-fou automatique sur tout `/admin`** *(le test le plus important de la suite)* ✅ *`Securite/AccesAdminTest`* — vérifié : réintroduire la faille `^/Admin` fait échouer 5 tests sur 6. Le responsable de club est ajouté aux profils refusés. Pour l'admin, seules les routes GET sont parcourues (les actions sont couvertes par SEC-08).
Le test lit la table de routage (`router->getRouteCollection()`), retient **toutes** les routes dont le chemin commence par `/admin`, et pour chacune (paramètres remplis avec des identifiants de la story) :
- anonyme → redirection vers `/login` (302) ;
- `joueur.a1` (ROLE_USER) → 403 ;
- `admin` → tout sauf 403/302-vers-login (200, 302 interne, 404 si la ressource n'existe pas, 405 si la méthode ne correspond pas).
Ainsi, une nouvelle route admin oubliée est testée sans écrire de test. Une liste blanche explicite (vide aujourd'hui) documente les éventuelles exceptions.

**SEC-02 — Règle `access_control`** ✅ : `config/packages/security.yaml` contient une règle couvrant `/admin` exigeant `ROLE_ADMIN` (test qui lit la configuration chargée, pour empêcher le retour de `^/Admin`). Accepter `^/admin` (main) et `^/(blvb/)?admin` (branche prod, servie sous le préfixe /blvb).

**SEC-03 — Pages publiques** ✅ *`Securite/PagesPubliquesTest`* : `/`, `/equipes`, `/equipe/{id}`, `/calendrier`, `/calendrierlieu`, `/classement`, `/reglement`, `/qui-sommes-nous`, `/clubs`, `/club/{id}`, ICS → 200 pour anonyme, joueur, capitaine, responsable de club, admin.

**SEC-04 — Pages réservées aux connectés** ✅ : `/mon-profil` → anonyme redirigé vers la connexion ; connecté → 200.

**SEC-05 — Composition côté site** `/equipe/{e}/saison/{s}/composition` (GET et les 4 POST) ✅ *`Securite/CompositionAccesTest`* (le responsable de club est aussi refusé : il ne gère pas les compositions) :

| Profil | Attendu |
|---|---|
| anonyme | refus (redirection connexion ou 403) |
| `joueur.a1` (membre, non capitaine) | 403 |
| `cap.a2` (capitaine d'une autre équipe) | 403 |
| `cap.a1.precedente` (capitaine de A1 la saison précédente seulement) | 403 pour la saison courante, 200 pour la précédente |
| `cap.a1` | 200 |
| `admin` | 200 |

**SEC-06 — Composition côté admin** ✅ *(couvert par SEC-01)* `/admin/equipe/{e}/saison/{s}/composition` : `cap.a1` → 403 (même s'il a les droits côté site) ; `admin` → 200.

**SEC-07 — Manipulation d'identifiants** ✅ : retirer / nommer capitaine un `MembreEquipe` d'une **autre** équipe ou saison en changeant l'identifiant dans l'URL → 404, aucune modification en base.

**SEC-08 — CSRF sur les actions qui modifient** ✅ *`Securite/JetonsAdminTest`* (20 actions de l'admin, sans jeton et avec un jeton faux ; API du calendrier de l'admin : 403 sans jeton, acceptée avec celui de la page) : pour chaque formulaire POST de l'admin (suppressions, clôture, favori, composition), un envoi **sans jeton** ou avec un jeton faux ne modifie rien et affiche « La page a expiré… ». Pour l'API de score : voir SCO-01.

**SEC-09 — Connexion** ✅ *`Securite/ConnexionTest`* : identifiants faux → message en français, pas de fuite (« identifiants invalides » sans préciser lequel) ; après connexion admin, le lien « Administration » est présent ; pour un joueur, absent.

**SEC-10 — Déconnexion** ✅ : `/logout` termine la session ; une page admin redemande la connexion.

**SEC-11 — Fiche du club** `/club/{id}/modifier` (voter `CLUB_MODIFIER`) ✅ *`FicheClubTest`* :

| Profil | Attendu |
|---|---|
| anonyme | redirection vers la connexion |
| `cap.a1` (capitaine d'une équipe du club, non responsable) | 403 |
| `resp.club` sur son club | 200 |
| `resp.club` sur un autre club | 403 |
| `admin` | 200 |

Le responsable ne gère ni les équipes, ni les gymnases, ni les responsables : `/admin/club/*` (liste, création, modification, suppression, en GET et en POST) → 403 pour lui, club intact ✅ *`ClubAdminTest::test_routes_admin_refusees_au_responsable`* (en attendant le garde-fou général SEC-01).
Il ne peut pas non plus s'attribuer d'équipes ni de responsables en ajoutant des champs à la requête de la fiche (`club_fiche[equipes]`, `club_fiche[responsables]`) → 422, rien d'enregistré ✅.

**SEC-13 — Saisies de la fiche du club** ✅ *`FicheClubTest`* :
- abréviation ou nom long avec retour à la ligne ou caractère de contrôle → 422 (l'abréviation est reprise dans le nom des agendas `.ics`) ; abréviation limitée aux lettres, chiffres, espace et `. & ' -` ;
- lien `javascript:`, `data:` → 422 (seuls http et https sont acceptés) ;
- HTML saisi (nom, description) affiché comme du texte sur la fiche, la liste des clubs, le calendrier et les équipes ;
- modification sans jeton de sécurité, ou envoyée depuis un autre site (en-tête `Origin` étranger) → 422, rien d'enregistré.

**SEC-14 — Agenda `.ics`** ✅ *`Integration/Service/CalendarIcsGeneratorTest`* : un retour à la ligne dans le nom de l'équipe ou du club n'ajoute aucune ligne au fichier (une seule fin d'agenda, aucune propriété injectée).

**SEC-12 — Envoi d'image (logo d'un club)** ✅ *`FicheClubTest`* : fichier non image → 422, rien d'enregistré ; image de plus de 2 Mo → 422, aucun fichier écrit ; nom du fichier généré par l'application (`<abréviation>-<aléa>.<ext>`), jamais celui envoyé : un nom avec `../` ne sort pas du dossier des images ; types acceptés JPEG, PNG, WebP, GIF (pas de SVG) ; remplacer ou retirer l'image supprime l'ancien fichier.

**MAI-01 — Expéditeur des e-mails** ✅ *`Securite/ExpediteurEmailTest`* : l'e-mail « mot de passe oublié » part de `blvb@vblc.fr` (en-tête From **et** enveloppe SMTP), le compte authentifié chez OVH, qui refuse tout autre expéditeur ; adresse unique réglée par `MAILER_FROM` (`config/packages/mailer.yaml`, valeur par défaut dans `services.yaml`) ; objet en français.

**MAI-02 — Rappel des scores non saisis** ✅ *`Integration/Service/RappelScoreTest`* (commande `app:scores:rappeler`, à planifier chaque jour par cron) : deux jours (réglable par `--jours`) après la date d'un match de la saison courante sans aucun score, le capitaine de chacune des deux équipes et les responsables de leur club (désignés pour la saison) reçoivent un e-mail. Un seul e-mail par destinataire, qui récapitule tous ses matchs (une personne à la fois capitaine et responsable n'en reçoit qu'un) ; un match n'est rappelé qu'une fois (`partie.rappel_score_envoye_le`) ; pas de rappel pour un match de moins de deux jours, à venir, sans date, ou dont un score (même 0-0) est saisi ; un match sans capitaine ni responsable n'est pas marqué (rappelé dès qu'un destinataire existe) ; `--simulation` n'envoie ni ne marque rien ; un e-mail refusé par le SMTP n'interrompt pas les autres.

### 4.2 Saisie des scores — `Fonctionnel/Front/SaisieScoreTest` (P0) ✅ lot 2

Cible : `PUT /front/partie/{id}/api/update`, corps JSON `{scoreReception, scoreDeplacement}`, en-tête `X-CSRF-TOKEN` (jeton `score_update`).

**SCO-01** ✅ — Sans en-tête CSRF ou jeton faux → 403, score inchangé.
**SCO-02** ✅ — Anonyme → refus 403, score inchangé. Tout refus de droits renvoie **403** « Modification interdite » (A4, décidé).
**SCO-03** — Matrice des droits sur le match de référence (A1 reçoit A2) ; les capitaines des deux équipes peuvent saisir (décidé le 15/10/2026). Règle unique : `SaisieScoreService::peutSaisir` (API via `ScoreVoter`, interface via `droitsSaisie`) :

| Profil | Attendu |
|---|---|
| `cap.a1` (capitaine de l'équipe qui reçoit, saison du match) | 200 ✅ |
| `cap.a2` (capitaine de l'équipe qui se déplace, saison du match) | 200 ✅ (les deux capitaines du match peuvent saisir) |
| `cap.b1` (autre poule) | refus ✅ |
| `joueur.a1` (membre non capitaine de A1) | refus ✅ |
| `cap.a1.precedente` | refus |
| `resp.club` (responsable du club de A1) | 200 ✅ |
| `admin` | 200 |

**SCO-13 — Responsable de club** ✅ *`SaisieScoreResponsableClubTest`* : il saisit **tous** les matchs des équipes de son club, à domicile et à l'extérieur, quelle que soit la poule (A1 reçoit A2, B1 reçoit B2, A4 reçoit A1 → 200) ; refus pour un match sans équipe de son club (A3 reçoit A4 → refus, score inchangé). Le capitaine reste limité aux matchs que son équipe **reçoit**.
**SCO-14 — Droits rattachés à une saison** ✅ : le responsable est désigné pour une saison (`ResponsableClub`), comme le capitaine. Désigné pour la saison courante : refus sur un match de la saison précédente. Désigné pour la saison précédente seulement : saisie des matchs de cette saison-là, mais refus pour la saison courante, pas de fiche du club, pas de rôle.
**SCO-15 — Retrait immédiat** ✅ : un responsable retiré par l'admin perd ses droits à la requête suivante, sans se reconnecter (une saisie préparée avant le retrait est refusée, score inchangé).

**SCO-04 — Scores valides** ✅ (data provider) : 3–0, 3–1, 3–2, 2–3, 1–3, 0–3 (en texte ou en nombres JSON, espaces tolérés) → 200, valeurs enregistrées, réponse `newScore` = « 3 - 1 ».
**SCO-05 — Scores invalides** ✅ → 400, score inchangé : 4–0, −1–3 (en chiffres), « a »–3, 2.5–3 (texte et nombre), champ absent, deux forfaits.
**SCO-06 — Scores impossibles** ✅ → 400 : 3–3, 0–0, 2–0, 0–2. Règle (A2, décidé, `App\Competition\Sport\Volley`, ex-`RegleScore`) : match complet (une équipe à 3 sets, l'autre de 0 à 2), match **interrompu** selon la règle 1R du règlement (2–1, 2–2, 1–1, 1–0, dans un sens ou dans l'autre) ou forfait.
**SCO-07 — Forfait** ✅ : `F` + vide → réception −1 / déplacement 3, `newScore` = « F - 3 » ; vide + `F` → symétrique ; `F` + `F` → 400.
**SCO-08 — Effacement** ✅ : deux champs vides → les deux valeurs à `null`, `newScore` = null, le classement est recalculé (l'équipe perd les points de ce match).
**SCO-09 — Classement mis à jour** ✅ après chaque saisie (vérifie la ligne `Classement` des deux équipes).
**SCO-10 — Match à venir** ✅ : refusé (403, « Ce match n'a pas encore eu lieu… ») au capitaine et au responsable de club, accepté pour l'admin (A5, décidé). Un match sans date est traité comme à venir.
**SCO-11** ✅ — Match inexistant → 404.
**SCO-16 — Match interrompu (règle 1R)** ✅ : saisi par le capitaine comme par le responsable de club ou l'admin (décidé) ; points du règlement, quel que soit le barème de la saison : 2–1 → 2 et 1 point, 2–2 → 1 point chacun (set-average inchangé), 1–1 et 1–0 → aucun point. La fenêtre de saisie propose l'option « Match interrompu (règle 1R) » avec un rappel de la règle et le résumé des points (vérifié avec Node, à couvrir par E2E-01).
**SCO-17 — Corps forgé** ✅ *`Fonctionnel/Front/SaisieScoreRobustesseTest`* : un tableau, un objet, un booléen ou une valeur absente à la place d'un score → 400 (et non 500), rien d'enregistré (relecture sécurité du lot 6).
**SCO-12 — Interface** (rendu HTML du calendrier) : pour `cap.a1`, match passé sans score → bouton « Saisir » à la place du tiret ; match futur → tiret, pas de bouton ; match avec score → score + crayon ; pour anonyme et `cap.b1` (autre poule) → jamais de bouton ; `cap.a2` voit le bouton sur le match de référence (il s'y déplace) ; pour `resp.club` → boutons sur les matchs des équipes de son club, à domicile comme à l'extérieur (crayon sur A2–A1, où A1 se déplace, que le capitaine de A1 n'a pas), aucun sur les autres, sur le calendrier comme sur l'accueil ✅ ; la balise `<meta name="csrf-token">` n'est présente que pour un utilisateur connecté. Chaque bouton porte l'URL de l'API générée par le routeur (`data-url-score`, préfixe /blvb en prod) ✅ *`UrlsGenereesTest`*.

### 4.3 Calcul du classement — `Unitaire/Service/ClassementServicePointsTest`, `Unitaire/Competition/Sport/VolleyTest`, `Unitaire/Competition/Sport/BaremeTest`, `Unitaire/Competition/Classement/CritereDepartageTest`, `Integration/Service/ClassementServiceTest` (P0) ✅ lot 2

Tous ces tests utilisent le barème « tous différents » de la story (5/4/2/1/−3) pour qu'une inversion de règle soit visible.

**CLA-01 — Points par résultat** (data provider, du point de vue de chaque équipe) :

| Score (sets gagnés–perdus) | Points attendus |
|---|---|
| 3–0, 3–1 | victoire forte (5) |
| 3–2 | victoire faible (4) |
| 2–3 | défaite forte (2) |
| 1–3, 0–3 | défaite faible (1) |
| match non joué (null) | 0 |
| vainqueur par forfait (3 contre −1) | victoire forte (5) ✅ (A1 corrigée) |
| équipe forfait (−1 contre 3) | points de forfait de la saison (−3) ✅ |
| match interrompu (règle 1R) 2–1 / 2–2 / 1–1 / 1–0 | 2 et 1 / 1 et 1 / 0 et 0 / 0 et 0 ✅ |

**CLA-02 — Le barème vient de la saison** ✅ : modifier `pointsVictoireForte` de la saison change le total ; deux saisons au barème différent ne se mélangent pas.
**CLA-03 — Domicile et extérieur** ✅ : les points d'une équipe additionnent ses matchs à domicile et à l'extérieur.
**CLA-04 — Contexte de poule** ✅ : un match d'une autre poule (phase 2, autre poule de la même équipe) n'est pas compté.
**CLA-05 — Sets** ✅ : total gagnés / perdus correct ; un forfait (−1) compte pour 0 set, sans retirer de sets ; un match interrompu à 2–2 n'est pas compté (règle 1R).
**CLA-06 — Ordre** ✅ : points décroissants ; à égalité, différence de sets décroissante.
**CLA-07 — Confrontation directe** ✅ (égalité de points et de différence de sets) : l'équipe qui a **gagné** le match direct est classée **devant**, qu'elle ait reçu ou non. Le comparateur était inversé (A3, corrigé ; le test échoue avec l'ancien code).
**CLA-08 — Confrontation non jouée ou nulle** ✅ : match direct sans score, ou interrompu à 2–2 / 1–1 → aucune préférence ; dernier critère : **ordre alphabétique** (classement identique d'un calcul à l'autre).
**CLA-09 — Aller-retour** ✅ : seul le **dernier match joué** entre les deux équipes départage (décidé en octobre 2026).
**CLA-10 — Positions** ✅ : 1 à n sans trou ni doublon ; recalcul **idempotent** (deux appels successifs → même résultat, pas de doublon de lignes `Classement`).
**CLA-11 — Nouvelle équipe ajoutée** ✅ à la poule → apparaît au classement avec 0 point au prochain recalcul.
**CLA-17 — Zones de montée et de descente** ✅ (`Integration/Twig/ZonesClassementTest`) : dans le classement d'une poule, la zone de descente n'apparaît (lignes et légende) que s'il existe une poule de niveau inférieur dans la phase, la zone de montée que s'il existe une poule de niveau supérieur (niveaux 1, 2, 3 : pas de descente au 3, pas de montée au 1).

Classement paramétrable (chantier des formats de compétition, lot 2 ; `docs/wizard-saison/decisions.md` D16 à D19). Le départage est une liste ordonnée de critères enregistrée par saison ; la liste par défaut (points, différence de sets, confrontation directe, ordre alphabétique) reproduit CLA-06 à CLA-09, qui servent de non-régression.

**CLA-12 — Quotient de sets** ✅ : à égalité de points et de différence de sets, 3 sets gagnés pour 1 perdu passe devant 4 pour 2 si le quotient est dans le départage ; sinon, ordre alphabétique.
**CLA-13 — Mini-classement** ✅ : trois équipes à égalité de points sont départagées par les points puis la différence de sets de leurs seuls matchs entre elles ; l'ordre obtenu diffère de celui du départage par défaut (différence de sets sur toute la poule).
**CLA-14 — Barème propre à la phase** ✅ : une valeur surchargée par la phase remplace celle de la saison, les autres restent celles de la saison.
**CLA-15 — Liste de départage** ✅ *`CritereDepartageTest`* : par défaut le départage d'avant le paramétrage (nouvelle saison, valeur absente, liste vide) ; codes inconnus ignorés, doublons retirés, ordre alphabétique toujours en dernier ; seuls points, différence et quotient de sets se calculent par équipe.
**CLA-16 — Barème de phase** ✅ *`BaremeTest`* : sans surcharge, barème de la saison ; surcharge partielle ; clés inconnues et valeurs non entières ignorées ; phase sans points (toutes les valeurs à 0).

### 4.4 Visibilité des coordonnées — `Fonctionnel/Front/CoordonneesTest` (P0, RGPD) ✅ lot 3

Règle validée : *on ne voit que les coordonnées du capitaine adverse ; seuls les membres d'une même équipe voient les coordonnées des joueurs non capitaines.* Le responsable d'un club voit les coordonnées des capitaines des poules où joue une équipe de son club (saison de sa désignation), comme un capitaine de ces poules.

**CON-01 — Matrice sur la fiche équipe de A1** ✅ (rendu HTML, on cherche l'e-mail/téléphone de chaque personne dans la page) :

| Spectateur | Capitaine de A1 | Joueurs non capitaines de A1 |
|---|---|---|
| anonyme | non visible | non visible |
| `joueur.a2` (autre équipe) | non visible | non visible |
| `cap.a2` (capitaine adverse, même poule) | **visible** | non visible |
| `cap.b1` (capitaine d'une autre poule) | non visible (figé : seuls les capitaines de la même poule, phase en cours) | non visible |
| `resp.club` (responsable du club de A1, non membre) | **visible** (décidé en octobre 2026 : comme un capitaine de la poule) | non visible |
| `joueur.a1` (coéquipier) | visible | **visible** |
| `admin` | visible | visible |

**CON-02** ✅ — Sur l'accueil et le calendrier, la ligne « Capitaine : … » d'un match n'apparaît que pour le capitaine de l'équipe adverse de ce match (`contact_capitaine_adverse`).
**CON-03** ✅ — Coordonnées prises sur la fiche joueur, à défaut sur le compte ; aucune coordonnée → mention neutre, pas de ligne vide.
**CON-04 — Saison** ✅ : le capitaine de la saison précédente n'est pas présenté comme capitaine de la saison courante.
**CON-05 — Pas de fuite par l'API ou l'ICS** ✅ : l'agenda ICS et les réponses JSON ne contiennent aucune coordonnée personnelle.
**CON-06** ✅ — Responsable de club : ligne « Capitaine » du capitaine adverse sur les matchs de son club (accueil, calendrier) ; capitaines visibles sur la fiche des équipes des poules où joue son club, jamais les autres joueurs ; rien dans une poule où son club n'a pas d'équipe.

### 4.5 Composition des équipes — `Fonctionnel/Front/CompositionTest` (P1) ✅ lot 3

**COM-01** ✅ — Ajouter un joueur existant → membre créé (équipe, saison) ; message nommant l'action.
**COM-02** ✅ — Ajouter un joueur déjà présent → refus « fait déjà partie », pas de doublon.
**COM-03** ✅ — Créer une fiche avec un e-mail **inconnu** → nouvelle fiche + membre.
**COM-04** ✅ — Créer une fiche avec l'e-mail d'un **compte existant** (casse différente : `Cap.A1@BLVB.test`) → fiche liée à ce compte, ou réutilisation de la fiche déjà liée ; pas de seconde fiche.
**COM-05** ✅ — Retirer → le membre disparaît, la fiche joueur reste ; message nommant le joueur.
**COM-06** ✅ — Désigner capitaine → **exactement un** capitaine pour (équipe, saison) après l'opération ; l'ancien redevient membre. Le capitaine qui passe la main n'a plus accès à la composition : il est renvoyé sur la fiche de l'équipe, qui affiche le message (corrigé : il tombait sur une page « accès refusé »).
**COM-07** ✅ — Une même personne peut être capitaine de deux équipes différentes (cas `cap.double` de la recette) sans interférence.
**COM-08 — Côté de retour** ✅ : chaque action lancée depuis `/admin/…` redirige vers `/admin/…` ; depuis le site, vers le site.
**COM-09** ✅ — Le sélecteur « Ajouter un joueur existant » ne propose pas les joueurs déjà dans la composition.
**COM-10 — Admin** ✅ : la page admin liste les saisons de l'équipe ; le lien « Gérer la composition » de la fiche équipe admin pointe vers la version admin.

### 4.6 Suppressions dans l'admin — `Fonctionnel/Admin/SuppressionTest` (P1) ✅ lot 3

Pour chaque entité : la suppression n'est plus proposée dans les listes ; elle se fait depuis la page de modification, via la fenêtre de confirmation (`data-action="confirmation#ouvrir"`).

**SUP-01 — Équipe sans match** ✅ → supprimée, message « L'équipe X a été supprimée. », sa composition supprimée, fiches joueurs conservées, retirée des poules.
**SUP-02 — Équipe avec matchs ou classement** ✅ → la page de modification affiche le **blocage** (pas de bouton de confirmation) ; un POST forcé avec un jeton valide → message « n'a pas pu être supprimée… Rien n'a été modifié », **pas de 500**, l'équipe et ses matchs existent toujours.
**SUP-03 — Gymnase utilisé** ✅ (équipe ou match) → même comportement que SUP-02 ; gymnase libre → supprimé avec ses créneaux.
**SUP-04 — Fiche joueur membre de compositions** ✅ → retirée des compositions puis supprimée ; le compte lié éventuel est conservé.
**SUP-05 — Compte utilisateur lié à une fiche** ✅ → compte supprimé, fiche conservée avec `user = null` ; demandes de réinitialisation de mot de passe du compte supprimées.
**SUP-06 — Son propre compte** ✅ → refusé côté serveur (message), compte intact.
**SUP-07 — Saison** ✅ : saison non affichée → supprimée avec phases/poules/journées/matchs. Saison **affichée sur le site** → refusée aussi côté serveur (A6, `ReglesAdminTest`).
**SUP-08 — Phase / poule** ✅ → supprimées avec leur contenu ; redirection vers la page de la saison.
**SUP-09 — Match** ✅ → supprimé ; redirection vers la saison, poule ouverte.
**SUP-10 — Suppressions en masse** ✅ (matchs d'une poule, d'une journée ; journées d'une poule) → n'affectent que la cible, jeton exigé.
**SUP-11 — Clôture de phase** ✅ → jeton `cloturer<id>` exigé ; sans jeton : message, phase non clôturée.

**POU-01 à POU-05 — Page « Modifier la poule »** ✅ *`Fonctionnel/Admin/ModifierPouleTest`* (D52) : nom et niveau modifiables, ni liste de phases, ni sélection d'équipes, ni journées ; équipes en lecture seule et lien « Composer les poules » vers la mise en place ; confirmation de suppression d'une poule vide (POU-01) ; enregistrement et retour à la saison sur la poule, niveau hors bornes refusé (POU-02) ; phase commencée : seul le nom se modifie, un niveau envoyé à la main est ignoré, ni suppression ni lien de composition (POU-03) ; phase clôturée : lecture seule, titre « Poule … », rien d'enregistré (POU-04) ; phase, équipes ou journées ajoutées à la main : 422, rien de déplacé (POU-05).

### 4.7 Génération des journées et des matchs — `Integration/Service/JourneeServiceTest`, `GenerationMatchsTest`, `OptimiseurTest` (P1, en partie `lent`)

Depuis le chantier des formats de compétition (§4.16), les journées et les matchs simples sont créés par le générateur du format de la phase (`App\Competition\Format\Championnat`, `Elimination`) ; `JourneeService` et `PartieService` gardent la même interface et l'optimiseur reste dans `PartieService`. Ces tests, inchangés, servent de non-régression.

**JOU-01** ✅ — `JourneeService::creerJournees` sur une poule de n équipes : n−1 journées si n pair, n si n impair (équipe exempte) ; numéros 1..k consécutifs.
**JOU-02** ✅ — Les journées tombent dans les dates de la phase et **sautent les semaines indisponibles** (indisponibilités de la saison).
**JOU-03** ✅ — Phase de finales : journées nommées selon `getNomJournee` (demi-finales, finale, barrage le cas échéant).
**MAT-01 — Round-robin** ✅ (`createCalendar`) : chaque paire d'équipes se rencontre **exactement une fois** par phase ; aucune équipe ne joue deux fois la même journée ; avec n impair, une équipe exempte par journée, chacune exactement une fois.
**MAT-02 — Domicile/extérieur** ✅ : écart domicile − extérieur ≤ 1 par équipe, **derbys exclus** (deux équipes du même gymnase : aucun déplacement, le match ne compte ni à domicile ni à l'extérieur) ; nombre de « breaks » (deux matchs consécutifs au même endroit) ≤ seuil documenté.
**MAT-03 — Date et lieu** ✅ : un match a lieu dans le gymnase de l'équipe qui reçoit, le jour de son créneau prioritaire, dans la semaine de la journée (`calculerDateMatch` — testable en **unitaire** : « mercredi » + semaine du 14/09 → mercredi 16/09 20:30).
**MAT-04 — Équipe sans gymnase** ✅ : pas d'erreur ; match créé sans lieu (« Gymnase à définir »).
**MAT-05 — Optimiseur** ✅ (`creerCalendrierOptimise`, `#[Group('lent')]`) : à capacité suffisante, aucune surcharge de gymnase ; à capacité insuffisante, les surcharges sont **rapportées** dans le rapport (pas d'exception) ; résultat identique sur deux exécutions (déterminisme) ; mémoire < 512 Mo sur 4 poules de 8 (mesuré : environ 55 Mo, 14 s).
**MAT-06** ✅ — Régénérer une poule (« Optimiser cette poule ») supprime puis recrée ses matchs, sans toucher aux autres poules.
**MAT-07** ✅ *`AlternanceTest`, `Fonctionnel/Admin/OptimiserPageTest`* — Page « Optimiser les matchs » : statut de chaque match (Dom, Ext, Der = derby, même gymnase) ; **break** = deuxième déplacement de suite (un derby compte comme domicile, une journée exempte n'interrompt pas la suite), signalé en orange ; légende des couleurs affichée (sarcelle = pas de déplacement, orange/rouge = à surveiller, comme la charge des gymnases).

### 4.8 Clôture de phase — `Integration/Service/PhaseServiceTest` + `Fonctionnel/Admin/CloturePhaseTest` (P1)

**PHA-01** ✅ — Montées/descentes : les `nbMontee` premiers de la poule de niveau k passent au niveau k−1, les `nbDescente` derniers au niveau k+1 ; les autres restent au même niveau.
**PHA-02** ✅ — Poule de niveau le plus haut : pas de montée ; le plus bas : pas de descente.
**PHA-03** ✅ — Après clôture : phase marquée close, équipes réparties dans la phase suivante, retour à l'étape Poules de cette phase (D40) ; ni journées ni matchs créés ; message « Phase clôturée : les équipes ont été réparties dans « Phase 2 ». Vérifiez les poules, puis créez les journées et les matchs. », sans avertissement.
**PHA-04** ✅ — Dernière phase (pas de suivante) → message d'erreur, rien ne change.
**PHA-05** ✅ — Clôturer deux fois → refusé par le contrôleur (« La phase X est déjà clôturée. Rien n'a été modifié. ») et sans effet dans le service : les matchs et scores de la phase suivante sont conservés.

Depuis le lot 3 des formats de compétition, la clôture applique les **règles de passage** de la phase (`RegleDePassage`) ; une phase sans règles enregistrées reçoit les règles par défaut du schéma 2025 (D21, D26), ce que vérifient PHA-01 et PHA-02 (phases construites par les tests, sans règles).

**PHA-06 — Vers une phase finale** ✅ (schéma 2025, D21) : chaque équipe va dans le tableau de son niveau, sans montée ni descente ; aucun avertissement.
**PHA-07 — Règles enregistrées** ✅ : elles remplacent les règles par défaut (ex. 1er et 2e de la poule A envoyés au niveau 2).
**PHA-08 — Avertir et continuer** ✅ (D24) : règle désignant une poule inconnue ignorée, rang avec deux destinations placé une seule fois (première règle), équipes sans poule nommées **en premier** ; la phase est close malgré tout.
**PHA-09 — Message de clôture avec avertissements** ✅ *`CloturePhaseTest`* (D28) : une seule alerte orange « Phase clôturée, mais certaines équipes n'ont peut-être pas été réparties comme prévu… », une ligne par problème (liste), puis le conseil « Pour corriger : … » ; pas de « succès » affiché en même temps.
**VER-01 — Vérification des règles** ✅ *`Unitaire/Competition/VerificationPassagesTest`* (D5, D28) : règles par défaut et règles complètes sans erreur ; « aucune poule prévue pour les 3e et 4e », « le 2e est prévu dans plusieurs poules » ; règles pour la saison suivante vérifiées seulement si la poule en a ; règle mal écrite, autre phase, poule inconnue, match inconnu, sans poule d'arrivée : « Règle « … » ignorée : … » ; plage vide d'une petite poule acceptée ; règle en clair avec sa notation entre crochets (« 1er et 2e de la Poule A (Phase 1) vers la Poule A (Phase 2) [P1.A#1..2] ») ; rang hors de la poule expliqué sans rang négatif. *`NotationPlaceTest`* : une plage vide lève `PlageVide` ; notation en clair (« 1er et 2e », « du 3e à l'antépénultième », « toutes les équipes », « vainqueur du match AB »…).

### 4.9 Agenda ICS — `Integration/Service/CalendarIcsGeneratorTest` + `Fonctionnel/Front/IcsTest` (P2)

**ICS-01** ✅ — `GET /calendar/saison/{s}/equipe/{e}.ics` → 200, `Content-Type: text/calendar; charset=utf-8`, nom de fichier en `.ics`.
**ICS-02** ✅ — Un `VEVENT` par match **de cette équipe et de cette saison** uniquement (pas la saison précédente).
**ICS-03 — UID stables** ✅ : deux générations successives → mêmes UID (sinon les agendas dupliquent les événements).
**ICS-04** ✅ — Fuseau `Europe/Paris` déclaré, heure de début correcte en été comme en hiver.
**ICS-05** ✅ — Description : phase, poule, journée ; score au format « 3–1 » quand il existe.
**ICS-06 — Cache** ✅ : `ETag` identique entre deux appels sans changement ; requête avec `If-None-Match` égal → 304 ; après saisie d'un score → nouvel `ETag`.
**ICS-07** ✅ — Lignes pliées à 75 octets (RFC 5545), fins de ligne CRLF.
**ICS-08** ✅ — Ancienne route `/calendar/{poule}/{equipe}.ics` toujours servie (abonnements existants).

### 4.10 Import Excel des utilisateurs — `Fonctionnel/Admin/ImportUtilisateursTest` (P2)

Fichier `.xlsx` généré par le test (PhpSpreadsheet), jamais un fichier réel.
**IMP-01** ✅ — Lignes valides → comptes créés (rôle utilisateur), fiches joueurs créées et liées.
**IMP-02** ✅ — E-mail d'une fiche existante (casse différente) → fiche liée au nouveau compte, pas de doublon.
**IMP-03** ✅ — Fiche déjà liée à un **autre** compte → avertissement dans le rapport, pas de modification de la fiche.
**IMP-04** ✅ — E-mail invalide → ligne rejetée avec le motif « Adresse e-mail invalide », les autres lignes importées.
**IMP-05** ✅ — Compte déjà existant → non dupliqué.

### 4.11 Calendrier par gymnase — `Unitaire/Service/CalendrierAnalyseServiceTest` + `Fonctionnel/Front/CalendrierGymnaseTest` (P2)

**GYM-01** ✅ — Date sans créneau avec matchs → alerte « hors créneau ».
**GYM-02** ✅ — Plus de matchs que de terrains → surcharge ; autant → complet ; moins → « N terrains libres ».
**GYM-03** ✅ — Créneau non prioritaire alors qu'un autre jour l'est → alerte priorité.
**GYM-04** ✅ — Date pendant des vacances scolaires (indisponibilité) avec match → alerte vacances ; sans match → simple mention.
**GYM-05 (fonctionnel)** ✅ — La page `/calendrierlieu` affiche aussi les dates de créneau **libres** de la phase, et le filtre « Seulement les dates à problème » s'appuie sur `data-alerte`.

### 4.12 « Mon équipe » et fonctions Twig — `Fonctionnel/Front/MonEquipeTest`, `Integration/Twig/SaisonCouranteTest` (P2)

**MEQ-01** ✅ — `mon_equipe_defaut()` : capitaine connecté → son équipe de la saison courante ; joueur non capitaine, anonyme → `null` (rendu `{'id': null}`, jamais `[]`, qui casse le contrôleur Stimulus).
**MEQ-02** ✅ — Capitaine de deux équipes → la première par nom, toujours la même.
**MEQ-03** ✅ — `mon_equipe_choix()` : équipes de la saison, triées alphabétiquement.
**ADM-01** ✅ — `admin_saison_courante()` : saison favorite, sinon la plus récente.
**ADM-02 — Alertes de l'admin lisibles** ✅ *`Fonctionnel/Admin/CloturePhaseTest`* (PHA-03, PHA-04) : toutes les alertes de l'admin (messages flash, erreurs des calendriers de poule, du formulaire de compte et du rapport d'import) passent par `admin/_alerte.html.twig` ; texte en couleur de base (classe `.admin-alerte`, contraste AA ; avant : texte coloré à environ 3:1), état porté par une icône pour chaque type (succès, avertissement, erreur, information) et le filet gauche, préfixe lu par les lecteurs d'écran conforme au type (« Succès : », « Attention : », « Erreur : ») ; `role="alert"` pour une erreur, `status` sinon.

### 4.13 Fumée : toutes les pages s'affichent — `Fonctionnel/Fumee` (P2, très rentable)

**FUM-01** ✅ *`Fumee/PagesTest`* (visiteur, capitaine, responsable de club ; les pages /admin sont parcourues par SEC-01) — Pour chaque route **GET** de l'application (lue dans le routeur, paramètres remplis depuis la story), avec le profil adéquat (anonyme pour le site, admin pour `/admin`) : statut 200 (ou 302 attendu), **aucune erreur Twig**, aucune « Notice/Deprecation ».
Couvre d'un coup les erreurs de gabarit (variables manquantes en `strict_variables`, gymnase absent → page 500 corrigée en octobre 2026).
**FUM-02** ✅ *`Fumee/PagesTest`, `Fumee/SansSaisonTest`* — Même parcours sur une saison **vide** (sans phase) et sur une base **sans aucune saison** (`/aucune-saison`).

### 4.14 Bout en bout dans un navigateur — `tests/e2e` (Playwright, lot 5, facultatif)

Réservé à ce que le PHP ne peut pas voir (JavaScript Stimulus) ; 5 à 8 scénarios maximum, exécutés en CI de nuit :
**E2E-01** saisie d'un score dans la fenêtre (mobile et ordinateur), par un capitaine et par un responsable de club ; **E2E-02** « Mon équipe » (choix, surlignage, onglet ouvert) ; **E2E-03** fenêtre de confirmation admin (Annuler a le focus, Échap ferme, rien n'est supprimé) ; **E2E-04** listes admin (recherche, compteur, tri clavier) ; **E2E-05** filtre gymnase pré-sélectionné par « Mon équipe » ; **E2E-06** tri de la page « Les équipes » (clic, second clic, non classées à la fin, surlignage « Mon équipe » conservé, boutons mobiles).

### 4.15 Clubs — `Fonctionnel/Front/FicheClubTest`, `Fonctionnel/Admin/ClubAdminTest`, `Fonctionnel/Front/NomEquipeAfficheTest`, `Integration/Service/ClubDepuisNomEquipeTest` (P1)

**Nom affiché**
**NOM-01** ✅ — Nom affiché = nom enregistré + abréviation du club en majuscules entre parenthèses (« EQUIPE A1 (CTA) », « Léo Lagrange (LÉO) ») ; sans club, le nom seul. Même résultat pour les entités (`Equipe::getNom`) et pour les tableaux renvoyés par les requêtes (`PartieRepository`, fiche équipe).
**NOM-02** ✅ — Titres (fiche équipe, composition, admin) : nom en majuscules.
**NOM-03** ✅ — Le formulaire de l'équipe modifie le nom **sans** club ; l'abréviation n'est jamais ajoutée deux fois.
**NOM-04** ✅ — Page « Les équipes » : abréviation en lien vers la fiche du club, nom long en infobulle.
**TRI-01** ✅ *`TriEquipesTest`* — Page « Les équipes », tri par poule (validé par l'ergonome) : en-têtes Équipe et Classement en boutons (Gymnase non triable), `aria-sort="ascending"` sur Équipe au chargement et sur la seule colonne triée ensuite, flèche `aria-hidden` ; lignes et cartes avec `data-tri-nom` et `data-tri-classement`, ordre alphabétique au chargement ; sur mobile, boutons « Trier : Nom | Classement » avec `aria-pressed`. Le tri lui-même (sens, non classées toujours à la fin, départage alphabétique) s'exécute dans le navigateur : à couvrir par E2E-06.

**Fiche et liste des clubs**
**CLU-01** ✅ — Fiche publique : nom long, abréviation, description, lien, logo, gymnases ; **seulement les équipes engagées dans la saison en cours**, avec leur poule.
**CLU-02** ✅ — Modification par le responsable : nom long, abréviation, description, lien ; description et lien vides acceptés (enregistrés `null`) ; lien invalide → 422 avec message.
**CLU-03** ✅ — Abréviation : avertissement sous le champ (nombre d'équipes, nom actuel) ; après changement, message avec le nouveau nom des équipes.
**CLU-04** ✅ — Liste `/clubs` triée par nom long, cartes vers les fiches, nombre d'équipes engagées dans la saison ; lien dans le menu « Le championnat ».
**CLU-05** ✅ — « Qui sommes-nous ? » : clubs tirés de la base, en lien vers leurs fiches.
**CLU-07** ✅ — « Mon profil » du responsable : rubrique « Mes clubs (responsable) » avec ses seuls clubs de la saison en cours, en lien vers leur fiche ; absente pour un capitaine non responsable.
**CLU-06** ✅ — Rôle `ROLE_RESPONSABLE_CLUB` déduit d'une désignation **pour la saison en cours** (présent pour `resp.club`, absent pour `cap.a1` et pour un responsable d'une saison précédente) ; retiré quand le club est supprimé.

**Administration**
**ADC-01** ✅ — Création d'un club (équipes, gymnases, responsables de la saison en cours) ; rattacher une équipe à un club la retire de son club précédent. Les responsables des saisons précédentes sont conservés.
**ADC-02** ✅ — Suppression d'un club : ses équipes restent, sans club ; les comptes ne sont pas supprimés.
**ADC-03** ✅ — Listes des équipes (colonne club) et des utilisateurs (badge « Responsable de club »).

**Reprise des données** (`app:clubs:depuis-noms-equipes`, lancée une fois au déploiement)
**REP-01** ✅ — Séparation nom / sigle (« EROAK (AOVB) », espaces, chiffres, parenthèses ordinaires laissées dans le nom).
**REP-02** ✅ — Clubs du référentiel créés (nom long, gymnases, description et lien) ; club existant réutilisé, son nom long et sa fiche **jamais écrasés** ; anciens sigles traduits (SJDL → VBLC) ; équipes sans sigle rattachées d'après le référentiel ; écriture des noms corrigée (table `NOMS_EQUIPES`).
**REP-03** ✅ — Sans `--force` : rien n'est enregistré ; relancée après application : aucun changement, aucun avertissement.
**REP-05** ✅ — Sigle ne différant du nom court du club que par la casse (« LES TAMALOUS (TOSSE) » pour le club « Tosse ») : un seul club, pas de doublon (régression : violation d'unicité en production, 13/10/2026) ; « BLOCK & TXOTX » et « TAKABLOKÉ » connus de la table d'écriture, écrits tels quels, sans avertissement.
**REP-04** ✅ *`ImportEquipesTest`* — Import Excel des équipes : « NOM (SIGLE) » rattaché au club du sigle (club existant réutilisé, club inconnu créé), sigle retiré du nom ; équipe sans sigle et parenthèses ordinaires : pas de club.

### 4.16 Formats de compétition (socle du wizard de saison) — `Unitaire/Competition/` (P1) ✅ lot 1 du chantier

Chantier décrit dans `docs/wizard-saison/` (méthode, décisions, plan, lots) ; catalogue des formats dans `docs/formats-competition.md`.

**NOT-01 — Notation des places** ✅ *`NotationPlaceTest`* : `P1.A#2`, `P1.A#-1` (rang compté depuis la fin), `P1.A#1..-3` (plage), `BAR1.AB.vainqueur` / `.perdant` sont lues, puis réécrites à l'identique ; notations mal formées refusées (rang 0, zéro initial, issue inconnue, espace dans un code…).
**NOT-02 — Rangs selon la taille de la poule** ✅ : l'avant-dernier est le 7e d'une poule de 8 et le 8e d'une poule de 9 ; « 1er à antépénultième » = 1–6 sur 8, 1–7 sur 9 ; rang hors de la poule ou plage vide refusés.
**NOT-03 — Résolution en équipes** ✅ *`ResolveurPlaceTest`* (résultats en mémoire) : plage dans l'ordre du classement, vainqueur et perdant d'un barrage joué ; barrage non joué, poule ou match inconnus refusés ; règles de la proposition du bureau (`docs/formats-competition.md` F2) : chaque équipe des poules A et B a exactement une destination.
**SPT-01 — Règles du volley** ✅ *`Sport/VolleyTest`* (ex-`RegleScoreTest`) : scores acceptés et refusés (§4.2 SCO-06), forfait encodé −1 / 3, points d'un match interrompu fixés quel que soit le barème, sets comptés pour le set-average (2–2 non compté, forfait : aucun set pour l'équipe forfait ni perdu par l'adversaire).
**FMT-01 — Choix du format** ✅ *`Format/FormatsTest`* : phase de championnat → `Championnat`, optimisé ; phase finale → `Elimination`, hors optimiseur ; aucun générateur → aucun format ; nombre de journées créées (championnat n-1 ou n, élimination avec ou sans tour de barrages, 0 sous deux équipes) et de matchs prévus (n(n-1)/2, n-1).
**COD-01 — Codes des phases et des poules** ✅ *`CodesCompetitionTest`* (lot 3, D20) : saison BLVB → `P1`, `P2`, `F`, poules `A` à `D` tirées des noms ; noms sans lettre finale → première lettre libre dans l'ordre des niveaux ; un code déjà saisi n'est jamais changé et les codes calculés évitent les codes pris ; plusieurs phases finales → `F`, `F2`. La migration `Version20261013120000` utilise le même calcul (vérifié sur une copie de la base de développement).
**SPT-02 — Sport de la saison** ✅ *`Sport/SportsTest`* (lot 4, D13) : une nouvelle saison est en volley-ball ; le sport est trouvé par le code enregistré (`Saison.sport`) ; code inconnu → volley-ball ; liste des sports proposés.
**MOD-01 — Modèle « Format BLVB actuel »** ✅ *`Modele/ModeleBlvbActuelTest`* (lot 4, D6, D10 ; remplace `config/app/saison.yaml`) : Phase 1 et Phase 2 en championnat, Phase Finale (fin au 31 juillet), dates relatives à l'année de début, codes P1, P2, F ; 4 poules A à D par phase (niveaux 1 à 4, 2 montées, 2 descentes) ; 12 règles de P1 vers P2, 16 de P2 vers la finale ; préfixe des noms pour les jeux de données ; modèle proposé par le registre.
**CPS-01 à CPS-03 — Copie d'une saison** ✅ *`Integration/Service/CopieSaisonTest`* (lot 4, D3, D10, D22) : sport, barème, départage, phases (dates décalées comme le début de saison, phases non closes), poules, codes, barème et options de phase (date de la journée des finales décalée comme la phase) et périodes sans match (décalées) recopiés ; règles de passage recopiées et rattachées aux poules de la nouvelle saison ; poules de la phase 1 pré-remplies par les montées et descentes « saison suivante » de la saison copiée (phase clôturée) ; saison copiée non terminée → poules vides et avertissement.
**WIZ-01 — Saison en brouillon** ✅ *`Fonctionnel/Admin/BrouillonSaisonTest`* (lot 4, D29) : un brouillon du wizard n'est jamais proposé sur le site, même demandé explicitement (`/set-saison`), ni choisi par défaut ; la liste des saisons de l'admin affiche « Brouillon · étape n sur 6 » sans le bouton « Afficher sur le site », et le serveur refuse aussi une requête directe avec un jeton valide ; l'agenda `.ics` d'une équipe pour un brouillon répond 404 (adresse devinée en changeant l'identifiant ; relecture sécurité).
**WIZ-02 à WIZ-05 — Wizard, étapes 1 et 2** ✅ *`Fonctionnel/Admin/WizardSaisonTest`* (lot 4, D29) : partir d'un modèle crée un brouillon (3 phases, 28 règles, nom « Saison AAAA-AAAA+1 ») ; copier une saison décale les dates d'un an et avertit, dès le choix à l'étape 1 puis après la copie, si la saison copiée n'est pas terminée ; étape 2 : dates incohérentes et nom déjà pris refusés (422), « Suivant » enregistre et avance, « Enregistrer et quitter » revient à la liste ; une saison lancée (matchs programmés, phase clôturée ou classement enregistré) n'ouvre pas le wizard, ni en lecture ni en envoi (rien enregistré), une étape pas encore atteinte renvoie à la dernière atteinte. *`Securite/AccesAdminTest`* (SEC-01) couvre les nouvelles routes (`etape` = 2).
**WIZ-06, WIZ-07 — Wizard, étape 3 (phases)** ✅ *`WizardSaisonTest`* : ajouter et supprimer une poule, monter une phase, ajouter (dernière, une poule, code attribué) et supprimer une phase ; modifications enregistrées par « Suivant » ; nom vidé (corrigé : erreur 500 avant ce test) ou code de poule en double refusés (422), rien enregistré.
**WIZ-08, WIZ-09 — Wizard, étape 4 (passages)** ✅ *`WizardSaisonTest`* : passages du modèle affichés en mode simple (y compris « Montées et descentes pour la saison suivante » vers la phase finale) ; « Suivant » sans changement les conserve ; « 1 montée » réécrit les règles de la poule ; mode détaillé, ajout d'une règle (corrigé : une règle ajoutée sans destination était ignorée), retour au mode simple (règles par défaut de la poule), « Rétablir les règles par défaut ». **WIZ-11, WIZ-12 — Wizard, étape 6 (vérification et validation)** ✅ *`WizardSaisonTest`* : saison du modèle prête à valider (avertissement : nombres d'équipes non indiqués), schéma par niveaux, passages en clair ; « Valider la saison » la sort du brouillon et mène à sa page (« Prochaine étape : composer les poules de « Phase 1 » ») ; une erreur (dates de phase incohérentes) bloque la validation (bouton `aria-disabled`, 422, toujours un brouillon), étape « Phases » marquée « ! » dans le stepper, lien « Corriger » vers l'étape 3. Dates comparées au jour près (corrigé : la fin de la phase finale du modèle, 1er août à l'heure courante, sortait de la saison ; elle est maintenant au 31 juillet, à minuit).
**WIZ-13 — Modifier une saison créée** ✅ *`WizardSaisonTest`* (D29, question 8) : tant que la saison n'est pas lancée, la page « Modifier la saison » propose « Modifier l'organisation de la saison » (wizard en mode édition : étapes 2 à 6, sans « Départ », bouton final « Terminer ») ; la page elle-même ne modifie plus que le nom, les dates et les périodes de fermeture (le barème est à l'étape 5 du wizard). Saison lancée (WIZ-05) : le wizard renvoie vers cette page, sans lien vers lui.
**WIZ-14 — Modifier une saison créée : garde-fous** ✅ *`WizardSaisonTest`* (relectures ergonome et sécurité) : « Enregistrer et quitter » sur une saison visible qui contient une erreur renvoie à l'étape 6 (« corrigez-les avant de quitter ») ; une phase clôturée ferme le wizard (avant : seul un score saisi le fermait, et supprimer une poule emportait matchs programmés et classements, ou provoquait une erreur 500).
**WIZ-15 — Wizard : entrées refusées** ✅ *`WizardSaisonTest`* (relecture sécurité) : sans jeton, les étapes 4, 5 et 6 n'enregistrent rien (règles, barème, validation) ; une destination `p:<poule d'une autre saison>` forgée est ignorée ; champs `p[...]` mal formés (chaîne au lieu d'un tableau) : pas d'erreur 500 ; niveau de poule hors de 1 à 20 refusé (422 ; avant : un niveau démesuré épuisait la mémoire à l'étape 4).
**WIZ-16 — Période de fermeture vide** ✅ *`WizardSaisonTest`* (bogue signalé par l utilisateur) : une période ajoutée à l étape 2 sans motif ni dates est refusée (422) avec un message par champ, au lieu de l erreur 500 « Expected argument of type DateTimeImmutable, null given » ; dates inversées refusées ; rien enregistré. Les messages sont affichés par le thème `admin/form/erreurs.html.twig` (rouge foncé, champ cerclé, `role="alert"`) et le bandeau « Dates reprises de la saison copiée » disparaît quand le formulaire a des erreurs (il masquait le vrai message).
**WIZ-17 — Ordre des phases et dates** ✅ *`WizardSaisonTest`* (remarque de l utilisateur) : une phase placée avant une phase qui commence plus tôt (par exemple la finale montée en premier avec ▲) est une erreur bloquante de l étape 6 ; une phase finale n a pas à être la dernière si les dates le permettent (le contrôle suit les dates, pas le type de phase) ; phases en parallèle (même début) acceptées ; recouvrement des dates = simple avertissement.
**WIZ-10 — Wizard, étape 5 (classement)** ✅ *`WizardSaisonTest`* : barème de la saison enregistré ; phase finale « aucun point » (barème de phase à 0) ; départage réordonné, critère ajouté et retiré (ordre alphabétique toujours en dernier) ; valeur non numérique refusée (422). *`Unitaire/Competition/PassagesSimplesTest`* : reconnaissance de la forme simple (montées, autres, descentes ; poule du haut ou du bas ; vers une phase finale avec la saison suivante), autres formes → mode détaillé, écriture des règles.
**MEP-01 à MEP-08 — Mise en place, étape « Poules »** ✅ *`Fonctionnel/Admin/MiseEnPlacePoulesTest`* (lot 5, D35 à D38) : écran (bilan « 4 équipes placées sur 5 prévues », origine de chaque équipe : montée, descente, reste, nouvelle ; « Équipes à placer » ; avertissement d'effectif ; poule vide en erreur, « Suivant : Journées » désactivé) ; placer une équipe ; déplacer (une seule poule par phase) et retirer ; ajouter une équipe de la base ; « Suivant : Journées » refusé avec une poule vide, accepté vers l'étape Journées ; phase lancée en lecture seule, modification refusée ; sécurité (jeton CSRF absent, poule d'une autre phase, équipe ou action inconnues, non-administrateur refusé) ; brouillon renvoyé vers le wizard, lien « Composer les poules » sur la saison. WIZ-11 mis à jour : la validation de la saison mène à l'étape Poules.
**MEP-09 à MEP-15 — Mise en place, étape « Journées »** ✅ *`Fonctionnel/Admin/MiseEnPlaceJourneesTest`* (lot 5, D35 à D39) : écran sans journée (bilan, semaines et fermetures, information, « Générer les journées manquantes », « Suivant : Matchs » désactivé avec sa raison) ; génération groupée (une journée par semaine, 3 pour 4 équipes, 3 pour 3 équipes, bouton groupé retiré, « Suivant » accepté) ; régénération d'une poule (journées recréées, matchs et scores de la poule supprimés, équipes conservées, « Générer » refusé si les journées existent) ; phase trop courte (alerte chiffrée avec lien vers les phases, aucune journée partielle laissée ; MEP-12b : régénération impossible refusée avant toute suppression) ; semaines de fermeture comptées et sautées ; sécurité et verrou (jeton absent, poule d'une autre phase, action inconnue, phase avec scores saisis en lecture seule, brouillon renvoyé vers le wizard, non-administrateur refusé).  MEP-15 : « Enregistrer et quitter » des étapes Poules et Journées (retour à la saison, message).
**PHA-10 — Avertissements avant de clôturer** ✅ *`CloturePhaseTest`* (D37) : règles de passage incomplètes → les problèmes sont listés dans la fenêtre « Clôturer la phase » (« À vérifier avant de clôturer : … »), avant le clic ; la phase n'est pas clôturée.
**PHA-11 — Clôture refusée côté serveur** ✅ *`CloturePhaseTest`* (D40) : une poule sans journées empêche la clôture même avec un jeton valide (mêmes conditions que le bouton : saison validée, phases précédentes clôturées, journées) ; rien n'est modifié.
**MEP-16 à MEP-20 — Mise en place, étape « Matchs »** ✅ *`Fonctionnel/Admin/MiseEnPlaceMatchsTest`* (lot 5, D35 à D40) : sans journées (erreur avec lien vers l'étape Journées, aucun bouton de génération, « Terminer » désactivé avec sa raison) ; génération de la phase (6 + 3 matchs, message, bilan « 9 matchs créés sur 9 prévus », déplacements de suite, équipe sans gymnase signalée, attente annoncée par le déclencheur, « Terminer » vers la saison) ; poule par poule (régénérer ne touche pas aux autres poules, « Générer » refusé si la poule a des matchs, accepté sinon) ; prérequis (journées manquantes : génération refusée, matchs conservés) ; sécurité et verrou (jeton absent, poule d'une autre phase, action inconnue, phase avec scores saisis en lecture seule, y compris par l'ancienne page « Optimiser » ; brouillon renvoyé vers le wizard, non-administrateur refusé).
**MEP-21 à MEP-23 — Replacer les matchs à venir** ✅ *`Fonctionnel/Admin/MiseEnPlaceMatchsTest`* (lot 5, D41) : replacement d'une poule (refusé sans la case « aucun match déjà joué n'attend la saisie de son score » ; les matchs joués sont conservés, les autres recréés dans leur journée, la liste des rencontres ne change pas ; avertissement sur le match sans score à date passée) ; phase lancée poule par poule (poule sans match joué régénérée en entier, poule avec match joué et phase entière refusées, replacement de la phase accepté avec le match joué conservé).
**MEP-24 — Calcul des matchs interrompu** ✅ *`Integration/Service/MiseEnPlaceMatchsEchecTest`* (lot 5) : une erreur en plein calcul annule la transaction, tous les matchs sont conservés, le message affiché est neutre et l'erreur est journalisée.
**WIZ-18 à WIZ-21 — Wizard, points reportés du lot 4** ✅ *`Fonctionnel/Admin/WizardReportsLot4Test`* (lot 5) : point de départ enregistré (copie ou modèle) et rappelé au récapitulatif, « Brouillon enregistré à H h mm » ; aide courte sous chaque critère de départage ; écart d'effectif dans le schéma (« 3 / 8 équipes ») ; alerte d'erreur de l'étape 3 avec un lien vers chaque champ.
**REG-01 — Règles de passage par défaut** ✅ *`ReglesParDefautTest`* (lot 3, D21, D22) : championnat → championnat, 2 montées et 2 descentes par poule vers le niveau voisin, pas de montée au niveau le plus haut ni de descente au plus bas ; vers une phase finale, chaque poule vers le tableau de son niveau, et montées / descentes enregistrées pour la saison suivante ; sans montée ni descente, une seule règle ; petite poule (moins d'équipes que de montées et descentes, nombre d'équipes connu) : la montée l'emporte, règles sans chevauchement ; niveau absent → première poule ; pas de phase suivante → aucune règle ; version entités (ordre, poule de destination, niveau de la saison suivante). La migration `Version20261013130000` utilise le même calcul (vérifié sur une copie de la base de développement : 28 règles pour la saison en cours).

**BAR-01 à BAR-05 — Phase de barrages** ✅ *`Integration/Service/BarrageTest`* (lot 6, D42, D43) : les équipes des règles de passage arrivent dans la poule de deux équipes, une journée et un match sont créés (BAR-01) ; le match est reçu par l'équipe issue de la poule de niveau supérieur (BAR-02) ; un barrage de plus ou moins de deux équipes est refusé avec un message clair, sans journée (BAR-03) ; vainqueur et perdant inconnus tant que le score n'est pas saisi, forfait = victoire de l'autre équipe (BAR-04) ; la clôture envoie le vainqueur dans la poule désignée par la règle et signale le perdant sans destination (BAR-05). *`CodesCompetitionTest`* : phases de barrages codées `BAR`, `BAR2` sans changer `P1`, `P2`, `F`.

**MOD-02 — Modèle « Format BLVB avec barrages »** ✅ *`Unitaire/Competition/Modele/ModeleBlvbAvecBarragesTest`* (lot 6, D44) : quatre phases (Phase 1, Barrages d'une semaine, Phase 2, Phase Finale ; codes P1, BAR, P2, F) ; trois barrages AB, BC, CD reçus par la poule supérieure ; règles de la phase 1 (1er monte, dernier descend, avant-dernier contre le 2e du dessous, les autres restent) et des barrages (vainqueur en haut, perdant en bas) ; vérification D5 sans erreur pour des poules de 8 équipes. **BAR-06** ✅ *`Integration/Service/ModeleAvecBarragesTest`* : parcours complet en base (clôture de la phase 1, barrages joués, clôture des barrages) : chaque poule de la phase 2 compte 8 équipes, vainqueurs en haut, perdants en bas. Le modèle n'est pas encore proposé dans le wizard (lot 6c).

**BAR-07 à BAR-09 — Barrages de fin de saison** ✅ *`Integration/Service/BarragesFinSaisonTest`* (lot 6g, D50) : clôture de la phase 2 du modèle « BLVB avec barrages » : toute la poule va au tableau de son niveau et l'avant-dernier du haut et le 2e du bas dans un barrage de fin de saison (A7 / B2, B7 / C2, C7 / D2), sans avertissement (BAR-07) ; une équipe de barrage reste dans le tableau de sa poule d'origine, les tableaux gardent 8 équipes (BAR-08) ; la phase finale, terminale, se clôture sans rien répartir ni avertir alors que les barrages la suivent, et ceux-ci restent intacts (BAR-09). Aucun passage vers la saison suivante : les poules de la saison suivante se remplissent à la main.

**VER-02 — Une destination par phase d'arrivée** ✅ *`Unitaire/Competition/VerificationPassagesPhasesTest`* (D50) : un rang peut aller dans le tableau final et dans un barrage (aucune erreur) ; deux poules d'une même phase pour un rang : signalé ; un rang sans destination : toujours signalé.

**ADJ-01 à ADJ-09 — Wizard en mode ajout d'une saison lancée** ✅ *`Fonctionnel/Admin/WizardModeAjoutTest`* (lot 6h, D51) : saison de la story (phase 1 avec matchs, phase 2 vide). L'étape Phases s'ouvre, la phase commencée en lecture seule hors du formulaire, étapes Phases, Passages et Vérification seulement (ADJ-01) ; une phase de barrages s'ajoute à la fin puis remonte entre la phase commencée et la phase 2, jamais avant la phase commencée (ADJ-02) ; les actions ne visent que les phases libres, la phase commencée et ses matchs restent (ADJ-03) ; le code d'une phase commencée est réservé (ADJ-04) ; les passages d'une phase clôturée ne s'affichent plus et ne changent pas, même envoyés à la main (ADJ-05) ; navigation Passages → Vérification sans Classement (ADJ-06) ; une phase ou une poule libre visée par les passages d'une phase commencée ne se supprime pas, message et passages conservés (ADJ-07) ; une phase avec des lignes de classement, même à zéro, est figée et les passages qui y mènent ne se modifient plus (ADJ-08) ; phase 1 commencée mais pas clôturée : ses passages restent modifiables et envoient ses équipes dans un barrage ajouté entre la phase 1 et la phase 2 (ADJ-09). WIZ-05 mis à jour : une saison lancée ouvre le wizard en mode ajout (lien « Ajouter des phases » sur « Modifier la saison », étapes 2 et 5 renvoyées à l'étape 3).

**WIZ-22 à WIZ-26 — Wizard, phase de barrages** ✅ *`Fonctionnel/Admin/WizardBarragesTest`* (lot 6c, D32, D42, D43) : le modèle « Format BLVB avec barrages » est proposé au départ (4 phases, 3 matchs, réception par la poule supérieure) ; étape 3 : phase « Barrages » avec « Qui reçoit le match ? » et « Ajouter un match », choix enregistré seulement pour un barrage ; étape 4 : une carte par match (« Le vainqueur joue ensuite dans » / « Le perdant joue ensuite dans », valeurs du modèle sélectionnées), pas de mode simple ni de « rétablir » vers ou depuis les barrages ; changer le vainqueur d'un match enregistre la règle, un perdant laissé vide ou malformé est signalé et sa règle retirée ; étape 6 : le modèle passe la vérification sans erreur.

**WIZ-27 — Petite finale dans le wizard** ✅ *`WizardBarragesTest`* : case « Jouer un match pour la troisième place (petite finale) » à l'étape 3 pour un tableau ; enregistrée dans les options de la phase finale seulement.

**ELI-01 à ELI-04 — Tableau à élimination complet** ✅ (lot 6d, B1, B2) : *`Unitaire/Competition/Format/EliminationPlacementTest`* : placement standard des têtes de série (8 → 1-8, 4-5, 2-7, 3-6 ; le 1er et le 2e dans des moitiés opposées). *`Integration/Service/EliminationTableauTest`* : têtes de série selon le classement de la phase précédente (équipes insérées dans le désordre) ; 8 équipes : quarts 1-8, 4-5, 2-7, 3-6 ; 12 équipes : barrages 5-12, 6-11, 7-10, 8-9 et les 4 premières exemptées contre les vainqueurs ; 9 équipes : un seul barrage 8-9 ; petite finale (option de la phase) entre les perdants des demi-finales, jouée avec la finale, aussi dans un tableau de 4 équipes (défaut corrigé le 16/10/2026 : les demi-finales y sont le premier tour), absente sous 4 équipes. Migration `Version20261015110000` (`partie.parent_perdants`).

**JFI-01 à JFI-05 — Journée des finales** ✅ *`Integration/Service/JourneeFinalesTest`* (lot 6e, C3, D45, D46) : finales seules : petites finales et finales de tous les tableaux le même jour au gymnase choisi, par créneaux d'autant de matchs que de terrains, du niveau le plus bas au niveau 1 (finale du niveau 1 en dernier), tours précédents un par semaine depuis le début de la phase ; un terrain : un match par créneau ; demi-finales et finales : toutes les demi-finales d'abord, les finales à un créneau suivant même avec des terrains libres ; régénérer un tableau ne déplace pas la finale d'un autre tableau retouchée à la main (un match avec un score n'est jamais déplacé) ; date trop proche du début de la phase pour les tours précédents → message (au singulier pour un seul tour), aucune journée, aussi signalé par la vérification de la saison ; vérification : gymnase manquant, date hors de la phase.

**WIZ-28, WIZ-29 — Journée des finales dans le wizard** ✅ *`Fonctionnel/Admin/WizardJourneeFinalesTest`* : à l'étape 3, réglages proposés pour le tableau seulement ; tours, gymnase, date, heure, terrains et durée enregistrés dans les options de la phase, retirés quand la case est décochée ; case cochée sans gymnase ni date, ou 0 terrain → 422 et messages sous les champs, rien d'enregistré ; case décochée : réglages masqués non contrôlés (pas d'erreur sur un champ invisible).

**WIZ-30 — Étape Phases, textes selon le type coché** ✅ *`Fonctionnel/Admin/WizardFormatMatchTest`* : la légende du bloc des poules (Poules, Tableaux, Matchs de barrage) et le bouton d'ajout (Ajouter une poule, un tableau, un match) ont une variante par type de phase ; celle du type enregistré est active (les autres portent `.type-inactif`), et la règle CSS `:has()` affiche celle du type coché avant l'enregistrement ; colonne « Nombre d'équipes prévues ». WIZ-23 vérifie la variante active « Ajouter un match » d'une phase de barrages.

**ALR-01 à ALR-07 — Championnat aller-retour** ✅ (lot 6f-2, A2, D47) : option `aller_retour` d'une phase de championnat (`Phase::estAllerRetour`).
- **ALR-01 à ALR-06** *`Integration/Service/AllerRetourTest`* : 4, 5 (impair) et 6 équipes : 2(n−1) journées (2n si n est impair) et n(n−1) matchs, chaque équipe reçoit chaque autre une fois, personne ne joue deux fois par journée (ALR-01) ; la journée k + (nombre de journées de l'aller) reprend la journée k, domicile et extérieur inversés (ALR-02) ; autant de réceptions que de déplacements, dates dans la semaine de la journée (ALR-03) ; le générateur annonce le double de matchs et de journées (ALR-04) ; phase trop courte : message, aucune journée créée (ALR-05) ; replacer les matchs à venir conserve le match joué dans sa journée et son sens, et garde le retour en miroir (ALR-06).
- **ALR-07** *`Fonctionnel/Admin/WizardAllerRetourTest`* : à l'étape 3, « Matchs aller et retour » proposé pour les championnats seulement, décoché par défaut ; enregistré pour la phase choisie, retiré en décochant ; ignoré pour un tableau à élimination.
L'aller simple (MAT-01 à MAT-06) est inchangé : mêmes tests, mêmes résultats.

**SET-01 à SET-05 — Format de match : 3 ou 2 sets gagnants, durée d'un match** ✅ (lot 6f, D48, D49) : option `sets_gagnants` de toute phase (3 par défaut, 2 en format court), lue par `Phase::getSetsGagnants` et passée au sport (`RegleSport`, paramètre `$setsGagnants`).
- **SET-01** *`Unitaire/Competition/Sport/VolleyDeuxSetsTest`* : en 2 sets gagnants, scores complets 2-0, 2-1, 1-2, 0-2, matchs interrompus 1-1, 1-0, 0-1 (sans point), forfait −1 / 2 ; refusés : 3-0, 3-1, 2-2, 0-0, 2-3, forfait à 3 ; 2-1 est complet en 2 sets et interrompu en 3 sets ; message d'erreur adapté au format ; points au barème de test (2-0 forte, 2-1 faible, 1-2 défaite forte, 0-2 défaite faible, forfait) ; sets comptés pour le départage.
- **SET-02** *`Integration/Service/FormatMatchClassementTest`* : le classement d'une poule suit le format de sa phase (points, forfait, interrompu 1-1 et 1-0 sans point, sets gagnés et perdus) ; le même score 2-1 vaut 4 et 2 points en 2 sets, 2 et 1 en 3 sets (règle 1R) ; le format court d'une phase ne touche pas les poules d'une autre phase.
- **SET-03** *`Fonctionnel/Front/SaisieScoreFormatCourtTest`* : l'API de score d'un match d'une phase à 2 sets accepte 2-0, 2-1, 1-2, 0-2, 1-1, 1-0 ; refuse (400, rien d'enregistré, message « 2 sets ») 3-0, 3-1, 2-2, 0-0, 2-3 ; forfait enregistré −1 / 2 (« F - 2 ») ; le bouton « Saisir » porte `data-sets-gagnants="2"` pour la fenêtre de saisie.
- **SET-04** *`Fonctionnel/Admin/WizardFormatMatchTest`* : à l'étape 3, « Format des matchs » proposé pour chaque phase (3 sets gagnants par défaut) ; 2 sets gagnants enregistré dans les options de la seule phase choisie, retiré en revenant à 3 ; valeur inconnue refusée (422), rien d'enregistré.
- **SET-05** *`Unitaire/Entity/PhaseDureeMatchTest`* et *`WizardFormatMatchTest`* : durée d'un match proposée pour la journée des finales selon le format (120 minutes en 3 sets gagnants, 75 en 2 sets), conservée si elle est enregistrée ; le champ de l'étape 3 est pré-rempli en conséquence.
La fenêtre de saisie elle-même (champs 0 à 2, aide du match interrompu, résumé des points) s'exécute dans le navigateur : à couvrir par E2E-01.

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
| `composer validate --no-check-publish` | bloquant | — | `--strict` exigerait `name`/`description`, inutiles pour une application |
| `composer audit`, `bin/console importmap:audit` | **informatif** (job `securite`, `continue-on-error`) | bloquant après la mise à jour des dépendances | Au 10/10/2026 : ~40 vulnérabilités connues côté PHP (PhpSpreadsheet, Twig, composants Symfony) et 3 côté JavaScript |
| Couverture (pcov) | publiée, non bloquante | seuils par fichier critique (§6.3) | |
| Infection (tests de mutation) | — | sur `ClassementService` et l'API de score | Vérifie que les tests détectent vraiment les changements de règle |

Raccourcis Composer (exécutables à l'identique en local et en CI) :

```
composer qualite   → lint:twig, lint:yaml, lint:container, composer validate (phpstan et php-cs-fixer au lot 5)
composer tests     → bin/phpunit (hors groupes lent et anomalie)
composer tests:tout→ bin/phpunit (avec le groupe lent)
composer securite  → composer audit + importmap:audit
En local, dans Docker : docker compose exec php composer tests
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
| **0 — Socle** ✅ | `dama/doctrine-test-bundle`, suites PHPUnit, factories manquantes, story `ChampionnatDeTest`, classes de base et connexion, workflow GitHub Actions (`qualite`, `tests`, `securite` informatif), raccourcis Composer, cache en mémoire en test | 20 tests verts en ~3 s ; `composer tests` en local dans Docker | fait le 10/10/2026 |
| **Clubs** ✅ | SEC-11 à SEC-14, SCO-13 à 15, NOM-*, CLU-*, ADC-*, REP-* ; story enrichie (clubs, `resp.club` par saison) ; relecture de l'expert sécurité | 77 tests verts (branche `clubs`) | fait le 11/10/2026 |
| **1 — Accès** ✅ | SEC-01 à SEC-10, FUM-01/02 | La faille `^/Admin` réintroduite volontairement fait échouer la suite (vérifié) ; 108 tests verts | fait le 12/10/2026 |
| **2 — Scores et classement** ✅ | SCO-*, CLA-* ; anomalies A1–A7 arbitrées et corrigées | 181 tests verts ; le départage et le set-average échouent avec l'ancien code (vérifié) | fait le 13/10/2026 |
| **3 — Données personnelles et admin** ✅ | CON-*, COM-*, SUP-* | Matrice de visibilité figée par les tests ; 210 tests verts | fait le 13/10/2026 (seuils de couverture : reportés) |
| **4 — Calculs et fonctions annexes** ✅ | JOU-*, MAT-*, PHA-*, ICS-*, IMP-*, GYM-*, MEQ-*, ADM-01 ; job de nuit (tests `lent`) | Génération de calendrier couverte ; 249 tests verts + 2 lents | fait le 12/10/2026 |
| **Contraste des alertes de l'admin** ✅ | ADM-02 : relecture du webdesigner (contraste, icônes, lecteurs d'écran) | 256 tests verts + 2 lents (`main`) | fait le 13/10/2026 |
| **Zones de montée / descente du classement** ✅ | CLA-17 : pas de zone de descente sans poule de niveau inférieur (ni de montée sans niveau supérieur) | 258 tests verts + 2 lents (`main`) | fait le 15/10/2026 |
| **Formats de compétition, lot 1** ✅ | NOT-*, SPT-01, FMT-01 (§4.16) ; `RegleScoreTest` devenu `VolleyTest` ; génération des calendriers déplacée dans `App\Competition\Format` sans changement de comportement | 309 tests verts + 2 lents (branche `formats-competition-lot1`) | fait le 13/10/2026 |
| **Formats de compétition, lot 2** ✅ | CLA-12 à CLA-16 (§4.3) : départage paramétrable par saison (quotient de sets, mini-classement), barème surchargeable par phase ; migration `Version20261013100000` | 319 tests verts + 2 lents (branche `formats-competition-lot2`) | fait le 13/10/2026 |
| **Formats de compétition, lot 3** ✅ | COD-01, REG-01 (§4.16) : codes des phases et des poules, règles de passage par défaut ; PHA-06 à PHA-09, VER-01 (§4.8) : clôture par les règles de passage, message relu par l'ergonome et le webdesigner ; fusion de `main` (ADM-02) | 351 tests verts + 2 lents (branche `formats-competition`) | fait le 13/10/2026 |
| **Formats de compétition, lot 4** (en cours) | SPT-02, MOD-01, CPS-01 à CPS-03, WIZ-01 (§4.16) : sport de la saison, modèle « Format BLVB actuel » (fin de `config/app/saison.yaml`), copie d'une saison, brouillon, wizard complet (WIZ-02 à WIZ-17), modification d'une saison créée, fin de `SaisonListener`, relectures ergonome, webdesigner et sécurité (WIZ-14 à WIZ-17) | 385 tests verts + 2 lents (branche `formats-competition-lot4`) | en cours |
| **Formats de compétition, lot 5** ✅ | MEP-01 à MEP-24 (étapes Poules, Journées, Matchs, replacement des matchs à venir, clôture sans création automatique) ; WIZ-18 à WIZ-21 ; PHA-10 et PHA-11 ; MEP-01 à MEP-08 (détail) : mise en place, étape « Poules » (composition de la phase, origine des équipes, équipes à placer, phase lancée en lecture seule, sécurité) ; MEP-09 à MEP-15 : étape « Journées » ; MEP-16 à MEP-23 : étape « Matchs » (dont replacement des matchs à venir, D41) ; PHA-03 mis à jour, PHA-10 et PHA-11 : clôture sans création automatique, avertissements avant clôture, conditions côté serveur (D37, D40) (génération, régénération, phase trop courte, régénération impossible sans perte, fermetures, sécurité et verrou) ; WIZ-11 mis à jour | 418 tests verts + 2 lents (branche `formats-competition-lot5`) | ✅ |
| **Formats de compétition, lot 6** (en cours) | BAR-01 à BAR-06, MOD-02, WIZ-22 à WIZ-29, ELI-01 à ELI-04, JFI-01 à JFI-05, SET-01 à SET-05, ALR-01 à ALR-07, BAR-07 à BAR-09, VER-02, ADJ-01 à ADJ-09 (§4.16), SCO-17 (§4.2) : wizard en mode ajout d'une saison lancée (6h, D51), barrages de fin de saison sans effet sur la phase finale (6g, D50), championnat aller-retour (6f-2), format de match à 2 ou 3 sets gagnants (6f-1), journée des finales (6e), tableau à élimination complet (têtes de série, exemptions, petite finale), phase de barrages (`PhaseType::BARRAGE`, `Phase.options`, générateur `Barrage`, issues de match, vérification des passages), modèle « BLVB avec barrages » proposé dans le wizard (étapes Phases, Passages, vérification) ; migrations `Version20261015100000` et `Version20261015110000` | 561 tests verts + 2 lents (branche `formats-competition-lot6`, avec MAI-01 et MAI-02 venus de `main`) | étapes 6a à 6d le 15/10/2026, 6e le 16/10/2026, 6f-1 (SET-01 à SET-05), 6f-2 (ALR-01 à ALR-07) et 6g (BAR-07 à BAR-09, VER-02) le 19/10/2026 |
| **Expéditeur des e-mails** ✅ | MAI-01 : un seul expéditeur, `blvb@vblc.fr` (anciennement `admin@blvb.fr` et `tech@vblc.fr`, refusés par le SMTP d'OVH) | 259 tests verts + 2 lents (`main`) | fait le 16/10/2026 |
| **Rappel des scores non saisis** ✅ | MAI-02 : e-mail aux capitaines et responsables de club deux jours après un match sans score (`app:scores:rappeler`, cron quotidien) | 269 tests verts + 2 lents (`main`) | fait le 17/10/2026 |
| **5 — Durcissement** | PHPStan niveau 6+, PHP-CS-Fixer étendu, Infection sur le classement, E2E Playwright | Baseline PHPStan réduite | 1 j |

Ordre conseillé : 0 → 1 → 2 (les lots 1 et 2 couvrent les risques P0). Chaque lot = une pull request relue, CI verte.

---

## 8. Anomalies relevées pendant l'analyse — arbitrées et corrigées (lot 2, octobre 2026)

Ces points ont été trouvés en lisant le code pour écrire ces spécifications. **Décisions** : A1 corrigée avant le lot 2 ; A2 : matchs complets **et** matchs interrompus selon la règle 1R, pour tous ; A3 : départage par le **dernier match direct joué**, puis ordre alphabétique ; A4 : 403 ; A5 : match à venir réservé à l'admin ; A6 : saison affichée protégée côté serveur ; A7 : inscription libre **fermée** (`/register` renvoie vers la connexion).

| # | Où | Constat | Proposition |
|---|---|---|---|
| A1 | `ClassementService::pointsPourMatch` | Forfait : le vainqueur (3 contre −1) tombe dans le cas par défaut et reçoit les points d'une **défaite faible** ; l'équipe forfait reçoit aussi une défaite faible ; `Saison::points_forfait` (−3 par défaut) n'est **jamais utilisé** | Vainqueur = victoire forte ; forfait = `points_forfait`. À confirmer avec le règlement |
| A2 | `FrontController::api_score_update` | Scores impossibles au volley acceptés (3–3, 0–0, 2–1…) : seule la plage 0–3 est contrôlée | Exiger exactement une équipe à 3 sets et l'autre entre 0 et 2 |
| A3 | `ClassementService::confrontationDirecte` | Convention de signe vraisemblablement inversée pour `usort` (le vainqueur du match direct serait classé derrière) ; un match direct non joué est compté comme une victoire de l'équipe qui se déplace ; en aller-retour, seul le premier match compte | Vérifier par CLA-07/08 puis corriger ; préciser la règle aller-retour selon le règlement |
| A4 | API de score | Refus de droits renvoyé en **400** (au lieu de 403) | 403 pour un refus de droits, 400 pour une donnée invalide |
| A5 | API de score | L'API accepte un score sur un match **à venir** (l'interface ne le propose qu'après la date) | Refuser pour un capitaine, autoriser pour un admin (correction d'erreur de date) |
| A6 | `SaisonController::delete` | La saison affichée sur le site n'est protégée que dans l'interface ; un POST direct la supprime | Refuser aussi côté serveur |
| A7 | `/register` | L'inscription libre est active (route accessible) alors que les liens sont commentés dans les menus | Décider : fermer la route ou l'assumer (avec vérification d'e-mail) |

### Défauts trouvés et corrigés par le lot 1

| Où | Constat | Correction |
|---|---|---|
| `JourneeController::createJournee` | « Créer les journées » ne vérifiait aucun jeton CSRF | Jeton dans le formulaire (`createjournee<poule>`), vérifié ; message « La page a expiré » |
| API du calendrier de l'admin (journées et matchs : PUT, DELETE, POST) | Le jeton envoyé par `calendarController.js` (en-tête `X-CSRF-TOKEN`) n'était jamais vérifié ; la suppression d'un match depuis la fenêtre ne l'envoyait pas | Vérification commune (`JetonApiTrait`), 403 sans jeton ; en-tête ajouté à la suppression |
| Suppressions groupées, génération des matchs, calendrier de la phase | Jeton faux : rien n'était fait, sans le dire | Message « La page a expiré : rechargez-la puis recommencez. Rien n'a été modifié. » |
| `/forgot-password` | Reste d'un modèle : erreur 500 pour tout le monde | Redirection permanente vers `/reset-password` |
| Formulaires (mot de passe, inscription, imports, comptes) | Contraintes en tableau : avertissements d'obsolescence Symfony 7.3, messages en anglais | Arguments nommés, messages en français |

### Défauts trouvés et corrigés par le lot 4

| Où | Constat | Correction |
|---|---|---|
| `CalendarIcsGenerator` | Un retour à la ligne dans le nom d'une équipe ajoutait une ligne (propriété arbitraire) à l'agenda | Caractères de contrôle remplacés par une espace, texte échappé (RFC 5545) |
| `MembreEquipeRepository::findEquipesOuCapitaine` | Capitaine de deux équipes : « Mon équipe » par défaut variait selon l'ordre de la base | Tri par nom d'équipe |
| `JourneeService::creerJournees` | Phase trop courte : le message d'erreur était calculé mais jamais renvoyé, aucun avertissement affiché | Message renvoyé |
| Clôture de phase (`PhaseController::cloturer`, `PhaseService`) | Une seconde clôture (double clic, second onglet) redistribuait les équipes et recréait les matchs de la phase suivante, scores perdus | Refus dans le contrôleur, service sans effet sur une phase close |
| `PhaseService::cloturerEtBasculer` | Les classements étaient lus sans être recalculés : une poule sans ligne de classement n'envoyait aucune équipe dans la phase suivante | Classements recalculés avant la répartition ; recherche de la phase suivante commune au contrôleur et au service (`phaseSuivante`) |
| Optimiseur (`PartieService::calculerScoreCombine`) | Poule impaire : une équipe pouvait recevoir 3 fois pour 1 déplacement | Pénalité de déséquilibre domicile/extérieur (écart ≤ 1 par équipe, derbys du même gymnase exclus) |

---

## 9. Définition de « terminé » pour une modification

Une pull request est fusionnable quand :
1. la CI est verte (qualité + tests) ;
2. tout nouveau comportement a au moins un test, tout correctif de bogue a le test qui le reproduisait ;
3. aucun test n'est ignoré sans justification ;
4. la baseline PHPStan n'a pas grossi ;
5. les données de test sont fabriquées par le test (aucune donnée réelle, aucune adresse hors `@blvb.test`).
