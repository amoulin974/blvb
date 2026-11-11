# Formules de tournoi pour l'espace club : base d'analyse des besoins

*Avis de l'agent « organisation-competition », 11/11/2026, enregistré tel quel sur la branche `espace-club`. Rien n'est décidé : ce document liste ce qu'un club peut organiser et ce que le site devrait savoir gérer.*

**Légende des affirmations**
- **[Règle]** : texte établi, avec sa source et sa date.
- **[Pratique]** : usage courant constaté dans des tournois publiés, qui n'est pas une règle.
- **[Proposition]** : idée de l'agent, à valider par le club.

Les codes A1, B3, C6, etc. renvoient au catalogue `docs/formats-competition.md`.

---

## À retenir

Trois formules couvrent la plupart des besoins d'un club loisir. Choix selon la taille et la durée :

| Situation | Formule recommandée | Pourquoi |
|---|---|---|
| **Soirée, 6 à 10 équipes déjà formées** | **Montante-descendante au temps** sur 3 terrains (variante en triangulaires quand le nombre d'équipes est impair) | Personne n'attend, le planning ne déborde pas (matchs au temps), et un forfait ou un retard ne casse rien. |
| **Soirée ou entraînement, inscriptions individuelles** | **« Mêlée »** : équipes retirées au sort à chaque tour, en respectant la mixité, avec un classement individuel | C'est le vrai besoin propre à un club : il a des adhérents, pas des équipes. C'est aussi la raison d'être de la catégorie de jeu (D65). |
| **Journée, 8 à 24 équipes** | **Poules puis tableaux de classement intégral** (C1 + B3) | Formule la plus répandue. Chaque équipe a un nombre de matchs garanti et un classement final. Elle réutilise le moteur du championnat (A1, B1 à B4). |

À garder pour plus tard, car peu demandées en loisir ou trop complexes au départ : système suisse (A8), double élimination (B5), échelle (A9). L'échelle reste utile pour un défi interne étalé sur plusieurs semaines.

Deux repères suffisent pour vérifier un planning :
- **Taux de jeu = 2T / n** (T terrains, n équipes) : c'est la part du temps où une équipe est sur le terrain. En loisir, viser **au moins 50 %**.
- **Arbitrage par les équipes possible si n − 2T ≥ T**, c'est-à-dire s'il y a au moins autant d'équipes au repos que de terrains. Sinon, il faut des bénévoles ou de l'auto-arbitrage.

---

## 0. Hypothèses de durée utilisées dans tous les calculs

Ces durées sont des **estimations** pour du loisir senior, pas des mesures. Un **créneau** = durée du match + changement de terrain, rotation et saisie du score.

| Code | Format de match | Jeu estimé | Créneau retenu |
|---|---|---|---|
| M1 | 6×6, 1 set de 25 points sec (comme au BLVB, règle 2D) | 18 à 22 min | **25 min** |
| M2 | 6×6, 2 sets de 21 points secs (score 2-0 ou 1-1 possible) | ~30 min | **35 min** |
| M3 | 6×6, 2 sets gagnants de 25 (format des phases BLVB, règle 2D) | 40 à 75 min | **60 min**, très variable |
| T10, T12, T15 | Au temps : 10, 12 ou 15 min de jeu | fixe | **13, 15 ou 18 min** |
| Q | 4×4, 3×3 ou 2×2 en 1 set de 21 points | ~15 min | **20 min** |

### Capacité d'une plage horaire

Formule : **créneaux = ⌊(plage − pauses − marge) / créneau⌋**, et **matchs possibles = créneaux × T**.

| Plage | Calcul du temps de jeu utile | Créneaux de 35 min | Créneaux de 25 min | Créneaux de 20 min | Créneaux de 15 min |
|---|---|---|---|---|---|
| Soirée 19h30 à 22h30 | 180 − 20 (installation et échauffement) − 15 (marge) = **145 min** | 4 | 5 | 7 | 9 |
| Journée 9h à 18h | 540 − 15 (accueil) − 45 (repas) − 45 (marge d'environ 10 %) = **435 min** | 12 | 17 | 21 | 29 |
| Deux journées | 2 × 435 = **870 min**, plutôt **~800 min** si le dimanche finit tôt pour la remise des prix | ~22 | ~32 | ~40 | ~53 |

### Nombre de terrains d'un gymnase

- La règle 1O du règlement BLVB 2025 joue les finales B, C et D « simultanément sur les terrains en travers ». Un grand gymnase (salle de handball de 44 × 24 m) accueille donc **3 terrains de 6×6 en travers**.
- En pratique, **la limite vient souvent du nombre de fourreaux de poteaux (et de filets) plutôt que de la surface**. C'est une donnée de chaque gymnase, que le club doit renseigner. **[Proposition]**
- **[Règle]** Dimensions FFVB pour le 4×4 : terrain de 8 × 16 m, aire de jeu de 12 × 20 m. Pour le 3×3 : terrain de 7 × 14 m, aire de 11 × 18 m. (FFVB, *Règlement particulier du circuit national Volley Outdoor 2025/2026*, art. 5.2.2, adopté par le CA le 24/01/2026.)
- Avec ces cotes, 3 aires de 4×4 réglementaires tiennent sur 44 × 24 m, ou 4 si on réduit les dégagements à 1 m. **[Calcul]** 3 × 12 = 36 m ≤ 44 m ; avec 1 m de dégagement, une aire fait 10 × 18 m et 4 × 10 = 40 m ≤ 44 m.

---

## 1. Durées : ce que chaque durée permet et impose

### Soirée (2 à 3 h)

- **Capacité** : 145 min utiles, soit 5 créneaux M1 ou 9 créneaux T12 par terrain. Avec 3 terrains : **15 matchs M1** ou **27 matchs T12**.
- **Formules qui tiennent** :
  - poule unique jusqu'à 6 équipes (15 matchs, voir 3.1) ;
  - montante-descendante ;
  - triangulaires ;
  - mêlée ;
  - roi du terrain.
- **Ne tiennent pas** : les tableaux à plusieurs tours en M2 ou M3, et tout ce qui dépasse environ 15 matchs en 6×6.
- **Contraintes** : fermeture du gymnase à heure fixe (le concierge, cf. règle 1R), pas de pause repas. **Le format au temps est le plus sûr.**

### Journée

- **Capacité** : 435 min utiles, soit 12 créneaux M2, 17 créneaux M1 ou 21 créneaux Q. Avec 3 terrains : 36 matchs M2.
- **Formules** : poules puis tableaux, système suisse sur 4 ou 5 rondes, double élimination jusqu'à 8 équipes.
- **Contraintes** :
  - prévoir le repas, souvent l'occasion d'une marge ;
  - limiter l'attente : avec un taux de jeu de 25 % (24 équipes sur 3 terrains), une équipe joue 3 matchs en 7 h, ce qui est à éviter (voir 3.2) ;
  - **[Règle]**, à titre de repère seulement : le circuit FFVB outdoor limite une équipe à **4 matchs par jour en 2 sets de 21**, **6 en 2 sets de 15**. Deux matchs en un set comptent pour un match en deux sets (art. 42, RPE Volley Outdoor 2025/2026). Ce plafond vise le beach et le green licenciés, pas le gymnase loisir, mais il donne un ordre de grandeur de la fatigue.
  - **[Règle]** Même texte, art. 41 : repos minimum entre deux matchs de **30 min** en 2 sets de 21, **15 min** en 2 sets de 15, **10 min** pour un match en un set.

### Deux journées

Utile au-delà d'environ 36 matchs de 6×6 sur 3 terrains, ou pour un tournoi festif avec nuit sur place.

Ce que la nuit change :
- **Hébergement et repas du soir** à organiser ou à faire payer : le site doit pouvoir porter des options d'inscription (repas, nuit) **[Proposition]**.
- **Équipes présentes un seul jour** : si le dimanche dépend des résultats du samedi (tableaux), une équipe absente le dimanche fait des forfaits en cascade. Deux parades :
  - inscription par jour, avec deux tournois liés ;
  - rendre le samedi autonome (poules avec classement final) et réserver le dimanche aux tableaux, avec une consolante pour les présents.
- **Le classement du samedi soir doit être figé et publié** avant le départ des équipes, pour que le planning du dimanche soit connu.

Exemple vérifié : **24 équipes en 6×6, 3 terrains, M2 (créneau de 35 min)**.

| Jour | Contenu | Matchs | Créneaux | Durée |
|---|---|---|---|---|
| Samedi | 6 poules de 4 (6 matchs chacune) | 6 × 6 = 36 | 36 / 3 = 12 | 12 × 35 = 420 min ≤ 435 |
| Dimanche | 3 tableaux de 8 en classement intégral (B3 : 8/2 × log2 8 = 12 matchs chacun) | 3 × 12 = 36 | 12 | 420 min ≤ 435, aucune réserve |

- Chaque équipe joue 3 + 3 = 6 matchs en deux jours.
- Le taux de jeu est de 2 × 3 / 24 = 25 % : beaucoup d'attente.
- **Variante plus agréable** : les 24 mêmes équipes en **4×4 sur 6 terrains** (en extérieur ou dans deux gymnases), avec un créneau Q de 20 min :
  - poules : 36 / 6 = 6 créneaux ;
  - tableaux : 36 / 6 = 6 créneaux ;
  - total : 12 × 20 = **240 min**, soit une seule journée, avec un taux de jeu de 12 / 24 = 50 %.

### Tournoi étalé sur plusieurs semaines

On joue sur le créneau d'entraînement du club.

- **Mini-championnat interne (A1)**. Exemple : 6 équipes, 2 terrains, 2 matchs M3 par terrain sur un créneau de 2 h, soit 4 matchs par soirée.
  - 15 matchs / 4 = 3,75, donc **4 soirées**. Avec 6 équipes, une journée compte au plus 3 matchs (une équipe par terrain et par créneau), ce qui reste compatible.
  - **Difficulté propre au club** : les effectifs changent d'une semaine à l'autre. Il faut décider si une équipe interne est fixe ou si l'on tolère des remplaçants, comme les jokers de la règle 7J.
- **Échelle ou défis (A9)** : classement permanent ; on défie une équipe ou un joueur classé jusqu'à X places au-dessus, dans un délai fixé.
  - Aucun nombre de matchs garanti.
  - Le site doit gérer le défi, l'acceptation, le délai, le forfait faute de réponse et l'échange des places.
- **Mêlée cumulée** : une mêlée à chaque séance, avec un classement individuel cumulé sur la saison. Le joueur s'inscrit à chaque séance (« à la carte »).
  - **[Proposition]** Classer à la moyenne par séance jouée, avec un minimum de séances, sinon l'assiduité l'emporte sur le jeu.

---

## 2. Formats de jeu

| Format | Joueurs par équipe (sur le terrain + remplaçants) | Terrain et filet | Pour qui, pour quoi |
|---|---|---|---|
| 6×6 | 6 + 2 à 4 | 9 × 18 m, filet selon la catégorie | C'est le format du championnat. Idéal avec des équipes de club déjà formées. Le moins de matchs par heure et par joueur. |
| 4×4 | 4 + 0 à 2 | 8 × 16 m **[Règle FFVB outdoor]**. En salle : un terrain réduit ou un demi-terrain en long **[Pratique]** | Beaucoup de touches de balle. Format roi des tournois loisir mixtes de l'été (green). |
| 3×3 | 3 + 0 ou 1 | 7 × 14 m **[Règle FFVB outdoor]**, ou 9 × 9 en salle | Petits effectifs, roi du terrain. Exigeant physiquement. |
| 2×2 | 2 | Beach ou green, 8 × 16 m | Sur sable ou herbe seulement en pratique. Rare en salle loisir. |

### Hauteurs de filet

**[Règle]** FFVB, RPE Volley Outdoor 2025/2026, art. 5.2.2 :
- masculin senior : 2,43 m ;
- féminin senior : 2,24 m ;
- **mixte senior : 2,35 m**.

La même échelle se retrouve en 6×6 à l'UFOLEP des Pyrénées-Atlantiques (règlement 2024/2025) : mixte 2,35 m, masculin 2,43 m, féminin 2,24 m. L'UFOLEP du Calvados (28/01/2025) fixe 2,35 m ± 3 cm.

**Le règlement BLVB 2025 ne fixe aucune hauteur de filet** (vérifié dans `reglement-2025.html`). La valeur d'usage au BLVB est à demander au club.

### Matchs au temps, aux points, ou en set unique

| Choix | Avantage | Limite |
|---|---|---|
| **Aux points** (sets de 25 ou 21, sec ou avec 2 points d'écart) | Fin naturelle, qualité de jeu | Durée variable : un set de 25 points dure de 15 à 25 min, le planning dérive. |
| **Au temps** (10 à 15 min) | Planning exact ; montante-descendante et mêlée deviennent possibles | Fin frustrante, et une égalité est possible : il faut une règle (point en or, ou égalité acceptée en poule). |
| **Hybride « 25 points ou N minutes »** | Durée plafonnée, fin naturelle le plus souvent | Il faut une règle quand le temps sonne (le plus avancé gagne, ou point en or). |

- **[Règle]** FFVB outdoor, art. 40 : base de 2 sets gagnants de 21 points, set décisif en 15. Sont admis aussi 2 sets de 15 (décisif en 11), ou **1 set de 30, de 21 ou de 15 points pour les matchs qui ne sont pas à élimination directe**.
- **[Pratique]** Volleyball Ireland, *King and Queen of the Court*, règlement daté du 10/07/2023 : manche de 15 min, et dernière manche « 15 min ou 15 points, le premier atteint ».
- **[Pratique]** Le règlement de la Coupe de France IUT (mis en ligne en décembre 2023) annoncerait des matchs de poule de 4×4 mixte d'environ 12 min, au temps, avec un point en mort subite en cas d'égalité. **Non vérifié** : le site était injoignable, l'information vient d'un résumé de recherche.

**[Proposition]** Format par défaut d'un tournoi loisir en salle : **1 set de 25 points sec** pour les poules, **2 sets de 25** pour les finales.

---

## 3. Formules

### 3.0 Tableau comparatif

Chaque exemple est calculé dans le détail en 3.1 à 3.12.

| # | Formule (code du catalogue) | Équipes | Exemple calculé | Minimum de matchs garanti | Taux de jeu | Durée de l'exemple | Mixte possible | Résiste au forfait et au retard |
|---|---|---|---|---|---|---|---|---|
| 3.1 | Poule unique (A1) | 4 à 6 | 6 équipes, 3 terrains, M1 | n − 1 = 5 | 100 % | 125 min (soirée) | oui | moyen |
| 3.2 | Poules puis tableaux de classement (C1 + B3) | 8 à 32 | 12 équipes, 3 terrains, M2 | 5 | 50 % | 350 min + pauses (journée) | oui | bon (poules), moyen (tableaux) |
| 3.3 | Plateaux en triangulaires (A7) | 6 à 12 | 9 équipes, 3 terrains, 1 set de 21 | 4 | 67 % | 120 min (soirée) | oui | très bon |
| 3.4 | Montante-descendante (« up/down ») | 2T ou 2T + 1 | 6 équipes, 3 terrains, T10 | 10 | 100 % | 120 min (soirée) | oui | très bon |
| 3.5 | Roi du terrain (king of the court) | 4 à 6 par terrain | 15 équipes de 3, 3 terrains | sans objet (temps de jeu) | ~40 à 60 % | 60 à 90 min | oui (2×2, 3×3) | très bon |
| 3.6 | Système suisse (A8) | 12 à 32 | 16 équipes, 4 terrains, M1, 5 rondes | 5 | 50 % | 250 min | oui | bon |
| 3.7 | Élimination directe (B1, B2) | 8 à 16 | 8 équipes | **1** | baisse à chaque tour | 3 tours | oui | mauvais |
| 3.8 | Consolante (B4) | 8 à 16 | 8 équipes | 2 | — | 3 tours | oui | moyen |
| 3.9 | Classement intégral (B3) | 4, 8, 16 | 8 équipes, 2 terrains, M1 | log2 n = 3 | 100 % / 2 | 150 min | oui | moyen |
| 3.10 | Double élimination (B5) | 6 à 16 | 8 équipes, 2 terrains, M2 | 2 | — | 8 à 9 créneaux = 280 à 315 min | oui | mauvais |
| 3.11 | Handicap (C6), qui se combine avec tout | tous | — | — | — | — | oui | — |
| 3.12 | Mêlée, équipes recomposées à chaque tour | 12 à 48 joueurs | 24 joueurs, 6×6, 2 terrains, T15 | 5 matchs par joueur | 100 % | 100 min + marge | oui, si le tirage tient compte de la catégorie | très bon |
| 3.13 | Échelle ou défis (A9) | 6 à 30 | étalée sur plusieurs semaines | 0 | — | plusieurs semaines | oui | bon |

### 3.1 Poule unique (A1)

- **Principe** : tout le monde rencontre tout le monde.
- **Équipes** : 4 à 6 sur une soirée, jusqu'à 8 sur une journée.
- **Calcul (exemple : 6 équipes, 3 terrains, M1)** :
  - 6 × 5 / 2 = 15 matchs, en 5 tours de 3 matchs simultanés ;
  - 5 × 25 = **125 min ≤ 145** : tient sur une soirée, avec 20 min de marge.
- **Autre cas : 5 équipes sur 2 terrains** : 10 matchs, 5 tours de 2 matchs avec une équipe exempte, soit 5 créneaux.
- **Minimum garanti** : n − 1 matchs.
- **Moyens** : avec n = 2T, aucune équipe n'est au repos, donc **auto-arbitrage**. La règle 1A du BLVB le prévoit déjà quand aucune équipe n'a de remplaçant : « ce sera de l'auto-arbitrage total ».
- **Limite vérifiée** : sur **un seul terrain**, une poule de 3 ou de 4 ne peut pas s'ordonner sans qu'une équipe enchaîne deux matchs. Le calcul a parcouru tous les ordres possibles. Une poule de 5 ou de 6 le peut, par exemple pour 5 : 1-2, 3-4, 1-5, 2-3, 4-5, 1-3, 2-4, 3-5, 1-4, 2-5. Le site devrait savoir produire cet ordre.
- **Avantages** : la formule la plus juste et la plus simple à comprendre.
- **Limites** : le volume croît en n² (8 équipes = 28 matchs) ; un forfait laisse des trous dans le classement.

### 3.2 Poules puis tableaux de classement (C1 + B3 ou B4)

- **Principe** : des poules de 3 à 5 équipes, puis des tableaux par niveau selon le rang. Tout le monde joue jusqu'au bout.
- **Équipes** : 8 à 32.
- **Calcul (exemple : 12 équipes, 3 terrains, M2 à 35 min)** :
  - 3 poules de 4, une par terrain : 3 × 6 = 18 matchs, soit 6 créneaux ;
  - 3 tableaux de 4, un par terrain, chacun avec 2 demi-finales, une petite finale et une finale : 3 × 4 = 12 matchs, soit 4 créneaux ;
  - total 30 matchs, 10 créneaux × 35 = **350 min**. Avec le repas (45) et une marge (35) : **430 min**, soit 9h00 à 16h10 environ.
  - Chaque équipe joue 3 + 2 = **5 matchs**. Taux de jeu : 6 / 12 = 50 %. Équipes au repos par créneau : 12 − 6 = 6 ≥ 3 terrains, donc les équipes peuvent arbitrer.
- **Composition des tableaux** :
  - A : les 3 premiers + le meilleur 2e ;
  - B : les 2 autres 2es + les 2 meilleurs 3es ;
  - C : le dernier 3e + les 3 quatrièmes.
  - Le « meilleur 2e » relève de C5. Ici les poules ont la même taille, donc la comparaison est valide.
- **Moyens** : un responsable de table qui tient les classements, ou le site. Les arbitres sont fournis par les équipes au repos.
- **Avantages** : la formule la plus connue. Chacun a un classement final et un nombre de matchs garanti.
- **Limites** :
  - le passage des poules aux tableaux est un **point de blocage** : tous les matchs de poule doivent être finis et saisis ;
  - un retard en poule décale tout ;
  - prévoir **un créneau de marge entre les deux phases**. **[Proposition]**

### 3.3 Plateaux en triangulaires, montants et descendants (A7)

- **Principe** : 3 équipes par terrain. Deux jouent, la troisième arbitre. Après le tour, les premières montent d'un terrain et les dernières descendent.
- **Équipes** : multiples de 3 (6, 9, 12). Avec une équipe absente, une triangulaire devient un match simple.
- **Calcul (exemple : 9 équipes, 3 terrains, 1 set de 21 points, créneau de 20 min)** :
  - un tour de triangulaire = 3 matchs = 3 créneaux ; 2 tours = 6 créneaux ;
  - 6 × 20 = **120 min ≤ 145** ;
  - chaque équipe joue 4 matchs et arbitre 2 fois. Taux de jeu : 6 / 9 = 67 %.
- **Avantages** : l'arbitrage est intégré ; c'est une formule très conviviale, proche de ce que le BLVB connaît (A7).
- **Limites** : il faut une règle de départage dans une triangulaire (sets, puis points).

### 3.4 Montante-descendante, ou « up/down »

- **Principe** : les terrains sont numérotés du haut vers le bas. Après chaque manche au temps, le vainqueur monte d'un terrain et le perdant descend. Le vainqueur du terrain du haut et le perdant de celui du bas restent en place.
- **[Pratique]** mobilesport.ch, fiche « Beach-volley – Forme de tournoi : Up/Down » (non datée) : la formule « favorise le déroulement de matches équilibrés, tout en laissant la porte ouverte à des surprises ». La même mécanique de terrains numérotés figure dans des fiches de la FFF et de l'USEP.
- **Équipes** : 2T. Avec 2T + 1, une équipe attend par manche : le perdant du bas sort et l'équipe en attente entre en bas.
- **Calcul (exemple : 6 équipes, 3 terrains, T10 + 2 min de rotation)** :
  - 120 min / 12 = **10 manches** ;
  - chaque équipe joue 10 matchs, soit 100 min de jeu ;
  - **avec 7 équipes** : chaque équipe attend environ 1 manche sur 7, donc joue environ 8 ou 9 manches sur 10.
- **Classement [Proposition]** : points de victoire pondérés par le terrain (3 en haut, 2 au milieu, 1 en bas), ou simplement la position finale. La formule converge vers un classement par niveau.
- **Moyens** : un chronomètre commun avec un signal sonore, et une saisie ultra simple (« qui a gagné ? »).
- **Avantages** : zéro attente, aucun débordement, matchs de plus en plus équilibrés, un forfait coûte une seule place.
- **Limites** : pas d'arbitre libre (auto-arbitrage) ; une égalité au temps demande une règle (point en or) ; le classement final est peu « officiel ».

### 3.5 Roi du terrain (king or queen of the court)

- **Principe** :
  - un seul terrain avec un côté « roi » et un côté « challenger » ;
  - **seule l'équipe côté roi marque des points** ;
  - si le challenger gagne l'échange, il passe côté roi ;
  - l'équipe éliminée de l'échange va en bout de file.
- **[Pratique]** Volleyball Ireland, règlement du 10/07/2023 :
  - 5 équipes de 2 par terrain ;
  - manches de 15 min, avec un temps mort à 10 min ;
  - l'équipe qui a le moins de points est éliminée à chaque manche ;
  - dernière manche : 15 min ou 15 points.
- **Équipes** : 4 à 6 par terrain, en 2×2 ou 3×3 (en salle, le 3×3 sur 9 × 9 m convient).
- **Calcul** : 3 terrains × 5 équipes × 3 joueurs = **45 joueurs** à la fois. Une équipe est sur le terrain 2 fois sur 5 en position d'attente, soit environ 40 %, mais les échanges sont très courts : l'attente se compte en échanges, pas en minutes.
- **Avantages** : spectaculaire, très ludique, pas d'arbitre, aucune saisie avant la fin.
- **Limites** : physique, inégal entre niveaux, classement à points cumulés. Pour une animation de fin de soirée plutôt que pour un tournoi principal.

### 3.6 Système suisse (A8)

- **Principe** : un nombre fixé de rondes. À chaque ronde, on apparie des équipes de total proche qui ne se sont pas encore rencontrées.
- **Équipes** : 12 à 32.
- **Calcul (exemple : 16 équipes, 4 terrains, M1, 5 rondes, environ log2 16 + 1)** :
  - 8 matchs par ronde, soit 2 créneaux par ronde ;
  - 10 créneaux × 25 = **250 min** ;
  - 5 matchs par équipe, taux de jeu de 8 / 16 = 50 %.
- **Moyens** : **appariement calculé après chaque ronde**. C'est impraticable à la main en temps réel, donc un fort argument pour le site.
- **Avantages** : un classement fiable sans élimination ; tout le monde joue le même nombre de matchs.
- **Limites** : formule méconnue des joueurs ; ronde suivante bloquée tant que la précédente n'est pas saisie ; un forfait demande un exempt (une victoire attribuée ou non, à décider).

### 3.7 Élimination directe (B1 ou B2)

- **Calcul** : n − 1 matchs, ⌈log2 n⌉ tours. Pour 8 équipes : 7 matchs, 3 tours (8 avec la petite finale).
- **Minimum garanti** : **1 match**. **À proscrire seule en loisir.** Elle n'a sa place qu'en phase finale, après des poules.

### 3.8 Consolante (B4)

- **Calcul pour 8 équipes** :
  - tableau principal : 7 matchs ;
  - consolante des 4 perdants du 1er tour : 3 matchs ;
  - total : 10 matchs. **Minimum garanti : 2 matchs.**
- **[Pratique]** L'UFOLEP du Calvados organise sa Coupe « avec une organisation de consolante » et précise qu'une équipe forfait au 1er tour « sera éliminée et non repêchée pour la Consolante » (règlement du 28/01/2025).

### 3.9 Classement intégral (B3)

- **Calcul pour 8 équipes** : 8/2 × log2 8 = 4 × 3 = **12 matchs**. Chaque équipe joue 3 matchs et obtient une place de 1 à 8.
- **Sur 2 terrains en M1** : chaque tour de 4 matchs demande 2 créneaux, donc 3 tours = 6 créneaux = **150 min**.
- **Sur 3 terrains** : ⌈4/3⌉ = 2 créneaux par tour, donc **aucun gain**, sauf à décaler les matchs. **Sur 4 terrains** : 3 créneaux = 75 min.
- **Limite** : il faut une puissance de 2 (4, 8, 16). Sinon, des exempts ou des barrages (B1).

### 3.10 Double élimination (B5)

- **Calcul pour 8 équipes** :
  - tableau des vainqueurs : 4 + 2 + 1 = 7 matchs ;
  - tableau des perdants : 2 + 2 + 1 + 1 = 6 matchs ;
  - grande finale : 1 match, plus 1 « revanche » éventuelle ;
  - total : **14 ou 15 matchs**, ce qui correspond à 2n − 2 ou 2n − 1.
  - **Minimum garanti : 2 matchs.**
- **Sur 2 terrains** : les dépendances entre matchs imposent **8 créneaux** (9 avec la revanche) au lieu des 7 théoriques (14 / 2). En M2 : 280 à 315 min.
- **[Règle]** Le circuit FFVB outdoor l'admet (art. 38.3 : « double élimination », ou combinaison de poules et d'élimination).
- **Limites** : difficile à suivre pour les joueurs ; les dernières équipes encore en course jouent beaucoup en fin de journée. **Pas prioritaire.**

### 3.11 Handicap (C6)

- **Principe** : l'équipe la plus faible part avec des points d'avance. Le handicap se combine avec n'importe quelle formule.
- **[Règle]** UFOLEP du Calvados, Coupe (28/01/2025) : un score de départ selon l'écart de division, **non appliqué en finale**. Pour une équipe mêlée, on retient « le niveau le plus élevé à partir de 3 joueurs ou joueuses dans le niveau concerné en championnat ». Les valeurs chiffrées n'ont pas pu être extraites du PDF.
- **[Règle BLVB]** La règle 1J utilise déjà des départs décalés pour la mixité (−8 et −5, voir la partie 5).
- **[Proposition]**
  - Pour des équipes de club déjà formées : le niveau de la poule de championnat de l'équipe (A à D) donne le niveau, et +3 points par niveau d'écart (valeur du catalogue C6).
  - Pour une mêlée : le niveau d'un joueur peut être déduit de la poule de son équipe au championnat. Le site connaît cette donnée sans rien demander de plus.

### 3.12 Mêlée : équipes recomposées à chaque tour, classement individuel

- **Principe** : les joueurs s'inscrivent seuls. À chaque tour, le site tire des équipes, en équilibrant la mixité et éventuellement le niveau. Chaque joueur marque les points de son équipe, d'où un classement individuel.
- **[Pratique]** Le nom vient des concours de pétanque « à la mêlée ». En volley, aucun règlement publié n'a été trouvé. La fiche mobilesport « Tournoi du caisson » (2021) décrit un classement individuel proche : chacun gagne ses points et ceux des adversaires qu'il a battus.
- **Calcul (exemple : 24 joueurs, dont 8 joueuses et 16 joueurs)** :

  | Formule de jeu | Quota de joueuses | Équipes possibles | Joueurs sur le terrain | Joueurs au repos par tour |
  |---|---|---|---|---|
  | 6×6 | au moins 2 | min(⌊24/6⌋, ⌊8/2⌋) = 4 | 24 | 0, sur 2 terrains |
  | 4×4 | au moins 2 | min(⌊24/4⌋ = 6, ⌊8/2⌋ = 4) = 4 | 16 | **8 joueurs** |
  | 4×4 | au moins 1 | min(6, 8) = 6 (2 équipes avec 2 joueuses, 4 avec 1) | 24 | 0, sur 3 terrains |

  - **Durée** : T15 + 5 min de tirage et d'installation = 20 min par tour. En 145 min : 7 tours théoriques ; en garder **5** (100 min), ce qui laisse 45 min pour l'accueil, le tirage initial et la marge.
  - **C'est exactement ce que dit D65** : le nombre de matchs dépend de la répartition entre joueuses et joueurs.
- **Formule générale** : avec k joueurs par équipe, un quota q de joueuses et F joueuses inscrites, le nombre d'équipes est **E = min(⌊inscrits / k⌋, ⌊F / q⌋)**. Les joueurs en trop jouent par roulement.
- **Classement individuel [Proposition]** : victoires, puis différence de points plafonnée par match (pour qu'un gros écart ne décide pas tout), puis moyenne par match si le nombre de matchs diffère.
  - Le classement reste **bruité** après 5 tours : le présenter comme un jeu, pas comme un palmarès.
  - **Éviter les mêmes coéquipiers** : à intégrer dans le tirage.
- **Avantages** :
  - **aucune équipe à former à l'avance** ;
  - les absents et les retardataires sont simplement tirés au tour suivant : la formule résiste aux retards mieux que toutes les autres ;
  - mélange les niveaux et les groupes du club.
- **Limites** :
  - le tirage à la main sur papier est lent et sujet aux contestations : **c'est la formule où le site apporte le plus** ;
  - la catégorie de jeu (D65) est obligatoire.

### 3.13 Échelle ou défis (A9)

Voir la partie 1, « Tournoi étalé ». Aucun minimum de matchs garanti.

**[Proposition]**
- obligation d'accepter un défi sous 10 jours, sinon l'équipe défiée perd sa place ;
- un défi au plus en cours par équipe ;
- interdiction de redéfier la même équipe avant N semaines.

---

## 4. Constitution des équipes

| Mode | Ce que l'inscription demande | Ce que le responsable fait | Ce que le site doit faire |
|---|---|---|---|
| **Équipes déjà formées** (équipes du championnat ou équipe ad hoc) | Un capitaine inscrit l'équipe et sa liste de joueurs | Il valide les inscriptions | Contrôler le quota de mixité de la liste (si une règle l'exige), gérer le nombre maximum d'équipes et une liste d'attente |
| **Inscriptions individuelles, équipes composées par le responsable** | Le joueur s'inscrit, avec sa catégorie de jeu (D65) et éventuellement son niveau | Il compose les équipes en glissant-déposant les joueurs | Afficher les compteurs de joueuses, de joueurs et de niveaux par équipe, et alerter si le quota n'est pas tenu |
| **Inscriptions individuelles, tirage équilibré** | Idem | Il lance le tirage, retouche, puis publie | Faire un **serpentin** (A5 appliqué aux joueurs) par catégorie puis par niveau : trier les joueuses par niveau et les distribuer en zigzag, faire de même pour les joueurs. Graine de tirage conservée pour pouvoir le refaire ou l'auditer. |
| **Équipes recomposées à chaque tour (mêlée)** | Idem, plus « présent / absent » au début de la séance | Il pointe les présents | Tirer à chaque tour, éviter de répéter les coéquipiers, tenir un classement individuel |
| **Joueurs invités sans compte** | Prénom, initiale du nom, catégorie de jeu, et l'adresse électronique seulement si l'on veut leur envoyer les résultats | Il les ajoute à la main | Ne rien publier sur l'invité ; supprimer ses données après le tournoi. **Question à trancher : l'assurance** (voir la partie 6). |

### Niveau des joueurs

**[Proposition]** Ne pas créer de champ « niveau ».
- Pour un joueur du championnat : utiliser la poule (A à D) de son équipe.
- Pour un invité : niveau déclaré par le responsable.

Cela évite de collecter une appréciation sur la personne (minimisation, dans l'esprit de D65).

### Licenciés FFVB

La règle 4J du BLVB limite à 3 les licenciés FFVB sur le terrain par set. Dans un tournoi interne, à reprendre ou non. Le tirage peut en tenir compte, et le site sait déjà qui est licencié.

---

## 5. Mixité

### 5.1 Ce qui existe : règles établies

**Règlement BLVB 2025** (`src/Reglement/Reprise/reglement-2025.html`, saison 2025-2026) :

- **Règle 1J** : « Il faut au moins deux filles minimum par équipe sur le terrain et 4 garçons maximum ».

  | Garçons | Filles | Conséquence |
  |---|---|---|
  | 6 | 0 | « Interdit : l'équipe est FORFAIT » |
  | 5 | 1 | « 1 garçon doit sortir et l'équipe démarre chaque set à −8 » |
  | 4 à 1 | 2 à 5 | « Pas de pénalités » |
  | 0 | 6 | « L'équipe adverse démarre chaque set à −5 » |

- **Règle 2J** : 5 joueurs minimum sur le terrain ; à 4 ou moins, l'équipe est forfait (avec la possibilité de se faire prêter un joueur adverse). « Si une fille arrive en cours de set, on termine le set, et cette règle ne s'applique plus pour le reste de la partie. »
- **Règle 3J** : « Le contre des filles n'est pas autorisé à moins d'une entente entre équipes avant le match. »
- **Règle 6J** : blessure de la seule joueuse, l'équipe finit le set à 5 et la règle R2 s'applique ensuite. *Le règlement renvoie à « R2 » sans la définir ; il s'agit probablement du set à −8. À clarifier.*
- **Hauteur de filet : non précisée.**

**Autres textes** :
- **FFVB, RPE Volley Outdoor 2025/2026** (CA du 24/01/2026), art. 23.1.2 :
  - 4×4 mixte : « de 4 à 6 joueurs, 4 sur le terrain dont 2 féminins, 2 masculins » ;
  - « obligation d'avoir toujours 2 joueurs par genre sur le terrain » ;
  - filet mixte à 2,35 m (art. 5.2.2) ;
  - art. 1 : « Un tournoi correspond à un type de pratique, une catégorie et un genre (masculine, féminine ou mixte) ». Un organisateur qui propose un tableau féminin et un masculin organise **deux tournois**.
- **UFOLEP Pyrénées-Atlantiques 2024/2025** (6×6) :
  - « Une équipe mixte se compose de 6 joueurs(es), avec au minimum 3 filles sur le terrain » ;
  - équipe incomplète : les joueuses ne peuvent être moins nombreuses que les joueurs ;
  - « Les équipes ne présentant qu'une seule joueuse sur le terrain seront déclarées forfait » ;
  - filet mixte à 2,35 m ;
  - le passage sur le contre en mixte n'est que **partiellement lisible** dans le PDF : il restreint le contre des hommes. À vérifier sur l'original.
- **UFOLEP Calvados** (28/01/2025, 6×6) : « 2 filles et 1 garçon au minimum, sur le terrain » ; filet à 2,35 m ± 3 cm.
- **FSGT** : le 4×4 mixte impose 2 garçons et 2 filles, et existe depuis 2016. **Source secondaire** (blog d'un club d'Argenteuil) ; le règlement n'a pas été trouvé sur fsgt.org.

**Constat** : le quota varie d'un organisateur à l'autre (2, 3, ou exactement 2 sur 4). **Ce doit être un paramètre du tournoi, pas une règle codée.**

### 5.2 Les façons de gérer la mixité

| Option | Principe | Ce qu'elle impose à l'inscription | Ce qu'elle impose à l'arbitrage | Remarques |
|---|---|---|---|---|
| **Quota sur le terrain** (BLVB 1J, FFVB 4×4, UFOLEP) | Minimum de joueuses (et parfois de joueurs) à chaque set ou en permanence | Catégorie de jeu de chaque joueur (D65) ; contrôle du quota de la *liste* (on ne peut pas tenir un quota de 2 avec une liste de 2 joueuses seulement) | Vérifier la composition à chaque set et à chaque remplacement ; appliquer la sanction (forfait, sortie d'un joueur) | Le plus répandu. Le choix « permanent » ou « au début du set » change beaucoup de choses en cas de blessure (cf. 6J). |
| **Points de départ** (BLVB 1J : −8 et −5) | Une composition hors norme est compensée au score plutôt qu'interdite | Idem | Annoncer et noter le score de départ de chaque set ; la saisie du score doit l'accepter | Garde le match joué, ce qui convient au loisir. À combiner avec le quota. |
| **Hauteur de filet** | 2,35 m en mixte, 2,43 m masculin, 2,24 m féminin (FFVB, UFOLEP) | Rien par joueur ; un paramètre par tournoi ou par tableau | Mesurer le filet. Impossible de changer la hauteur entre deux matchs d'un même terrain sans perte de temps | À fixer par tableau, pas par match. |
| **Règles de jeu adaptées** (contre des filles interdit, BLVB 3J ; restrictions d'attaque ; alternance des genres au service) | On compense dans le jeu lui-même | Rien de plus | L'arbitre juge des fautes supplémentaires. **Plus difficile en auto-arbitrage.** | L'alternance au service en 4×4 mixte est citée par un résultat de recherche (Coupe IUT), **non vérifiée**. |
| **Tableaux séparés** (féminin, masculin, mixte) | On ne mélange pas | Catégorie de jeu ; inscription dans un tableau ; contrôle de cohérence (pas de joueur dans le tableau féminin) | Rien de particulier | Il faut assez d'inscrits par tableau. FSGT, d'après la même source secondaire : un championnat féminin de 4×4 seulement à partir de 6 équipes. Pour un club loisir, le tableau féminin risque d'être trop petit. |
| **Handicap par équipe** (C6) | Points d'avance selon le niveau, éventuellement selon la composition | Un niveau par équipe ou par joueur | Annoncer le score de départ | Compense le niveau plutôt que la mixité, mais les deux se combinent. |

**[Proposition] pour un tournoi interne de club** :
- mixte par défaut ;
- quota au choix (« au moins N joueuses sur le terrain »), avec la sanction choisie dans une liste : forfait, joueur en moins, points de départ ;
- filet à 2,35 m ;
- tableaux séparés seulement si le nombre d'inscrites le permet. Le site peut **calculer la faisabilité** avec la formule E = min(⌊inscrits/k⌋, ⌊F/q⌋) de 3.12 et l'afficher pendant les inscriptions.

### 5.3 Mixité selon la formule

- **Toutes les formules** se jouent en mixte avec quota.
- **Mêlée et tirages** : la mixité est **garantie par le tirage**. C'est le seul cas où le site la produit au lieu de la contrôler.
- **Montante-descendante et roi du terrain** : en tableaux séparés, il faut des terrains séparés ; en mixte, un quota fixe (par exemple 2×2 mixte, 1 et 1).
- **Tableaux séparés** : se combinent avec toutes les formules, mais doublent les besoins en terrains ou en temps.

---

## 6. Questions à trancher par le club

1. **Quelles occasions ?** Soirée de fin d'année, journée festive, tournoi d'été en extérieur, défi interne sur la saison ? Cela fixe les durées à gérer en priorité.
2. **Qui s'inscrit ?** Des équipes déjà formées, des joueurs seuls, ou les deux ? Le cas « joueurs seuls » rend la catégorie de jeu (D65) et le tirage indispensables.
3. **Joueurs extérieurs au club** (conjoints, amis, joueurs d'autres clubs) : acceptés ? Sous quelle couverture d'assurance ?
   - Ce sont des adhérents de l'association BLVB ou de leur club, et l'assurance des invités est à vérifier.
   - La FFVB propose une licence événementielle pour des tournois ouverts (exemple de Nevers, cité par un résultat de recherche, **non vérifié**).
4. **Règle de mixité de référence** : reprendre la 1J du BLVB (au moins 2 joueuses, −8 et −5), ou laisser chaque tournoi choisir ?
5. **Règle 4J** (3 licenciés FFVB au plus) : s'applique-t-elle aux tournois internes ?
6. **Saisie des résultats** : par l'arbitre, par un capitaine, ou par un responsable de table unique ?
7. **Visibilité** : les résultats et classements d'un tournoi interne sont-ils publics, réservés au club, ou réservés aux participants ? Les classements individuels de la mêlée nomment des personnes : par défaut **réservés au club**, par analogie avec D66.
8. **Inscription payante** (repas, participation) : le site doit-il la gérer, ou renvoyer vers HelloAsso comme le font la plupart des clubs ?

---

## 7. Ce que le site devrait savoir gérer

### 7.1 Paramètres communs à toutes les formules

**Cadre**
- Club organisateur, titre, dates (une ou plusieurs journées), heure de début et de fin de chaque journée, lieux.
- Pour chaque lieu : **nombre de terrains utilisables**, réglé par tournoi et non déduit du gymnase.
- Visibilité (public, club, participants).
- Nombre maximum d'équipes ou de joueurs ; liste d'attente ; date limite d'inscription.
- Options d'inscription (repas, nuit), sans paiement.

**Format de jeu**
- Joueurs sur le terrain (6, 4, 3, 2) ; taille minimale et maximale de la liste d'une équipe.
- Hauteur de filet par tableau (indicative).
- Format de score par phase : nombre de sets, points par set, set sec ou 2 points d'écart, set décisif, **ou durée** en minutes, **ou hybride** (points ou minutes).
- Règle d'égalité pour les matchs au temps : point en or, ou égalité acceptée.
- **Durée d'un créneau** (jeu + rotation), réglable par phase.
- **Repos minimum** entre deux matchs d'une équipe ; les valeurs FFVB outdoor (10, 15, 30 min) peuvent servir de valeurs par défaut.
- Nombre maximum de matchs par équipe et par jour.

**Constitution des équipes**
- Mode : équipes formées / composées par le responsable / tirage équilibré / tirage à chaque tour.
- Critères de tirage : mixité, niveau, éviter les mêmes coéquipiers, licenciés FFVB.
- Graine du tirage conservée.
- Invités sans compte.

**Mixité**
- Type de tableau : mixte, féminin, masculin, ou plusieurs tableaux.
- Quota (minimum de joueuses, minimum de joueurs, permanent ou au début du set).
- Sanction : forfait, joueur en moins, points de départ, avec leurs valeurs (−8 et −5 au BLVB).
- Règles particulières affichées (contre des joueuses, service) : **texte libre, sans contrôle**.

**Formule (les phases, comme pour le championnat)**
- Phases enchaînées avec les mêmes briques que le wizard : poules (A1), triangulaires (A7), suisse (A8), échelle (A9), tableaux (B1 à B5), règles de passage (notation `PHASE.POULE#rang` de D4), meilleurs Xe (C5), handicap (C6).
- **Briques nouvelles** : montante-descendante ; roi du terrain (score seulement) ; mêlée, c'est-à-dire des tours avec un tirage et un classement individuel.

**Classement et départage**
- Barème par phase : victoire et défaite. Le circuit FFVB outdoor utilise victoire 2, défaite 1, forfait 0 (art. 38.5). Le BLVB utilise 3, 2, 1, 0 et −1 (règle 1D).
- Critères ordonnés (E2), dont le mini-classement entre équipes à égalité.
- Pour la mêlée : critères individuels (victoires, différence de points plafonnée, moyenne par match).

**Forfaits, retards, absences**
- Délai de tolérance avant forfait d'un match.
- Score attribué en cas de forfait : par exemple 25-0. Au circuit FFVB outdoor : « 21/0 : 21/0 » et 0 point (art. 50).
- **Une équipe forfait sur un match continue-t-elle le tournoi ?** Le circuit FFVB outdoor l'autorise (art. 50, cas 4).
- Retrait d'une équipe en cours de poule : résultats annulés ou conservés (rejoint PO2).
- Exempt dans un tableau ou au suisse : victoire attribuée ou non.
- En mêlée : un absent n'est simplement pas tiré.

**Planning**
- Génération du planning sur T terrains en respectant : le repos minimum, l'ordre des matchs d'une poule sur un terrain (voir 3.1), et l'équipe arbitre (une équipe au repos, désignée).
- **Calculs affichés avant validation** :
  - durée totale, heure de fin ;
  - minimum de matchs par équipe ;
  - taux de jeu 2T/n ;
  - arbitrage possible (n − 2T ≥ T) ;
  - faisabilité de la mixité (E = min(⌊inscrits/k⌋, ⌊F/q⌋)) ;
  - **marge** (alerte en dessous de 10 %).
- **Recalage en cours de journée** : un match qui déborde décale les matchs suivants du même terrain. Le site propose de les basculer sur un terrain libre ou de réduire le format de la phase suivante.
- **[Règle]** Le circuit FFVB outdoor autorise l'organisateur, en cas de retard, à « modifier la formule sportive » (art. 43). Le site doit donc permettre de **changer le format de score d'une phase pas encore commencée**.

**Saisie des résultats au gymnase**
- Sur téléphone, par l'arbitre ou un capitaine désigné, avec un **QR code par terrain** qui ouvre directement le match en cours. **[Proposition]**
- Correction par le responsable du tournoi.
- Le réseau est disponible dans les gymnases selon D66.
- Score de départ (handicap ou mixité) saisi ou pré-rempli.
- **Écran de suivi** à projeter dans le gymnase : match en cours et suivant par terrain, classements en direct.
- Pour la montante-descendante et la mêlée : saisie limitée à « qui a gagné » plus le score, en deux gestes.

### 7.2 Formules à traiter en priorité, avec l'argument

1. **Poules puis tableaux (C1 + B3 ou B4), avec des équipes déjà formées.**
   - *Argument* : c'est la formule la plus répandue dans les tournois loisir publiés (Strasbourg, IUT, UFOLEP Calvados, circuit FFVB), et la plus attendue par les joueurs.
   - Elle **réutilise presque tout le moteur existant** (A1, B1 à B4, notation des places, départage E2). Le travail nouveau porte surtout sur le planning d'une journée sur T terrains et la saisie au gymnase, deux briques dont toutes les autres formules ont besoin.
2. **Mêlée et tirage équilibré des équipes à partir d'inscriptions individuelles.**
   - *Argument* : c'est le besoin **propre à un tournoi interne**. Un club a des adhérents, pas des équipes, et c'est la raison pour laquelle D65 prévoit la catégorie de jeu.
   - C'est aussi la tâche la plus pénible à la main : tirage, respect de la mixité, classement individuel.
   - Le tirage équilibré sert aussi à constituer des équipes fixes pour la formule 1.
3. **Montante-descendante au temps.**
   - *Argument* : c'est la formule idéale pour une soirée (zéro attente, aucun débordement, résistante aux absences).
   - Elle est simple à programmer : pas de tableau, un chronomètre et une saisie « vainqueur ».

**Ensuite** : triangulaires (A7) et handicap (C6), qui se greffent sur l'existant.

**Plus tard, ou seulement sur demande** :
- système suisse : utile, mais méconnu des joueurs ;
- double élimination : complexe, rare en loisir ;
- échelle : n'a de sens que pour un défi étalé sur une saison ;
- roi du terrain : une animation qui n'a besoin que d'un score final.

---

## Sources

Consultées le 11/11/2026. Le statut indique ce qui a été réellement lu.

| Source | Date | Statut |
|---|---|---|
| Règlement BLVB 2025, `src/Reglement/Reprise/reglement-2025.html` (règles 1J à 7J, 1R, 2R, 1D, 2D, 1A, 1O à 3O) | saison 2025-2026 | lu en entier |
| `docs/formats-competition.md` ; `docs/wizard-saison/decisions.md` (D4, D65, D66) | 2026 | lus |
| [FFVB, Règlement particulier des épreuves du circuit national Volley Outdoor 2025/2026](https://extranet.ffvb.org/data/Files/manuel_juridique/2025-2026/FFvolley_RPE_CN_OUTDOOR_2025-26.pdf) (art. 1, 5.2.2, 23, 38 à 43, 50) | adopté par le CA le 24/01/2026 | lu par extraction du texte |
| [UFOLEP Pyrénées-Atlantiques, règlement volley-ball](https://cd.ufolep.org/pyreneesatlantiques/pyreneesatlantiques_d/data_1/pdf/re/reglementvolley2425.pdf) | saison 2024/2025 | lu par extraction, quelques passages tronqués |
| [UFOLEP Calvados, règlement volley](https://cd.ufolep.org/calvados/calvados_d/data_1/pdf/aa/aarglementvolleyufolepcalvadosjanvier2025.pdf) | 28/01/2025 | lu par extraction, valeurs du handicap illisibles |
| [Volleyball Ireland, King and Queen of the Court, règlement](https://www.volleyballireland.com/wp-content/uploads/2023/10/VLY-King-and-Queen-of-the-Court-Comp-Rules-2023.pdf) | 10/07/2023 | lu par extraction |
| [mobilesport.ch, « Tournoi du caisson »](https://assets01.sdd1.ch/assets/lbwp-cdn/mobilesport/files/1616426495/formes_tournois_3_caisson.pdf) | 2021 (métadonnées) | lu |
| [mobilesport.ch, « Beach-volley – Forme de tournoi : Up/Down »](https://assets01.sdd1.ch/assets/lbwp-cdn/mobilesport/files/1696446758/mobilesport-beach-volley--forme-de-tournoi-updown.pdf) | non datée | titre et phrase d'accroche seulement |
| [abargenteuil.fr, FSGT volley-ball](https://abargenteuil.fr/blog/fsgt-volley-ball/) (4×4 mixte 2+2 depuis 2016) | non datée | source secondaire (blog), non vérifiée sur fsgt.org |
| [Coupe de France IUT, règlement volley](https://coupedefrance.iut.fr/wp-content/uploads/2023/12/REGLEMENT-VOLLEY-.pdf) | mise en ligne en 12/2023 | site injoignable ; contenu connu par un résumé de recherche, **non vérifié** |
| [Unistra, règlement de la Nuit du VB 2024](https://sport.unistra.fr/fileadmin/upload/SUAPS/SUAPS/Evenements/Nuit_du_VB/Reglement_Nuit_VB_2024.pdf) (au moins 3 femmes et 3 hommes, poules puis coupe, matchs de 15 min) | 2024 | erreur 404 ; résumé de recherche seulement, **non vérifié** |
| [FFVB, présentation du Soft Volley (AG 2026)](https://extranet.ffvb.org/data/Files/Instances_ffvb/ag_ffvb/ag2026/Validation/FFvolley_AG2026_D6_Presentation_Soft_Volley.pdf) | 2026 | résumé de recherche seulement |
| [US Capbretonnaise Volley Loisir, Capbreton Summer Green 2026](https://www.helloasso.com/associations/union-sportive-capbretonnaise-volley-loisir/evenements/tournoi-gazon-2026) | 2026 | page refusée (403) ; le tournoi existe, son contenu n'a pas été vu. **Exemple local à demander directement au club**, comme [ESEVB, green 4×4 mixte](https://www.helloasso.com/associations/excellence-sud-essonne-volley-ball/evenements/tournoi-green-volley-4x4-mixte) (14/06/2026) et [Blagnac, 4×4 mixte 2025](https://www.helloasso.com/associations/blagnac-volley-ball/evenements/tournoi-de-volley-4x4-mixte-blagnac-volley-ball-2025) |

### Calculs vérifiés à part

- **Ordre des matchs d'une poule sur un seul terrain** : recherche exhaustive. Une poule de 3 ou de 4 ne peut pas s'ordonner sans qu'une équipe enchaîne deux matchs ; une poule de 5 ou de 6 le peut.
- **Double élimination à 8 équipes** : 14 matchs (15 avec la revanche), et 8 créneaux minimum sur 2 terrains à cause des dépendances entre matchs.
