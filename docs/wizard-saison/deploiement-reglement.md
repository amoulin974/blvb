# Mise en production de `main` : checklist pour Francis

Formats de compétition (lots 1 à 6), wizard de saison, règlement par saison (R1 à R5), fermetures de gymnase, paramètres du
site, limitation des essais de connexion. Relue par l'agent `deploiement` le 31/10/2026 sur `main` 4e5b37b. Seul Francis
fusionne dans `prod`.

**Verdict : prêt avec réserves.** Fusion de `main` dans `prod` simulée (`git merge-tree`) : aucun conflit, `.env` compris.
Nouveautés : paquet Composer `symfony/html-sanitizer`, une soixantaine de paquets importmap (éditeur TipTap 3), dossiers
`public/uploads/reglement` et `public/uploads/site`, 13 migrations (ajouts de colonnes et de tables, et `motif` rendu facultatif sur
une table vide), une variable d'environnement à régler pour la limitation : `SYMFONY_TRUSTED_PROXIES`. Pas de reconstruction d'image :
`Dockerfile`, `compose.yaml` et `frankenphp/` sont inchangés, et le code est monté dans le conteneur.

## 0. Fusion de `main` dans `prod` (Francis)

Prendre le code de `main`, garder de `prod` seulement les fichiers propres au serveur : `.env`, `Dockerfile`,
`frankenphp/Caddyfile`, `frankenphp/Caddyfile.old`, `frankenphp/docker-entrypoint.sh`, `prefix: /blvb` de
`config/routes.yaml`, `trusted_proxies` / `trusted_headers` / `assets.base_path: '/blvb'` de
`config/packages/framework.yaml`, `^/(blvb/)?admin` de `config/packages/security.yaml`. Accepter les suppressions venues
de `main` (`config/app/saison.yaml`, `SaisonListener`, `front/reglement_2025.html.twig`…). Contrôles :

```bash
git diff --stat origin/main prod      # seulement les fichiers serveur, les fichiers Eclipse (.buildpath, .project, .settings/) et
                                      # assets/controllers/mon_equipe_controller.js (saut de ligne final, sans effet)
git grep -nE '^(<<<<<<< |=======$|>>>>>>> )' prod -- . ':!vendor'     # rien
```

## 1 à 12. Sur le serveur, dans le dossier du projet

Le dossier est monté dans le conteneur (`.:/app`) et le site tourne en environnement dev : **pas de `--no-dev`**. Du
`git pull` à la fin des migrations, le site est en panne (quelques minutes) : enchaîner les étapes 3 à 9, prévenir le
bureau. Chaque démarrage du conteneur lance `doctrine:migrations:migrate` : ne pas redémarrer entre le `git pull` et l'étape 4.

```bash
# 1. Vérifications préalables
docker compose exec php php bin/console about | grep -E 'Environment|Debug'
docker compose exec php php -r 'require "vendor/autoload.php"; (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env"); echo ($_SERVER["APP_SECRET"] ?? "") === "" ? "APP_SECRET VIDE\n" : "APP_SECRET défini\n";'
docker compose exec php php bin/console dbal:run-sql "SELECT DATABASE()"
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|Latest|New'
git log -1 --oneline        # noter le commit actuel (3bafba4 attendu) pour un retour arrière
# Adresse utilisée par les commandes en ligne (cron) : attendu https://www.blvb.fr ou https://www.blvb.fr:443.
# Si elle vaut localhost, les liens des e-mails de rappel seront faux. À évaluer par Francis (non testé) : SERVER_NAME=www.blvb.fr
# dans le .env de prod, puis `docker compose up -d php`, car un simple restart ne relit pas l'environnement.
docker compose exec php printenv DEFAULT_URI

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

# 7. Migrations (13), puis contrôle
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:migrations:status | grep -E 'Current|New'   # New : 0

# 8. Dossiers des images de l'éditeur du règlement et du logo du site
docker compose exec php sh -c 'mkdir -p public/uploads/reglement public/uploads/site && chmod 775 public/uploads/reglement public/uploads/site && ls -ld public/uploads/reglement public/uploads/site'

# 9. Cache et redémarrage
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

# 12. Paquets : voir la réserve sur symfony/mime
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
| Version20261030091911 | table `fermeture_gymnase` |
| Version20261030213902 | table `parametres_site`, une ligne : limitation désactivée |
| Version20261030223748 | `fermeture_gymnase.cree_par_id` : auteur de la fermeture |
| Version20261030225406 | `fermeture_gymnase.motif` facultatif |

## Vérifications en ligne

- `/blvb/` : accueil, styles chargés ; `/blvb/reglement` : texte 2025, sommaire, images en `/blvb/images/reglement/…` ;
  sélecteur de saison : le retour reste sous `/blvb` ; fenêtre de saisie du score : « Lire la règle 1R » mène à `#regle-1r`.
- `/blvb/admin` : liste des saisons ; « Règlement » du menu : l'éditeur se charge, enregistrer, historique ; une image
  envoyée a une adresse `/blvb/uploads/reglement/…`.
- Classements et calendriers de la saison en cours inchangés.
- « Paramètres du site » : la page s'ouvre ; un logo envoyé a une adresse `/blvb/uploads/site/…`.
- Fermeture de gymnase : création depuis « Mon club » ; un match à la main dans ce gymnase est refusé ; `/blvb/calendrierlieu` affiche
  « Gymnase fermé ». Retirer la fermeture d'essai ensuite.
- Liste des saisons sur téléphone : en cartes, sans défilement horizontal.

## Retour arrière

Code seul (cas normal, les migrations n'ont fait qu'ajouter). Le redémarrage relance les migrations avec des versions inconnues du
code : d'après le code, Doctrine passe outre en mode non interactif, mais ce cas n'a pas été essayé.

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

## Après le déploiement : adresse de contact et limitation des essais de connexion (D61, D62)

**Adresse de contact** : à renseigner tout de suite dans « Paramètres du site ». Sinon, les messages de refus d'une fermeture de
gymnase montrent les adresses de connexion des admins.

**Limitation** : elle est **désactivée au déploiement** ; tant qu'elle l'est, les essais de mot de passe sont illimités (le tableau
de bord le signale). Elle suppose que le site voit la vraie IP des visiteurs. `SYMFONY_TRUSTED_PROXIES` n'est dans aucun fichier
versionné : sans réglage sur le serveur, la vérification échouera très probablement, en montrant une adresse privée.

1. Admin, page « Paramètres du site » : « Lancer la vérification ». Le message dit quoi régler ; l'adresse « Il voit … » est celle du
   proxy, **à confirmer par Francis**, à mettre dans `.env.local`. Ne mettre ni `0.0.0.0/0`, ni `REMOTE_ADDR`.
   **Effet de bord** : dès qu'un proxy est déclaré, `prod` croit aussi `x-forwarded-host`, `-proto`, `-port` et `-prefix`, sans
   `trusted_hosts`. Faire le contrôle ci-dessous, puis relancer la vérification, valable une heure.
2. Francis confirme que le site n'est pas joignable sans passer par le proxy (`compose.yaml` publie 80 et 443 sur toutes les
   interfaces) ; l'admin coche la case et active.
3. Faire l'essai ci-dessous depuis l'extérieur du serveur. Un `curl` sans jeton n'est jamais compté : il ne prouve rien.
4. Depuis une autre connexion (téléphone en 4G), même adresse : pas de blocage. Sinon, tous les visiteurs partagent la même
   IP, et 20 échecs sur le site bloquent tout le monde : désactiver.

```bash
# Proxy (adresse confirmée par Francis)
echo 'SYMFONY_TRUSTED_PROXIES=ADRESSE_DU_PROXY' >> .env.local
docker compose restart php
curl -sI https://www.blvb.fr/blvb/admin | grep -i '^location'                                       # reste /blvb/login, sans /blvb/blvb
curl -sI -H 'X-Forwarded-Host: exemple.invalid' https://www.blvb.fr/blvb/admin | grep -i '^location'  # sans exemple.invalid

# Accès direct, depuis l'extérieur du serveur : le site ne doit pas répondre
curl -sI --max-time 5 http://IP_DU_SERVEUR/blvb/login | head -1

# Limitation activée, depuis l'extérieur, à enchaîner en moins de 30 s :
# les 3 premiers affichent « Identifiants invalides », le 4e « Trop d'essais de connexion ».
J=$(mktemp); for i in 1 2 3 4; do
  curl -s -o /dev/null -c "$J" -b "$J" -H 'Origin: https://www.blvb.fr' -H "X-Forwarded-For: 198.51.100.$i" \
    -d '_username=essai@exemple.fr&_password=faux&_csrf_token=csrf-token' https://www.blvb.fr/blvb/login
  curl -s -c "$J" -b "$J" https://www.blvb.fr/blvb/login | grep -oE "Identifiants invalides|Trop d.essais de connexion"
done; rm -f "$J"
docker compose exec php php bin/console cache:pool:clear connexions.cache    # lève ensuite les compteurs, sans couper la protection
```

Pour couper la limite, si un admin est bloqué : `docker compose exec php php bin/console app:connexion-limitee:desactiver`.
Un `cache:clear`, donc un déploiement, remet aussi les compteurs à zéro.

## Réserves, à traiter après

- Mot de passe SMTP en clair dans le `.env` versionné de `prod` : le déplacer dans `.env.local` et le changer.
- `APP_SECRET` : le définir s'il est vide (étape 1).
- Port MySQL 3307 publié par `compose.yaml` : vérifier que le pare-feu le ferme depuis l'extérieur.
- Crons : vérifier d'abord qu'il n'en existe pas déjà (`crontab -l | grep -E 'scores:rappeler|cache:pool:prune'`). Le compte qui les
  exécute doit avoir accès à Docker.
  - Rappel des scores, après le contrôle de `DEFAULT_URI` : le **premier passage envoie tout l'arriéré** de la saison, à voir avec
    `docker compose exec php php bin/console app:scores:rappeler --simulation`.
    `0 9 * * * cd /chemin/du/projet && docker compose exec -T php php bin/console app:scores:rappeler`
  - Nettoyage des compteurs d'essais, à l'activation de la limitation (sans elle, aucun compteur n'est créé) :
    `0 4 * * * cd /chemin/du/projet && docker compose exec -T php php bin/console cache:pool:prune`
- `symfony/mime` : `composer audit` signale CVE-2026-45070 (gravité moyenne), en 7.4.19 sur `main` comme sur `prod`, corrigée en 7.4.22.
- Mots de passe de la base en clair dans `compose.yaml`, alors que le port 3307 est publié ; `DATABASE_OLD_URL` et phpMyAdmin publié
  sur le port 8080 avec `PMA_ARBITRARY=1` : exposition réelle à vérifier.
- Pas de mentions légales tant que `conditions-utilisation-v2` n'est pas fusionnée : le lien du pied de page reste vide.
- Données 2025 (back-office) : journées de la phase finale aux noms et dates incohérents, journée 3 des poules C et D
  pendant les vacances d'hiver, noms des fermetures, tableaux nommés « Poule A » à « Poule D », date et gymnase de la
  journée des finales.
- Bureau : corriger dans l'éditeur les coquilles du texte 2025 (liste dans `reglement.md`).
