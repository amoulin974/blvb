# Mise en production du règlement par saison (et des lots de formats) : checklist pour Francis

Préparée par l'agent `deploiement` le 21/10/2026, sur la branche `reglement` (qui contient le lot 6 des formats de
compétition). **À relire au moment de la fusion dans `main` :** l'état de `prod` aura pu changer d'ici là.
Seul Francis fusionne dans `prod`.

## Préalables, côté développement

1. Fusionner selon D15 : `formats-competition-lot6` et `reglement` → `formats-competition` → `main`.
2. **Vulnérabilités JavaScript** (`importmap:audit`) : aucune depuis le 22/10/2026 (TipTap 3.31.4, `prosemirror-view`
   1.42.6). Les paquets de l'éditeur ont changé : `importmap:install` est indispensable au déploiement.
3. **Secret** : le `.env` de `prod` contient le mot de passe SMTP en clair ; le déplacer dans `.env.local` (ou un secret
   Symfony), puis changer ce mot de passe.

## Points d'attention sur le serveur

- **Migrations automatiques** : `frankenphp/docker-entrypoint.sh` lance `doctrine:migrations:migrate` à chaque
  démarrage du conteneur ; les 9 migrations passeront au redémarrage. **Sauvegarder la base avant.** Aucune ne supprime
  de donnée (ajouts de colonnes et de tables ; reprises : départage, codes des phases et poules avec index uniques,
  règles de passage 2025).
- **`vendor/` avant le redémarrage** : le serveur monte `.:/app` ; sans `symfony/html-sanitizer`, le conteneur ne démarre
  plus. Lancer `composer install` dans le conteneur **encore en marche**, avant de redémarrer.
- **Environnement** : le `.env` versionné de `prod` dit `APP_ENV=dev` ; vérifier l'environnement réel avant
  `composer install --no-dev`.
- **Page publique vide** entre la migration et l'import : lancer `app:reglement:importer --force` juste après le
  redémarrage.
- **Données 2025 à corriger dans le back-office avant l'import** (voir `reglement.md`) : journées de la phase finale,
  journée 3 des poules C et D pendant les vacances d'hiver, noms des fermetures, tableaux nommés « Poule A » à « Poule D ».
- **Ne jamais lancer en production** : `app:recette:charger`.

## Fusion de `main` dans `prod` (Francis)

Prendre le code de `main` et ne garder de `prod` que les fichiers serveur : `.env`, `Dockerfile`, `frankenphp/Caddyfile`,
`frankenphp/Caddyfile.old`, `frankenphp/docker-entrypoint.sh`, `prefix: /blvb` (`config/routes.yaml`), `trusted_proxies`,
`trusted_headers` et `assets.base_path` (`config/packages/framework.yaml`), `^/(blvb/)?admin` (`security.yaml`).
Prendre `config/services.yaml` de `main` (paramètre `app.dossier_images_reglement`, plus d'import de
`app/saison.yaml`) et accepter les suppressions (`config/app/saison.yaml`, `SaisonListener`, `SaisonType`, `RegleScore`,
`front/reglement_2025.html.twig`). Après la fusion, `git diff --stat origin/main origin/prod` ne doit lister que les
fichiers serveur. À nettoyer au passage : fichiers Eclipse (`.buildpath`, `.project`, `.settings/`) présents seulement
dans `prod`.

## Commandes, sur le serveur, après la fusion

```bash
# Sauvegarde, puis code
mysqldump --single-transaction --routines blvb > ~/blvb-avant-reglement-$(date +%F).sql
git pull

# Environnement réel (décide de --no-dev)
docker compose exec php php bin/console about | grep -E 'Environment|Debug'

# Dépendances PHP, conteneur encore en marche
docker compose exec php composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader   # si prod
# docker compose exec php composer install --prefer-dist --no-interaction                               # si dev

# Dépendances JavaScript (TipTap et ses extensions)
docker compose exec php php bin/console importmap:install

# Dossier des images envoyées depuis l'éditeur
docker compose exec php sh -c 'mkdir -p public/uploads/reglement && chmod 775 public/uploads/reglement && ls -ld public/uploads/reglement public/uploads/clubs'

# Reconstruction et redémarrage (migrations à l'entrypoint), puis contrôle
docker compose up -d --build php
docker compose logs --tail=50 php
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|New'

# Assets, cache, worker
docker compose exec php php bin/console tailwind:build --minify
docker compose exec php php bin/console asset-map:compile
docker compose exec php php bin/console cache:clear
docker compose restart php

# Reprise du règlement 2025 : simulation (11 parties, 15 règles, 6 images, ancre #regle-1r présente), puis application
docker compose exec php php bin/console app:reglement:importer
docker compose exec php php bin/console app:reglement:importer --force
```

## Contrôles en ligne

`https://www.blvb.fr/blvb/reglement` ; images en `/blvb/images/reglement/…` (ni 404, ni `/blvb/blvb`) ; lien « Lire la
règle 1R » de la fenêtre de saisie du score ; retour du sélecteur de saison sous `/blvb` ; envoi d'une image depuis
l'éditeur (adresse en `/blvb/uploads/reglement/…`).

Note : le préfixe `/blvb` est écrit dans le contenu des règlements (images importées et envoyées) ; une copie de la base
de production en local aura donc des images cassées.

## Facultatif : barrages ajoutés à la saison en cours

Si le bureau veut les barrages du modèle « BLVB avec barrages » sur la saison déjà lancée (créée sans eux) :

```
php bin/console app:saison:ajouter-barrages                 # simulation : ce qui serait ajouté, ou pourquoi c'est refusé
php bin/console app:saison:ajouter-barrages --force         # enregistrement
php bin/console app:saison:ajouter-barrages --seulement=fin --force   # seulement les barrages de fin de saison
```

Barrages entre les phases : seulement si la phase 1 n'est pas clôturée et si la phase 2 n'a pas commencé ; barrages de
fin de saison : si la phase 2 n'est pas clôturée. Vérifier ensuite dans « Modifier la saison » → « Ajouter des phases »
(dates des barrages, passages), et retirer les passages « saison suivante » de la phase 2 si le bureau suit le modèle.

