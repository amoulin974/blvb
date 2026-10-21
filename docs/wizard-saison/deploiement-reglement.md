# Mise en production des formats de compétition et du règlement par saison : checklist pour Francis

Établie par l'agent `deploiement` après la fusion de `formats-competition` dans `main` (commit 2ef6fd6, lots 1 à 6 des
formats, wizard de saison, règlement par saison R1 à R5). Seul Francis fusionne dans `prod`.

**Verdict : prêt avec réserves.** Fusion de `main` dans `prod` simulée (`git merge-tree`) : aucun conflit, `.env`
compris. Aucune nouvelle variable d'environnement. Nouveautés : paquet Composer `symfony/html-sanitizer`, une
soixantaine de paquets importmap (éditeur TipTap 3), dossier `public/uploads/reglement`, 9 migrations (ajouts de
colonnes et de tables seulement).

## 0. Fusion de `main` dans `prod` (Francis)

Prendre le code de `main`, garder de `prod` seulement les fichiers propres au serveur : `.env`, `Dockerfile`,
`frankenphp/Caddyfile`, `frankenphp/Caddyfile.old`, `frankenphp/docker-entrypoint.sh`, `prefix: /blvb` de
`config/routes.yaml`, `trusted_proxies` / `trusted_headers` / `assets.base_path: '/blvb'` de
`config/packages/framework.yaml`, `^/(blvb/)?admin` de `config/packages/security.yaml`. Accepter les suppressions venues
de `main` (`config/app/saison.yaml`, `SaisonListener`, `front/reglement_2025.html.twig`…). Contrôles :

```bash
git diff --stat origin/main prod      # seulement les fichiers serveur (et les fichiers Eclipse .buildpath, .project, .settings/)
git grep -nE '^(<<<<<<< |=======$|>>>>>>> )' prod -- . ':!vendor'     # rien
```

## 1 à 12. Sur le serveur, dans le dossier du projet

Le dossier est monté dans le conteneur (`.:/app`) et le site tourne en environnement dev : **pas de `--no-dev`**. Du
`git pull` à la fin des migrations, le site est en panne (quelques minutes) : enchaîner les étapes 3 à 9, prévenir le
bureau.

```bash
# 1. Vérifications préalables
docker compose exec php php bin/console about | grep -E 'Environment|Debug'
docker compose exec php php -r 'require "vendor/autoload.php"; (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env"); echo ($_SERVER["APP_SECRET"] ?? "") === "" ? "APP_SECRET VIDE\n" : "APP_SECRET défini\n";'
docker compose exec php php bin/console dbal:run-sql "SELECT DATABASE()"
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|Latest|New'
git log -1 --oneline        # noter le commit actuel (3bafba4 attendu) pour un retour arrière

# 2. Sauvegarde de la base (remplacer blvb_new si SELECT DATABASE() donne un autre nom)
docker compose exec -T database sh -c 'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" blvb_new' > ~/blvb-avant-formats-$(date +%F-%H%M).sql
ls -lh ~/blvb-avant-formats-*.sql && tail -1 ~/blvb-avant-formats-*.sql   # doit finir par « -- Dump completed »

# 3. Code
git pull

# 4. Dépendances PHP, dans le conteneur
docker compose exec php composer install --prefer-dist --no-interaction --no-progress

# 5. Dépendances JavaScript (éditeur TipTap 3)
docker compose exec php php bin/console importmap:install

# 6. CSS et assets (l'entrypoint du serveur ne compile pas Tailwind)
docker compose exec php php bin/console tailwind:build --minify
docker compose exec php php bin/console asset-map:compile

# 7. Migrations (9 nouvelles), puis contrôle
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|New'   # New : 0

# 8. Dossier des images de l'éditeur du règlement
docker compose exec php sh -c 'mkdir -p public/uploads/reglement && chmod 775 public/uploads/reglement && ls -ld public/uploads/reglement'

# 9. Cache et redémarrage (le worker FrankenPHP garde les anciennes classes)
docker compose exec php php bin/console cache:clear
docker compose restart php
docker compose logs --tail=50 php        # attendu : « PHP app ready! », aucune erreur

# 10. Reprise du règlement 2025 : simulation, puis enregistrement
#     (attendu : 11 parties, 15 règles, 6 images, ancre #regle-1r, aucune image absente)
docker compose exec php php bin/console app:reglement:importer
docker compose exec php php bin/console app:reglement:importer --force

# 11. Facultatif, si le bureau le décide : barrages du modèle sur la saison en cours (simulation, puis --force)
docker compose exec php php bin/console app:saison:ajouter-barrages
docker compose exec php php bin/console app:saison:ajouter-barrages --force
# ou seulement les barrages de fin de saison : --seulement=fin --force

# 12. Paquets : aucune faille attendue
docker compose exec php composer audit
docker compose exec php php bin/console importmap:audit
```

Ne jamais lancer `app:recette:charger` en production.

### Migrations

| Migration | Effet |
|---|---|
| Version20261013100000 | `saison.departage`, `phase.bareme` ; départage actuel inscrit dans chaque saison |
| Version20261013120000 | codes des phases et des poules (P1, P2, F ; A, B…), calculés pour les saisons existantes |
| Version20261013130000 | table `regle_de_passage`, règles du schéma 2025 générées pour les saisons existantes |
| Version20261013140000 | `saison.sport` (« volley ») |
| Version20261013150000 | `saison.brouillon`, `saison.etape_wizard`, `poule.nb_equipes_prevu` |
| Version20261014180000 | `saison.depart_libelle`, `saison.modifie_le` |
| Version20261015100000 | `phase.options` |
| Version20261015110000 | `partie.parent_perdants` |
| Version20261020164942 | tables `reglement` et `reglement_version` |

## Vérifications en ligne

- `/blvb/` : accueil, styles chargés ; `/blvb/reglement` : texte 2025, sommaire, images en `/blvb/images/reglement/…` ;
  sélecteur de saison : le retour reste sous `/blvb` ; fenêtre de saisie du score : « Lire la règle 1R » mène à `#regle-1r`.
- `/blvb/admin` : liste des saisons ; « Règlement » du menu : l'éditeur se charge, enregistrer, historique ; une image
  envoyée a une adresse `/blvb/uploads/reglement/…`.
- Classements et calendriers de la saison en cours inchangés.

## Retour arrière

Code seul (cas normal, les migrations n'ont fait qu'ajouter) :

```bash
git checkout 3bafba4          # le commit noté à l'étape 1
docker compose exec php composer install --prefer-dist --no-interaction --no-progress
docker compose exec php php bin/console importmap:install
docker compose exec php php bin/console tailwind:build --minify
docker compose exec php php bin/console asset-map:compile
docker compose exec php php bin/console cache:clear
docker compose restart php
```

Base, seulement si une migration a échoué ou si des données sont abîmées (après le retour du code ; ce qui a été saisi
depuis le déploiement est perdu) :

```bash
docker compose exec -T database sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" blvb_new' < ~/blvb-avant-formats-AAAA-MM-JJ-HHMM.sql
docker compose restart php
```

## Réserves, à traiter après

- Mot de passe SMTP en clair dans le `.env` versionné de `prod` : le déplacer dans `.env.local` et le changer.
- `APP_SECRET` : le définir s'il est vide (étape 1).
- Port MySQL 3307 publié par `compose.yaml` : vérifier que le pare-feu le ferme depuis l'extérieur.
- Cron du rappel des scores : `0 9 * * * cd /chemin/du/projet && docker compose exec -T php php bin/console app:scores:rappeler`.
- Données 2025 (back-office) : journées de la phase finale aux noms et dates incohérents, journée 3 des poules C et D
  pendant les vacances d'hiver, noms des fermetures, tableaux nommés « Poule A » à « Poule D », date et gymnase de la
  journée des finales.
- Bureau : corriger dans l'éditeur les coquilles du texte 2025 (liste dans `reglement.md`).
