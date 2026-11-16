# Formats de compétition : catalogue et paramétrage d'une saison

Ce document sert de base de travail au bureau pour choisir le format de chaque saison,
et de référence pour le paramétrage dans l'application.

Une saison se construit en assemblant trois briques :

1. **Des phases**, chacune jouée selon un **format** (partie A, B ou C).
2. **Des règles de passage** entre deux phases : qui va où, avec combien de points (partie D).
3. **Des règles de classement** : barème de points et critères de départage (partie E).

Le règlement change chaque année ; ces trois briques doivent donc être des **paramètres de la
saison**, et non du code.

---

## Notations

- `n` = nombre d'équipes de la poule ou du tableau
- *Journée* = un tour où chaque équipe joue au plus une fois
- *Exempt* = équipe qui ne joue pas lors d'une journée (cas d'un nombre impair d'équipes)

---

## A. Formats « toutes les équipes se rencontrent » (poules)

### A1. Championnat aller simple
- **Principe** : chaque équipe rencontre une fois chaque autre équipe.
- **Volume** : `n(n-1)/2` matchs ; `n-1` journées si `n` est pair, `n` journées si `n` est impair (une équipe exempte à chaque journée).
- **Pour** : c'est le plus équitable pour un volume de matchs donné.
- **Contre** : l'avantage du terrain n'est pas équilibré : la moitié des équipes reçoit une fois de plus que les autres.
- **Paramètres** : alternance domicile/extérieur, éviter deux réceptions consécutives (les « breaks »).
- **BLVB** : c'est le format actuel des phases 1 et 2 (poules de 8 et 9 équipes).

### A2. Championnat aller-retour
- **Principe** : comme A1, mais chaque équipe reçoit une fois et se déplace une fois contre chaque adversaire.
- **Volume** : `n(n-1)` matchs, `2(n-1)` journées.
- **Pour** : avantage du terrain parfaitement équilibré.
- **Contre** : il faut deux fois plus de dates, ce qui est difficile à tenir avec des créneaux de gymnase en semaine et sans match pendant les vacances scolaires.
- **Variante** : *retour en miroir* (même ordre des matchs) ou *retour décalé*.

### A3. Poules de niveau avec montées et descentes (pyramide)
- **Principe** : plusieurs poules hiérarchisées (A > B > C > D). À la fin de la phase, les premiers montent d'un niveau et les derniers descendent.
- **Paramètres** : nombre de montants et de descendants **par poule** (pas forcément symétrique), devenir des équipes de la poule du haut et de celle du bas.
- **Point d'attention** : avec des poules de tailles différentes (8 et 9), le nombre de montées et de descentes doit rester équilibré, sinon les tailles des poules dérivent d'une phase à l'autre.
- **BLVB** : c'est le principe actuel (2 montées, 2 descentes).

### A4. Poules de brassage puis poules de niveau
- **Principe** : une première phase courte en poules mélangées (par tirage ou par secteur géographique), puis une répartition en poules de niveau selon le classement.
- **Pour** : utile en début de saison ou quand beaucoup d'équipes sont nouvelles et que leur niveau est inconnu.
- **Paramètres** : méthode de répartition dans les poules de brassage (tirage au sort, serpentin, géographie), règle de répartition en poules de niveau.

### A5. Répartition en serpentin (méthode de constitution des poules)
- **Principe** : on classe les équipes, puis on les distribue en zigzag. Avec 4 poules : 1-2-3-4 dans les poules A-B-C-D, puis 5-6-7-8 dans D-C-B-A, et ainsi de suite.
- **Usage** : pour constituer des poules **homogènes entre elles** (et non des poules de niveau).

### A6. Poules géographiques
- **Principe** : poules constituées par proximité des gymnases.
- **Pour** : réduit les déplacements et facilite les « derbys » (deux équipes du même gymnase).
- **Contre** : le niveau des poules est hétérogène, d'où la nécessité d'une phase suivante par niveau (A4).

### A7. Plateaux (triangulaires et quadrangulaires)
- **Principe** : 3 ou 4 équipes se retrouvent dans un même gymnase sur une soirée et jouent toutes entre elles, en matchs courts (2 sets gagnants, ou un nombre de sets fixe).
- **Pour** : il faut moins de dates et moins de créneaux, et la soirée est plus conviviale.
- **Contre** : il faut un créneau long et un gymnase capable d'accueillir plusieurs équipes ; le format de match n'est pas le même.
- **Paramètres** : taille du plateau, format de match propre au plateau, rotation des gymnases qui reçoivent.

### A8. Système suisse
- **Principe** : nombre de rondes fixé à l'avance (souvent environ `log2(n) + 2`). À chaque ronde, on fait jouer entre elles les équipes qui ont un total de points proche, sans qu'elles se soient déjà rencontrées.
- **Pour** : donne un classement fiable pour beaucoup d'équipes avec peu de dates.
- **Contre** : le calendrier n'est connu qu'une ronde à l'avance, ce qui complique la réservation des gymnases.

### A9. Échelle (ladder) ou défis
- **Principe** : classement permanent ; une équipe peut défier une équipe classée au-dessus d'elle (jusqu'à X places plus haut) et prend sa place si elle gagne.
- **Usage** : convient à une compétition annexe et informelle, pas à un championnat principal.

---

## B. Formats à élimination (tableaux)

### B1. Élimination directe
- **Principe** : le perdant est éliminé. `n-1` matchs, `⌈log2(n)⌉` tours.
- **Têtes de série** : le placement standard sur 8 équipes est **1-8, 4-5, 3-6, 2-7**. Ainsi, le 1er et le 2e ne peuvent se rencontrer qu'en finale (c'est le schéma actuel des finales BLVB).
- **Quand `n` n'est pas une puissance de 2** : on ramène le tableau à `2^k` équipes, avec `2^k` la puissance de 2 juste inférieure à `n` :
  - **Tour préliminaire (barrages)** : `n - 2^k` matchs entre les équipes les moins bien classées.
  - **Exemptions** : les `2^(k+1) - n` meilleures équipes passent directement au tour suivant.
  - Exemple avec 9 équipes : un barrage 8e contre 9e, dont le vainqueur rencontre le 1er.
  - Exemple avec 12 équipes : 4 barrages (5-12, 6-11, 7-10, 8-9), et les 4 premiers sont exemptés.
- **Paramètres** : qui reçoit (le mieux classé, ou un terrain neutre), format de match (3 sets gagnants ou 2 sets gagnants).

### B2. Élimination directe avec petite finale
- C'est B1 avec un match pour la 3e place entre les perdants des demi-finales.

### B3. Tableau de classement intégral
- **Principe** : les perdants continuent de jouer pour les places suivantes (matchs pour les places 5 à 8, puis 5e et 7e place…). Chaque équipe obtient un classement final précis.
- **Pour** : toutes les équipes jouent le même nombre de matchs, ce qui est apprécié en loisir.
- **Contre** : le nombre de matchs augmente (`n/2 × log2(n)`).

### B4. Consolante
- **Principe** : les perdants du premier tour jouent un second tableau (« Coupe B », « Plate »).
- **Pour** : c'est un bon compromis entre B1 et B3.

### B5. Double élimination
- **Principe** : une équipe n'est éliminée qu'après sa deuxième défaite (tableau principal et tableau des perdants).
- **Volume** : environ `2n-2` matchs. C'est rare en volley sur une saison, car il y a trop de dates.

### B6. Confrontations en aller-retour
- **Principe** : chaque tour se joue en deux matchs. En volley, s'il y a une victoire de chaque côté, on départage par un **set en or** (un set décisif en 15 points joué juste après le match retour), ou au quotient de sets.
- **Contre** : il faut deux fois plus de dates par tour.

---

## C. Formats hybrides et barrages

### C1. Poules puis élimination directe
- **Principe** : le classement d'une phase de poules détermine le placement dans un tableau.
- **BLVB** : c'est la phase finale actuelle. Le classement de la phase 2 de chaque poule donne un tableau propre à chaque niveau.

### C2. Play-offs et play-downs (séparation du classement)
- **Principe** : après la saison régulière, la poule est coupée en deux. Le haut joue le titre, le bas joue le maintien, avec des points conservés en totalité, à moitié, ou remis à zéro.

### C3. Final four ou journée des finales
- **Principe** : demi-finales et finales regroupées sur un lieu et une date unique.
- **BLVB** : c'est la finale du vendredi soir avec la remise des prix.
- **Paramètres** : nombre de terrains disponibles, ordre de passage des finales, durée d'un match (pour construire le planning de la soirée).

### C4. Barrage de montée et descente
- **Principe** : match entre une équipe mal classée de la poule supérieure et une équipe bien classée de la poule inférieure. Le vainqueur joue dans la poule supérieure à la phase suivante.
- **Proposition du bureau** : *avant-dernier de la poule N* contre *2e de la poule N+1*.
- **Variantes** :
  - un match sec chez l'équipe de la poule supérieure (c'est l'usage courant : elle « défend » sa place) ;
  - un match sec sur terrain neutre ;
  - un aller-retour avec set en or ;
  - une triangulaire (avant-dernier N, 2e N+1, 3e N+1).
- Voir la partie F pour l'analyse appliquée au BLVB.

### C5. Meilleurs Xe (repêchage)
- **Principe** : on qualifie les premiers de chaque poule, plus les meilleurs 2e (ou 3e) toutes poules confondues.
- **Point d'attention** : avec des poules de tailles différentes, on ne peut pas comparer les points bruts. Il faut soit **ramener à une moyenne de points par match**, soit **retirer les matchs joués contre le dernier** des poules les plus grandes.

### C6. Coupe à handicap
- **Principe** : compétition ouverte à toutes les équipes, tous niveaux confondus. L'équipe de niveau inférieur démarre chaque set avec un bonus de points (par exemple +3 par niveau d'écart).
- **Pour** : c'est très apprécié en loisir, et le règlement BLVB applique déjà des débuts de set décalés (-5, -8) pour la mixité.

---

## D. Règles de passage entre deux phases

Ce sont les règles qui changent le plus d'une année sur l'autre. Elles doivent pouvoir s'écrire
sous la forme : **source → destination**.

| Source | Exemple | Destination |
|---|---|---|
| Rang dans une poule | 1er et 2e de la poule B (phase 1) | Poule A (phase 2) |
| Plage de rangs | 3e au 6e de la poule B | Poule B |
| Vainqueur ou perdant d'un match | Vainqueur du barrage A/B | Poule A |
| Meilleurs Xe | Les 2 meilleurs 3e | Tableau final |
| Toutes les équipes de la poule, dans l'ordre du classement | Poule 1 (phase 2), du 1er au 8e | Tableau final niveau 1, têtes de série 1 à 8 |

Pour chaque passage, il faut aussi fixer :

- **les points conservés** : remise à zéro (cas actuel), conservation totale, conservation à moitié, ou seulement les points obtenus contre les équipes qualifiées avec soi ;
- **le placement** dans la destination : tête de série selon le rang, serpentin, ou tirage au sort ;
- **le devenir de l'équipe en fin de saison** : le schéma 2025 précise, par exemple, que les montées et descentes de la phase 2 valent pour la *saison suivante*, alors que l'équipe joue malgré tout le tableau final de sa poule actuelle.

---

## E. Règles de classement

### E1. Barème de points (en volley, match en 3 sets gagnants)
| Résultat | Barème BLVB actuel | Barème FFVB courant |
|---|---|---|
| Victoire 3-0 ou 3-1 | 3 | 3 |
| Victoire 3-2 | 2 | 2 |
| Défaite 2-3 | 1 | 1 |
| Défaite 0-3 ou 1-3 | 0 | 0 |
| Forfait (perdant) | -1 dans le règlement, **-3 par défaut dans l'application** | -1 ou 0 selon la compétition |

Le barème doit pouvoir changer **par phase** : un barrage ou un tableau final n'attribue pas de points.

### E2. Critères de départage (liste ordonnée, configurable)
Les critères possibles, dans l'ordre où on les applique habituellement :
1. Points au classement
2. Nombre de victoires
3. **Quotient de sets** (sets gagnés / sets perdus). C'est la règle FFVB ; le règlement BLVB utilise la *différence*, qui avantage les équipes qui ont joué plus de matchs.
4. Quotient de points (seulement si les scores des sets sont saisis, ce qui n'est pas le cas aujourd'hui)
5. Confrontation directe. **Si 3 équipes ou plus sont à égalité**, il faut établir un mini-classement sur les seuls matchs qu'elles ont joués entre elles.
6. Nombre de forfaits (le moins), ou fair-play
7. Tirage au sort

---

## F. Application au BLVB

### F1. Format actuel, tel que décrit dans le schéma 2025

| Phase | Format | Passage vers la phase suivante |
|---|---|---|
| Phase 1 (sept. → janv.) | A1 + A3 : 4 poules (8, 8, 9, 9) | 2 montées, 2 descentes |
| Phase 2 (fév. → mai) | A1 + A3 : 4 poules | Classement complet de chaque poule vers le tableau de son niveau, et montées et descentes appliquées à la **saison suivante** |
| Phase finale (mai) | B1 : un tableau par poule, avec barrage si `n` > 8 | Finale lors de la journée des finales (C3) |

### F2. Proposition du bureau : barrages entre poules

Lecture proposée : **le 1er monte directement, le dernier descend directement, le 2e de la poule N+1
rencontre l'avant-dernier de la poule N.**

Points à trancher :

1. **Montées directes** : 1 seule montée directe et 1 barrage, ou 2 montées directes et un barrage pour le 3e ? Cela change l'équilibre des poules.
2. **Qui reçoit** : je recommande l'équipe de la poule supérieure, en match sec en 3 sets gagnants (en volley, l'égalité est impossible, donc pas besoin de prolongation).
3. **Date** : aujourd'hui la phase 1 se termine le 1er février et la phase 2 commence le même jour. Il faut **prévoir une semaine tampon** pour jouer les barrages, et tous les matchs reportés de la phase 1 doivent être joués avant. Il faut fixer une date limite ferme.
4. **Calendrier de la phase 2** : la composition des poules n'est connue qu'après les barrages. Deux options :
   - **(a)** générer le calendrier de la phase 2 après les barrages. C'est simple, mais il ne reste que quelques jours pour le publier ;
   - **(b)** générer le calendrier à l'avance avec des places du type « Vainqueur barrage A/B ». C'est plus confortable pour les clubs, mais le gymnase de cette place n'est connu qu'après le barrage, ce qui fausse l'optimisation des créneaux.

   **Je recommande l'option (a)** : les contraintes de gymnase sont le cœur de l'outil.
5. **Poules de 9 équipes** : l'avant-dernier est le 8e.
6. **Fin de saison** : appliquer aussi ce mécanisme aux montées et descentes de la phase 2 vers la saison suivante ? Si oui, les barrages se jouent en même temps que les tableaux finaux, et il faut des dates en plus.

### F3. Phase finale : barrages pour obtenir un tableau de 8
- Le principe actuel (barrages entre les moins bien classés, exemptions pour les meilleurs) est le bon (B1).
- Le placement doit suivre le schéma **1-8, 4-5, 3-6, 2-7**, et le vainqueur du barrage doit prendre la place du 8e.
- Alternative pour les poules de 9 : qualifier seulement les 8 premiers et proposer une consolante (B4) aux équipes éliminées en quart, pour que chacune joue au moins deux matchs.

### F4. Incohérences relevées dans le règlement publié (à corriger lors de la mise à jour)
1. **Schéma, groupe A de la phase 2** : il indique « du 1er au 8e de la phase 1 + les 2 premiers du groupe B », soit 10 équipes pour une poule de 8. Il faut probablement lire « du 1er au 6e ».
2. **Forfait** : le règlement indique -1 point, mais la valeur par défaut dans l'application est -3.
3. **Dates des quarts de finale** : « du 27/07/2026 au 08/05/2026 », la date de début est postérieure à la date de fin.
4. **« Aller simple/retour »** : la formule est ambiguë et doit être tranchée (A1 ou A2).
5. **Départage** : préciser le cas de 3 équipes ou plus à égalité, et choisir entre différence et quotient de sets.
6. **Poules de 9** : elles ont une journée de plus que les poules de 8. Le règlement doit dire comment l'équipe exempte est gérée (aucun point, ou victoire fictive).

---

## G. Paramètres d'une saison (pour le développement)

Ce qu'une saison doit pouvoir décrire, en remplacement de `config/app/saison.yaml` (figé) et
des valeurs écrites en dur (`setNbMonteeDefaut(2)`, `setNbDescenteDefaut(2)`) :

```yaml
saison:
  bareme: { victoire_forte: 3, victoire_faible: 2, defaite_forte: 1, defaite_faible: 0, forfait: -1 }
  departage: [points, victoires, quotient_sets, confrontation_directe, tirage_au_sort]
  phases:
    - code: P1
      format: championnat          # championnat | elimination | barrage | suisse | plateaux
      options: { aller_retour: false }
      poules: [{ code: A, niveau: 1 }, { code: B, niveau: 2 }, { code: C, niveau: 3 }, { code: D, niveau: 4 }]
    - code: BAR1
      format: barrage
      options: { match: sec, reception: mieux_classe_niveau_sup }
      matchs:
        - { code: AB, equipe1: "P1.A#-2", equipe2: "P1.B#2" }   # avant-dernier A contre 2e B
        - { code: BC, equipe1: "P1.B#-2", equipe2: "P1.C#2" }
        - { code: CD, equipe1: "P1.C#-2", equipe2: "P1.D#2" }
    - code: P2
      format: championnat
      points_conserves: 0
      poules:
        - code: A
          niveau: 1
          equipes: ["P1.A#1..-3", "BAR1.AB.vainqueur", "P1.B#1"]
        # ...
    - code: F
      format: elimination
      options: { taille_tableau: 8, placement: standard, petite_finale: false, reception: mieux_classe }
      poules:
        - { code: "1", equipes: ["P2.A#1..-1"] }
```

Notation des places : `PHASE.POULE#rang`, avec un rang négatif compté depuis la fin (`#-1` = dernier).
`#1..-3` désigne une plage de rangs, et `PHASE.MATCH.vainqueur` le vainqueur d'un match.

Principes :
- **Une saison = une copie de format.** On duplique la saison précédente, on modifie, et les saisons passées restent consultables avec *leurs* règles.
- **Les règles de passage sont des données.** `PhaseService::cloturerEtBasculer` les applique au lieu de calculer niveau ± 1.
- **Une vérification avant validation** : chaque équipe a exactement une destination, et les tailles de poules attendues sont respectées. C'est elle qui aurait détecté l'erreur 1 de la liste F4.
- **Le règlement en ligne est généré à partir de ces paramètres** (schéma des phases, barème, départage) : il ne peut plus être en décalage avec ce que fait l'application.
