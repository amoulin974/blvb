
# BLVB - Gestion de championnat

Ce projet est une application web permettant de gérer un championnat sportif depuis la création des équipes et des matchs jusqu’à la diffusion des calendriers et des résultats.

Référentiel du projet :  
https://github.com/amoulin974/blvb

---

## 🚀 Technologies utilisées

- **Symfony 7.3**
- **PHP 8.2+**
- **Composer**
- **Node.js / npm**
- **Doctrine ORM**
- **Base de données** (MySQL conseillé)
- **Docker**
- **AssetMapper & Tailwind Bundle**

---

## ✅ Prérequis

| Outil | Version minimale | Lien |
|------|------------------|------|
| Docker | - | https://www.docker.com/ |
| PHP | 8.2 | https://www.php.net/ |
| Composer | 2.x | https://getcomposer.org/download/ |
| Node.js | 18+ | https://nodejs.org |
| npm | 9+ | https://www.npmjs.com/ |
| Symfony CLI *(optionnel)* | - | https://symfony.com/download |
| Serveur SQL (MySQL/MariaDB/PostgreSQL) | - | selon votre choix |

Pour vérifier votre installation :

```bash
php -v
composer -V
node -v
npm -v
docker -v
```

---

## 🧑‍💻 Installation en développement

### 1. Cloner le projet
```bash
git clone https://github.com/amoulin974/blvb.git
cd blvb
```

### 2. Installer les dépendances PHP
```bash
composer install
```



### 4. Configurer les variables d’environnement
Copier le fichier .env en .env.local :

Sous linux
```bash
cp .env .env.local
```
Dans cmd sous windows
```bash
copy .env .env.local
```

Modifier `DATABASE_URL` :

Dans le fichier .env.local :
```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/blvb?charset=utf8mb4"
```

### 5. Créer la base de données
```bash
php bin/console doctrine:database:create
```

### 6. Lancer les migrations
```bash
php bin/console doctrine:migrations:migrate
```

### 7. Créer un compte administrateur

Avec la console hasher le mot de passe
```bash
php bin/console security:hash-password
```
Dans la table user 
```bash
INSERT INTO `user`(`id`, `email`, `roles`, `password`, `is_verified`, `nom`, `prenom`, `telephone`) VALUES ('1','votreemail','["ROLE_USER","ROLE_ADMIN"]','hash_fourni_par_la_console','1','votrenom','votreprenom','votretel')
```

### 8. Lancer le serveur de dev
```bash
symfony server:start
```


---

## 🌐 Installation en production

### 1. Cloner le projet
```bash
git clone https://github.com/amoulin974/blvb.git
cd blvb
```

### 2. Modifier tous les mots de passe par défaut
Dans le docker compose, modifier dans le service database le nom de la base, le mot de passe root, le nom de l'utilisateur qui sera utilisé par le site et son mot de passe
```bash
    environment:
      MYSQL_DATABASE: blvb_new 
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_USER: symfony
      MYSQL_PASSWORD: symfonypassword
```

Dans la partie healthcheak qui sert à vérifier que le service database est bien démarré, il faut indiquer le motdepasse root précédemment créé en ajoutant -p juste avant (sans espace pour éviter l'ouverture d'uneligne de commande exemple avec mot de passe rootpassword : -prootpassword)
```bash
    healthcheck:
        test: [ "CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-prootpassword" ]
```

Dans le service php il faut répercuter les modification faite sur le service database
```bash
      DATABASE_URL: mysql://NouveauUser:NouveauPassword@database:3306/NouveauNomBase?serverVersion=8.4.7&charset=utf8mb4
```   

### 3. Configurer les variables d’environnement
Copier le fichier .env en .env.local :

Sous linux
```bash
cp .env .env.local
```
Dans cmd sous windows
```bash
copy .env .env.local
```

Configurer `.env.local` :

```env.local
APP_ENV=prod
APP_DEBUG=0
DEFAULT_URI=https://monsite.com avec votre vrai nom de domaine :)
```
Dans un terminal générer une clé
```bash
php -r 'echo bin2hex(random_bytes(16));'
```

Copier le code généré dans le fichier .env.local
```env.local
APP_KEY=la clé générée
```

Modifier la connexion à la bd utilisé par symfony en utilisant le usersymfony son mot de passe, le nom de la base de donnée que vous avez créé dans le point 2
```env.local
DATABASE_URL="mysql://NouveauUser:NouveauPassword@database:3306/NouveauNomBase?serverVersion=8.4.7&charset=utf8mb4"
```

### 4. Démarrer les conteneurs
```bash
docker compose up -d --build
```

Vérifier si les conteneurs ont bien démarré
```bash
docker compose ps
```

### 5. Installer les dépendances depuis les conteneurs
```bash
docker compose exec php composer install
docker compose exec php php bin/console importmap:install
docker compose exec php php bin/console asset-map:compile
docker compose exec php php bin/console tailwind:build --minify
```



### 6. Gérer les données
Créer la base de données
```bash
docker compose exec php php bin/console doctrine:database:create
```
Migrations :
```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Import des données :
Si vous avez des données en dev vous pouvez vous connecter au phpmyadmin à l'adresse https:monsite.com:8080


### 7. Nettoyage cache :
```bash
php bin/console cache:clear --env=prod
```

---

## 🤝 Contribution
Les contributions sont les bienvenues !