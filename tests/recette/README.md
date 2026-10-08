# Base de recette

Une base locale **séparée** (`blvb_recette`) pour dérouler des tests fonctionnels sérieux, sans toucher à la base
de développement (`blvb_new`). Elle est la copie de la base de développement, plus une **« Saison 2026-2027 test »**
(saison par défaut dans cette base) et une **« Saison 2025-2026 test »** archivée.

## Commandes

| Commande | Effet |
|---|---|
| `bin/recette reinitialiser` | recrée `blvb_recette` depuis `blvb_new`, puis charge les données de test (≈ 12 s) |
| `bin/recette utiliser` | l'application (conteneur `php`) pointe sur `blvb_recette` |
| `bin/recette dev` | l'application revient sur `blvb_new` |

Scénarios (`bin/recette reinitialiser --scenario=…`) :

- `en-cours` (défaut) : phase 1 en cours, matchs passés saisis, matchs à venir ;
- `phase1-terminee` : tous les matchs de la phase 1 saisis, phase non clôturée (tester la **clôture** et les montées/descentes) ;
- `vierge` : structure, équipes et journées sans aucun match (tester la **génération / l'optimisation** des matchs).

Les dates sont **relatives à aujourd'hui** : la saison démarre 2 semaines avant la semaine en cours, il y a donc toujours
une journée en cours, des journées passées et des journées à venir. Relancer `reinitialiser` rafraîchit les dates.
Les vacances scolaires sont celles de 2026-2027 (Toussaint, Noël, hiver, Pâques).

La commande refuse de tourner sur une base dont le nom ne contient pas « recette ».

## Comptes

Mot de passe commun : **`Recette-2026!`**. Tous les e-mails se terminent par `@blvb.test`.

| Compte | Situation |
|---|---|
| `admin` | administrateur |
| `cap.a1` | capitaine de ARRATS (poule A), téléphone renseigné ; en 2025-2026, capitaine de ADOUR |
| `cap.a2` | capitaine de BIDART VB, **sans téléphone** |
| `cap.a4`, `cap.a6`, `cap.a7` | capitaines d'équipes de la poule A |
| `cap.a5` | capitaine de GOIZ ARGI : coordonnées **seulement sur le compte** (la fiche n'en a pas) |
| `cap.b1`, `cap.c1`, `cap.d1` | capitaines de KOSKO ALAI (B), SOKOA (C), ADOUR (D) ; `cap.d1` était simple joueur en 2025-2026 |
| `cap.double` | capitaine de **deux équipes** : LAPURDI VOLLEY (B) et BAIGORRI (D) |
| `joueur.a1`, `joueur.a2` | simples joueurs (non capitaines) de ARRATS et de BIDART VB |
| `mobile` | joueur de CAPBRETON LOISIR en 2026-2027, de CHALOSSE en 2025-2026 |
| `user.sansfiche` | compte sans fiche joueur |
| `user.nonverifie` | compte dont l'e-mail n'est pas vérifié |
| `autre.compte` | compte lié à la fiche « Import Lié » (cas d'import) |

## Données

- **Poules de la phase 1** : A = 8 équipes (7 journées), B = 6, C = 5 (nombre impair, une équipe au repos par journée),
  D = 4 (terminée). **Phase 2** : poule A remplie avec des journées mais sans match, autres poules vides. **Phase finale** vide.
- **7 gymnases** « Test … » avec créneaux, capacités et priorités variés ; un gymnase sans créneau (C5), une équipe sans gymnase (D4).
- Équipes particulières : A3 (capitaine sans compte, fiche seule), A8 (aucun capitaine), B6 (aucun joueur).
- Classements calculés par l'application ; un forfait enregistré (-1 / 3).
- Les matchs sont générés par les services de l'application (journées, calendrier optimisé), puis quelques-uns sont déplacés
  pour créer les cas ci-dessous. La commande **affiche la liste exacte** de ces matchs à la fin du chargement.

## Cas préparés → ce qu'ils permettent de tester

| Cas | Où le voir |
|---|---|
| Match joué **sans score** (bouton « Saisir » pour son capitaine et l'admin) | accueil, calendrier |
| Surcharge d'un gymnase (plusieurs matchs pour 1 terrain) | calendrier par gymnase, « Seulement les dates à problème » |
| Match **hors créneau** | calendrier par gymnase |
| Créneau **non prioritaire** | calendrier par gymnase |
| Match placé **pendant les vacances** | calendrier par gymnase |
| Dates de créneau **libres** (dont pendant les vacances) | calendrier par gymnase |
| Match **sans gymnase** ; équipe sans gymnase | calendrier, fiche équipe, liste des équipes |
| Accès aux coordonnées : capitaine adverse / autre poule / simple joueur / admin | fiche équipe, accueil et calendrier (ligne « Capitaine : … » des matchs d'un capitaine) |
| Capitaine absent, capitaine sans compte, sans téléphone, repli fiche → compte | fiche équipe |
| Changement d'équipe entre saisons (composition différente) | sélecteur de saison, fiche équipe, agenda `.ics` par saison |
| Poule vide, poule sans match, phase vide | classement, calendrier, équipes |
| « Mon équipe » (poule ouverte, surlignage, filtre du gymnase) | toutes les pages (choix gardé dans le navigateur) |
| Import Excel d'utilisateurs | `/admin/user/excel/explication` avec `var/recette/import_utilisateurs.xlsx` |

### Fichier d'import Excel

Généré dans `var/recette/import_utilisateurs.xlsx` (dans le conteneur : `/app/var/recette/…`). Résultat attendu à l'import :

| Ligne | Attendu |
|---|---|
| `nouveau.joueur@…` | compte **et** fiche créés |
| `import.existant@…` | compte créé, fiche existante **reliée** |
| `IMPORT.CASSE@…` | compte créé, fiche reliée malgré la casse |
| `import.lie@…` | compte créé, fiche **non reliée** (déjà liée à un autre compte), avertissement |
| `user.sansfiche@…` | rejetée (compte existant) |
| `pas-un-email` | rejetée (« Adresse e-mail invalide ») |
| e-mail vide | rejetée (format de ligne incorrect) |

Totaux attendus : **4 comptes créés, 1 fiche créée, 2 fiches reliées, 1 avertissement, 3 lignes rejetées**.

## À savoir

- `bin/recette reinitialiser` **écrase** `blvb_recette` ; la base de développement n'est jamais modifiée.
- Après `utiliser` ou `dev`, le cache de l'application est vidé (la liste des saisons y est mise en cache).
- Les tests automatisés (PHPUnit, dossier `tests/`) utilisent leur propre base (`blvb_new_test`), indépendante de celle-ci.
