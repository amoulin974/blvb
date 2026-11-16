# BLVB : gestion du championnat de volley

Site du championnat de volley-ball loisir Basco Landais ([www.blvb.fr](https://www.blvb.fr)) : création des saisons,
des équipes et des matchs, saisie des scores, classements, calendriers et règlement.

## Ce que fait le site

| Qui | Ce qu'il peut faire |
|---|---|
| Visiteur | Calendriers, classements, équipes, clubs, règlement ; terrains libres par gymnase ; choisir son équipe ; ajouter ses matchs à son agenda |
| Joueur | En plus : composition de son équipe et contact du capitaine |
| Capitaine | En plus : saisir le score, contact du capitaine adverse, gérer la composition, rappel par courriel si un score manque |
| Responsable de club | En plus : scores de toutes les équipes du club, fiche du club, fermetures de gymnase |
| Administrateur | Tableau de bord, création de saison (wizard, formats de compétition), règlement, paramètres du site |

Volley Stats (page du match par équipe : composition de chaque set, score point par point, statistiques) est réalisé et
n'est pas encore annoncé : il doit d'abord être essayé avec quelques joueurs.

## Technologies

Symfony 7.4, PHP 8.4 (FrankenPHP), Doctrine, MySQL 8.4, Twig, Stimulus et Turbo, AssetMapper, Tailwind et daisyUI, Docker.

## Démarrer en local

Seul Docker est nécessaire. Sous Windows, travailler dans WSL : depuis `\\wsl.localhost\…`, git refuse le dépôt
(« dubious ownership »).

```bash
git clone https://github.com/amoulin974/blvb.git
cd blvb
docker compose up -d --build   # installe les dépendances, applique les migrations, compile le CSS
bin/recette reinitialiser      # base de recette : une copie de la base locale, plus des données de test
bin/recette utiliser           # l'application utilise cette base (retour : bin/recette dev)
```

Le site est sur <https://localhost>. Tous les comptes de test ont le mot de passe `Recette-2026!` ; le compte
administrateur est `admin@blvb.test`. Les autres comptes (capitaine, joueur, responsable) et les scénarios de données
sont dans [`tests/recette/README.md`](tests/recette/README.md).

Ces mots de passe, comme ceux de `compose.yaml`, ne servent qu'en local : ne jamais les réutiliser sur un serveur.

## Au quotidien

Les commandes se lancent à la racine du projet, conteneurs démarrés.

| Besoin | Commande |
|---|---|
| Lancer les tests | `docker compose exec php composer tests` |
| Lancer tous les tests, lents compris | `docker compose exec php composer tests:tout` |
| Contrôler Twig, YAML et les services | `docker compose exec php composer qualite` |
| Recompiler le CSS après un changement de style | `docker compose exec php php bin/console tailwind:build` |
| Voir l'état de la base après un changement de branche | `bin/base etat` |
| Mettre la base dans l'état attendu par la branche | `bin/base aligner` |

`bin/base aligner` fait une sauvegarde, annule les migrations absentes de la branche et applique les siennes ;
`bin/base restaurer` remet la dernière sauvegarde.

Les tests utilisent leur propre base, recréée à chaque lancement. Avant le premier lancement, il faut donner les droits
sur cette base et, en cas d'échec à sa remise à zéro, vérifier sur quelle base tourne l'application : voir
[`tests/README.md`](tests/README.md).

## Organisation du code

| Dossier | Contenu |
|---|---|
| `src/Competition/` | Formats de compétition, phases, passages, classement, règles du sport |
| `src/Reglement/` | Règlement par saison : édition, versions, éléments calculés |
| `src/VolleyStats/` | Page du match : journal des événements, état du set, statistiques |
| `src/Service/`, `src/Security/` | Services du site ; droits d'accès (voters) |
| `src/Controller/Front/`, `src/Controller/Admin/` | Site public, administration |
| `templates/front/`, `templates/admin/` | Gabarits Twig correspondants |
| `assets/controllers/` | Contrôleurs Stimulus |
| `tests/` | Tests unitaires, d'intégration et fonctionnels |

## Documentation

| Document | Sujet |
|---|---|
| [`tests/README.md`](tests/README.md) | Lancer les tests, résoudre les blocages |
| [`tests/SPECIFICATIONS.md`](tests/SPECIFICATIONS.md) | Ce que vérifie chaque test |
| [`tests/recette/README.md`](tests/recette/README.md) | Base de recette, comptes et scénarios |
| [`docs/formats-competition.md`](docs/formats-competition.md) | Catalogue des formats de compétition |
| [`docs/wizard-saison/methode.md`](docs/wizard-saison/methode.md) | Méthode de travail et journal du chantier |
| [`docs/wizard-saison/decisions.md`](docs/wizard-saison/decisions.md) | Décisions de conception, numérotées |
| [`docs/wizard-saison/lots.md`](docs/wizard-saison/lots.md) | État du chantier, section « Reprendre ici » |
| [`docs/volley-stats/`](docs/volley-stats/) | Analyse et spécifications de Volley Stats |
| [`docs/presentation-par-role.md`](docs/presentation-par-role.md) | Présentation des fonctionnalités selon le rôle |
| [`CLAUDE.md`](CLAUDE.md) | Consignes permanentes du projet |

## Règles du projet

- **Français partout** : code métier, libellés, documentation, messages de commit (`domaine : description en minuscules`).
- **Tests** : toute modification de test met à jour `tests/SPECIFICATIONS.md` dans le même commit.
- **Décisions** : aucune décision de conception sans l'avoir consignée dans `docs/wizard-saison/decisions.md`.
- **Secrets** : ce dépôt est public. Aucun mot de passe de production, aucune donnée personnelle dans le code ou la
  documentation.

## Mise en production

La branche `prod` est gérée par Francis seul : on s'arrête à `main`. La procédure à lui transmettre est dans
[`docs/wizard-saison/deploiement-reglement.md`](docs/wizard-saison/deploiement-reglement.md).

## Licence

Propriétaire (`composer.json`) : aucune réutilisation sans autorisation.
