
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

### 3. Installer les dépendances front-end

```bash
npm install
npm run dev
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

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

Configurer `.env.local` :

```env
APP_ENV=prod
APP_DEBUG=0
DATABASE_URL="mysql://user:password@serveur:3306/blvb"
```

Compilation des assets :
```bash
php bin/console importmap:install
php bin/console tailwind:build --minify
```

Migrations :
```bash
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

Nettoyage cache :
```bash
php bin/console cache:clear --env=prod
```

Racine web = **public/**

---

## 🧰 Commandes utiles

| Commande | Description |
|---------|-------------|
| `php bin/console` | Liste toutes les commandes |
| `php bin/console tailwind:build -w` | Compilation CSS en continu (watch) |
| `php bin/console cache:clear` | Vide le cache |
| `php bin/console doctrine:schema:validate` | Vérifie la base |

---

## 🤝 Contribution
Les contributions sont les bienvenues !
