# Mêlée : complément au modèle de calcul (après D78, D79, D80)

Ce complément se lit avec `calcul-melee.md`, dont il reprend les symboles et les numéros d'équations. Avis de l'agent organisation-competition du 16/11/2026, enregistré tel quel ; questions à trancher en fin de document.

**Légende** : **[Décidé]** D77 à D80 ; **[Règle]** texte établi, avec sa source ; **[Proposition]** mon idée, à valider.

**Exemple de référence (E0)** : soirée de 19 h 30 à 22 h 30, temps utile U = 135 min, changement d'équipe b = 5 min, 6 contre 6, 2 terrains. Tous les calculs sont faits à la main : je n'ai rien pu exécuter.

## À retenir

1. **Une seule règle sert pour la carte conseillée en durée « calculée » et pour le bouton « programme égal ».** Pour chaque nombre de tours, on prend la durée la plus longue qui tient. On classe ensuite par écart, puis par durée la plus proche de 10 min.
   - 28 joueurs : 7 tours de 14 min.
   - 30 joueurs : 10 tours de 8 min (variante : 5 tours de 20 min).
   - 26 joueurs : aucun programme égal n'existe.
   - 24 joueurs : rien à proposer.
2. **Les remplaçants n'ajoutent pas de temps de jeu.** En 6 contre 6 sur un match, 12 joueurs sont sur le terrain, avec ou sans remplaçants. De 16 à 19 joueurs, c'est le 4 contre 4 sur 2 terrains qui fait jouer plus (16 sur le terrain).
3. **Deux tableaux, terrains variables** : l'égalité s'obtient quand la part des terrains d'un tableau, cumulée sur la soirée, égale sa part des joueurs.
   - Avec 10 joueuses et 20 joueurs sur 2 terrains, je retrouve les chiffres de D80 : 6 matchs contre 6 ou 7, au lieu de 9 contre 5 ou 6.
   - Sur le même cas, 7 tours de 14 min donnent 5 matchs à tout le monde.
   - La formule ne règle pas tout : avec 8 joueuses et 20 joueurs en 4 contre 4 sur 3 terrains, aucun échange de terrain ne réduit l'écart.

---

## 1. Défauts décidés et ordre de classement

### 1.1 Ce qui est parcouru et ce qui ne l'est plus

| Réglage | Statut | Parcouru par le calcul ? |
|---|---|---|
| Durée de match g | **[Décidé D78, D80]** 10 min, état « par défaut », sur la carte conseillée seulement | non sur la carte conseillée tant que l'état est « par défaut » ; oui (8 à 20) sur les deux autres cartes et en état « calculée » |
| Joueurs en trop | **[Décidé D78]** au repos (k_max = k) | non ; les remplaçants (k_max = k + 2) sont une option proposée, jamais choisie par le site |
| Changement d'équipe b | **[Décidé D78]** 5 min | **non**. Les formules du §1.2 du modèle s'écrivent avec b fixé |
| Nombre de tours N | calculé | oui |
| Terrains T | pré-rempli (D76) | oui, de 1 à la capacité |

**Changement d'équipe comme sortie de conflit [Proposition]** : il reste proposé, mais dans un seul cas et en dernier.
- Cas : le responsable a fixé des valeurs incompatibles (encadré de conflit, règle 6 du §3.2).
- Rang : après les options qui touchent au nombre de tours ou à la durée.
- Limite : à la baisse seulement, jusqu'à 3 min, avec la ligne « c'est court pour changer de terrain ».
- Exemple (cas C2 du modèle) : 11 tours fixés donnent g = ⌊135/11⌋ − 5 = 7, sous le minimum de 8. Options, dans l'ordre : « 10 tours de 8 min » (130 ≤ 135), puis « 11 tours de 8 min avec 4 min de changement » (11 × 12 = 132 ≤ 135).
- Il n'apparaît jamais dans les trois cartes ni dans le programme égal.

### 1.2 Durée maximale pour un nombre de tours

Pour ne jamais raccourcir la soirée (H26, validée par D79), un programme candidat est un nombre de tours N avec la durée la plus longue qui tient :

```
(22) g_max(N) = min(20, ⌊U / N⌋ − b)        candidat si g_max(N) ≥ 8
```

Sur E0 :

| N | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 |
|---|---|---|---|---|---|---|---|---|
| ⌊135/N⌋ − 5 | 28 | 22 | 17 | 14 | 11 | 10 | 8 | 7 |
| g_max(N) | 20 | 20 | 17 | 14 | 11 | 10 | 8 | impossible |
| N × (g + 5) | 100 | 125 | 132 | 133 | 128 | 135 | 130 | — |

### 1.3 Nouvelle règle 4 du §3.2 [Proposition, remplace l'ancienne]

| Carte | État de la durée | Valeurs imposées | Ordre de classement |
|---|---|---|---|
| Tout le monde joue autant (conseillée) | **par défaut** | g = 10, b, joueurs en trop | aucun classement : N = ⌊U/(10 + b)⌋ et T = le plus de terrains utiles. Le programme égal du §1.4 est calculé à côté. |
| Tout le monde joue autant | **calculée** (« laisser le site choisir ») | b, joueurs en trop | parmi les candidats (22) : écart Δt le plus petit ; puis au moins 4 matchs par joueur ; puis g le plus proche de 10 ; puis temps de jeu minimal le plus grand |
| Des matchs plus longs | libre | b, joueurs en trop | temps de jeu minimal (m_min × g) le plus grand, avec au moins 4 matchs ; puis g le plus grand |
| Le plus de matchs | libre | b, joueurs en trop | m_min le plus grand ; puis Δm le plus petit ; puis g le plus grand |
| Les trois | **fixée par vous** | g, b, joueurs en trop | mêmes ordres, à g fixé ; doublons fusionnés |

- « Durée proche de 14 » disparaît : c'est « proche de 10 », la valeur par défaut décidée.
- Avec le repos, Δt = g × Δm : classer par Δt ou par Δm revient au même.
- Contrôle sur 24 joueurs : carte conseillée 9 × 10 (90 min), matchs plus longs 6 × 17 (102 min), le plus de matchs 10 × 8 (80 min). En état « calculée », la carte conseillée reste à 9 × 10 : tous les candidats ont un écart nul et 10 min est la durée la plus proche de 10. Elle ne se confond donc plus avec « Des matchs plus longs ».
- Contrôle sur 28 joueurs, « Des matchs plus longs » : 6 × 17 donne 144 = 5 × 28 + 4, soit au moins 5 matchs et 85 min. 7 × 14 donne 6 matchs et 84 min. Le site retient 6 × 17.

### 1.4 Le programme égal (D79) : règle exacte

**Quand le chercher** : le programme à 10 min de la carte conseillée a un écart d'au moins 1 match.

**Règle [Proposition]** :
1. Calculer le cycle c = P / pgcd(P, R), avec R = P − S (équation 10).
2. Candidats : les multiples N de c pour lesquels g_max(N) ≥ 8, et N × S / P ≥ 4 matchs.
3. Retenir celui dont la durée est la plus proche de 10 min. À égalité, le plus de temps de jeu.
4. S'il existe un autre candidat qui donne au moins 10 min de jeu de plus par joueur, l'afficher en variante sous le bouton.
5. S'il n'y a aucun candidat : pas de bouton. Le site dit que l'écart d'un match est le minimum possible et propose les remplaçants, avec le temps de jeu.

**En mêlée mixte**, la règle ne s'applique que si la part de joueuses atteint le minimum (équation 15 : F/P ≥ q/k). Sinon l'écart vient du minimum de joueuses : le site affiche l'écart joueuses / joueurs et le bouton « baisser le minimum » (D78, point 5).

**Vérification**, 2 terrains, repos, S = 24. À 10 min : 9 tours, soit 216 places.

| P | R | Cycle c | À 9 × 10 min (216 places) | Candidats égaux | Ce que la carte propose |
|---|---|---|---|---|---|
| 24 | 0 | 1 | 9 matchs pour tous | — | rien : déjà égal |
| 26 | 2 | 13 | 216 = 8 × 26 + 8 : 8 joueurs à 9 matchs, 18 à 8 | N = 13 : g = ⌊135/13⌋ − 5 = 5 < 8, impossible | pas de bouton. Option remplaçants : équipes de 7, 7, 6 et 6, chacun 9 matchs, de 82,9 à 84,3 min sur le terrain |
| 28 | 4 | 7 | 216 = 7 × 28 + 20 : 20 joueurs à 8 matchs, 8 à 7 | N = 7, g = 14 (133 ≤ 135). N = 14 : g = 4, impossible | **[7 tours de 14 min]** : 7 × 24 / 28 = 6 matchs, 84 min |
| 30 | 6 | 5 | 216 = 7 × 30 + 6 : 6 joueurs à 8 matchs, 24 à 7 | N = 5, g = 20 : 4 matchs, 80 min. N = 10, g = 8 : 8 matchs, 64 min | **[10 tours de 8 min]** (à 2 min de 10). Variante : 5 tours de 20 min |

Détail du cas à 26 avec remplaçants : 9 × 14 = 126 places en équipe de 7, soit 4 × 26 + 22. 22 joueurs passent 5 fois en équipe de 7 (5 × 8,57 + 4 × 10 = 82,9 min) et 4 joueurs 4 fois (4 × 8,57 + 5 × 10 = 84,3 min). Total : 2 160 min, soit 9 × 24 × 10.

Autres effectifs, pour les tests :
- 27 joueurs (c = 9) : égal dès 9 × 10, 8 matchs chacun.
- 32 joueurs (c = 4) : 8 tours de 11 min, 6 matchs, 66 min. Le candidat 4 × 20 est écarté (3 matchs).
- 25, 29, 31 joueurs : cycle égal à P, aucun programme égal.

**Ce que le bénévole doit lire sur le bouton** : le programme égal ne donne pas « plus » à tout le monde.
- À 28 joueurs : 6 matchs de 14 min pour tous, contre 7 ou 8 matchs de 10 min. Moins de matchs, plus de jeu (84 min contre 70 à 80).
- À 30 joueurs : 8 matchs de 8 min pour tous, contre 7 ou 8 matchs de 10 min. Autant de matchs ou plus, moins de jeu (64 min contre 70 à 80).

Le bouton doit donc toujours afficher matchs et minutes.

---

## 2. Pointage : « programme du soir »

**La limite « ± 2 min » du cas C11 est supprimée [suite de D78, point 4]** : le site ne fait que proposer, donc toute durée de 8 à 20 min est permise.

**Règle de proposition [Proposition]** :
1. **Temps utile du soir** : U_soir = fermeture − clôture − marge − heure de début. L'heure de début est celle prévue pour le tour 1, ou l'heure courante plus 5 min si elle est dépassée.
2. **Recalculer le programme prévu** (N et g inchangés) avec les présents : qui joue, repos, matchs par joueur.
3. **Calculer le « conseillé ce soir »** avec la règle de la carte choisie à la création (§1.3), sur les présents.
   - Une valeur « fixée par vous » est respectée.
   - Une valeur « par défaut » ou « calculée » est libre.
4. **Afficher la proposition seulement si elle apporte quelque chose** :
   - l'écart de matchs passe de 1 ou plus à 0 ;
   - ou le programme prévu ne tient plus avant la fermeture ;
   - ou le nombre de matchs par tour change (passage sous 20 présents, par exemple) ;
   - sinon : « Programme inchangé ».
5. **Le programme prévu reste choisi.**
   - Exception : s'il n'est plus réalisable (salle rendue après la fermeture, ou plus assez de joueurs), aucune carte n'est choisie. « Tirer le tour 1 » reste inactif tant que le responsable n'a pas choisi. Le site n'applique toujours rien de lui-même.
6. **Durée fixée par le responsable, sans programme égal sur toute la soirée** : la proposition la relâche et le dit (« en passant les matchs de 10 à 14 min »). Le programme égal qui garde la durée mais finit plus tôt (7 tours de 10 min, fin à 21 h 35) va sous « Voir les autres programmes ».

**Exemple** : 28 présents, prévu 9 × 10. Conseillé ce soir : 7 tours de 14 min, 6 matchs (84 min), fin du jeu 19 h 50 + 133 min = 22 h 03. Prévu : 20 joueurs à 8 matchs, 8 à 7, fin 22 h 05.

**Retardataire après le tirage du tour 1** : il entre dans le roulement des repos au tour suivant. Le site recalcule les compteurs, sans nouvelle proposition.

---

## 3. Retard en cours de soirée (H32)

Notations, à la fin du tour i :
- N' = N − i, les tours restants ;
- h : heure réelle de début du tour suivant ;
- J_prévu : fin du jeu prévue ;
- Z : heure où la salle est rendue.

```
(23) J = h + N' × (g + b)                     fin du jeu projetée
(24) Z = J + C                                salle rendue
(25) retard = J − J_prévu
(26) marge réelle = fermeture − C − J_prévu
(27) alerte  ⇔  Z > fermeture  ⇔  retard > marge réelle
(28) raccourcir : g' = ⌊(fermeture − C − h) / N'⌋ − b      proposé si g' ≥ 8
(29) supprimer : x = ⌈(Z − fermeture) / (g + b)⌉ tours, en partant du dernier
```

- **Retard inférieur ou égal à la marge réelle** : une ligne, sans action. Je propose de l'afficher à partir de 3 min.
- **Retard supérieur** : l'encadré d'alerte.
- **Ordre des options** :
  - carte « Tout le monde joue autant » : raccourcir d'abord, car le nombre de matchs de chacun ne change pas ;
  - deux autres cartes : supprimer d'abord ;
  - si g' < 8 : suppression seule.
- Chaque option affiche la répartition des matchs après coup, recalculée avec le roulement réel.
- « Plus tard » : l'encadré revient à la fin du tour suivant si (27) est toujours vraie.

**Vérification sur E0** (9 × 10, J_prévu = 22 h 05, salle rendue à 22 h 15, marge réelle = 15 min) :
- 6 min de retard : J = 22 h 11, Z = 22 h 21, avant 22 h 30. Simple ligne.
- 18 min de retard après le tour 4 : h = 20 h 50 + 18 = 21 h 08, N' = 5. Z = 21 h 08 + 75 + 10 = 22 h 33, après 22 h 30 : alerte.
  - Raccourcir : g' = ⌊72/5⌋ − 5 = 9. Fin du jeu 21 h 08 + 70 = 22 h 18, salle rendue à 22 h 28.
  - Supprimer : x = ⌈3/15⌉ = 1 tour. Fin du jeu 22 h 08, salle rendue à 22 h 18.
  - Ces chiffres sont ceux du wireframe de l'ergonome (§3.4 du parcours v3).

**Marge** : après « raccourcir », il reste ici 2 min. Le site doit l'écrire : « Il ne reste que 2 min de marge. » Voir la question 7.

---

## 4. De 13 à 19 joueurs, 6 contre 6, 2 terrains, repos par défaut

**Ce que donne le modèle** : M = min(2, ⌊P/10⌋) = 1 match tant que P < 20. Le deuxième terrain reste vide et 12 joueurs jouent.

**[Règle]** Règlement BLVB 2025, règle 2J : « 5 minimum sur le terrain ». C'est une règle du championnat. Je la transpose à la mêlée, comme le modèle le fait déjà (k_min = 5).

**Correction d'une idée du parcours v3** (§5, point 3 : « avec des remplaçants, 16 joueraient ») : 16 sont dans une équipe, mais 12 seulement sont sur le terrain. Le temps de jeu moyen est le même qu'avec le repos, soit 9 × 12 × 10 / P. Les remplaçants changent la forme de l'attente (dans le match plutôt qu'un tour entier), pas sa quantité.

Chiffres pour 9 tours de 10 min. En 6 contre 6 : 108 places avec le repos, 144 avec des équipes de 8.

| P | 6×6, repos (défaut) | 6×6, remplaçants | 4×4 sur 2 terrains |
|---|---|---|---|
| 13 | 1 au repos ; 4 joueurs à 9 matchs, 9 à 8 (80 à 90 min) | équipes de 7 et 6 ; 9 matchs, 83 min en moyenne | équipes de 4, 3, 3, 3 : tous jouent, 90 min (*) |
| 14 | 2 au repos ; 10 à 8 matchs, 4 à 7 (70 à 80) | 7 et 7 ; 9 matchs, 77 min | 4, 4, 3, 3 : 90 min (*) |
| 15 | 3 au repos ; 3 à 8, 12 à 7 (70 à 80) | 8 et 7 ; 9 matchs, 72 min | 4, 4, 4, 3 : 90 min (*) |
| 16 | 4 au repos ; 12 à 7, 4 à 6 (60 à 70) | 8 et 8 ; 9 matchs, 67,5 min | 4 équipes de 4 : **tous jouent, 90 min** |
| 17 | 5 au repos ; 6 à 7, 11 à 6 (60 à 70) | 1 au repos ; 8 à 9 matchs, 9 à 8 ; 63,5 min | 1 au repos ; 8 à 9 matchs, 9 à 8 (80 à 90) |
| 18 | 6 au repos ; 6 matchs pour tous (60) | 2 au repos ; 8 matchs pour tous, 60 min | 2 au repos ; **8 matchs pour tous, 80 min** |
| 19 | 7 au repos ; 13 à 6, 6 à 5 (50 à 60) | 3 au repos ; 11 à 8, 8 à 7 ; 57 min | 3 au repos ; 11 à 8, 8 à 7 (70 à 80) |

(*) Suppose qu'on accepte des équipes de 3 en 4 contre 4. C'est une **proposition**, par analogie avec la règle 2J qui tolère 5 joueurs pour 6. Sans elle, le 4 contre 4 ne fait qu'un match de 13 à 15 joueurs (8 jouent) et n'est pas proposé.

Contrôles : 108 = 8 × 13 + 4 = 7 × 14 + 10 = 7 × 15 + 3 = 6 × 16 + 12 = 6 × 17 + 6 = 6 × 18 = 5 × 19 + 13. Et 144 = 8 × 17 + 8 = 8 × 18 = 7 × 19 + 11.

**Ce que le site doit proposer [Proposition]** :
- **Déclencheur** : un terrain reste vide faute de joueurs (M < T), et un autre format mettrait plus de monde sur le terrain.
- **De 16 à 19 joueurs** : verdict d'alerte « Un seul match par tour : le 2e terrain reste vide ».
  - Option mise en avant : [Jouer en 4 contre 4 sur 2 terrains], avec le chiffre (« 16 jouent à chaque tour au lieu de 12 »).
  - Option secondaire : [Remplaçants], avec la phrase « personne n'attend un tour entier ; le temps de jeu ne change pas ».
- **De 13 à 15 joueurs** : 1 à 3 au repos, ce qui reste acceptable.
  - Le repos reste le défaut.
  - Option [Remplaçants] : tout le monde dans une équipe.
  - Option [4 contre 4] seulement si les équipes de 3 sont admises.
- **Effet de seuil à 20** : à 19 joueurs, 7 sont au repos. À 20, tous jouent (4 équipes de 5). Le site doit le dire à 18 et 19 : « À 20 joueurs, tout le monde joue. »
- **Au pointage** : mêmes options dans « Programme du soir ». Le changement de format est signalé (« annoncé en 6 contre 6 ») et n'est jamais appliqué d'office.
- **Remarque de terrain** : 18 joueurs en 6 contre 6 au repos, c'est la tournante connue des clubs à 3 équipes (une équipe attend). Le 4 contre 4 donne 80 min au lieu de 60.

---

## 5. Deux tableaux, terrains variables d'un tour à l'autre (D80)

### 5.1 Variables

- Pour chaque tour i de 1 à N : a_i = terrains des joueuses, e_i = terrains des joueurs.
- Places d'un tableau X (F joueuses ou H joueurs) qui a x terrains : S_X(x) = min(P_X, 2x × k_max).
  - Les équipes ont de k_min à k_max joueurs.
  - S_X(0) = 0 : tout le tableau est au repos.
- Joueurs sur le terrain : L_X(x) = min(P_X, 2x × k).

### 5.2 Contraintes

```
(30) a_i + e_i ≤ T                                   à chaque tour
(31) a_i ≤ ⌊F / (2 k_min)⌋    e_i ≤ ⌊H / (2 k_min)⌋    terrains utiles
(32) aucun terrain vide qu'un tableau pourrait occuper :
     ni (a_i + 1, e_i) ni (a_i, e_i + 1) ne respecte (30) et (31)
(33) F ≥ 2 k_min et H ≥ 2 k_min ; chaque tableau joue au moins 4 matchs par personne [seuil proposé]
(34) même format k, même g, même b, même N pour les deux tableaux      [Décidé D77 ; format : H30]
```

- **(32) est indispensable.** Sans elle, on « égalise » en laissant un terrain vide, ce qui ne donne rien à personne.
- **Un tableau peut avoir 0 terrain pendant un tour** : c'est l'exemple même de D80. « Au moins un match possible » (D77) se lit donc sur la soirée, pas sur chaque tour.

### 5.3 Ce qui compte : les totaux

```
(35) Σ_X = somme sur les tours de S_X(terrains de X au tour i)
(36) chaque personne du tableau X joue ⌊Σ_X / P_X⌋ ou ⌈Σ_X / P_X⌉ matchs
(37) égalité dans le tableau X  ⇔  P_X divise Σ_X
(38) repère : l'égalité entre tableaux demande
     (terrains-tours des joueuses) / (N × T) ≈ F / P
```

L'ordre des tours ne change pas le nombre de matchs. Il change seulement les filets et les attentes. On choisit donc d'abord **combien de tours** de chaque répartition, puis **dans quel ordre**.

### 5.4 Objectif, dans l'ordre

1. Écart de matchs sur l'ensemble des participants (le plus servi moins le moins servi), le plus petit.
2. Écart entre la moyenne des joueuses et celle des joueurs, le plus petit.
3. Écart de temps de jeu, le plus petit.
4. Total des places, le plus grand.
5. Le moins de changements de filet.

**Avec des remplaçants**, les critères 1 et 2 se calculent sur le temps passé sur le terrain (L, pas S). Sinon le nombre de matchs « sur la feuille » masque l'écart réel.

### 5.5 Calcul

1. Lister les répartitions possibles d'un tour, par (30) à (32). Il y en a au plus T + 1.
2. Essayer toutes les façons de répartir les N tours entre elles. Exemple : 10 tours et 4 répartitions font 286 cas.
3. Calculer (35) et (36) pour chaque cas, et classer par le §5.4.
4. La **répartition fixe** est le cas où une seule répartition sert à tous les tours.
5. Ordonner les tours selon la variante choisie (§5.6).

**Programme égal en deux tableaux** : même bouton qu'au §1.4. La recherche porte sur les couples (N, g_max(N)) et, pour chacun, sur les répartitions.

### 5.6 Les trois variantes

Les joueuses occupent toujours les terrains de plus petit numéro. Si tous les terrains sont utilisés :

```
(39) changements de filet = somme sur i de |a_(i+1) − a_i|      (un changement = un terrain, une fois)
(40) attente d'un tableau = plus longue suite de tours à 0 terrain × (g + b)
```

| Variante | Ordre des tours | Changements de filet | Attente |
|---|---|---|---|
| **Fixe** | la même répartition tout le soir | 0 | aucune attente de tableau ; l'écart de matchs reste |
| **Tours groupés** | toutes les répartitions identiques à la suite | |a_N − a_1|, soit 1 dans les cas courants | si un tableau n'a pas de terrain pendant z tours : z × (g + b) d'un bloc. Placé en fin de soirée, ce bloc est une fin plus tôt pour ce tableau. |
| **Tours répartis** | les tours « différents » espacés régulièrement, le premier et le dernier aux extrémités s'il y en a au moins 2 | 2z − 2 dans ce cas (2z s'ils sont tous au milieu) | 1 tour au plus (g + b) |

- **Attente individuelle** : quand aucun tableau n'est à 0 terrain, on regarde le roulement. Si deux tours voisins totalisent plus de repos que le tableau n'a de joueurs, certains se reposent deux tours de suite. Les tours répartis l'évitent.
- **Hypothèse sur le filet** : régler un filet de 19 cm prend 2 à 3 min à deux et tient dans les 5 min du changement d'équipe. Je ne l'ai pas vérifié sur les poteaux des gymnases du championnat.

### 5.7 Quand proposer [Proposition de seuil]

- La meilleure répartition **fixe** laisse un écart d'**au moins 2 matchs**, **et** la répartition variable le réduit d'au moins 1 match.
- Un écart d'un match est le bruit normal du roulement : il ne justifie pas de toucher aux filets.
- Si rien ne l'améliore (exemple E du §5.9), le site affiche l'écart et les autres options : remplaçants pour le tableau en surnombre, mêlée mixte, un terrain de plus.
- **Variante mise en avant** :
  - tours groupés si les deux hauteurs diffèrent (un seul réglage) ;
  - tours répartis si le club a mis la même hauteur aux deux tableaux (aucun réglage).
- Le responsable choisit toujours : le site n'applique rien seul.

### 5.8 Roulement des repos et remplaçants

- **Un roulement par tableau, indépendant.** À chaque tour, ceux qui ont joué le plus de matchs se reposent. À égalité : celui dont le dernier repos est le plus ancien. Cette règle garantit un écart d'un match au plus dans le tableau, à tout moment, même si le nombre de places varie d'un tour à l'autre. C'est (36), qui généralise (9).
- Un tour où le tableau entier est au repos ne compte pas dans le roulement.
- **Les remplaçants ne remplacent pas les terrains variables.** Exemple A en répartition fixe avec remplaçants : les joueurs sont 16 sur la feuille (7 ou 8 matchs) mais 12 sur le terrain. Ils jouent 9 × 12 × 10 / 20 = 54 min, contre 90 min pour les joueuses.
- La répartition optimale est la même avec ou sans remplaçants, puisqu'elle se calcule sur le terrain.
- **Pointage** : la répartition est recalculée avec les présents de chaque tableau, selon la règle du §2 (proposée, jamais appliquée).
- **Retard** : « raccourcir » garde la répartition. « Supprimer le dernier tour » oblige à recalculer la répartition des tours restants à partir des matchs déjà joués. En deux tableaux, raccourcir passe donc en premier.

### 5.9 Vérifications (9 tours de 10 min, repos, sauf mention)

**A. 10 joueuses, 20 joueurs, 6×6, 2 terrains** (l'exemple de D80)
- Terrains utiles : joueuses ⌊10/10⌋ = 1, joueurs ⌊20/10⌋ = 2.
- Répartitions possibles par (32) : (1,1) et (0,2).
  - (1,1) : 10 joueuses jouent (2 équipes de 5) ; 12 joueurs jouent, 8 au repos.
  - (0,2) : 20 joueurs jouent (4 équipes de 5).
- Avec x tours en (1,1) : joueuses = x matchs ; places des joueurs = 12x + 20(9 − x) = 180 − 8x.

| x | Joueuses | Joueurs | Écart |
|---|---|---|---|
| 9 (fixe) | 9 | 108 = 5 × 20 + 8 : 8 joueurs à 6 matchs, 12 à 5 | **4** |
| 7 | 7 | 124 = 6 × 20 + 4 : 4 à 7, 16 à 6 | 1 (moyennes 7 et 6,2) |
| **6** | **6** | 132 = 6 × 20 + 12 : **12 à 7, 8 à 6** | **1** (moyennes 6 et 6,6) |
| 5 | 5 | 140 = 7 × 20 : 7 | 2 |

- Retenu : x = 6 (critère 2). Ce sont les chiffres de D80.
- Repère (38) : 18 terrains-tours × 10/30 = 6.
- Temps de jeu : 60 min pour les joueuses, 60 à 70 min pour les joueurs.
- Variantes :
  - fixe : 0 changement de filet ; écart 9 contre 5 ou 6 ;
  - groupés (tours 1 à 6 à deux tableaux, tours 7 à 9 aux joueurs) : **1 changement** (terrain 1, de 2,24 à 2,43 m après le tour 6) ; les joueuses n'ont plus de terrain pendant 3 × 15 = 45 min, donc elles finissent à 21 h 20 au lieu de 22 h 05 ;
  - répartis (joueurs seuls aux tours 1, 5 et 9) : **4 changements** (après les tours 1, 4, 5 et 8) ; attente d'un tour au plus (15 min) ; les joueuses commencent à 20 h 05 et finissent à 21 h 50.
- Programme égal : 7 tours de 14 min (133 ≤ 135), dont 5 en (1,1) et 2 en (0,2). Joueuses : 5 matchs. Joueurs : 5 × 12 + 2 × 20 = 100 = 5 × 20, soit 5 matchs. **Tout le monde à 5 matchs et 70 min.**
- Pour comparer : en mêlée mixte, 24 jouent à chaque tour. 216 = 7 × 30 + 6, soit 7 ou 8 matchs chacun. Le bouton [Passer en mêlée mixte] reste utile.

**B. 10 joueuses, 20 joueurs, 6×6, 3 terrains**
- Une seule répartition par (32) : (1,2). Joueuses : 2 équipes de 5. Joueurs : 4 équipes de 5.
- 9 matchs et 90 min pour tous. Écart 0. Rien à proposer, aucun changement de filet.

**C. 12 joueuses, 24 joueurs, 6×6, 3 terrains**
- Terrains utiles : 1 et 2. Une seule répartition : (1,2). 12 et 24 jouent.
- 9 matchs pour tous. Écart 0. Rien à proposer.
- Même effectif sur **2 terrains** :
  - fixe (1,1) : joueuses 9 matchs ; joueurs 108 = 4 × 24 + 12, soit 12 à 5 matchs et 12 à 4. Écart 5.
  - variable : places des joueurs = 12x + 24(9 − x) = 216 − 12x. Avec x = 6 : joueuses 6, joueurs 144/24 = 6. **Écart 0, 60 min pour tous.**
  - 3 tours aux joueurs seuls : mêmes changements de filet et mêmes attentes que l'exemple A.

**D. 8 joueuses, 16 joueurs, 4×4, 2 terrains**
- Terrains utiles : 1 et 2. Répartitions : (1,1) et (0,2).
- Fixe : joueuses 9 matchs ; joueurs 72 places pour 16, soit 8 à 5 matchs et 8 à 4. Écart 5.
- Variable : places des joueurs = 8x + 16(9 − x) = 144 − 8x. Avec x = 6 : joueuses 6, joueurs 96/16 = 6. **Écart 0.**
- Repère (38) : 18 × 8/24 = 6.

**E. 8 joueuses, 20 joueurs, 4×4, 3 terrains** (la limite ; équipes de 4 strictement)
- Terrains utiles : joueuses 1, joueurs ⌊20/8⌋ = 2. Une seule répartition par (32) : (1,2).
- Joueuses 9 matchs ; joueurs 144 = 7 × 20 + 4, soit 4 à 8 matchs et 16 à 7. Écart 2.
- **Aucune répartition variable ne l'améliore.** Retirer un tour aux joueuses laisserait un terrain vide, car les joueurs ne peuvent pas en occuper 3.
- Le site affiche l'écart et propose :
  - remplaçants pour les joueurs : 4 équipes de 5, 9 matchs, 72 min sur le terrain contre 90 ;
  - ou la mêlée mixte.

**F. 20 joueuses, 30 joueurs, 6×6, 3 terrains** (aucun tableau ne s'arrête)
- Terrains utiles : 2 et 3. Répartitions : (1,2), (2,1) et (0,3).
- Fixe (1,2) : joueuses 108 = 5 × 20 + 8 (8 à 6 matchs, 12 à 5) ; joueurs 216 = 7 × 30 + 6 (6 à 8, 24 à 7). Écart 3.
- Variable, 7 tours en (1,2) et 2 tours en (2,1) :
  - joueuses 84 + 40 = 124 = 6 × 20 + 4 : 4 à 7 matchs, 16 à 6 ;
  - joueurs 168 + 24 = 192 = 6 × 30 + 12 : 12 à 7, 18 à 6 ;
  - **écart 1**, moyennes 6,2 et 6,4.
- Écart 0 impossible : il faudrait 120 et 180 places. Les solutions de 12 n₁ + 20 n₂ = 120 donnent 186 ou 162 places aux joueurs.
- Variantes :
  - groupés (les deux tours (2,1) à la fin) : 1 changement de filet (terrain 2), mais 18 + 18 = 36 repos pour 30 joueurs, donc 6 joueurs au repos deux tours de suite ;
  - répartis (tours 1 et 9) : 2 changements, personne deux fois de suite au repos.
- Ici les tours répartis sont le meilleur choix.

---

## 6. Filet

**Sources** :
- **[Règle]** Le règlement BLVB 2025 ne fixe aucune hauteur de filet. Vérifié : le mot « filet » n'y apparaît que pour les fautes de jeu.
- **[Règle, vérification partielle]** 2,43 m (joueurs) et 2,24 m (joueuses) : Règles officielles du volley-ball FIVB 2025-2028, approuvées au Congrès mondial de 2024. Plusieurs résumés consultés ce jour confirment les valeurs. Je n'ai pas ouvert le texte officiel : le numéro d'article (2.1.1) n'est pas vérifié.
- **2,35 m** : **[Décidé D78]** comme défaut du mode mixte. Je n'ai trouvé aucun texte fédéral qui le fixe (recherche du 16/11/2026). La référence au RPE Volley Outdoor FFVB citée dans mes avis précédents reste non vérifiée. Le libellé doit rester « hauteur mixte », jamais « hauteur officielle ».

**Défaut en « Ouvertes à tous » [Proposition, confirme H28]** : 2,35 m, libellé « hauteur mixte ».
- Raison : sans minimum de joueuses, le site ne demande pas la catégorie (D65). Il ne peut donc pas savoir qui joue.
- Une mêlée ouverte à tous réunit en général joueuses et joueurs.
- La valeur est modifiable, comme les autres.

**Ce que le site dit quand une répartition change la hauteur d'un terrain [Proposition]** :
- **Sur la proposition** (étape 3 et pointage), sous chaque variante : « 1 réglage de filet : terrain 1, après le tour 6, de 2,24 m à 2,43 m. » ou « 4 réglages de filet (terrain 1, après les tours 1, 4, 5 et 8). »
- **Répartition fixe** : « Aucun réglage de filet pendant la soirée. »
- **Au pointage**, si la répartition proposée diffère de celle prévue : « Le terrain 2 passe au tableau des joueuses : filet à régler à 2,24 m avant le tour 1. »
- **Sur l'écran du tour**, dès la fin du tour précédent, au-dessus du tirage : « Avant le tour 7 : terrain 1, monter le filet à 2,43 m (il passe au tableau des joueurs). » Verbe « monter » ou « baisser » selon le sens.
- **Même hauteur pour les deux tableaux** : aucun message de filet. La ligne devient « Aucun réglage de filet : même hauteur pour les deux tableaux. »

---

## Questions à trancher

1. **Programme égal quand il en existe deux** (30 joueurs) : le plus proche de 10 min (10 tours de 8 min, 8 matchs, 64 min) ou le plus de jeu (5 tours de 20 min, 4 matchs, 80 min) ? Je recommande le premier sur le bouton et le second en variante : il garde l'esprit de la mêlée (beaucoup de matchs, attente de 13 min au lieu de 25).
2. **Équipes de 3 en 4 contre 4** : les accepter ? Sans elles, le 4 contre 4 n'aide pas de 13 à 15 joueurs.
3. **De 16 à 19 joueurs** : le site met-il le 4 contre 4 en avant (ma recommandation), ou seulement en option derrière le repos ?
4. **Seuil de proposition des terrains variables** : écart d'au moins 2 matchs en répartition fixe (ma proposition), ou dès 1 match ?
5. **Variante mise en avant** : tours groupés quand les hauteurs diffèrent, tours répartis sinon ? Et pour les tours groupés, bloc en fin de soirée (un tableau finit plus tôt) ou au début (il arrive plus tard) ?
6. **« Joueurs en trop » en deux tableaux** : un seul réglage commun (wireframe actuel) ou un par tableau ? Un réglage par tableau permettrait de mettre des remplaçants chez les joueurs seulement (exemple E).
7. **Retard** : après « raccourcir », garder une réserve (5 min, par exemple) ou utiliser tout le temps restant et l'afficher ? Avec 5 min de réserve, l'exemple donne 8 min au lieu de 9.
8. **Réglage du filet** : tient-il dans les 5 min du changement d'équipe avec les poteaux des gymnases ? Sinon, il faut allonger le changement du tour concerné, ou déconseiller les tours répartis.
9. **2,35 m** : à faire confirmer par un texte, ou à assumer comme choix du championnat.

## Fichiers lus

- `/home/amoul/projects/blvb/docs/espace-club/calcul-melee.md`
- `/home/amoul/projects/blvb/docs/espace-club/parcours-melee-v3.md`
- `/home/amoul/projects/blvb/docs/wizard-saison/decisions.md` (D76 à D80)
- `/home/amoul/projects/blvb/src/Reglement/Reprise/reglement-2025.html` (règles 1J et 2J ; absence de hauteur de filet)

Sources :
- [FIVB, Official Volleyball Rules (page officielle)](https://www.fivb.com/volleyball/the-game/official-volleyball-rules/)
- [FIVB, Règles officielles du volley-ball 2025-2028, version française (PDF, non ouvert)](https://www.fivb.com/wp-content/uploads/2025/06/FIVB-Volleyball_Rules2025_2028-FR-v04.pdf)
- [Résumé des règles 2025-2028 (source secondaire)](https://knowt.com/note/db18c4b7-3bb5-474e-87a0-d18bc19ac352/Volleyball-Rules-2025-2028)
- [FFvolley, RCOM 2026-27 (trouvé, sans passage sur la hauteur du filet)](https://extranet.ffvb.org/data/Files/manuel_juridique/2026-2027/FFvolley_RCOM_2026-27.pdf)
