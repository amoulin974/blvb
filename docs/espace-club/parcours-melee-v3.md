# Mêlée : parcours v3 (avis de l'ergonome, après D77 et D78)

**Verdict : oui avec conditions.** D78 se dessine sans refonte : la v2 reste la base, et je ne décris que ce qui change ou manque. Deux conditions avant la maquette :
- confirmer **H25** et **H26** : comment les 10 min par défaut cohabitent avec les trois scénarios ;
- confirmer **H29** : l'option « classement par catégorie » est verrouillée dès la première inscription.

Enregistré tel quel le 16/11/2026 ; complète `parcours-melee-v2.md`. Hypothèses H25 à H33 à faire valider. Tous les exemples chiffrés reprennent la soirée de référence du modèle (19 h 30 à 22 h 30, 2 terrains, 6 contre 6, temps utile de 135 min) et sont recalculés avec ses équations.

Lus :
- /home/amoul/projects/blvb/docs/espace-club/README.md
- /home/amoul/projects/blvb/docs/wizard-saison/decisions.md (D65, D76, D77, D78)
- /home/amoul/projects/blvb/docs/espace-club/parcours-melee-v2.md
- /home/amoul/projects/blvb/docs/espace-club/parcours-melee.md
- /home/amoul/projects/blvb/docs/espace-club/calcul-melee.md

## Hypothèses nouvelles (à faire valider)

- **H25** : la durée de 10 min a un troisième état, **« Valeur par défaut »**. Elle s'impose à la seule carte conseillée ; les deux autres cartes restent calculées librement.
- **H26** : si 10 min ne permettent pas l'égalité, la carte conseillée garde toute la soirée (9 tours) et propose en un geste le programme égal. Elle ne raccourcit jamais la soirée d'elle-même.
- **H27** : « Joueurs en trop » quitte les réglages et rejoint le bloc « Qui joue à chaque tour », à côté de la phrase qu'il modifie.
- **H28** : le filet et le classement par catégorie se règlent à l'étape 2. En mode « Ouvertes à tous », le filet est à 2,35 m par défaut (D78 ne le dit pas).
- **H29** : le classement par catégorie s'ajoute au classement général (3 onglets). L'option, comme le passage en deux tableaux, ne peut plus être activée après la première inscription.
- **H30** : en deux tableaux, même format pour les deux ; lettres d'équipe uniques sur la soirée (A et B pour les joueuses, C à F pour les joueurs) ; échange de joueurs à l'intérieur d'un tableau seulement.
- **H31** : le maximum d'inscrits par défaut est 24 avec « Au repos » (32 avec « Remplaçants ») ; en deux tableaux, un maximum et une liste d'attente par tableau.
- **H32** : l'alerte « tour en retard » n'apparaît que si la salle serait rendue après la fermeture. Un retard absorbé par la marge donne une simple ligne.
- **H33** : en mêlée réservée, une case à cocher remplace le choix Féminine / Masculine.

## 1. Étape 2 · Format et mixité (complétée)

Trois ajouts, dans l'ordre de l'écran.

**a. Cinquième mode sous « Les équipes \* »** : « Deux tableaux : les joueuses d'un côté, les joueurs de l'autre ».
- Erreur s'il n'y a qu'un terrain : « Deux tableaux demandent au moins 2 terrains (étape 1). »

**b. « Classement \* »**, visible en mode Mixtes seulement.
- « Un seul classement », choisi par défaut.
- « Un classement général, un classement des joueuses et un classement des joueurs ».
- Avertissement affiché dès que le second choix est pris : « Ce classement montre aux participants et aux membres du club qui joue en catégorie féminine ou masculine. Chaque joueur en sera prévenu avant de s'inscrire. »
- Après la première inscription, le second choix est inactif, avec l'explication : « Des joueurs sont déjà inscrits sans avoir été prévenus : ce classement ne peut plus être ajouté. » Le retirer reste possible.
- En deux tableaux, pas de choix, une ligne : « Un classement par tableau. »

**c. « Hauteur du filet »** : une ligne compacte avec « Modifier ».
- La valeur suit le mode tant qu'on ne l'a pas modifiée.
- Déplié : 3 choix et « Autre » (de 2,00 à 2,50 m).
- Elle reste modifiable après la publication et le jour J, dans « Modifier en cours ».

**Texte sur la catégorie**, sous « Les équipes » : il dépend maintenant de qui la voit.

| Cas | Texte |
|---|---|
| Mixtes, un seul classement | inchangé : « … Elle n'est visible que de lui et de vous. » |
| Mixtes, avec classement par catégorie | « Chaque joueur indiquera sa catégorie de jeu en s'inscrivant, s'il ne l'a pas déjà fait. Elle sera visible des participants et des membres du club, dans le classement. » |
| Deux tableaux | « Chaque joueur indiquera sa catégorie de jeu en s'inscrivant, s'il ne l'a pas déjà fait. Elle sera visible des participants et des membres du club : les équipes et le classement sont séparés. » |
| Féminines ou Masculines | « Seuls les membres de catégorie féminine pourront s'inscrire. Les participants et les membres du club verront qui est inscrit. » (ou « masculine ») |

```
+--------------------------------------+
| Étape 2 sur 5 · Format et mixité     |
+--------------------------------------+
| Format *            (inchangé)       |
| Les équipes *                        |
| +----------------------------------+ |
| |(o) Mixtes                        | |
| |  Au moins [ − ]  2  [ + ]        | |
| |  joueuses par équipe             | |
| +----------------------------------+ |
| |( ) Ouvertes à tous, sans minimum | |
| |    de joueuses                   | |
| +----------------------------------+ |
| |( ) Féminines : réservées aux     | |
| |    joueuses                      | |
| +----------------------------------+ |
| |( ) Masculines : réservées aux    | |
| |    joueurs                       | |
| +----------------------------------+ |
| |( ) Deux tableaux : les joueuses  | |
| |    d'un côté, les joueurs de     | |
| |    l'autre                       | |
| +----------------------------------+ |
| (texte sur la catégorie, selon le    |
|  tableau ci-dessus)                  |
| Classement *                         |
| (o) Un seul classement               |
| ( ) Un classement général, un        |
|     classement des joueuses et un    |
|     classement des joueurs           |
| Hauteur du filet                     |
| 2,35 m · hauteur mixte               |
| Valeur par défaut       [ Modifier ] |
| [x] Équilibrer les niveaux           |
|   (inchangé)                         |
|           [ Enregistrer et quitter ] |
|======================================|
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+

CLASSEMENT PAR CATÉGORIE CHOISI
| ( ) Un seul classement               |
| (o) Un classement général, un        |
|     classement des joueuses et un    |
|     classement des joueurs           |
| +- ! ------------------------------+ |
| | Ce classement montre aux         | |
| | participants et aux membres du   | |
| | club qui joue en catégorie       | |
| | féminine ou masculine. Chaque    | |
| | joueur en sera prévenu avant de  | |
| | s'inscrire.                      | |
| +----------------------------------+ |

FILET DÉPLIÉ
| Hauteur du filet                     |
| ( ) 2,24 m · hauteur des joueuses    |
| (o) 2,35 m · hauteur mixte           |
| ( ) 2,43 m · hauteur des joueurs     |
| ( ) Autre : [ 2,30 ] m               |
| (cadenas) Valeur fixée par vous      |
| [ Revenir à 2,35 m ]                 |

FILET EN DEUX TABLEAUX
| Hauteur du filet                     |
| Terrains des joueuses : 2,24 m       |
| Terrains des joueurs : 2,43 m        |
| Valeurs par défaut      [ Modifier ] |
```

## 2. Étape 3 · Programme (modifiée)

### 2.1 Les 10 min et les trois scénarios

La durée n'est pas « fixée par vous » d'office : elle a un **troisième état**.

| État | Quand | Libellé sous la valeur | Effet sur les cartes |
|---|---|---|---|
| Par défaut | à l'arrivée | « Valeur par défaut » et [Laisser le site choisir] | la carte conseillée est calculée à 10 min ; les deux autres librement |
| Fixée | après − ou + | (cadenas) « Valeur fixée par vous » et [Laisser le site choisir] | les 3 cartes la respectent ; doublons fusionnés (règle v2) |
| Calculée | après « Laisser le site choisir », ou après le choix d'une autre carte | « Valeur calculée par le site » et [Revenir à 10 min] | les 3 cartes sont libres |

- **Pourquoi** : si 10 min étaient fixées pour les trois cartes, elles donneraient le même programme (9 tours) et l'idée de scénarios disparaîtrait dès l'arrivée.
- **Aide sous « Choisissez un programme »** : « Le premier programme garde vos matchs de 10 min. Les deux autres montrent ce que donnerait une autre durée. »
- **Choisir une autre carte** fait passer la durée à « Valeur calculée par le site ». Revenir à la première carte la remet à « Valeur par défaut ».

### 2.2 Bloc « Qui joue à chaque tour » (nouveau)

Il se place entre l'estimation des joueurs et les cartes. C'est la réponse immédiate demandée par D78.

Il contient :
1. un titre-verdict ;
2. qui joue et qui est au repos ;
3. le nombre de matchs de chacun avec le programme choisi ;
4. le réglage **« Joueurs en trop »**, toujours au même endroit :
   - « Au repos, à tour de rôle », choisi par défaut ; aide : « Chacun joue des matchs entiers à 6 contre 6. » ;
   - « Remplaçants : équipes jusqu'à 8 joueurs » ; aide : « Tout le monde est dans une équipe ; on tourne au service. »

Le bloc suit la carte choisie. Il n'est pas annoncé aux lecteurs d'écran : la barre de résumé collée reste la seule zone `aria-live`, et commence par le verdict (« Tout le monde joue · 9 tours de 10 min · fin du jeu 22 h 05 »).

| Cas | Titre-verdict | Texte |
|---|---|---|
| 24 joueurs | « Tout le monde peut jouer » | « À chaque tour : 2 matchs, 4 équipes de 6, personne au repos. Avec 9 tours de 10 min, chacun joue 9 matchs. » Sous le réglage : « Aucun joueur en trop avec 24 joueurs : ce choix servira si vous êtes plus nombreux. » |
| 28 joueurs, repos | « 4 joueurs au repos à chaque tour » | « 24 jouent (2 matchs, 4 équipes de 6), 4 sont au repos, à tour de rôle. Avec 9 tours de 10 min, 20 joueurs jouent 8 matchs et 8 joueurs en jouent 7. » Puis la proposition (H26) : « Pour que tout le monde joue autant : » [7 tours de 14 min], « chacun 6 matchs (84 min) ». |
| 28 joueurs, remplaçants | « Tout le monde peut jouer » | « À chaque tour : 4 équipes de 7, dont 1 remplaçant par équipe. Avec 9 tours de 10 min, chacun joue 9 matchs, environ 77 min sur le terrain. » |
| 18 joueurs, repos | « Un seul match par tour » | « 12 jouent, 6 sont au repos, à tour de rôle. Avec 9 tours de 10 min, chacun joue 6 matchs. » Options : [Remplaçants : 2 équipes de 8, 2 au repos], [Essayer le 4 contre 4]. |
| moins de 10 joueurs | « Pas assez de joueurs » | « Il faut au moins 10 joueurs pour un match à 6 contre 6. » |

Le bouton de proposition applique la durée, qui passe à « Valeur calculée par le site ».

Pluriels calculés : « 1 joueur au repos » / « 4 joueurs au repos », « 1 match » / « 2 matchs ».

### 2.3 Changement d'équipe : le message

- Compteur de 3 à 10 min, « Valeur par défaut » à 5.
- Dès que la valeur diffère de 5 : (cadenas) « Valeur fixée par vous », [Revenir à 5 min], et l'encadré **« Ce que cela change »** sous la ligne.
- L'encadré compare toujours avec 5 min et reste affiché tant que la valeur diffère de 5. Pas de message fugitif.
- Il a trois lignes fixes : tours, fin du jeu, matchs par joueur. « toujours » marque ce qui ne bouge pas.

Exemples vérifiés :
- **3 min** : « 10 tours au lieu de 9 · fin du jeu à 22 h 00 au lieu de 22 h 05 · chacun joue 10 matchs au lieu de 9 ».
- **4 min** : « toujours 9 tours · fin du jeu à 21 h 56 au lieu de 22 h 05 · chacun joue toujours 9 matchs ».
- **8 min** : « 7 tours au lieu de 9 · fin du jeu à 21 h 56 au lieu de 22 h 05 · chacun joue 7 matchs au lieu de 9 ».

Compléments :
- en dessous de 5 min, une ligne s'ajoute : « 3 min, c'est court pour changer de terrain à 24. » ;
- si la durée est « calculée par le site », une quatrième ligne : « matchs de 15 min au lieu de 14 ».

### 2.4 Deux tableaux : répartition des terrains

- **Estimation** : deux compteurs, « Joueuses » et « Joueurs », à la place de « dont … joueuses ».
- **Bloc « Qui joue à chaque tour »** : une section par tableau. Chacune a son compteur « Terrains » et son verdict.
- **Sous les deux sections** : « 3 terrains utilisés sur 3 disponibles. », puis l'état « Répartition calculée par le site » ou (cadenas) « Répartition fixée par vous » et [Laisser le site choisir].
- **Terrains tous répartis** : le + est inactif, avec l'explication « Les 3 terrains sont déjà répartis : retirez-en un à l'autre tableau. »
- **La ligne « Terrains utilisés » des réglages disparaît** dans ce mode : une seule source.
- **Cartes** : la troisième ligne se dédouble : « Joueuses : 9 matchs chacune » et « Joueurs : 9 matchs chacun ».
- **Écart entre tableaux** : un encadré sous le bloc (exemple dans le wireframe).
- **Erreur** : « Il faut au moins 10 joueuses pour faire un match dans le tableau des joueuses. » Options : [Passer en mêlée mixte], [Essayer le 4 contre 4].

### 2.5 Wireframes

```
+--------------------------------------+
| Étape 3 sur 5 · Programme            |
+--------------------------------------+
| Combien de joueurs attendez-vous ?   |
| [ − ]   24   [ + ]  joueurs          |
| dont [ − ]  8  [ + ]  joueuses       |
| Une estimation suffit : le programme |
| sera recalculé avec les vrais        |
| chiffres à la fin des inscriptions   |
| et au pointage.                      |
| +- QUI JOUE À CHAQUE TOUR ---------+ |
| | (v) TOUT LE MONDE PEUT JOUER     | |
| | À chaque tour : 2 matchs,        | |
| | 4 équipes de 6, personne au      | |
| | repos.                           | |
| | Avec 9 tours de 10 min, chacun   | |
| | joue 9 matchs.                   | |
| | Joueurs en trop                  | |
| | (o) Au repos, à tour de rôle     | |
| | ( ) Remplaçants : équipes        | |
| |     jusqu'à 8 joueurs            | |
| | Aucun joueur en trop avec 24     | |
| | joueurs : ce choix servira si    | |
| | vous êtes plus nombreux.         | |
| +----------------------------------+ |
| Choisissez un programme *            |
| Le premier programme garde vos       |
| matchs de 10 min. Les deux autres    |
| montrent ce que donnerait une autre  |
| durée.                               |
| +----------------------------------+ |
| |(o) Tout le monde joue autant     | |
| |    (conseillé)                   | |
| |    9 tours de 10 min             | |
| |    De 19 h 50 à 22 h 05          | |
| |    Chacun joue 9 matchs (90 min) | |
| +----------------------------------+ |
| |( ) Des matchs plus longs         | |
| |    6 tours de 17 min             | |
| |    De 19 h 50 à 22 h 02          | |
| |    Chacun joue 6 matchs (102 min)| |
| +----------------------------------+ |
| |( ) Le plus de matchs             | |
| |    10 tours de 8 min             | |
| |    De 19 h 50 à 22 h 00          | |
| |    Chacun joue 10 matchs (80 min)| |
| +----------------------------------+ |
| RÉGLAGES DU PROGRAMME CHOISI         |
| Durée d'un match                     |
| [ − ]    10 min    [ + ]             |
| Valeur par défaut                    |
| [ Laisser le site choisir ]          |
| Nombre de tours                      |
| [ − ]      9       [ + ]             |
| Valeur calculée par le site          |
| Terrains utilisés                    |
| [ − ]      2       [ + ]  sur 2      |
| Valeur calculée par le site          |
| Changement d'équipe entre deux tours |
| [ − ]    5 min     [ + ]             |
| Valeur par défaut                    |
| Le temps de rejoindre son terrain et |
| de s'échauffer.                      |
| Salle rendue à 22 h 15, 15 min avant |
| la fermeture.                        |
| (brassage, « Accueil, pause et       |
|  marge » : inchangés)                |
|           [ Enregistrer et quitter ] |
|======================================|
| Tout le monde joue · 9 tours de      |
| 10 min · fin du jeu 22 h 05          |
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+

28 JOUEURS, AU REPOS (défaut)
| +- QUI JOUE À CHAQUE TOUR ---------+ |
| | ! 4 JOUEURS AU REPOS À CHAQUE    | |
| |   TOUR                           | |
| | 24 jouent (2 matchs, 4 équipes   | |
| | de 6), 4 sont au repos, à tour   | |
| | de rôle.                         | |
| | Avec 9 tours de 10 min,          | |
| | 20 joueurs jouent 8 matchs et    | |
| | 8 joueurs en jouent 7.           | |
| | Pour que tout le monde joue      | |
| | autant :                         | |
| | [ 7 tours de 14 min            ] | |
| |   chacun 6 matchs (84 min)       | |
| | Joueurs en trop                  | |
| | (o) Au repos, à tour de rôle     | |
| |     Chacun joue des matchs       | |
| |     entiers à 6 contre 6.        | |
| | ( ) Remplaçants : équipes        | |
| |     jusqu'à 8 joueurs            | |
| |     Tout le monde est dans une   | |
| |     équipe ; on tourne au        | |
| |     service.                     | |
| +----------------------------------+ |
(carte 1 : « 9 tours de 10 min · De 19 h 50
 à 22 h 05 · Chacun joue 7 ou 8 matchs
 (70 à 80 min) » ; barre collée : « 4 au
 repos par tour · 9 tours de 10 min · fin
 du jeu 22 h 05 »)

CHANGEMENT D'ÉQUIPE MODIFIÉ
| Changement d'équipe entre deux tours |
| [ − ]    3 min     [ + ]             |
| (cadenas) Valeur fixée par vous      |
| [ Revenir à 5 min ]                  |
| +- i · CE QUE CELA CHANGE ---------+ |
| | Avec 3 min au lieu de 5 :        | |
| | · 10 tours au lieu de 9          | |
| | · fin du jeu à 22 h 00 au lieu   | |
| |   de 22 h 05                     | |
| | · chacun joue 10 matchs au lieu  | |
| |   de 9                           | |
| | 3 min, c'est court pour changer  | |
| | de terrain à 24.                 | |
| +----------------------------------+ |

DEUX TABLEAUX (3 terrains, 10 joueuses, 20 joueurs)
| Combien de joueurs attendez-vous ?   |
| Joueuses   [ − ]   10   [ + ]        |
| Joueurs    [ − ]   20   [ + ]        |
| +- QUI JOUE À CHAQUE TOUR ---------+ |
| | TABLEAU DES JOUEUSES             | |
| | Terrains  [ − ]   1   [ + ]      | |
| | (v) Toutes jouent : 1 match,     | |
| |     2 équipes de 5.              | |
| | TABLEAU DES JOUEURS              | |
| | Terrains  [ − ]   2   [ + ]      | |
| | (v) Tous jouent : 2 matchs,      | |
| |     4 équipes de 5.              | |
| | 3 terrains utilisés sur 3        | |
| | disponibles.                     | |
| | Répartition calculée par le site | |
| | Avec 9 tours de 10 min, chacune  | |
| | et chacun joue 9 matchs.         | |
| | Joueurs en trop                  | |
| | (o) Au repos, à tour de rôle     | |
| | ( ) Remplaçants : équipes        | |
| |     jusqu'à 8 joueurs            | |
| +----------------------------------+ |
| +----------------------------------+ |
| |(o) Tout le monde joue autant     | |
| |    (conseillé)                   | |
| |    9 tours de 10 min             | |
| |    De 19 h 50 à 22 h 05          | |
| |    Joueuses : 9 matchs chacune   | |
| |    Joueurs : 9 matchs chacun     | |
| +----------------------------------+ |

DEUX TABLEAUX, ÉCART (2 terrains seulement)
| | TABLEAU DES JOUEURS              | |
| | Terrains  [ − ]   1   [ + ]      | |
| | ! 12 jouent, 8 sont au repos à   | |
| |   chaque tour, à tour de rôle.   | |
| +----------------------------------+ |
| +- ! ------------------------------+ |
| | Les joueurs joueront moins que   | |
| | les joueuses : 5 ou 6 matchs     | |
| | contre 9.                        | |
| | [ Passer en mêlée mixte        ] | |
| | Ou ajoutez un terrain (étape 1). | |
| +----------------------------------+ |
```

### 2.6 Étapes 4 et 5 : retouches

**Étape 4 · « Nombre maximum de joueurs par date »**
- Défaut : 24. Aide : « Au-delà de 24, des joueurs seront au repos à chaque tour. »
- En deux tableaux, deux champs : « Maximum de joueuses » et « Maximum de joueurs ».

**Étape 5 · « Règles affichées aux joueurs »**
- Mixte : « 6 contre 6 · mixte, au moins 2 joueuses par équipe · matchs de 10 min · filet à 2,35 m ».
- Avec l'option, on ajoute : « · classement général et par catégorie ».
- Deux tableaux : « 6 contre 6 · deux tableaux, joueuses et joueurs · matchs de 10 min · filet à 2,24 m et 2,43 m ».

**Étape 5 · Réglages avancés**
- Le groupe « Score » ne contient plus la hauteur du filet.
- Le groupe « Classement » affiche l'option en lecture, avec un lien : « Classement général et par catégorie (étape 2) ».

## 3. Jour J redessiné

Les onglets ne changent pas : Présents | Tour | Scores | Classement.

### 3.1 Pointage et « Programme du soir »

- Le bloc est **toujours affiché** sous la liste. Il commence par la phrase « qui joue », comme à l'étape 3.
- **Si rien ne change**, une ligne : « Programme inchangé : 9 tours de 10 min. Tout le monde joue à chaque tour, 9 matchs chacun. »
- **Sinon**, deux cartes à choix unique. Le **programme prévu reste choisi** : le site propose, il n'applique rien (D78, point 4).
- « Tirer le tour 1 » applique la carte choisie. La barre collée la rappelle.
- **Deux tableaux** :
  - la liste est en deux sections, « Joueuses · 12 présentes sur 13 » et « Joueurs · 22 présents sur 24 » ;
  - le bloc donne une ligne par tableau, avec ses terrains ;
  - si une autre répartition est meilleure, elle est proposée de la même façon.
- **Mixte** : la ligne « dont 9 joueuses (vu de vous seul) » reste, sauf si le classement par catégorie est activé (la mention entre parenthèses disparaît alors).

```
+--------------------------------------+
| MÊLÉE DU JEUDI 4 DÉCEMBRE            |
| Avant le tour 1 · début à 19 h 50    |
| [Présents] [Tour] [Scores] [Classt]  |
+--------------------------------------+
| Qui est là ?                         |
| 28 présents sur 30 inscrits,         |
| dont 9 joueuses (vu de vous seul)    |
| [ Tous présents ]                    |
| +----------------++----------------+ |
| |[x] Ana B.      ||[x] Jon E.      | |
| +----------------++----------------+ |
| |[ ] Léa C.      ||[x] Marc D.     | |
| +----------------++----------------+ |
| [ + Ajouter un membre non inscrit ]  |
| > Ajuster un niveau                  |
| +- PROGRAMME DU SOIR --------------+ |
| | ! 4 JOUEURS AU REPOS À CHAQUE    | |
| |   TOUR                           | |
| | 24 jouent (2 matchs, 4 équipes   | |
| | de 6), 4 sont au repos, à tour   | |
| | de rôle.                         | |
| | Choisissez le programme :        | |
| | +------------------------------+ | |
| | |( ) 7 tours de 14 min         | | |
| | |    Conseillé ce soir         | | |
| | |    Tout le monde joue        | | |
| | |    6 matchs (84 min)         | | |
| | |    Fin du jeu à 22 h 03      | | |
| | +------------------------------+ | |
| | |(o) 9 tours de 10 min         | | |
| | |    Programme prévu           | | |
| | |    20 joueurs jouent 8       | | |
| | |    matchs, 8 en jouent 7     | | |
| | |    Fin du jeu à 22 h 05      | | |
| | +------------------------------+ | |
| | > Voir les autres programmes     | |
| | > Joueurs en trop : au repos     | |
| +----------------------------------+ |
|======================================|
| 9 tours de 10 min · 4 au repos       |
| [        Tirer le tour 1         ]   |
+--------------------------------------+

DEUX TABLEAUX (bloc seul)
| +- PROGRAMME DU SOIR --------------+ |
| | 9 tours de 10 min, 3 terrains    | |
| | JOUEUSES · terrain 1             | |
| | (v) Toutes jouent : 2 équipes    | |
| |     de 6. 9 matchs chacune.      | |
| | JOUEURS · terrains 2 et 3        | |
| | (v) Tous jouent : 4 équipes de   | |
| |     5 ou 6. 9 matchs chacun.     | |
| | Programme inchangé.              | |
| +----------------------------------+ |
```

Le libellé de l'onglet reste « Classement » en entier : « Classt » n'est qu'une abréviation du dessin.

### 3.2 Tour, avec deux tableaux

- **Un seul compte à rebours**, en tête, commun aux deux tableaux, avec la ligne « Même horaire pour les deux tableaux ». Sans son (D78, point 8).
- **Temps écoulé** : « Temps écoulé : on finit l'échange en cours. » C'est la seule annonce faite aux lecteurs d'écran.
- **Une section par tableau**, dans l'ordre des terrains. Chaque terrain rappelle sa hauteur de filet.
- **« Au repos ce tour-ci »** : une ligne par tableau.
- **Un seul bouton** : « Tirer le tour 1 » tire les deux tableaux.
- **Échange refusé entre tableaux** : « Échange impossible : Léa et Marc ne jouent pas dans le même tableau. »
- **Vue du participant** : en tête, « Votre match : terrain 1, équipe A (tableau des joueuses) ». Son tableau est affiché en premier.

```
+--------------------------------------+
| TOUR 3 SUR 9 · en cours              |
|               07:42                  |
| Fin du tour à 20 h 30                |
| Même horaire pour les deux tableaux  |
+--------------------------------------+
| TABLEAU DES JOUEUSES                 |
| Terrain 1 · filet à 2,24 m           |
| Équipe A : Ana, Inès, Léa, Maïa,     |
|   Zoé, Emma                          |
| contre                               |
| Équipe B : Lou, Jade, Nina, Eva,     |
|   Rose, Alba                         |
| TABLEAU DES JOUEURS                  |
| Terrain 2 · filet à 2,43 m           |
| Équipe C : ...                       |
| contre                               |
| Équipe D : ...                       |
| Terrain 3 · filet à 2,43 m           |
| Équipe E : ...                       |
| contre                               |
| Équipe F : ...                       |
| Au repos ce tour-ci : Tom, Paul      |
| > Tour suivant (déjà tiré)           |
| > Modifier en cours                  |
|======================================|
| [       Saisir les scores        ]   |
+--------------------------------------+
```

### 3.3 Scores, avec deux tableaux

- **Des sections, pas des onglets** : 6 matchs au plus, et le responsable saisit tout sans changer de vue.
- **Compteur en bas** : « 1 score saisi sur 3 » (« 2 scores saisis »).
- **Navigation entre tours**, en tête, pour corriger : « ‹ Tour 2 » et « Tour 4 › ».

```
+--------------------------------------+
| SCORES DU TOUR 3                     |
| [ ‹ Tour 2 ]            [ Tour 4 › ] |
+--------------------------------------+
| TABLEAU DES JOUEUSES                 |
| Terrain 1                            |
| Équipe A  [ 16 ] - [ 15 ]  Équipe B  |
| TABLEAU DES JOUEURS                  |
| Terrain 2                            |
| Équipe C  [    ] - [    ]  Équipe D  |
| Terrain 3                            |
| Équipe E  [    ] - [    ]  Équipe F  |
| 1 score saisi sur 3. Vous pourrez    |
| saisir ou corriger plus tard,        |
| jusqu'à la clôture de la mêlée.      |
|======================================|
| [     Enregistrer les scores     ]   |
+--------------------------------------+
```

### 3.4 Tour en retard

**Retard absorbé par la marge** : une ligne sous le compte à rebours, sans action : « 6 min de retard : fin du jeu vers 22 h 11, toujours avant la fermeture. »

**Retard qui dépasse** : l'encadré, au-dessus de « Modifier en cours ».
- Chaque option affiche sa conséquence.
- L'ordre suit l'objectif (inchangé depuis la v2).
- En deux tableaux, le titre précise « (les deux tableaux) ».
- Si raccourcir donnerait moins de 8 min, seule la suppression est proposée.

```
| +- ! 18 MIN DE RETARD -------------+ |
| | À ce rythme, la salle serait     | |
| | rendue à 22 h 33, après la       | |
| | fermeture (22 h 30).             | |
| | [ Raccourcir les 5 tours       ] | |
| | [ restants à 9 min             ] | |
| |   Chacun joue toujours 9 matchs. | |
| |   Salle rendue à 22 h 28.        | |
| | [ Supprimer le dernier tour    ] | |
| |   Chacun joue 8 matchs au lieu   | |
| |   de 9. Salle rendue à 22 h 18.  | |
| | [ Plus tard ]                    | |
| +----------------------------------+ |
```

### 3.5 Classement

| Mêlée | Onglets (au moins 44 px, `role="tablist"`) | Onglet ouvert par défaut |
|---|---|---|
| Un seul classement | aucun | – |
| Mixte avec classement par catégorie | Général, Joueuses, Joueurs | Général |
| Deux tableaux | Joueuses, Joueurs | celui du participant ; Joueuses pour le responsable |

- Des onglets ici, pas des sections : les listes font de 10 à 30 lignes.
- Le rang est recalculé dans chaque onglet (1, 2, 3…), sur les mêmes matchs.
- Sous-titre : « Classement des joueuses · 9 classées », « Classement des joueurs · 19 classés », « Classement général · 28 classés ». Singulier : « 1 classée », « 1 classé ».
- Le dépliant « Pas encore classés (moins de 5 matchs) » est présent dans chaque onglet ; « Pas encore classées » dans l'onglet Joueuses.

```
+--------------------------------------+
| CLASSEMENT                           |
| [ Général ][ Joueuses ][ Joueurs ]   |
+--------------------------------------+
| Classement des joueuses · 9 classées |
| Pour le plaisir : les équipes        |
| changent à chaque tour.              |
| 1  Léa C.                            |
|    7 victoires sur 9 matchs, +4,2    |
| 2  Ana B.                            |
|    6 victoires sur 9 matchs, +2,0    |
| ...                                  |
| > Pas encore classées (moins de      |
|   5 matchs)                          |
+--------------------------------------+
```

## 4. Inscription d'un membre

La ligne de règles, sous le lieu, reprend la source unique de l'étape 5 (avec le filet).

### 4.1 Mêlée réservée

- **Mention sous le titre** : « Mêlée féminine : réservée aux joueuses » ou « Mêlée masculine : réservée aux joueurs ».
- **Catégorie connue et conforme** : pas de champ. Une ligne : « Les participants et les membres du club verront votre inscription à cette mêlée féminine. »
- **Catégorie inconnue** : une case obligatoire « Je joue en catégorie féminine \* » (H33). On n'enregistre jamais « masculine » pour quelqu'un qui ne peut pas s'inscrire.
  - Aide : « Votre catégorie de jeu sera notée sur votre fiche. Les participants et les membres du club verront votre inscription à cette mêlée féminine. »
  - Erreur : « Cochez la case pour vous inscrire : cette mêlée est réservée aux joueuses. »
- **Catégorie connue et différente** : pas de formulaire. « Cette mêlée est réservée aux joueuses : vous ne pouvez pas vous y inscrire. » Je remplace le « réservée à la catégorie féminine » de la v2, pour reprendre le mot de l'étape 2.

### 4.2 Deux tableaux

- **Mention** : « Deux tableaux : les joueuses d'un côté, les joueurs de l'autre ».
- **Places par tableau** : « Joueuses : 8 inscrites sur 12 · Joueurs : 20 inscrits sur 24 ». Si complet : « (complet : liste d'attente) ».
- **Catégorie inconnue** : « Votre tableau \* », avec deux choix : « Tableau des joueuses (catégorie féminine) » et « Tableau des joueurs (catégorie masculine) ».
- **Catégorie connue** : « Vous jouerez dans le tableau des joueuses. » et le lien « Modifier ma catégorie de jeu ».
- **Avertissement, toujours affiché avant le bouton** : « Les équipes et le classement sont séparés : les participants et les membres du club verront dans quel tableau vous jouez, donc votre catégorie de jeu. »
- **Confirmation** : « Inscription enregistrée pour le jeudi 4 décembre, dans le tableau des joueuses. »

### 4.3 Mêlée mixte avec classement par catégorie

- **Catégorie inconnue** : le champ « Catégorie de jeu \* » de la v1, avec une nouvelle aide : « Sert à composer des équipes mixtes et à établir le classement des joueuses et celui des joueurs. Les participants et les membres du club la verront dans ce classement. »
- **Catégorie connue** : un encadré avant le bouton : « Cette mêlée a un classement par catégorie : votre catégorie de jeu (féminine) y sera visible des participants et des membres du club. »

```
DEUX TABLEAUX, CATÉGORIE INCONNUE
+--------------------------------------+
| MÊLÉE DU JEUDI 4 DÉCEMBRE            |
| Deux tableaux : les joueuses d'un    |
| côté, les joueurs de l'autre         |
| Inscriptions jusqu'au mer. 3 déc.    |
| à 12 h                               |
| Saint-Bernard, Bayonne · 19 h 30     |
| 6 contre 6 · matchs de 10 min ·      |
| filet à 2,24 m et 2,43 m             |
| Joueuses : 8 inscrites sur 12        |
| Joueurs : 20 inscrits sur 24         |
| Votre tableau *                      |
| +----------------------------------+ |
| |( ) Tableau des joueuses          | |
| |    (catégorie féminine)          | |
| +----------------------------------+ |
| |( ) Tableau des joueurs           | |
| |    (catégorie masculine)         | |
| +----------------------------------+ |
| +- i ------------------------------+ |
| | Les équipes et le classement     | |
| | sont séparés : les participants  | |
| | et les membres du club verront   | |
| | dans quel tableau vous jouez,    | |
| | donc votre catégorie de jeu.     | |
| +----------------------------------+ |
|======================================|
| [          Je m'inscris          ]   |
+--------------------------------------+

MÊLÉE RÉSERVÉE, CATÉGORIE INCONNUE
| MÊLÉE DU JEUDI 4 DÉCEMBRE            |
| Mêlée féminine : réservée aux        |
| joueuses                             |
| ...                                  |
| [ ] Je joue en catégorie féminine *  |
|   Votre catégorie de jeu sera notée  |
|   sur votre fiche. Les participants  |
|   et les membres du club verront     |
|   votre inscription à cette mêlée    |
|   féminine.                          |
|======================================|
| [          Je m'inscris          ]   |


MÊLÉE RÉSERVÉE, CATÉGORIE DIFFÉRENTE
| +- i ------------------------------+ |
| | Cette mêlée est réservée aux     | |
| | joueuses : vous ne pouvez pas    | |
| | vous y inscrire.                 | |
| +----------------------------------+ |
(pas de bouton)

MIXTE AVEC CLASSEMENT PAR CATÉGORIE, CATÉGORIE CONNUE
| Je viens :                           |
| [x] jeudi 4 décembre (18 inscrits    |
|     sur 24)                          |
| +- i ------------------------------+ |
| | Cette mêlée a un classement par  | |
| | catégorie : votre catégorie de   | |
| | jeu (féminine) y sera visible    | |
| | des participants et des membres  | |
| | du club.                         | |
| +----------------------------------+ |
|======================================|
| [          Je m'inscris          ]   |
```

## 5. Incohérences entre D78 et le modèle de calcul

1. **10 min contre « durée proche de 14 »** (calcul-melee.md §3.2, règle 4). L'objectif par défaut classe par écart, puis temps de jeu, puis durée proche de 14 : il ne sortira jamais 10 min de lui-même. Pour 24 joueurs, il donnerait même 6 tours de 17 min, comme « Des matchs plus longs ». Il faut écrire dans le modèle que la durée par défaut est imposée à la carte conseillée (H25).
2. **10 min et repos par défaut contre « tout le monde joue autant »**. Avec le repos, l'égalité demande un nombre de tours multiple du cycle. Pour 28 joueurs, c'est 7 tours : à 10 min, la soirée finit 30 min plus tôt, ou bien on garde 9 tours et l'écart est d'un match. Les défauts retirent donc le levier de l'objectif par défaut. D'où H26. L'expert doit dire ce que la carte conseillée affiche dans ce cas.
3. **Repos par défaut : effectifs mal servis**. De 13 à 19 joueurs sur 2 terrains en 6 contre 6, le modèle ne fait qu'un match : 12 jouent, jusqu'à 7 attendent. Avec des remplaçants, 16 joueraient. Le bloc « Qui joue » le dit et propose l'option, mais le cas sera fréquent.
4. **Changement d'équipe**. Le modèle le fait varier de 3 à 10 min et le propose comme sortie de conflit (« battement de 4 min »). D78 en fait un réglage : il sort du parcours automatique. Reste à dire s'il demeure une option de conflit ; je la garde, comme en v2.
5. **Pointage : « plus ou moins 2 min »** (cas C11). Depuis 10 min, le programme égal pour 28 présents est à 14 min, soit 4 min de plus. La limite est à lever, puisque le site ne fait que proposer.
6. **Retard et marge**. Le programme garde 15 min de marge. L'exemple de la v2 (6 min de retard) ne mettait pas la fermeture en cause. D'où H32.
7. **Filet** :
   - D78 ne donne pas de valeur pour « Ouvertes à tous » ; le modèle dit « au choix du club » ;
   - en deux tableaux, changer la répartition le soir oblige à régler un filet : le message de proposition doit le dire ;
   - 2,35 m ne doit pas être présenté comme une hauteur officielle. « Hauteur mixte » suffit.
8. **Deux tableaux**. Le modèle exige 10 joueuses en 6 contre 6, ce qui sera souvent manqué. Il ne dit pas si les deux tableaux peuvent avoir des formats différents ; j'ai supposé que non (H30).
9. **Écart joueuses / joueurs « au responsable seul »** (D78, point 5). Le classement par catégorie affiche « 7 victoires sur 10 matchs » : l'écart de matchs devient visible de tous dès que l'option est activée.

## 6. Points d'attention

1. **Catégorie de jeu, à voir avec l'agent securite** :
   - une mêlée réservée révèle aussi la catégorie de tous les inscrits, ce que D77 et D78 ne disent pas ; les textes ci-dessus le prévoient, les conditions d'utilisation devront suivre ;
   - verrou de l'option après la première inscription (H29) ;
   - question ouverte : un joueur peut-il refuser d'apparaître dans le classement par catégorie tout en restant au classement général ?
2. **Composant unique** : le bloc « Qui joue à chaque tour » sert à l'étape 3, à la clôture et au pointage. Un seul gabarit, avec les cartes de programme de la v2.
3. **Longueur de l'étape 3** : environ 7 hauteurs d'écran à 360 px. La barre collée, qui commence par le verdict, devient indispensable. À vérifier en maquette avant d'envisager un découpage.

## 7. Pour le webdesigner

1. Les trois états d'une valeur (« par défaut », « fixée par vous », « calculée par le site »), à distinguer sans la couleur seule.
2. Le titre-verdict du bloc « Qui joue » : état favorable et état d'alerte, avec une icône doublée du texte.
3. L'encadré « Ce que cela change » : informatif, à distinguer du conflit et de l'alerte.
4. Les sections « Tableau des joueuses » et « Tableau des joueurs » : aucune couleur genrée (ni rose ni bleu). Même consigne pour les onglets du classement.
5. Les cartes du « Programme du soir » : faire cohabiter les mentions « Conseillé ce soir » et « Programme prévu » avec l'état choisi.
6. Le compte à rebours commun et la ligne de retard : lisibles à 3 m.
7. Le rappel « filet à 2,24 m » sur chaque terrain : discret, mais lisible.
8. Les onglets du classement : 3 onglets de 44 px sur 360 px, état choisi visible autrement que par la couleur.
