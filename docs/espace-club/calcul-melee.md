# Mêlée : modèle de calcul « fixer ou faire calculer »

Avis de l'agent organisation-competition du 11/11/2026, enregistré tel quel (suite de D76). Rien n'est décidé ; questions à trancher en fin de document.

**Ce que j'ai lu** :
- `/home/amoul/projects/blvb/docs/espace-club/parametres-melee.md` (mon avis précédent, notations P1 à P73) ;
- `/home/amoul/projects/blvb/docs/espace-club/parcours-melee.md` (parcours de l'ergonome) ;
- D76 dans `/home/amoul/projects/blvb/docs/wizard-saison/decisions.md` ;
- le §3.12 de `/home/amoul/projects/blvb/docs/espace-club/formules-tournoi.md` ;
- les règles 1J et 2J de `/home/amoul/projects/blvb/src/Reglement/Reprise/reglement-2025.html` ;
- pour les terrains : `/home/amoul/projects/blvb/src/Entity/Creneau.php`, `/home/amoul/projects/blvb/src/Service/CalendrierAnalyseService.php` et `/home/amoul/projects/blvb/templates/front/fragments/_lieu_statut.html.twig`.

**Légende** : **[Règle]** texte établi, avec sa source ; **[Décidé]** D74 à D76 ; **[Proposition]** mon idée, à valider. Toutes les valeurs par défaut sont des propositions.

---

## À retenir

1. **Il y a deux étages de calcul, à ne pas mélanger.**
   - **Le programme** (temps et places) : tours, durée de match, battement, terrains, joueurs par équipe et remplaçants. Il fixe des **bornes** : combien de matchs chacun joue, l'écart entre joueurs, avec combien de personnes différentes on peut jouer.
   - **Le tirage** (qui joue avec qui) atteint ces bornes au mieux, tour par tour.
   - Le responsable règle seulement le premier étage. L'équilibre des niveaux et le refus des coéquipiers répétés se jouent au second, et ne se mesurent qu'après le tirage.

2. **Tout est discret et petit : le site peut tout essayer.**
   - Valeurs à parcourir : durée de match de 8 à 20 min, battement de 3 à 10 min, nombre de tours, terrains de 1 à la capacité, 4 formats, 4 tailles maximales d'équipe. Cela fait environ 10 000 combinaisons, évaluées en quelques millisecondes.
   - Ce qui est fixé réduit la liste. Les contraintes éliminent des combinaisons. L'objectif choisi les classe.
   - **Surcontraint** (aucune combinaison possible) : le site relâche une valeur fixée à la fois et propose les 2 ou 3 solutions les plus proches.
   - **Sous-contraint** (plusieurs solutions) : le site montre la meilleure, plus 2 options qui gagnent sur un autre objectif.

3. **Je recommande des scénarios côte à côte, dont chaque valeur se fixe en la modifiant.**
   - 3 cartes calculées : « Le plus de matchs », « Des matchs plus longs », « Tout le monde joue autant ».
   - Modifier une valeur la fixe (cadenas « fixé par vous »). Les autres se recalculent. Un lien « laisser le site choisir » libère la valeur.
   - Le même calcul resservira à la clôture des inscriptions, au pointage et en cours de soirée.

**Deux découvertes chiffrées qui changent les conseils** :
- **Des matchs plus longs donnent plus de jeu, pas moins.** Sur une soirée de 3 h, 6 tours de 17 min donnent 102 min de jeu par joueur, contre 84 min pour 7 tours de 12 min. La raison : moins de battements.
- **Correction de mon avis précédent (note de P49)** : en 6×6 sur 2 terrains, des coéquipiers répétés sont **inévitables dès le 2e tour**, et pas seulement après 4 tours (démonstration au §1.6).

---

## 1. Grandeurs liées et équations

### 1.1 Les grandeurs

| Symbole | Grandeur | Source habituelle | Plage |
|---|---|---|---|
| P | joueurs (inscrits à la création, présents le jour J) | hypothèse, puis pointage | 10 à 48 |
| F, H | joueuses, joueurs (P = F + H) | catégorie de jeu, si demandée | — |
| T | terrains utilisés | pré-rempli depuis le créneau (§5.2) | 1 à la capacité |
| k | joueurs sur le terrain par équipe (format) | saisi | 6, 4, 3, 2 |
| k_min, k_max | taille minimale et maximale d'une équipe (k_max − k = remplaçants) | défaut | 5 à 8 en 6×6 |
| q | minimum de joueuses par équipe | saisi | 0 à k |
| D | durée de la date (fermeture − ouverture) | saisi ou créneau | minutes |
| A | accueil et échauffement | défaut 20 | minutes |
| B | pauses (une de 15 min si D > 180) | défaut | minutes |
| C | clôture (résultats, rangement), sur la dernière date | défaut 10 | minutes |
| μ | marge | 10 %, au moins 10 min | — |
| g | durée de jeu d'un match | saisi ou calculé | 8 à 20 min |
| b | battement entre deux tours | défaut 5 | 3 à 10 min |
| d | durée d'un tour = g + b | calculé | — |
| N | nombre de tours de la date | calculé ou saisi | ≥ 1 |
| M, E | matchs par tour, équipes par tour (E = 2M) | calculé | — |
| S | joueurs placés par tour (sur la feuille) | calculé | — |
| L | joueurs sur le terrain à un instant donné | calculé | — |
| R | joueurs au repos par tour | calculé | — |
| m | matchs joués par un joueur (sur la feuille) | calculé | min et max |
| t | temps de jeu d'un joueur (sur le terrain) | calculé | min et max |
| Δm, Δt | écart entre le joueur le plus servi et le moins servi | calculé | — |
| s | taille d'une équipe sur la feuille (s − 1 coéquipiers par match) | calculé | — |
| X | coéquipiers distincts d'un joueur | borne | ≤ P − 1 |
| ρ | nombre maximal de fois avec un même coéquipier | borne | ≥ 1 |

### 1.2 Temps

```
(1) U_brut = D − A − B − C
(2) U      = U_brut − max(μ × U_brut, 10)          temps utile
(3) N × (g + b) ≤ U                               on finit avant la fermeture
(4) fin du jeu = ouverture + A + B + N × (g + b)  (+ C : salle rendue)
```

On peut résoudre (3) dans les trois sens :
- N = ⌊U / (g + b)⌋ ;
- g = ⌊U / N⌋ − b ;
- b = ⌊U / N⌋ − g.

**Exemple de référence (E0)**, utilisé dans toute la suite : soirée de 19h30 à 22h30, dernière date.
- D = 180, A = 20, B = 0, C = 10, donc U_brut = 150.
- Marge : max(15, 10) = 15, donc **U = 135**.
- b = 5.

| g (min) | d | N = ⌊135/d⌋ | Contrôle | Jeu par joueur, s'il joue à chaque tour (N × g) | Part du temps à jouer, g/d |
|---|---|---|---|---|---|
| 8 | 13 | 10 | 130 ≤ 135 < 143 | 80 | 62 % |
| 10 | 15 | 9 | 135 ≤ 135 | 90 | 67 % |
| 11 | 16 | 8 | 128 | 88 | 69 % |
| 12 | 17 | 7 | 119 (8 tours : 136 > 135) | 84 | 71 % |
| 14 | 19 | 7 | 133 | 98 | 74 % |
| 17 | 22 | 6 | 132 | **102** | 77 % |
| 20 | 25 | 5 | 125 | 100 | 80 % |

Dans l'autre sens, g = ⌊135/N⌋ − 5 :
- N = 6 donne g = 17 ;
- N = 7 donne 14 ;
- N = 8 donne 11 ;
- N = 9 donne 10 ;
- N = 10 donne 8 ;
- N = 11 donne 7, sous le minimum de 8 : impossible avec b = 5 (voir §3, cas C2).

### 1.3 Places par tour

```
(5) M = min(T, ⌊P / (2 k_min)⌋)        E = 2M
(6) S = min(P, E × k_max)              placés (sur la feuille)
(7) L = min(P, E × k)                  sur le terrain
(8) R = P − S                          au repos
```

Le roulement des repos (P53) donne à chacun ⌊N·S/P⌋ ou ⌈N·S/P⌉ matchs :

```
(9)  m_min = ⌊N·S / P⌋     m_max = ⌈N·S / P⌉     Δm ≤ 1
(10) Δm = 0  ⇔  P divise N·R  ⇔  N est un multiple du cycle c = P / pgcd(P, R)
     sinon : (N·R mod P) joueurs ont un repos de plus que les autres
```

**Exemple** : P = 28, 6×6, T = 2, repos d'abord (k_max = 6).
- M = min(2, ⌊28/10⌋) = 2, donc E = 4, S = 24, R = 4.
- c = 28 / pgcd(28, 4) = **7**.

| N | Calcul N × 24 / 28 | Répartition | Δm |
|---|---|---|---|
| 6 | 144 = 5 × 28 + 4 | 4 joueurs à 6 matchs, 24 à 5 | 1 |
| 7 | 168 = 6 × 28 | tous à 6 | **0** |
| 8 | 192 = 6 × 28 + 24 | 24 joueurs à 7, 4 à 6 | 1 |

Avec E0 : N = 7, donc g = ⌊135/7⌋ − 5 = **14 min**. Chacun joue 6 matchs, soit 84 min, et se repose une fois. C'est exactement le raisonnement de l'utilisateur.

### 1.4 Temps de jeu

**Hypothèse** : les remplaçants tournent au service, donc dans une équipe de s > k joueurs, chacun est sur le terrain environ k/s du match.

```
(11) temps sur le terrain pour un match : g × min(1, k/s)
(12) temps moyen : t̄ = N × g × L / P
```

Il y a deux façons d'absorber le surnombre, et elles ne donnent pas le même écart :
- **Repos d'abord** : l'écart vient par tours entiers, Δt = g (14 min dans l'exemple ci-dessus si N n'est pas un multiple de c).
- **Remplaçants d'abord** (défaut P52) : l'écart vient par fraction de match. P = 28 en 4 équipes de 7 : chacun joue 6/7 de chaque match, et l'**égalité est exacte quel que soit N**. P = 26, équipes de 7, 7, 6 et 6 : écart par tour de g/7, soit 1,7 min pour g = 12, que le tirage compense en alternant.

**Conséquence** : avec les remplaçants par défaut, « tout le monde joue autant » est presque acquis. Le réglage fin de N (le cycle c) ne compte que si le responsable choisit « repos d'abord », ou si P > 2T × k_max.

### 1.5 Mixité

- **[Règle]** Règle 1J BLVB (règlement 2025) : « au moins deux filles minimum par équipe sur le terrain et 4 garçons maximum ».
- **[Règle]** Règle 2J : « 5 minimum sur le terrain ».
- **[Décidé D74]** En mêlée, q est un paramètre.
- **[Proposition]** On transpose la règle 1J ainsi : au plus k − q joueurs par équipe sur le terrain.

Les inégalités :

```
(13) places réservées aux joueuses : r_F = min(F, q·E)
(14) si F < q·E : les joueuses sont réparties au mieux (P24 a), il manque q·E − F places de joueuses
(15) équité joueuses / joueurs : possible seulement si τ = L/P donne assez de joueuses sur le terrain
     τ × F ≥ r_F
     ⇔ part de joueuses F/P ≥ q/k      (si F ≥ q·E)
     ⇔ personne au repos (L ≥ P)       (si F ≤ q·E)
     sinon : τ_F = r_F / F  et  τ_H = (L − r_F) / H
```

**Exemple vérifié** : P = 28, F = 8, H = 20, 6×6, T = 2, q = 2.
- E = 4 et L = 24.
- F/P = 0,286, moins que q/k = 0,333 : l'équité est impossible.
- Les joueuses jouent tout le temps : τ_F = 8/8 = 1.
- Les joueurs jouent les 4/5 du temps : τ_H = 16/20 = 0,8.
- Sur 7 tours de 14 min : **98 min de jeu pour une joueuse, 78,4 min pour un joueur**.
- Le résultat est le même avec les remplaçants : dans chaque équipe de 7 (2 joueuses, 5 joueurs), 4 joueurs seulement peuvent être sur le terrain.

Avec F = 10 : 10/28 = 0,357 ≥ 0,333, l'équité est possible et tout le monde joue τ = 0,857.

### 1.6 Brassage

```
(16) coéquipiers par match : s − 1 ; sur m matchs : m(s − 1) places de coéquipier
(17) coéquipiers distincts : X ≤ min(P − 1, m(s − 1))
(18) même coéquipier :      ρ ≥ ⌈m(s − 1) / (P − 1)⌉
(19) aucun coéquipier répété au tour 2 ⇒ s ≤ E
```

Pour (19), la raison : chaque nouvelle équipe ne doit prendre qu'un joueur dans chaque équipe du tour précédent.

**Vérification** : 24 joueurs en 6×6 sur 2 terrains, donc s = 6 et E = 4. Comme 6 > 4, des répétitions sont inévitables au tour 2. Au mieux, chaque équipe prend 2, 2, 1 et 1 joueurs dans les anciennes équipes : 2 paires répétées par équipe, **8 paires sur 60**. Sur 8 tours : 8 × 5 = 40 places pour 23 personnes, donc ρ ≥ ⌈40/23⌉ = 2.

Autres cas :
- **4×4 sur 3 terrains, 24 joueurs** (s = 4 ≤ E = 6) : aucune répétition est théoriquement possible jusqu'à ⌊23/3⌋ = 7 tours. C'est une borne : le tirage approché ne garantit pas de l'atteindre (c'est le « problème des golfeurs »).
- **36 joueurs en 6×6 sur 3 terrains** : au plus 3 tours sans répétition (lignes, colonnes et symboles d'un carré latin d'ordre 6). Un 4e tour demanderait deux carrés latins orthogonaux d'ordre 6, qui n'existent pas (problème des 36 officiers d'Euler).

« Mélanger » recouvre deux objectifs **opposés** :
- **jouer avec le plus de monde** : il faut plus de tours (X augmente avec m) ;
- **ne pas rejouer avec les mêmes** : il faut moins de tours ou des équipes plus petites (ρ augmente avec m).

Les mêmes formules valent pour les adversaires, avec s adversaires par match.

### 1.7 Inscriptions

```
(20) P_max = 2T × k_max                                  (tout le monde sur la feuille)
     P_max(m*) = ⌊N × S / m*⌋                            (chacun joue au moins m* matchs)
(21) P_min = 2 k_min ; joueuses nécessaires = q × E pour E équipes
```

---

## 2. Contraintes et objectifs

### 2.1 Contraintes

| Contrainte | Dure ou souple | Si elle est violée |
|---|---|---|
| Fermeture du gymnase (3) | **dure** | jamais relâchée |
| T ≤ terrains libres du créneau (§5.2) | dure, sauf si le responsable force | alerte |
| k_min ≤ s ≤ k_max | dure | — |
| g de 8 à 20, b de 3 à 10 | dure (bornes du site) | — |
| Minimum de joueuses q | **souple** : répartir au mieux (P24 a) | alerte au responsable |
| Valeurs fixées par le responsable | dures tant qu'aucun conflit n'apparaît | en cas de conflit : options de relâchement (§3.2) |

### 2.2 Objectifs

| Objectif | Indicateur | Levier principal du programme |
|---|---|---|
| Le plus de matchs | m_min | N élevé, donc g court |
| Des matchs plus longs | g ; temps de jeu N·g·τ | g long, moins de battements |
| Tout le monde joue autant | Δt, puis Δm | remplaçants, N multiple de c, part de joueuses ≥ q/k |
| Jouer avec le plus de monde | X | N élevé, s grand |
| Ne pas rejouer avec les mêmes | ρ | N faible, s petit, E ≥ s |
| Équipes de niveau proche | mesuré après le tirage | tirage (P46) |

### 2.3 Arbitrages chiffrés (E0, U = 135, b = 5)

**a) Matchs longs ou nombreux.** Tout le monde joue à chaque tour (remplaçants) ; 24 joueurs en 6×6 sur 2 terrains, s = 6.

| Option | N × g | Matchs | Jeu (min) | ρ minimal | X maximal |
|---|---|---|---|---|---|
| Matchs courts | 10 × 8 | 10 | 80 | ⌈50/23⌉ = 3 | 23 |
| Défaut actuel | 7 × 12 | 7 | 84 | ⌈35/23⌉ = 2 | 23 |
| Équilibré | 7 × 14 | 7 | 98 | 2 | 23 |
| Matchs longs | 6 × 17 | 6 | **102** | 2 | 23 |
| Très longs | 5 × 20 | 5 | 100 | 2 | 23 (25 places pour 23) |

Lecture :
- passer de 7 à 10 matchs coûte 18 min de jeu (98 contre 80) et ajoute une répétition ;
- **12 min est un mauvais défaut sur ce créneau** : 14 min donne les mêmes 7 tours, avec 14 min de jeu en plus.

**b) Le plus de matchs ou tout le monde pareil.** P = 30, repos d'abord, S = 24, R = 6, donc c = 30 / pgcd(30, 6) = 5.

| Option | Matchs par joueur | Jeu par joueur | Δ |
|---|---|---|---|
| 5 tours × 20 min | 4 pour tous | 80 min | 0 |
| 7 tours × 14 min | 18 joueurs à 6, 12 à 5 (168 = 5 × 30 + 18) | 70 à 84 min | 14 min |
| 10 tours × 8 min | 8 pour tous | 64 min | 0 |

**c) Mixité et équilibre** (§1.5) : avec 8 joueuses sur 28 et q = 2, l'écart de 98 contre 78 min est **imposé par le quota**. Trois façons de le réduire :
- abaisser q à 1 ;
- ajouter un terrain, si personne n'est au repos ;
- l'accepter, en l'affichant.

**d) Mixité et brassage** : avec 8 joueuses et 2 par équipe sur 4 équipes, il y a 4 paires de joueuses par tour, pour C(8,2) = 28 paires possibles. Au-delà de 7 tours, deux joueuses se retrouvent forcément ensemble deux fois. Quand elles sont peu nombreuses, les joueuses rejouent plus souvent ensemble que les joueurs.

**e) Brassage et équilibre des niveaux** : le conflit est **faible dans les bornes** et réel dans le tirage.
- Exemple : 4 joueurs de niveau A pour 4 équipes, avec un équilibre strict. Ces 4 joueurs ne jouent jamais ensemble : 6 paires sur 276 sont exclues, ce qui ne change pas ρ.
- En revanche, un tirage glouton qui équilibre tend à reformer les mêmes associations « fort avec moins fort ».
- **[Proposition]** Ne pas le chiffrer à la création. Après chaque tirage, afficher au responsable « 3 paires déjà ensemble », et offrir un réglage « équilibre des niveaux : strict / souple / sans ».

---

## 3. Ce que le site calcule selon ce que le responsable fixe

### 3.1 Combinaisons courantes

Toujours E0 (U = 135, b = 5), 6×6, k_min = 5, sauf mention contraire.

| # | Le responsable fixe | Le site calcule | Exemple vérifié | Surcontraint : options proposées | Sous-contraint : options montrées |
|---|---|---|---|---|---|
| C1 | créneau + g | N = ⌊U/(g+b)⌋, heure de fin | g = 12 : N = 7, jeu de 19h50 à 21h49, salle rendue à 21h59 | g > U − b : « 1 seul tour » | sur cet exemple, il reste 16 min de jeu non utilisées : « 7 × 14 min » (même nombre de tours, plus de jeu) ou « 8 × 12 min avec 14 min de marge au lieu de 15 » |
| C2 | créneau + N | g = ⌊U/N⌋ − b | N = 8 : g = 11 (128 ≤ 135) | N = 11 : g = 7 < 8. Options : b = 4 et g = 8 (132) ; ou N = 10 et g = 8 (130) | — |
| C3 | créneau + g + N | contrôle (3) | g = 15, N = 8 : 160 > 135, et même > 150 sans marge | N = 6 × 15 (120) ; N = 8 × 11 (128) ; 7 × 14 (133, un pas sur chaque valeur) | — |
| C4 | g + N, sans fermeture | heure de fin et marge | 7 × 12 à partir de 19h50 : fin 21h49 | fin après la fermeture : C3 | — |
| C5 | P + créneau + « au moins m* matchs » | T, ou g, ou P_max | P = 40, repos d'abord, g = 12 et N = 7, m* = 6 : il faut 7S ≥ 240, soit S ≥ 35, E = 6, **T = 3** (7 × 36 / 40 = 6,3) | gymnase à 2 terrains (S = 24 : 4 matchs) : (a) **10 × 8 min**, 6 matchs pour tous (240 = 6 × 40) ; (b) **limiter à 28 inscrits** (7 × 24 / 28 = 6) ; (c) 3e terrain si possible | — |
| C6 | P + créneau + « tout le monde joue autant » | N multiple de c, puis g | P = 28, repos d'abord : c = 7, N = 7, **g = 14** | aucun multiple de c ne tient (P = 26, R = 2, c = 13 : 13 × 13 = 169 > 135) : Δm = 1 au mieux, ou remplaçants, Δt ≈ g/7 | P = 30, c = 5 : 5 × 20 ou 10 × 8 (tableau 2.3 b) |
| C7 | P + « personne au repos » | T ≥ ⌈P / (2 k_max)⌉, ou ⌈P/(2k)⌉ sans remplaçant | P = 30, k_max = 8 : T ≥ 2 | T demandé > terrains libres : 4×4, ou remplaçants jusqu'à 9, ou plafond P_max | — |
| C8 | « jouer avec au moins X personnes » | m ≥ ⌈X/(s−1)⌉, donc N_min, puis g | 24 joueurs, X = 23 (tout le monde au moins une fois) : m ≥ ⌈23/5⌉ = **5 tours** au minimum (borne : le tirage en demandera en pratique davantage) | N_min × (8 + b) > U : réduire X ou passer à des équipes plus grandes | — |
| C9 | « au plus ρ* fois avec le même » | N_max = ⌊ρ*(P−1)/(s−1)⌋ et s ≤ E si ρ* = 1 | 24 joueurs en 6×6, ρ* = 2 : N ≤ ⌊46/5⌋ = 9. ρ* = 1 : **impossible en 6×6 sur 2 terrains** (19) | options : 4×4 sur 3 terrains (jusqu'à 7 tours) ; accepter ρ = 2 ; 6×6 sur 3 terrains avec 36 joueurs (3 tours) | — |
| C10 | T + k + k_max (places) | P_max et P_min, joueuses nécessaires | 2 × 2 × 8 = 32 ; P_min = 10 ; joueuses : 2 × 4 = 8 pour 4 équipes | — | — |

**Jour J (C11).** Les présents diffèrent des inscrits : le site recalcule avec les valeurs fixées.
- **[Proposition]** Si l'objectif est « tout le monde joue autant », proposer d'ajuster g de ± 2 min pour tomber sur un multiple de c. Exemple : 28 présents au lieu de 32, c = 7, donc 7 × 14 au lieu de 8 × 11.
- Le cycle c dépend du P exact : **l'équité exacte ne se règle qu'à la clôture ou au pointage**, pas à la création.

**En cours de soirée (C12), quand un tour déborde.** Même calcul, sur le temps restant U' et les tours restants N'.
- Objectif « tout le monde joue autant » : **raccourcir les tours restants** (g' = ⌊U'/N'⌋ − b). Supprimer un tour casserait le cycle.
- Objectifs « le plus de matchs » ou « matchs longs » : supprimer le dernier tour, comme le proposait mon avis précédent.

### 3.2 Règles du calcul [Proposition, pour les développeurs]

1. **Parcourir toutes les combinaisons** : g de 8 à 20, b de 3 à 10, k_max de k à k + 3, T de 1 à la capacité, N de 1 à ⌊U/(g+b)⌋. Les valeurs fixées ne sont pas parcourues.
2. **Écarter** celles qui violent une contrainte dure (§2.1).
3. **Calculer** les indicateurs : N, heure de fin, marge réelle, m_min et m_max, t_min et t_max, Δt, X, ρ, et τ_F et τ_H si la mêlée est mixte.
4. **Classer** dans l'ordre de priorité de l'objectif choisi :

   | Objectif | Ordre de priorité |
   |---|---|
   | Le plus de matchs | m_min, puis −Δm, puis g |
   | Des matchs plus longs | t_min (temps de jeu), avec m_min ≥ 4 [seuil proposé], puis g |
   | Tout le monde joue autant (**défaut**) | −Δt, puis t_min, puis g proche de 14 |
   | Mélanger au maximum | −ρ, puis X, puis t_min |

5. **Sous-contraint** : afficher la meilleure combinaison, plus la meilleure de chacun des deux autres objectifs, en écartant les doublons.
6. **Surcontraint** : pour chaque valeur fixée, chercher la plus petite modification qui rend le problème possible, en écart relatif.
   - Proposer les 2 ou 3 plus petites, par exemple : « 7 tours au lieu de 8 », « 14 min au lieu de 15 », « battement de 4 min ».
   - Ne jamais toucher à la fermeture du gymnase.
   - Chaque option est un bouton qui applique la modification.

---

## 4. Présentation aux bénévoles

| Forme | Avantages | Limites |
|---|---|---|
| A. Interrupteur « fixer / laisser choisir » sur chaque réglage | couvre tous les cas | abstrait ; le bénévole doit comprendre les liens entre réglages ; il crée facilement des conflits sans le savoir |
| B. Objectifs prédéfinis et quelques valeurs fixées | simple | cache les compromis ; « mélanger » est ambigu (§1.6) |
| C. Scénarios côte à côte | concret (« 7 tours de 14 min, chacun 6 matchs, fin 22h02 ») ; les compromis se voient | ne laisse fixer qu'au travers de C |

**Je recommande C, avec le mécanisme de A caché dans la saisie.**

1. Une ligne d'hypothèse : « Pour **28** joueurs, dont **8** joueuses », pré-remplie avec 2T × k. Elle se change d'un geste, et le site rappelle : « recalculé à la clôture et au pointage ».
2. **Trois cartes** : « Le plus de matchs », « Des matchs plus longs » et « Tout le monde joue autant », cette dernière proposée par défaut. Chacune affiche :
   - tours × durée ;
   - heure de fin ;
   - « chacun joue 6 matchs (84 min) » ou « 5 à 6 matchs » ;
   - au repos par tour ;
   - « joue avec environ 20 personnes différentes » ;
   - la mixité si elle est tendue : « les joueuses jouent plus que les joueurs : 98 contre 78 min ».
3. Le responsable **choisit une carte**. Toute valeur modifiée devient **fixée**, avec le libellé « fixé par vous » et le lien « laisser le site choisir ». Les cartes se recalculent autour.
4. **En cas de conflit**, un encadré : « Impossible avec 8 tours de 15 min avant 22h30. Choisissez : [7 tours de 14 min] [8 tours de 11 min] [6 tours de 15 min] ».
5. **« Mélanger au maximum »** n'apparaît pas comme 4e carte par défaut. On le propose seulement s'il change réellement le résultat, c'est-à-dire si un autre format ou d'autres terrains sont possibles, avec un libellé précis : « Changer souvent de coéquipiers (4×4 sur 3 terrains) ».

**Pourquoi** :
- les bénévoles raisonnent en résultats, pas en variables ;
- au niveau du programme, deux objectifs seulement s'opposent vraiment (nombre de matchs contre durée), plus l'équité ;
- « modifier, c'est fixer » donne la souplesse de A sans demander au bénévole de comprendre les liens entre réglages ;
- le même composant sert à la création, à la clôture, au pointage et en cours de soirée.

**Réglages exposés dans les cartes** : heures, terrains, format, minimum de joueuses, durée de match, battement, nombre de tours, remplaçants (« jusqu'à 8 par équipe » ou « repos d'abord »). Accueil, clôture, marge et pause restent dans les réglages avancés.

---

## 5. Non-mixte et terrains pré-remplis

### 5.1 Trois modes de mixité [Décidé D76 pour le principe ; détail en Proposition]

| Mode | Contrainte du programme | Catégorie demandée à l'inscription | Ce qui change dans le calcul | Filet [Proposition] |
|---|---|---|---|---|
| **Mixte** | q ≥ 1, équations (13) à (15) | oui (D65 : visible du joueur et du responsable seulement) | équité, alerte quand F/P < q/k | une hauteur intermédiaire (2,35 m dans mon avis précédent, d'après le RPE Volley Outdoor FFVB 2025/2026, art. 5.2.2 ; **non revérifié ici**) |
| **Ouvert, sans quota** | q = 0, (13) à (15) supprimées | **non** (minimisation D65) | aucun | au choix du club |
| **Réservé à une catégorie** | q = 0, une seule catégorie | oui, pour filtrer, au moment où le joueur s'inscrit | aucun | 2,24 m (joueuses) ou 2,43 m (joueurs) : hauteurs des règles FIVB 2025-2028, en lien dans le règlement BLVB ; **numéro d'article non vérifié** |
| *Option : deux tableaux le même soir* | deux calculs, T_F + T_H ≤ T, mêmes N, g et b (même rendez-vous) | oui | chaque tableau doit avoir au moins 2 k_min joueurs | une hauteur par terrain |

- **[Règle]** Le règlement BLVB 2025 ne fixe **aucune hauteur de filet**, et sa règle 1J vaut pour le championnat mixte, pas pour une mêlée.
- **Exemple de deux tableaux** : P = 30 (10 joueuses, 20 joueurs), T = 3. Les joueuses ont 1 terrain (2 équipes de 5) et les joueurs 2 terrains (4 équipes de 5) : personne au repos.
- **Même cas avec T = 2** : il faut sacrifier un tableau (2 équipes de 8 joueurs et 4 au repos sur un seul terrain pour les joueurs). Je conseille alors le mode mixte.
- **Répartition des terrains** : le site choisit celle qui rapproche le plus τ_F et τ_H.

### 5.2 Terrains pré-remplis depuis `Creneau::capacite` [Décidé D76 ; règles de calcul en Proposition]

Ce que fait le code aujourd'hui :
- `Creneau` porte un jour de la semaine, des heures et une `capacite` ;
- les gabarits l'affichent en terrains (« N terrains libres sur M », `_lieu_statut.html.twig`) ;
- `CalendrierAnalyseService::analyser` **additionne** la capacité de tous les créneaux du jour, ce qui convient pour compter les matchs du championnat sur une journée.

Pour une mêlée, il faut un calcul différent :
1. **Créneaux retenus** : ceux du lieu dont le jour est celui de la date **et** qui chevauchent les heures de la mêlée.
2. **Capacité sur la plage** : à chaque instant, additionner les créneaux qui se chevauchent, puis retenir le **minimum** sur la plage. Exemple : créneaux de 18h à 20h (1 terrain) et de 20h à 22h30 (2 terrains), mêlée de 19h30 à 22h30 : 1 terrain, pas 3.
3. **Enlever les matchs du championnat** déjà programmés ce jour-là dans ce gymnase (même calcul de terrains libres que `_lieu_statut`). Si le résultat est 0 : alerte « Tous les terrains sont pris par le championnat ce soir-là ».
4. **Aucun créneau ce jour-là** : pas de pré-remplissage. On reprend la valeur précédente, sinon 2, avec l'aide « Pas de créneau habituel ce jour-là : indiquez le nombre de terrains ».
5. **Afficher l'origine** : « Pré-rempli : créneau du jeudi 20h-22h30, 2 terrains ». La valeur reste modifiable, et devient alors « fixée par vous ».
6. **Bonus** : pré-remplir aussi l'ouverture et la fermeture avec les heures du créneau.
7. **Formats réduits** : la capacité compte des terrains de 6×6. En 3×3 ou 2×2, un terrain peut accueillir deux matchs en travers. Je ne le déduis pas automatiquement : on propose « 2 matchs par terrain ? » seulement en 3×3 ou 2×2.
8. **T devient une contrainte dure** (T ≤ capacité libre) : le calcul ne propose pas plus de terrains, sauf si le responsable a forcé la valeur.

---

## Questions à trancher

1. **Objectif par défaut** : « Tout le monde joue autant » (ma recommandation pour du loisir) ou « Le plus de matchs » ?
2. **Durée de match par défaut** : sur une soirée de 3 h, 14 min plutôt que 12 (même nombre de tours, 14 min de jeu en plus) ? Ou laisser le calcul la choisir dans tous les cas ?
3. **Remplaçants d'abord** (k_max = 8, égalité presque acquise, mais temps partagé dans le match) ou **repos d'abord** (matchs complets, égalité seulement si N est un multiple du cycle) ?
4. **Ajustement au pointage** : le site propose-t-il seulement un nouveau programme (g ± 2 min), ou l'applique-t-il de lui-même ?
5. **Écart joueuses / joueurs imposé par le quota** : l'afficher au responsable seul (D65), ou le traiter automatiquement (abaisser q en cours) ?
6. **Deux tableaux non mixtes le même soir** : dans la v1, ou seulement « ouvert sans quota » et « réservé à une catégorie » ?
7. **Hauteurs de filet** : à faire vérifier dans les textes FIVB et FFVB avant de les afficher. Je ne les ai pas revérifiées pour cet avis.
8. **Correction de la note sous P49** de `parametres-melee.md` : en 6×6 sur 2 terrains, les répétitions de coéquipiers commencent au tour 2. Le défaut « éviter les mêmes coéquipiers » reste juste, mais la promesse doit être « le moins possible ».
