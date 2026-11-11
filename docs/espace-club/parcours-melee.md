# Mêlée : parcours du responsable, du jour J et du membre (avis de l'ergonome)

**Verdict : oui, la conception peut commencer.** Aucune des 9 questions du §7 n'empêche de dessiner les écrans. Là où il fallait trancher, j'ai pris une hypothèse (liste H1 à H10 ci-dessous). Deux d'entre elles sont à faire confirmer avant de coder, parce qu'elles changent un écran :
- **H3** : le niveau est déduit de la poule du joueur (question 3) ;
- **H9** : le minuteur (question 9).

Enregistré tel quel le 11/11/2026 ; hypothèses H1 à H10 à faire valider par l'utilisateur.

Ce que j'ai lu :
- `/home/amoul/projects/blvb/docs/espace-club/parametres-melee.md` et le §3.12 de `formules-tournoi.md` ;
- D65, D74 et D75 dans `/home/amoul/projects/blvb/docs/wizard-saison/decisions.md` ;
- les gabarits existants que je reprends :
  - `templates/admin/saisonwizard/layout.html.twig` et `_navigation.html.twig` : indicateur d'étapes « Étape X sur Y · libellé », barre du bas collée avec « Précédent / Suivant » et « Enregistrer et quitter » ;
  - `templates/front/feuille_match/_presents.html.twig` : « Qui est là ? », cases `choix-carte choix-ligne`, `details.depliant` ;
  - l'entrée « Mon club » de `templates/base_front.html.twig`.

### Hypothèses (à faire valider par l'utilisateur)

- **H1 (question 1)** : les membres qui peuvent s'inscrire sont ceux de la saison qui contient la 1re date. Rien n'est demandé au responsable ; la saison est seulement rappelée dans le récapitulatif.
- **H2 (question 2)** : la catégorie de jeu n'est jamais montrée aux participants : pas de compteur, pas de couleur, pas de classement par catégorie en v1.
- **H3 (question 3)** : le niveau vient de la poule du joueur et n'est vu que du responsable. Un membre sans équipe reçoit le niveau médian, que le responsable peut ajuster au pointage.
- **H4 (question 4)** : la saisie après coup suffit en v1.
- **H5 (question 5)** : pas de mêlée réservée à une catégorie en v1 (P25 retiré).
- **H6 (question 6)** : minimum de 1 joueuse par défaut en 4×4.
- **H7 (question 7)** : on répartit les joueuses au mieux et on prévient le responsable, sans sanction.
- **H8 (question 8)** : classement au pourcentage de victoires, affiché « 7 victoires sur 10 matchs ».
- **H9 (question 9)** : le site affiche un compte à rebours et l'heure de fin du tour ; le signal reste le sifflet du responsable. Pas de son imposé.
- **H10 (vocabulaire)** : une « période » s'appelle **« date »** à l'écran (« Date 1 », « Ajouter une autre date »). « Période » est trop abstrait pour des bénévoles ; « période » reste le terme du code.
- **Club** : il n'est pas demandé. On crée la mêlée depuis « Mon club : XXX », donc le club est déjà connu.

---

## 1. Création : 4 étapes

Je regroupe les 5 étapes du §6 de l'expert en 4, et je place titre et description à la fin, parce que le titre par défaut dépend de la date.

**Point d'entrée.** Sur la page « Mon club », une section « Mêlées » : liste des mêlées avec leur état, et un bouton « Créer une mêlée ».

**En-tête commun aux 4 étapes :**
- retour « ‹ Mon club : BAB » ;
- titre « Nouvelle mêlée » ;
- sous-titre « Brouillon · visible de vous seul » ;
- indicateur d'étapes 1 à 4, puis « Étape N sur 4 · libellé ».

**Barre du bas :**
- « Enregistrer et quitter » en bouton secondaire, au-dessus de la barre ;
- « Précédent » et « Suivant → » collés en bas de l'écran.

### Étape 1 · Quand et où

Une carte par date. Pour chaque date, dans l'ordre :

| Libellé | Champ | Défaut | Aide | Message |
|---|---|---|---|---|
| **Date** | date, obligatoire | vide | – | erreur « Indiquez la date. » ; alerte D60 « Le gymnase {nom} est fermé ce jour-là ({motif}). Choisissez une autre date ou un autre gymnase. » |
| **Ouverture du gymnase** | heure, obligatoire | 19:30 | – | – |
| **Fermeture du gymnase** | heure, obligatoire | 22:30 | « Heure à laquelle la salle doit être libérée. » | erreur « La fermeture doit être après l'ouverture. » ; alerte si moins de 75 min : « Moins d'1 h 15 : vous ne pourrez jouer que 2 ou 3 tours. » |
| **Gymnase** | liste déroulante, obligatoire | gymnase habituel du club | – | les gymnases du club en tête, puis les autres (pas de saisie libre, pour garder une seule source) |
| **Nombre de terrains** | compteur − / + de 1 à 6 | dernier nombre utilisé dans ce gymnase, sinon 2 | « Nombre de matchs joués en même temps. » | – |

Sous les cartes :
- bouton en pointillés « + Ajouter une autre date », avec l'aide « Même gymnase, mêmes heures, mêmes terrains : seule la date est à choisir. » ;
- sur chaque carte à partir de la date 2 : lien discret « Retirer cette date ».

### Étape 2 · Le jeu

Champs, dans l'ordre :

1. **Format** : 4 cartes à choix unique.
   - « 6 contre 6 (comme au championnat) », choisi par défaut ;
   - « 4 contre 4 » ;
   - « 3 contre 3 » ;
   - « 2 contre 2 ».
2. **Minimum de joueuses par équipe** : compteur − / + de 0 à k.
   - Défaut : 2 en 6 contre 6, 1 sinon. La valeur suit le format tant que le responsable ne l'a pas changée.
   - Aide : « 0 = pas de minimum. Si les joueuses sont moins nombreuses le jour J, le site les répartit au mieux et vous prévient. »
3. **Durée d'un match** : 3 cartes à choix unique.
   - « Temps limité (conseillé) », choisi par défaut ;
   - « 25 points ou temps limité, au premier atteint » ;
   - « Aux points seulement ». Si on le choisit, alerte : « Déconseillé : à chaque tour, tous les terrains attendent la fin du match le plus long. Préférez “25 points ou temps limité”. »
4. **Temps de jeu (minutes)** : champ numérique de 8 à 20, défaut 12. Masqué si « Aux points seulement ».
   - Aide : « Au signal, on finit l'échange en cours. »
5. **Points pour gagner** : 15, 21 ou 25, défaut 25, en set sec. Seulement pour les deux formules avec points.
6. **Équilibrer les niveaux** : interrupteur, activé par défaut.
   - Aide : « Le site s'appuie sur la poule de l'équipe de chaque joueur au championnat. Ce niveau n'est montré qu'à vous. »

**Encadré calculé « Votre programme »** (annoncé aux lecteurs d'écran avec `aria-live="polite"`) :
- une ligne par date, par exemple « Jeu. 4 déc. : 8 tours, de 19 h 50 à 22 h 06 » ;
- « Total : 15 tours » ;
- « Un tour = 12 min de jeu + 5 min pour changer d'équipe. » ;
- « Joueurs par date : entre 20 et 32, tout le monde joue à chaque tour (des remplaçants au-delà de 24). De 10 à 19 : un seul match par tour. Moins de 10 : impossible. »
- Pluriels calculés partout : « 1 tour » / « 2 tours », « 1 match » / « 2 matchs ».

### Étape 3 · Inscriptions

1. **Fin des inscriptions** : date et heure ; défaut la veille de la 1re date, à 12 h 00.
   - Aide : « Après, vous pourrez encore inscrire un membre vous-même, même le jour J. »
   - Erreur : « La fin des inscriptions doit être avant le début de la mêlée. »
2. **Les joueurs s'inscrivent** : seulement s'il y a 2 dates ou plus.
   - Choix : « date par date (conseillé) », par défaut, ou « pour toutes les dates ».
3. **Nombre maximum de joueurs par date** : champ pré-rempli à 32, plus une case « Sans limite ».
   - Aide : « Calculé : 2 terrains × 2 équipes × 8 joueurs (6 sur le terrain et 2 remplaçants). »
4. **Liste d'attente quand c'est complet** : case cochée par défaut.
   - Aide : « La première personne en attente prend la place libérée et reçoit un message. »
5. **Encadré d'information** (affiché seulement si le minimum de joueuses est supérieur à 0) : « Comme vous avez fixé un minimum de joueuses, chaque joueur indiquera sa catégorie de jeu (féminine ou masculine) en s'inscrivant, s'il ne l'a pas déjà fait. Elle n'est visible que de lui et de vous. »
6. **Ligne calculée** : « Il faut au moins 10 joueurs, dont 4 joueuses. Vous serez prévenu à la fin des inscriptions s'il en manque. »

### Étape 4 · Vérifier et publier

1. **Titre** : pré-rempli « Mêlée du jeudi 4 décembre », ou « Mêlée des 4 et 11 décembre » s'il y a deux dates ; 80 caractères au plus.
2. **Mot d'accueil (facultatif)** : zone de texte ; exemple affiché « Tenue, apéritif, covoiturage… ».
3. **Règles affichées aux joueurs** : résumé **généré, non modifiable**.
   - Exemple : « 6 contre 6 · au moins 2 joueuses par équipe · 12 min par match · filet à 2,35 m ».
   - Il n'est pas modifiable pour qu'il ne contredise jamais les réglages : une seule source.
4. **Règles particulières (facultatif)** : pré-rempli avec « Contre des joueuses non autorisé sauf entente. »
5. **Récapitulatif** : une carte par étape, avec un lien « Modifier » qui ramène à l'étape. On y rappelle la saison : « Ouverte aux membres du club pour la saison 2026-2027 ».
6. **Réglages avancés** : un dépliant, fermé par défaut. Chaque groupe montre ses valeurs actuelles sur une ligne, puis se déplie à son tour :
   - **Temps** : accueil 20 min ; pause automatique ; résultats et rangement 10 min ; marge 10 % ; changement d'équipe 5 min ; nombre de tours « calculé (8) » ou choisi.
   - **Équipes** : de 5 à 8 joueurs ; joueurs en trop d'abord remplaçants ; joueuses en nombre insuffisant réparties au mieux.
   - **Score** : on finit l'échange ; point en or en cas d'égalité ; hauteur du filet.
   - **Tirage** : éviter les mêmes coéquipiers ; éviter les mêmes adversaires ; équipes publiées automatiquement.
   - **Classement** : pourcentage de victoires ; écart plafonné à 10 points ; minimum de matchs pour être classé « calculé (5) » ; classement à la fin de chaque date.
   - **Absences** : remplacement autorisé pendant un match ; désinscription possible jusqu'au début.
7. **Boutons** :
   - « Publier et ouvrir les inscriptions » (principal) ;
   - « Enregistrer le brouillon ».
   - Message après publication : « Mêlée publiée. Les membres du club peuvent s'inscrire jusqu'au mercredi 3 décembre à 12 h. »

---

## 2. Maquettes fil de fer (360 px)

Notation : `[ ]` champ, `( )` choix unique, `[x]` case, `[ − ] 2 [ + ]` compteur, `▸` dépliant fermé, `═══` barre collée en bas.

```
ÉTAPE 1
+--------------------------------------+
| < Mon club : BAB                     |
| NOUVELLE MÊLÉE                       |
| Brouillon · visible de vous seul     |
| (1)---(2)---(3)---(4)                |
| Étape 1 sur 4 · Quand et où          |
+--------------------------------------+
| +----------------------------------+ |
| | DATE 1                           | |
| | Date *                           | |
| | [ jeu. 04/12/2026          ]     | |
| | Ouverture *    Fermeture *       | |
| | [ 19:30   ]    [ 22:30   ]       | |
| | Heure à laquelle la salle doit   | |
| | être libérée.                    | |
| | Gymnase *                        | |
| | [ Saint-Bernard, Bayonne    v ]  | |
| | Nombre de terrains *             | |
| | [ − ]      2      [ + ]          | |
| | Nombre de matchs joués en même   | |
| | temps.                           | |
| +----------------------------------+ |
| + - - - - - - - - - - - - - - - - -+ |
| |   + Ajouter une autre date       | |
| + - - - - - - - - - - - - - - - - -+ |
| Même gymnase, mêmes heures, mêmes    |
| terrains : seule la date est à       |
| choisir.                             |
|           [ Enregistrer et quitter ] |
|======================================|
| [           Suivant  ->            ] |
+--------------------------------------+

DATE 2 (après « Ajouter »)
| +----------------------------------+ |
| | DATE 2       Retirer cette date  | |
| | Date *                           | |
| | [ jj/mm/aaaa               ]     | |
| | ! Le gymnase Saint-Bernard est   | |
| |   fermé ce jour-là (Vacances de  | |
| |   Noël). Choisissez une autre    | |
| |   date ou un autre gymnase.      | |
| | ...heures, gymnase, terrains     | |
| |    recopiés...                   | |
| +----------------------------------+ |
```

```
ÉTAPE 2
+--------------------------------------+
| Étape 2 sur 4 · Le jeu               |
+--------------------------------------+
| Format *                             |
| +----------------++----------------+ |
| |(o) 6 contre 6  ||( ) 4 contre 4  | |
| | comme au       ||                | |
| | championnat    ||                | |
| +----------------++----------------+ |
| +----------------++----------------+ |
| |( ) 3 contre 3  ||( ) 2 contre 2  | |
| +----------------++----------------+ |
| Minimum de joueuses par équipe *     |
| [ − ]      2      [ + ]              |
| 0 = pas de minimum. Si les joueuses  |
| sont moins nombreuses le jour J, le  |
| site les répartit au mieux et vous   |
| prévient.                            |
| Durée d'un match *                   |
| +----------------------------------+ |
| |(o) Temps limité (conseillé)      | |
| +----------------------------------+ |
| |( ) 25 points ou temps limité,    | |
| |    au premier atteint            | |
| +----------------------------------+ |
| |( ) Aux points seulement          | |
| +----------------------------------+ |
| Temps de jeu (minutes) *             |
| [ 12 ]                               |
| Au signal, on finit l'échange en     |
| cours.                               |
| [x] Équilibrer les niveaux           |
|   Le site s'appuie sur la poule de   |
|   l'équipe de chaque joueur au       |
|   championnat. Ce niveau n'est       |
|   montré qu'à vous.                  |
| +- VOTRE PROGRAMME (calculé) ------+ |
| | Jeu. 4 déc. : 8 tours,           | | 
| |   de 19 h 50 à 22 h 06           | |
| | Jeu. 11 déc. : 7 tours,          | |
| |   de 19 h 50 à 21 h 49           | |
| | Total : 15 tours                 | |
| | Un tour = 12 min de jeu + 5 min  | |
| | pour changer d'équipe.           | |
| | Joueurs par date :               | |
| | · 20 à 32 : tout le monde joue   | |
| |   (remplaçants au-delà de 24)    | |
| | · 10 à 19 : 1 seul match/tour    | |
| | · moins de 10 : impossible       | |
| +----------------------------------+ |
|           [ Enregistrer et quitter ] |
|======================================|
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+
```

```
ÉTAPE 3
+--------------------------------------+
| Étape 3 sur 4 · Inscriptions         |
+--------------------------------------+
| Fin des inscriptions *               |
| [ 03/12/2026 ]  [ 12:00 ]            |
| Après, vous pourrez encore inscrire  |
| un membre vous-même, même le jour J. |
| Les joueurs s'inscrivent             |
| (o) date par date (conseillé)        |
| ( ) pour toutes les dates            |
| Nombre maximum de joueurs par date   |
| [ 32 ]     [ ] Sans limite           |
| Calculé : 2 terrains × 2 équipes ×   |
| 8 joueurs (6 sur le terrain et 2     |
| remplaçants).                        |
| [x] Liste d'attente quand c'est      |
|     complet                          |
|   La première personne en attente    |
|   prend la place libérée et reçoit   |
|   un message.                        |
| +- i ------------------------------+ |
| | Comme vous avez fixé un minimum  | |
| | de joueuses, chaque joueur       | |
| | indiquera sa catégorie de jeu    | |
| | (féminine ou masculine) en       | |
| | s'inscrivant. Elle n'est visible | |
| | que de lui et de vous.           | |
| +----------------------------------+ |
| Il faut au moins 10 joueurs, dont 4  |
| joueuses. Vous serez prévenu à la    |
| fin des inscriptions s'il en manque. |
|======================================|
| [ <- Précédent ]  [  Suivant ->  ]   |
+--------------------------------------+
```

```
ÉTAPE 4
+--------------------------------------+
| Étape 4 sur 4 · Vérifier et publier  |
+--------------------------------------+
| Titre *                              |
| [ Mêlée des 4 et 11 décembre     ]   |
| Mot d'accueil (facultatif)           |
| [ Tenue, apéritif, covoiturage… ]    |
| [                                ]   |
| Règles affichées aux joueurs         |
| 6 contre 6 · au moins 2 joueuses     |
| par équipe · 12 min par match ·      |
| filet à 2,35 m                       |
| Règles particulières (facultatif)    |
| [ Contre des joueuses non autorisé ] |
| [ sauf entente.                    ] |
| +- Quand et où ---------- Modifier + |
| | Jeu. 4 déc., 19 h 30-22 h 30     | |
| | Jeu. 11 déc., 19 h 30-22 h 30    | |
| | Saint-Bernard, 2 terrains        | |
| +----------------------------------+ |
| +- Le jeu --------------- Modifier + |
| | 6 contre 6, 2 joueuses minimum,  | |
| | 12 min, niveaux équilibrés       | |
| | 15 tours au total                | |
| +----------------------------------+ |
| +- Inscriptions --------- Modifier + |
| | Jusqu'au mer. 3 déc. à 12 h,     | |
| | date par date, 32 au plus,       | |
| | liste d'attente                  | |
| | Membres du club, saison 2026-2027| |
| +----------------------------------+ |
| > Réglages avancés                   |
|   (déplié :)                         |
|   > Temps : accueil 20 min, marge    |
|     10 %, 8 tours calculés           |
|   > Équipes : de 5 à 8 joueurs       |
|   > Score : point en or si égalité   |
|   > Tirage : éviter les mêmes        |
|     coéquipiers                      |
|   > Classement : % de victoires,     |
|     5 matchs minimum                 |
|   > Absences : remplacement autorisé |
|          [ Enregistrer le brouillon ]|
|======================================|
| [  Publier et ouvrir les           ] |
| [  inscriptions                    ] |
+--------------------------------------+
```

---

## 3. Jour J et inscription du membre (en bref)

**Navigation du jour J (responsable).** La page de la mêlée affiche un bandeau « Tour 3 sur 8 · en cours », puis 4 onglets d'au moins 44 px de haut, toujours au même endroit : **Présents | Tour | Scores | Classement**.

```
J1 PRÉSENTS                        J2 TOUR (équipes tirées)
+------------------------------+   +------------------------------+
| Qui est là ?                 |   | TOUR 1 · 19 h 50             |
| 18 présents dont 5 joueuses  |   | TERRAIN 1                    |
| (vu du responsable seul)     |   | Équipe A : Léa, Marc, Paul,  |
| [ Tous présents ]            |   |   Inès, Jon, Ana             |
| +-----------++-------------+ |   | contre                       |
| |[x] Ana B. ||[x] Jon E.   | |   | Équipe B : ...               |
| +-----------++-------------+ |   | TERRAIN 2                    |
| |[ ] Léa C. ||[x] Marc D.  | |   | Équipe C ... contre Équipe D |
| +-----------++-------------+ |   | Au repos : Tom, Zoé          |
| [ + Ajouter un membre non    |   | [ Échanger deux joueurs ]    |
|   inscrit ]                  |   |==============================|
| > Ajuster un niveau          |   | [     Lancer le tour 1     ] |
| Tour 1 : 2 matchs, 4 équipes |   +------------------------------+
| de 4 ou 5, personne au repos |   
| ! Pas assez de joueuses : 2  |   J3 TOUR EN COURS
|   équipes en auront 1 au     |   +------------------------------+
|   lieu de 2.                 |   | TOUR 3 SUR 8                 |
|==============================|   |          08:42               |
| [    Tirer le tour 1       ] |   | Fin prévue : 20 h 41         |
+------------------------------+   | (terrains et équipes)        |
                                   | > Tour suivant (déjà tiré)   |
                                   | > Modifier en cours :        |
                                   |   terrains, temps de jeu,    |
                                   |   nombre de tours, minimum   |
                                   |   de joueuses                |
                                   |==============================|
                                   | [   Saisir les scores      ] |
                                   +------------------------------+
J4 SCORES                          J5 CLASSEMENT
+------------------------------+   +------------------------------+
| TOUR 3                       |   | CLASSEMENT DU JEU            |
| Terrain 1                    |   | Pour le plaisir : les        |
| Équipe A  [ 16 ] - [ 15 ]    |   | équipes changent à chaque    |
|                   Équipe B   |   | tour.                        |
| Terrain 2                    |   | 1  Léa C.   7 victoires sur  |
| Équipe C  [    ] - [    ]    |   |             10 matchs, +4,2  |
|                   Équipe D   |   | 2  ...                       |
| Vous pourrez saisir ou       |   | > Pas encore classés (moins  |
| corriger plus tard, jusqu'à  |   |   de 5 matchs)               |
| la clôture de la mêlée.      |   +------------------------------+
|==============================|
| [  Enregistrer les scores  ] |
+------------------------------+
```

Détails du jour J :
- **Présents** : on reprend le motif « Qui est là ? » de la feuille de match. Le tirage est refusé tant que le pointage n'est pas fait, avec un bouton inactif expliqué : « Pointez les présents avant de tirer le tour 1. »
- **Échanger deux joueurs** : possible tant que le tour n'a pas commencé. On touche un nom, puis un autre.
- **Scores** :
  - champs numériques avec `inputmode="numeric"` ;
  - égalité refusée si la règle est le point en or : « Égalité impossible : jouez le point en or et notez le score final (par exemple 16-15). » ;
  - un score manquant ne bloque jamais le tour suivant.
- **Vue des participants** : les mêmes écrans Tour et Classement, en lecture. En tête, « Votre match : terrain 2, équipe C » ou « Vous êtes au repos ce tour-ci ». Aucun compteur ni aucune marque de catégorie ou de niveau (D65).

**Inscription du membre** (depuis « Mon club » et un encart sur l'accueil s'il y a une mêlée ouverte) :

```
+--------------------------------------+
| MÊLÉE DES 4 ET 11 DÉCEMBRE           |
| Inscriptions jusqu'au mer. 3 déc.    |
| à 12 h                               |
| Saint-Bernard, Bayonne · 19 h 30     |
| 6 contre 6 · 12 min par match        |
| Mot d'accueil...                     |
| Je viens :                           |
| [x] jeudi 4 décembre (18 inscrits    |
|     sur 32)                          |
| [ ] jeudi 11 décembre (complet :     |
|     liste d'attente)                 |
| Catégorie de jeu *                   |
| ( ) Féminine   ( ) Masculine         |
| Sert seulement à composer des        |
| équipes mixtes. Visible de vous et   |
| du responsable du club, jamais       |
| affichée aux autres.                 |
|======================================|
| [         Je m'inscris             ] |
+--------------------------------------+
```

Messages de l'inscription :
- **Catégorie de jeu** : le champ n'apparaît que si le minimum de joueuses est supérieur à 0 et que la catégorie n'est pas encore connue.
- **Après l'envoi** : « Inscription enregistrée pour le jeudi 4 décembre. » Le bouton devient « Me désinscrire », avec une confirmation.
- **Après la clôture** : « Inscriptions closes : adressez-vous au responsable du club. »
- **Visiteur non connecté** : « Connectez-vous pour vous inscrire. »
- **Non-membre** : la page n'existe pas pour lui (D74).
- **Pluriels** : « 1 inscrit » / « 18 inscrits ».

---

## 4. Points laissés au webdesigner

1. Le style du compte à rebours : lisible à 3 m et le soir, sans s'appuyer sur la couleur seule.
2. La distinction des équipes A, B, C et D : jamais de code de couleur qui évoque une catégorie.
3. L'aspect de l'encadré calculé « Votre programme », face à un encadré d'alerte.
4. Les pastilles d'état de la mêlée : « Brouillon », « Inscriptions ouvertes », « Inscriptions closes », « En cours », « Terminée ».
5. La mise en avant de « Votre match » pour le joueur.
6. Le compteur − / + : boutons de 44 px, avec les noms accessibles « Retirer un terrain » et « Ajouter un terrain ».

## Points d'attention

1. **Mot de passe vers l'existant** : l'indicateur d'étapes et la barre du bas n'existent que dans le back-office (`templates/admin/saisonwizard/`), alors que ce parcours est côté public, dans `base_front`. Il faut en faire des composants partagés plutôt que les recopier.
2. **Lecteurs d'écran** : le compte à rebours ne doit pas annoncer chaque seconde. Il faut annoncer seulement la fin du temps, et ne mettre `aria-live` que sur l'encadré calculé et les alertes.
3. **Questions à faire confirmer avant de coder** :
   - H3 (niveau visible du responsable seul, tiré de la poule) ;
   - H9 (minuteur sans son) ;
   - H10 (le mot « date » à l'écran pour une période).

Fichiers utiles :
- /home/amoul/projects/blvb/docs/espace-club/parametres-melee.md
- /home/amoul/projects/blvb/templates/admin/saisonwizard/layout.html.twig
- /home/amoul/projects/blvb/templates/admin/saisonwizard/_navigation.html.twig
- /home/amoul/projects/blvb/templates/front/feuille_match/_presents.html.twig
- /home/amoul/projects/blvb/templates/front/club/show.html.twig
