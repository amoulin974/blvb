# Mail à Francis : brouillon à compléter

Texte de la mise en production des lots 1 à 6 et du règlement, écrit le 22/10/2026. **À compléter avant envoi** avec ce que `main` a
reçu depuis ; la référence reste `deploiement-reglement.md`.

## À ajouter ou à corriger

- **Contenu** : fermetures de gymnase, paramètres du site (adresse de contact, logo), limitation des essais de connexion.
- **« Aucune nouvelle variable d'environnement »** : à remplacer. `SYMFONY_TRUSTED_PROXIES` est à régler dans `.env.local` pour activer
  la limitation, et `DEFAULT_URI` est à contrôler avant le cron des rappels (risque de liens en `localhost`).
- **Migrations** : 13 au lieu de 9 ; elles ajoutent des colonnes et des tables, et rendent `motif` facultatif sur une table vide.
- **Contrôle `git diff --stat`** : il liste aussi les fichiers Eclipse et `assets/controllers/mon_equipe_controller.js`.
- **Dossier** : `public/uploads/site` en plus de `public/uploads/reglement`.
- **Avant le `git pull`** : `printenv DEFAULT_URI`. Ne pas redémarrer entre le `git pull` et `composer install` : le démarrage lance les migrations.
- **Après le déploiement** : renseigner l'adresse de contact ; déclarer le proxy, vérifier, activer, faire l'essai réel, le tout dans la
  section « Après le déploiement » de `deploiement-reglement.md`.
- **Crons** : vérifier qu'ils n'existent pas déjà ; simulation du rappel des scores avant le premier envoi (tout l'arriéré partirait) ;
  `cache:pool:prune` à l'activation de la limitation.
- **Vérifications en ligne** : page des paramètres, logo en `/blvb/uploads/site/…`, fermeture de gymnase, liste des saisons sur téléphone.
- **Réserves** : `DATABASE_OLD_URL`,
  `APP_SECRET` ; pas de mentions légales tant que `conditions-utilisation-v2` n'est pas fusionnée.
- **À lui demander pour les conditions** : nom de l'éditeur, statut, adresse, directeur de publication, durée de conservation des comptes
  après la dernière saison, durée de conservation des journaux du serveur.
- **Paquets** : mis à jour le 31/10/2026 (dont `symfony/mime`, CVE-2026-45070) ; `composer audit` et `importmap:audit` ne doivent rien signaler.

## Texte de départ

Bonjour Francis,

main contient une grosse évolution prête à partir en production :

- le wizard de création de saison et les nouveaux formats (barrages, journée des finales, aller-retour, format en 2 sets gagnants) ;
- le règlement par saison, modifiable depuis l'admin.

La checklist détaillée est dans le dépôt : docs/wizard-saison/deploiement-reglement.md.

Ce qu'il faut savoir :

- La fusion de main dans prod se fait sans conflit, y compris sur le .env (simulation faite). Il faut garder tes fichiers serveur (.env, Dockerfile, frankenphp/*, le préfixe /blvb dans routes.yaml, framework.yaml et security.yaml) et accepter les suppressions venues de main.
- Aucune nouvelle variable d'environnement.
- Le site est indisponible quelques minutes entre le git pull et la fin des migrations : il faut enchaîner les étapes 3 à 9. Prévenir le bureau.
- Pas de --no-dev sur composer install : le site tourne en environnement dev.

### 1. Fusion et contrôles

```bash
git diff --stat origin/main prod      # ne doit lister que les fichiers serveur
git grep -nE '^(<<<<<<< |=======$|>>>>>>> )' prod -- . ':!vendor'     # ne doit rien afficher
```

### 2. Sur le serveur, dans le dossier du projet

```bash
# Vérifications et commit actuel (pour un retour arrière)
docker compose exec php php bin/console dbal:run-sql "SELECT DATABASE()"
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|Latest|New'
git log -1 --oneline        # 3bafba4 attendu

# Sauvegarde de la base (remplacer blvb_new si SELECT DATABASE() donne un autre nom)
docker compose exec -T database sh -c 'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" blvb_new' > ~/blvb-avant-formats-$(date +%F-%H%M).sql
tail -1 ~/blvb-avant-formats-*.sql      # doit finir par « -- Dump completed »

# Mise à jour (enchaîner jusqu'au redémarrage)
git pull
docker compose exec php composer install --prefer-dist --no-interaction --no-progress
docker compose exec php php bin/console importmap:install
docker compose exec php php bin/console tailwind:build --minify
docker compose exec php php bin/console asset-map:compile
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|New'   # New : 0
docker compose exec php sh -c 'mkdir -p public/uploads/reglement && chmod 775 public/uploads/reglement'
docker compose exec php php bin/console cache:clear
docker compose restart php
docker compose logs --tail=50 php        # « PHP app ready! », aucune erreur

# Ajout des barrages
docker compose exec php php bin/console app:saison:ajouter-barrages            # simulation
docker compose exec php php bin/console app:saison:ajouter-barrages --force

# Reprise du règlement 2025 : simulation, puis enregistrement
docker compose exec php php bin/console app:reglement:importer
docker compose exec php php bin/console app:reglement:importer --force

# Contrôle des paquets
docker compose exec php composer audit
docker compose exec php php bin/console importmap:audit
```

Les 9 migrations ne font qu'ajouter des colonnes et des tables. Elles reprennent les saisons existantes : codes des phases et des poules, règles de montée et descente, départage. Aucune donnée n'est supprimée.

### 3. Vérifications

- /blvb/ et /blvb/reglement : texte 2025, sommaire et images.
- Après un changement de saison dans le menu, on doit rester sous /blvb.
- Dans l'admin, menu « Règlement » : l'éditeur se charge, et une image envoyée a une adresse en /blvb/uploads/reglement/….
- Les classements et calendriers de la saison en cours ne doivent pas avoir changé.

### 4. En cas de problème

- Retour du code : git checkout 3bafba4, puis composer install, importmap:install, tailwind:build --minify, asset-map:compile, cache:clear et docker compose restart php.
- La base n'est à restaurer depuis la sauvegarde que si une migration a échoué. Les commandes sont dans le document.
