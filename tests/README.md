# Lancer les tests

Guide pratique : quelles commandes lancer, où, et que faire quand ça bloque.
Les autres documents :
- ce que chaque test vérifie et pourquoi : [`SPECIFICATIONS.md`](SPECIFICATIONS.md) ;
- la base de recette, pour les tests manuels dans le navigateur : [`recette/README.md`](recette/README.md).

Toutes les commandes se lancent **depuis WSL**, à la racine du projet, conteneurs démarrés (`docker compose up -d`).
Depuis Windows, préfixer par `wsl.exe -d Ubuntu -- bash -c 'cd ~/projects/blvb && …'`.

---

## 1. Avant la première fois (une fois par poste)

1. Installer les dépendances dans le conteneur :
   ```bash
   docker compose exec php composer install
   ```
   À refaire **après chaque pull ou changement de branche qui modifie `composer.lock`**. Sinon le `vendor` du
   conteneur est en retard, et l'application ou les tests s'arrêtent sur une erreur de classe ou de composant
   absent (ex. « HtmlSanitizer component is not installed », « Cannot bootstrap extension … DAMA »).
2. Donner à l'utilisateur MySQL `symfony` les droits sur les bases de test, que Foundry recrée à chaque lancement :
   ```bash
   docker compose exec -T database mysql -uroot -prootpassword \
     -e "GRANT ALL PRIVILEGES ON \`blvb\_new\_test%\`.* TO 'symfony'@'%';"
   ```
   Symptôme si c'est oublié : « could not reset the database » au premier test d'intégration.
3. Compiler le CSS, que les gabarits référencent :
   ```bash
   docker compose exec php php bin/console tailwind:build
   ```

## 2. Tests automatisés (PHPUnit)

Ils utilisent leur propre base, `blvb_new_test`, recréée à chaque lancement. Chaque test s'exécute dans une
transaction annulée à la fin. Les bases de développement et de recette ne sont jamais touchées.

| Commande | Ce qu'elle lance | Durée |
|---|---|---|
| `docker compose exec php composer tests` | toute la suite, sauf les groupes `lent` et `anomalie` | ≈ 1 à 2 min |
| `docker compose exec php composer tests:tout` | toute la suite avec les tests lents (groupe `lent`, ex. optimiseur) | plus long |
| `docker compose exec php php bin/phpunit --testsuite unitaire` | les tests unitaires seuls, pendant le développement | quelques secondes |
| `docker compose exec php php bin/phpunit --testsuite integration` | les tests d'intégration (services avec la base) | |
| `docker compose exec php php bin/phpunit --testsuite fonctionnel` | les tests fonctionnels (pages et API) | |

Pour cibler un ou quelques tests :
```bash
# une classe, une méthode, ou plusieurs classes (expression régulière)
docker compose exec php php bin/phpunit --filter ReglementAdminTest
docker compose exec php php bin/phpunit --filter test_enregistrer_et_conflit
docker compose exec php php bin/phpunit --filter "ReglementAdminTest|RenduReglementTest"

# un fichier ou un dossier
docker compose exec php php bin/phpunit tests/Fonctionnel/Admin

# un groupe exclu par défaut
docker compose exec php php bin/phpunit --group lent

# rapport lisible : une ligne par test, avec son identifiant (ex. « RGL-17 … »)
docker compose exec php php bin/phpunit --testdox --filter Reglement
```

À savoir :
- `bin/phpunit` n'est pas exécutable dans le conteneur : toujours écrire `php bin/phpunit`.
- Pour **toute la suite**, passer par `composer tests` : lancée par `php bin/phpunit` seul, elle dépasse les 128 Mo de
  mémoire de PHP. Les commandes ciblées ci-dessus n'ont pas ce problème.
- Une notice, un avertissement ou une dépréciation fait **échouer** le test (`failOnDeprecation` / `Notice` / `Warning`
  dans `phpunit.dist.xml`).
- Le groupe `anomalie` marque un défaut connu, pas encore corrigé. Il est exclu partout, y compris en CI.

## 3. Contrôles de qualité et de sécurité

| Commande | Ce qu'elle vérifie |
|---|---|
| `docker compose exec php composer qualite` | syntaxe des gabarits Twig et des fichiers YAML, conteneur de services, `composer.json` |
| `docker compose exec php php bin/console lint:twig templates` | les gabarits seuls (rapide, après une retouche de Twig) |
| `docker compose exec php composer securite` | vulnérabilités connues des dépendances PHP et JavaScript |

## 4. Si l'application tourne sur la base de recette

Après `bin/recette utiliser`, le conteneur `php` pointe sur `blvb_recette`, et les tests cherchent alors une base
`blvb_recette_test` qui n'existe pas. Deux solutions :
- revenir sur la base de développement : `bin/recette dev` ;
- ou lancer les tests en forçant l'adresse de la base de développement :
  ```bash
  docker compose exec -e DATABASE_URL="mysql://symfony:symfonypassword@database:3306/blvb_new?serverVersion=8.4.7&charset=utf8mb4" \
    php composer tests
  ```

Pour les tests manuels (préparer une base avec des saisons, des matchs, des comptes et un règlement de démonstration),
voir [`recette/README.md`](recette/README.md) : `bin/recette reinitialiser [--scenario=…]`, `bin/recette utiliser`,
`bin/recette dev`.

## 5. Dans un script

Une commande `docker compose exec` lancée depuis un script lit l'entrée standard et avale la suite du script.
Ajouter `-T` et `< /dev/null` :
```bash
docker compose exec -T php composer tests < /dev/null
```

## 6. Intégration continue

À chaque push sur une branche autre que `prod`, et à chaque pull request vers `main`, GitHub Actions
(`.github/workflows/ci.yml`) lance trois jobs :
- `qualite` : `composer qualite` ;
- `tests` : base de test reconstruite par les migrations, `doctrine:schema:validate`, `tailwind:build`, puis PHPUnit ;
- `securite` : informatif, il ne bloque pas.

Suivre les exécutions depuis WSL avec `gh run list` et `gh run view` (après `gh auth login`). Détail des jobs et règles
de blocage : [`SPECIFICATIONS.md`](SPECIFICATIONS.md), §6.

## 7. Quand on modifie un test

Toute modification de test (ajout, suppression, renommage, changement de ce qui est vérifié) met à jour
[`SPECIFICATIONS.md`](SPECIFICATIONS.md) **dans le même commit** :
- l'arborescence (§3.1) ;
- le catalogue avec les identifiants (§4) ;
- le plan avec le nombre de tests (§7), vérifié en relançant la suite.

Si les données ou les scénarios de recette changent, mettre aussi à jour [`recette/README.md`](recette/README.md).
