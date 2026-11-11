# Mêlée : parcours révisé avec le modèle « fixer ou faire calculer » (avis de l'ergonome)

**Verdict : oui avec conditions.** Aucune des 8 questions de l'expert n'empêche de dessiner les écrans. J'ai tranché par hypothèse, à faire confirmer : H11 à H24 ci-dessous. Trois d'entre elles changent un écran et sont à confirmer avant de coder :
- **H13** : le battement n'est pas calculé ;
- **H16** : remplaçants ou repos ;
- **H19** : pas de deux tableaux en v1.

J'ai lu :
- /home/amoul/projects/blvb/docs/espace-club/calcul-melee.md
- /home/amoul/projects/blvb/docs/espace-club/parcours-melee.md
- D76 dans /home/amoul/projects/blvb/docs/wizard-saison/decisions.md
- P22 à P58 de /home/amoul/projects/blvb/docs/espace-club/parametres-melee.md
- /home/amoul/projects/blvb/templates/front/fragments/_lieu_statut.html.twig

## Hypothèses

- **H11, 5 étapes au lieu de 4.** L'ancienne étape 2 « Le jeu » est éclatée :
  - « Format et mixité » : les choix du club ;
  - « Programme » : les cartes de scénarios.
  
  À 360 px, tout réunir ferait un écran de plus de 6 hauteurs. Surtout, le format doit être choisi avant le calcul.
- **H12, le format et la mixité sont toujours choisis par le responsable.** Le site ne les calcule pas. Il ne propose un autre format que par la carte « Autre idée » ou par une option de conflit.
- **H13, le battement est un réglage (5 min par défaut), pas une valeur calculée.** Sinon, le classement prendrait toujours 3 min, ce qui est irréaliste pour changer 24 personnes. Seule une option de conflit peut le changer, par exemple « battement de 4 min ». C'est contraire au §3.2 de l'expert, qui parcourt b de 3 à 10 : à valider avec lui.
- **H14 (question 1)** : objectif par défaut « Tout le monde joue autant », avec la mention « (conseillé) ».
- **H15 (question 2)** : plus de durée par défaut. La durée est calculée tant que le responsable ne la fixe pas.
- **H16 (question 3)** : « Remplaçants » par défaut. Le choix est visible à l'étape 3, parce qu'il change les cartes.
- **H17 (question 4)** : au pointage, le site propose et n'applique jamais de lui-même.
- **H18 (question 5)** : l'écart entre joueuses et joueurs est affiché au responsable seul, avec une option en un geste. Rien n'est automatique.
- **H19 (question 6)** : pas de deux tableaux le même soir en v1. Il reste 4 modes : Mixte, Ouverte à tous, Féminine, Masculine.
- **H20 (question 7)** : aucune hauteur de filet pré-remplie avant vérification. Le champ reste vide (« À indiquer, facultatif »).
- **H21** : « Aux points seulement » est retiré. Avec ce mode, on ne peut calculer ni la durée d'un match ni l'heure de fin. « Au temps ou à 25 points, au premier atteint » passe en réglages avancés.
- **H22** : la durée d'un match et le battement sont communs à toutes les dates. Le nombre de tours et les terrains utilisés se règlent date par date.
- **H23** : une seule estimation de joueurs vaut pour toutes les dates.
- **H24 (question 8)** : le libellé du tirage devient « Éviter de rejouer avec les mêmes coéquipiers (le moins possible) ».

## Règles d'affichage du « fixé ou calculé »

- **Libellés neutres**, pour éviter les accords (« fixée », « fixé ») d'une ligne à l'autre :
  - « Valeur calculée par le site » ;
  - « Valeur fixée par vous », avec une icône de cadenas (`aria-hidden`) : le texte porte l'information, pas l'icône ni la couleur.
- **Toucher − ou + fixe la valeur.** Le calcul part 0,5 s après la dernière touche, pas à chaque appui.
- **« Laisser le site choisir »** est un bouton de 44 px de haut. Son nom accessible complet est « Laisser le site choisir la durée d'un match ».
- **Défaut majeur à 360 px** : on modifie une valeur sous les cartes, qui se recalculent hors de l'écran. D'où :
  - une **barre de résumé collée** au-dessus de « Précédent / Suivant », par exemple « 7 tours de 14 min · fin du jeu 22 h 03 · chacun 7 matchs ». Elle porte `aria-live="polite"` et est la seule zone annoncée ;
  - **l'encadré de conflit sous la ligne modifiée**, là où se trouve le regard, et non en haut.
- **Cartes** : `fieldset` avec la `legend` « Choisissez un programme », et des boutons radio (motif `choix-carte`).
- **Doublons** : jamais deux cartes identiques. Si deux objectifs donnent le même programme, une seule carte porte les deux titres : « Tout le monde joue autant et le plus de matchs ».
- **Tout est fixé** : une seule carte, intitulée « Votre programme », avec l'aide « Vous avez tout fixé : un seul programme possible. »

## Étape 1 · Quand et où (modifiée)

Ordre des champs : Gymnase, puis Date. Les heures et les terrains dépendent des deux.

| Libellé | Défaut | Aide ou message |
|---|---|---|
| **Ouverture** et **Fermeture** | heures du créneau ce jour-là, sinon 19:30 et 22:30 | aide « Reprises du créneau du jeudi. » ; si on change de date ou de gymnase, les heures se mettent à jour tant que le responsable ne les a pas modifiées |
| **Terrains disponibles** (compteur de 1 à 6) | capacité la plus faible sur la plage, moins les matchs du championnat ce soir-là | voir les variantes ci-dessous |

Aide permanente sous les terrains : « Le site en utilisera autant qu'il faut, jamais plus. »

Variantes sous les terrains :
- **a** : « Repris du créneau du jeudi (2 terrains). »
- **b** : « Créneau du jeudi : 3 terrains, dont 1 pris par un match du championnat. »
- **c** : « Pas de créneau habituel ce jour-là : indiquez le nombre de terrains. » La valeur précédente est reprise, sinon 2.
- **d** (modifié) : « Valeur fixée par vous (le créneau en prévoit 2). » et le bouton [Reprendre 2]. Si la valeur dépasse le créneau, l'alerte « Plus que les 2 terrains du créneau : vérifiez auprès du gymnase. » s'ajoute.
- **e** : « Tous les terrains sont pris par le championnat ce soir-là. Choisissez une autre date ou un autre gymnase. » Le champ est vide ; on peut le remplir pour forcer.

Aide de « + Ajouter une autre date » : « Même gymnase, mêmes heures : seule la date est à choisir. Les terrains sont repris du créneau de ce jour-là. »

```
+--------------------------------------+
| < Mon club : BAB                     |
| NOUVELLE MÊLÉE                       |
| Brouillon · visible de vous seul     |
| (1)--(2)--(3)--(4)--(5)              |
| Étape 1 sur 5 · Quand et où          |
+--------------------------------------+
| +- DATE 1 -------------------------+ |
| | Gymnase *                        | |
| | [ Saint-Bernard, Bayonne    v ]  | |
| | Date *                           | |
| | [ jeu. 04/12/2026          ]     | |
| | Ouverture *    Fermeture *       | |
| | [ 19:30   ]    [ 22:30   ]       | |
| | Reprises du créneau du jeudi.    | |
| | Fermeture : heure à laquelle la  | |
| | salle doit être libérée.         | |
| | Terrains disponibles *           | |
| | [ − ]      2      [ + ]          | |
| | Repris du créneau du jeudi       | |
| | (2 terrains). Le site en         | |
| | utilisera autant qu'il faut,     | |
| | jamais plus.                     | |
| +----------------------------------+ |
| + - - - - - - - - - - - - - - - - -+ |
| |   + Ajouter une autre date       | |
| + - - - - - - - - - - - - - - - - -+ |
|           [ Enregistrer et quitter ] |
|======================================|
| [           Suivant  ->            ] |
+--------------------------------------+

VARIANTE d (terrains modifiés)
| | Terrains disponibles *           | |
| | [ − ]      3      [ + ]          | |
| | (cadenas) Valeur fixée par vous  | |
| | (le créneau en prévoit 2).       | |
| | [ Reprendre 2 ]                  | |
| | ! Plus que les 2 terrains du     | |
| |   créneau : vérifiez auprès du   | |
| |   gymnase.                       | |
```

## Étape 2 · Format et mixité (nouvelle)

1. **Format** : 4 cartes, comme avant. « 6 contre 6 (comme au championnat) » est choisi par défaut.
2. **Les équipes \*** : 4 lignes à choix unique.
   - « Mixtes », choisi par défaut. Il ouvre le compteur **« Au moins [2] joueuses par équipe »**, de 1 à k. Le défaut est 2 en 6 contre 6, 1 sinon, et il suit le format tant qu'on ne le modifie pas. Le 0 disparaît : c'est le mode suivant.
   - « Ouvertes à tous, sans minimum de joueuses ».
   - « Féminines : réservées aux joueuses ».
   - « Masculines : réservées aux joueurs ».
   
   Aide calculée selon le mode (elle remplace l'encadré de l'ancienne étape 3) :
   - modes Mixtes, Féminines et Masculines : « Chaque joueur indiquera sa catégorie de jeu en s'inscrivant, s'il ne l'a pas déjà fait. Elle n'est visible que de lui et de vous. » ;
   - mode Ouvertes : « Personne n'aura à indiquer sa catégorie de jeu. »
3. **Équilibrer les niveaux** : interrupteur, sans changement.

```
+--------------------------------------+
| Étape 2 sur 5 · Format et mixité     |
+--------------------------------------+
| Format *                             |
| [(o) 6 contre 6  ][( ) 4 contre 4  ] |
| [ comme au champ.][                ] |
| [( ) 3 contre 3  ][( ) 2 contre 2  ] |
| Les équipes *                        |
| +----------------------------------+ |
| |(o) Mixtes                        | |
| |  Au moins                        | |
| |  [ − ]    2    [ + ]             | |
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
| Chaque joueur indiquera sa catégorie |
| de jeu en s'inscrivant, s'il ne l'a  |
| pas déjà fait. Elle n'est visible    |
| que de lui et de vous.               |
| [x] Équilibrer les niveaux           |
|   Le site s'appuie sur la poule de   |
|   chaque joueur au championnat. Ce   |
|   niveau n'est montré qu'à vous.     |
|           [ Enregistrer et quitter ] |
|======================================|
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+
```

## Étape 3 · Programme (nouvelle, remplace « Le jeu »)

Contenu, dans l'ordre :

1. **« Combien de joueurs attendez-vous ? »**
   - Compteur de 10 à 48. Le défaut est 2 × terrains × joueurs sur le terrain, soit 24.
   - En mode Mixtes seulement : « dont [8] joueuses ». Le défaut est le minimum par équipe × le nombre d'équipes.
   - Aide : « Une estimation suffit : le programme sera recalculé avec les vrais chiffres à la fin des inscriptions et au pointage. »
2. **« Choisissez un programme \* »** : 3 cartes empilées. Chacune affiche seulement ce qui la distingue des autres, en 3 lignes :
   - « 7 tours de 14 min » ;
   - « De 19 h 50 à 22 h 03 » ;
   - « Chacun joue 7 matchs (98 min) », ou « 5 à 6 matchs (70 à 84 min) ».
   
   Avec plusieurs dates, une ligne par date : « Jeu. 4 déc. : 7 tours, de 19 h 50 à 22 h 03 ». Pluriels calculés : « 1 tour » / « 2 tours », « 1 match » / « 2 matchs ».
3. **Écart joueuses / joueurs** : une note sous les cartes, seulement si l'écart est tendu. Il est le même pour les 3 cartes, d'où une note unique plutôt qu'une ligne par carte.
4. **Carte « Autre idée »**, seulement si un autre format change vraiment le brassage (règle 5 du §4 de l'expert). Le bouton [Essayer ce format] modifie l'étape 2 et affiche « Format changé : 4 contre 4 (étape 2). »
5. **« Réglages du programme choisi »**, une ligne par valeur, avec son état sous la valeur :
   - **Durée d'un match**, de 8 à 20 min ;
   - **Nombre de tours**, une ligne par date s'il y en a plusieurs ;
   - **Terrains utilisés**, « sur 2 disponibles » ;
   - **Changement d'équipe entre deux tours**, 5 min (réglage, voir H13). Aide : « Le temps de rejoindre son terrain et de s'échauffer. » ;
   - **Joueurs en trop** : « Remplaçants, jusqu'à 8 par équipe » (défaut) ou « Au repos : matchs à 6 complets ».
6. **Lignes calculées, en lecture seule** :
   - « Salle rendue à 22 h 13, 17 min avant la fermeture. » ;
   - « Chacun joue avec environ 20 personnes différentes. On retrouve parfois les mêmes coéquipiers, le moins possible. »
7. **Dépliant fermé « Accueil, pause et marge »** : accueil 20 min, pause, résultats et rangement 10 min, marge 10 %. Il quitte les réglages avancés de la dernière étape, parce qu'il change le calcul affiché ici.
8. **Barre collée** : le résumé `aria-live`, puis « Précédent / Suivant ».

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
| Choisissez un programme *            |
| +----------------------------------+ |
| |(o) Tout le monde joue autant     | |
| |    (conseillé)                   | |
| |    7 tours de 14 min             | |
| |    De 19 h 50 à 22 h 03          | |
| |    Chacun joue 7 matchs (98 min) | |
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
| [ − ]    14 min    [ + ]             |
| Valeur calculée par le site          |
| Nombre de tours                      |
| [ − ]      7       [ + ]             |
| Valeur calculée par le site          |
| Terrains utilisés                    |
| [ − ]      2       [ + ]  sur 2      |
| Valeur calculée par le site          |
| Changement d'équipe entre deux tours |
| [ − ]    5 min     [ + ]             |
| Joueurs en trop                      |
| (o) Remplaçants, jusqu'à 8 par équipe|
| ( ) Au repos : matchs à 6 complets   |
| Salle rendue à 22 h 13, 17 min avant |
| la fermeture.                        |
| Chacun joue avec environ 20          |
| personnes différentes. On retrouve   |
| parfois les mêmes coéquipiers, le    |
| moins possible.                      |
| > Accueil, pause et marge            |
|   (accueil 20 min, rangement 10 min, |
|   marge 10 %)                        |
|           [ Enregistrer et quitter ] |
|======================================|
| 7 tours de 14 min · fin du jeu       |
| 22 h 03 · chacun 7 matchs            |
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+

VALEUR FIXÉE
| Durée d'un match                     |
| [ − ]    15 min    [ + ]             |
| (cadenas) Valeur fixée par vous      |
| [ Laisser le site choisir ]          |

CONFLIT (sous la ligne modifiée ; résumé collé :
« ! Impossible avant 22 h 30 : choisissez une solution »)
| Nombre de tours                      |
| [ − ]      8       [ + ]             |
| (cadenas) Valeur fixée par vous      |
| [ Laisser le site choisir ]          |
| +- ! IMPOSSIBLE -------------------+ |
| | 8 tours de 15 min ne tiennent    | |
| | pas avant la fermeture du        | |
| | gymnase (22 h 30).               | |
| | Choisissez une solution :        | |
| | [ 7 tours de 14 min            ] | |
| |   1 tour et 1 min de moins       | |
| | [ 8 tours de 11 min            ] | |
| |   4 min de moins par match       | |
| | [ 6 tours de 15 min            ] | |
| |   2 tours de moins               | |
| | Ou modifiez une valeur fixée.    | |
| +----------------------------------+ |
(cartes grisées, avec la mention « En attente
 d'une solution » ; Suivant inactif, expliqué :
 « Choisissez une solution pour continuer. »)

ÉCART JOUEUSES (sous les cartes ; 28 joueurs, dont 8 joueuses)
| +- ! ------------------------------+ |
| | Avec 8 joueuses sur 28 et au     | |
| | moins 2 par équipe, les joueuses | |
| | joueront plus que les joueurs :  | |
| | environ 98 min contre 78 min.    | |
| | [ Passer à 1 joueuse minimum   ] | |
| | Ou ajoutez un terrain (étape 1). | |
| +----------------------------------+ |

AUTRE IDÉE (seulement si utile)
| + - - - - - - - - - - - - - - - - -+ |
| | Autre idée : changer souvent de  | |
| | coéquipiers                      | |
| | 4 contre 4 sur 3 terrains,       | |
| | 7 tours de 14 min                | |
| | [ Essayer ce format ]            | |
| + - - - - - - - - - - - - - - - - -+ |
```

Règles des boutons :
- **Option de conflit** : la valeur appliquée devient « Valeur fixée par vous ». Le focus va à la ligne modifiée, et le résumé collé l'annonce.
- **Encadrés** : jamais de `role="alert"` sur un recalcul ordinaire. Seul le conflit est annoncé, et une seule fois.

## Étape 4 · Inscriptions (peu modifiée)

- **Encadré sur la catégorie** : retiré, il est désormais à l'étape 2.
- **« Nombre maximum de joueurs par date »** :
  - le défaut suit l'étape 3 : 32, avec l'aide « Calculé : 2 terrains × 2 équipes × 8 joueurs (6 sur le terrain et 2 remplaçants). » ;
  - avec « Au repos », l'aide devient « Au-delà de 24, des joueurs seront au repos à chaque tour. »
- **Ligne calculée** :
  - mode Mixtes : « Il faut au moins 10 joueurs, dont 4 joueuses. » ;
  - mode Ouvertes : « Il faut au moins 10 joueurs. » ;
  - modes Féminines et Masculines : « Il faut au moins 10 joueurs. Seuls les membres de catégorie féminine pourront s'inscrire. » (ou « masculine »).

## Étape 5 · Vérifier et publier (modifiée)

- **Règles affichées aux joueurs** : « 6 contre 6 · mixte, au moins 2 joueuses par équipe · matchs de 14 min environ ».
  - Pas de nombre de tours : il peut changer au pointage.
  - Pas de filet tant que la hauteur n'est pas indiquée (H20).
- **Récapitulatif** : 4 cartes avec le lien « Modifier » : Quand et où, Format et mixité, Programme, Inscriptions. Texte de la carte Programme : « Tout le monde joue autant : 7 tours de 14 min, prévu pour 24 joueurs. Recalculé à la fin des inscriptions et au pointage. »
- **Réglages avancés restants** :
  - Équipes : de 5 à 8 joueurs ; joueuses en nombre insuffisant réparties au mieux ;
  - Score : « Fin d'un match : au temps » ou « au temps ou à [25] points, au premier atteint » ; point en or ; hauteur du filet ;
  - Tirage ;
  - Classement ;
  - Absences.
  
  Le groupe « Temps » est passé à l'étape 3.

```
| +- Programme ------------ Modifier + |
| | Tout le monde joue autant :      | |
| | 7 tours de 14 min, prévu pour    | |
| | 24 joueurs. Recalculé à la fin   | |
| | des inscriptions et au pointage. | |
| +----------------------------------+ |
| > Réglages avancés                   |
|   > Équipes : de 5 à 8 joueurs       |
|   > Score : au temps, point en or    |
|   > Tirage : éviter de rejouer avec  |
|     les mêmes (le moins possible)    |
|   > Classement : % de victoires      |
|   > Absences : remplacement autorisé |
```

## Clôture, pointage et soirée

**Clôture des inscriptions.** La page de la mêlée affiche une section « Programme pour 30 inscrits ». Elle reprend le même composant (cartes et réglages), en conservant les valeurs fixées. Une alerte n'apparaît que si le programme change.

**Pointage (J1).** Un bloc s'ajoute sous la liste, seulement si le nombre de présents change le résultat. Sinon, une seule ligne : « Programme inchangé : 8 tours de 11 min, chacun joue 6 matchs. »

```
| 28 présents (32 inscrits)            |
| +- Programme du soir --------------+ |
| | Avec 28 présents, 4 joueurs      | |
| | joueraient 1 match de moins que  | |
| | les autres.                      | |
| | [ 7 tours de 14 min : tout le  ] | |
| | [ monde joue 6 matchs          ] | |
| | [ Garder 8 tours de 11 min     ] | |
| | > Voir les 3 programmes          | |
| +----------------------------------+ |
|==============================|
| [    Tirer le tour 1       ] |
```

Le bouton « Tirer le tour 1 » reste actif sans choix : on garde alors le programme prévu.

**En cours de soirée (J3), tour en retard.** Un encadré s'affiche au-dessus du dépliant « Modifier en cours ».

```
| +- ! ------------------------------+ |
| | Le tour 3 a fini 6 min après     | |
| | l'heure prévue. Pour finir le    | |
| | jeu avant 22 h 30 :              | |
| | [ Raccourcir les 5 tours        ]| |
| | [ restants à 12 min             ]| |
| | [ Supprimer le dernier tour     ]| |
| | [ Plus tard ]                    | |
| +----------------------------------+ |
```

- L'option proposée en premier dépend de l'objectif choisi :
  - « Tout le monde joue autant » : raccourcir les tours restants ;
  - les deux autres objectifs : supprimer le dernier tour.
- « Plus tard » masque l'encadré. Il revient au tour suivant si le retard demeure.

**Inscription d'un membre, mêlée réservée.** Si sa catégorie n'est pas connue, on lui demande « Catégorie de jeu \* ». Si elle ne correspond pas, on affiche « Cette mêlée est réservée à la catégorie féminine : vous ne pouvez pas vous y inscrire. »

## Points d'attention

1. **H13, le battement** : à confirmer avec l'expert. S'il reste calculé, le site choisira toujours 3 min.
2. **H21, « Aux points seulement » retiré** : à faire confirmer par l'utilisateur.
3. **Composants partagés** : les cartes de programme, l'encadré de conflit et la barre de résumé collée servent à la création, à la clôture, au pointage et en soirée. Il faut un seul composant Twig/Stimulus, et pas quatre copies.
4. **Graphisme, à voir avec le webdesigner** :
   - la carte « Autre idée » en pointillés ;
   - l'état grisé des cartes pendant un conflit ;
   - la distinction entre « Valeur calculée » et « Valeur fixée », qui ne doit pas reposer sur la couleur seule.

Enregistré tel quel le 11/11/2026 ; remplace les étapes 2 à 4 de `parcours-melee.md`. Hypothèses H11 à H24 à faire valider.
