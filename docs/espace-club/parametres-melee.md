# Mêlée : paramètres pour la créer et la faire tourner

Avis de l'agent « organisation-competition » du 11/11/2026, enregistré tel quel. Rien n'est décidé au-delà de D65, D74 et D75.

**Ce que j'ai lu** : `docs/espace-club/formules-tournoi.md` (§3.12, §4, §5, §7), les décisions D65, D74 et D75 de `docs/wizard-saison/decisions.md`, et les règles 1J à 7J et 1R de `src/Reglement/Reprise/reglement-2025.html`, relues pour cet avis.

**Légende**
- **[Règle]** : texte établi, avec sa source.
- **[Décidé]** : décision D65, D74 ou D75.
- **[Proposition]** : mon idée, à valider.

Toutes les valeurs par défaut sont des propositions, sauf mention contraire.

---

## À retenir

1. **Le responsable n'a qu'une douzaine de valeurs à saisir.**
   - Il saisit : titre ; pour chaque période, date, heures, lieu et terrains ; format (6×6, 4×4…) ; minimum de joueuses ; durée d'un match ; clôture des inscriptions ; équilibrage par niveau oui ou non.
   - Le reste est calculé (nombre de tours, places, faisabilité de la mixité, heure de fin) ou rangé dans des réglages avancés, avec une valeur par défaut.

2. **Le tour est un point de rendez-vous.** Les joueurs de tous les terrains sont mélangés à nouveau à chaque tour. Le tour suivant ne peut donc commencer qu'à la fin du match le plus long.
   - Conséquence : le **match au temps** (par défaut) ou **hybride** (« 25 points ou 12 min ») est presque obligatoire.
   - Le match aux points seul fait attendre tous les autres terrains.

3. **Petite correction de la consigne** : le nombre de terrains ne donne pas le nombre de tours.
   - **Nombre de tours** = durée de la période ÷ durée d'un tour.
   - **Nombre de terrains** = combien de joueurs jouent à chaque tour, donc combien de matchs chacun joue.

4. **Le manque de joueuses est le piège principal.**
   - Exemple : 24 présents dont 6 joueuses, 6×6, 2 terrains, au moins 2 joueuses par équipe.
   - Si l'on respecte strictement le minimum : on ne peut faire que 3 équipes. Le nombre d'équipes doit être pair, il reste donc **un seul match** et 8 joueurs au repos.
   - Si l'on répartit au mieux (2, 2, 1, 1 joueuses) : **tout le monde joue**.
   - Je recommande « répartir au mieux et prévenir le responsable » plutôt qu'une sanction : dans une mêlée, personne n'est responsable de la composition, c'est le tirage qui la fait.

5. **D65 impose des précautions d'affichage.**
   - La catégorie de jeu n'est visible que de l'admin, du responsable et du joueur [Décidé D65].
   - Les équipes tirées doivent donc être affichées aux participants **sans compteur ni marque de catégorie**.
   - Un classement séparé par catégorie la révélerait aux membres du club. C'est à trancher (question 2).

---

## 1. Conventions des tableaux

**Statut**
- **Obl.** : obligatoire, demandé.
- **Fac.** : facultatif, visible.
- **Calc.** : calculé par le site, modifiable s'il est indiqué « Calc. modifiable ».
- **Av.** : réglage avancé, caché derrière la valeur par défaut.
- **Fixe** : fixé par une décision, pas un paramètre en v1.

**Moment où le paramètre est fixé**
- **C** : à la création, avant publication.
- **I** : modifiable jusqu'à la clôture des inscriptions. Les inscrits sont prévenus si la date, l'heure ou le lieu change.
- **J** : le jour J, avant le premier tour de la période.
- **EC** : modifiable en cours, s'applique aux tours suivants.
- **F** : figé dès le premier résultat saisi.

**Notations des calculs**
- P : présents ; F : joueuses présentes.
- T : terrains ; k : joueurs sur le terrain par équipe.
- k_min, k_max : taille minimale et maximale d'une équipe ; q : minimum de joueuses.
- d : durée d'un tour ; N : nombre de tours.

---

## 2. Paramètres, dans l'ordre d'un parcours de création

### Étape 1. Identité et visibilité

| # | Paramètre | Rôle | Valeurs | Défaut recommandé | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P1 | Titre | Nommer la mêlée | texte, 80 caractères | « Mêlée du {date de la 1re période} » | Obl. (pré-rempli) | C, I | date (P11) |
| P2 | Club organisateur | Fixe qui peut s'inscrire et voir | clubs dont l'utilisateur est responsable | son club, s'il n'en a qu'un | Calc. | C, puis figé | droits du responsable |
| P3 | Saison | Fixe quels membres peuvent s'inscrire (lien joueur / club / saison, D75) | saisons | saison de la date de la 1re période | Calc. | C, puis figé | P11 ; voir question 1 |
| P4 | Description, mot d'accueil | Infos pratiques (tenue, apéritif…) | texte libre | vide | Fac. | C, I, EC | — |
| P5 | Règles particulières | Contre des joueuses, rotation des remplaçants… | texte libre, sans contrôle | pré-rempli avec un **résumé généré** des paramètres de jeu, plus : « Contre des joueuses non autorisé sauf entente (règle 3J BLVB) » | Fac. | C, I | P20 à P31 |
| P6 | Hauteur de filet | Information | texte ou valeur | 2,35 m en mixte (voir la note sous le tableau) | Fac. | C, I | P25 |
| P7 | Visibilité | Qui voit les inscrits, les équipes, les résultats | — | membres du club et participants [Décidé D74] | Fixe | — | — |
| P8 | État | Brouillon, publié (inscriptions ouvertes), inscriptions closes, en cours, terminé | — | brouillon | Calc. (actions) | — | — |

Notes :
- **P6** : 2,35 m en mixte, 2,24 m pour une mêlée réservée aux joueuses, 2,43 m pour une mêlée réservée aux joueurs. **[Règle]** FFVB, RPE Volley Outdoor 2025/2026, art. 5.2.2 (CA du 24/01/2026) ; même échelle à l'UFOLEP 64 (2024/2025). **Le règlement BLVB 2025 ne fixe aucune hauteur.**
- **Pas de co-organisateur ni de délégué de saisie en v1** : la saisie revient au responsable [Décidé D74].

### Étape 2. Périodes (une ou plusieurs)

Les périodes 2 et suivantes reprennent par défaut le lieu, les horaires et les terrains de la période 1 : seule la date change.

| # | Paramètre | Rôle | Valeurs | Défaut | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P10 | Nombre de périodes | Durée du tournoi | 1 à n | 1 | Calc. (nombre de périodes ajoutées) | C, I | [Décidé D74] |
| P11 | Date | — | date | — | Obl. | C, I | alerte si le gymnase est fermé ce jour-là (fermetures D60) |
| P12 | Heure d'accès au gymnase | Début de la période | heure | aucune ; suggestions 19h30 ou 14h00 | Obl. | C, I | — |
| P13 | Heure de fermeture | Fin dure, celle du concierge (cf. règle 1R) | heure > P12 | — | Obl. | C, I | une alerte si P13 − P12 < 75 min |
| P14 | Lieu | — | gymnases du club, ou autre | gymnase habituel du club | Obl. | C, I | D60 |
| P15 | Nombre de terrains | Combien de matchs ont lieu en même temps | 1 à 6 | valeur du dernier tournoi dans ce gymnase, sinon 2 | Obl. | C, I, **EC** (un terrain perdu) | donne les places (P39) et le nombre de joueurs au repos |
| P16 | Accueil et échauffement | Temps avant le 1er tour ; le pointage et le 1er tirage se font pendant ce temps | minutes | 20 | Av. | C, J | P32 |
| P17 | Pause | Boire, souffler | 0 ou une pause de X min | aucune si la période dure 3 h ou moins, sinon une pause de 15 min au milieu | Av. | C, EC | P32 |
| P18 | Temps de clôture | Proclamation des résultats, rangement | minutes | 10 pour la dernière période, 0 pour les autres | Av. | C, EC | P32 |
| P19 | Marge | Absorber les retards | % du temps utile | 10 %, au moins 10 min | Av. | C | P32 |

### Étape 3. Format de jeu et mixité

| # | Paramètre | Rôle | Valeurs | Défaut | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P20 | Joueurs sur le terrain (k) | Format | 6, 4, 3, 2 | **6** (format du championnat) | Obl. | C, jusqu'à J | P21, P22, P23, P39, P40 |
| P21 | Taille minimale d'une équipe (k_min) | Permet de jouer en sous-nombre plutôt que de laisser des joueurs au repos | k − 1 ou k | **5 en 6×6**, k sinon | Av. | C, EC | P20 |
| P22 | Taille maximale d'une équipe (k_max) | Absorbe le surnombre par des **remplaçants** qui tournent au service, plutôt que par du repos | k à k + 3 | **8 en 6×6, 5 en 4×4**, k en 3×3 et 2×2 | Av. | C, EC | P20, P39, P52 |
| P23 | Minimum de joueuses par équipe (q) | Mixité | 0 à k (0 = pas de contrainte) | **2 en 6×6** (comme la règle 1J) ; **1 en 4×4 et 3×3** (voir la note) ; 1 en 2×2 | Obl. [Décidé D74] | C, I, **EC** | P24, P44, faisabilité |
| P24 | Si les joueuses présentes ne suffisent pas | Comportement quand F < q × nombre d'équipes | (a) répartir au mieux, avec un écart d'au plus 1 joueuse entre équipes, et alerter le responsable ; (b) faire moins d'équipes ; (c) répartir au mieux, et l'équipe sous le minimum part avec X points de retard | **(a)** | Av. | C, J, EC | P23, P15 |
| P25 | Ouvert à | Mêlée mixte ou réservée à une catégorie (tableaux séparés) | tous ; catégorie féminine ; catégorie masculine | **tous** | Av. | C, puis figé à la 1re inscription | P23 (sans objet si réservée), P6 ; question 5 |
| P26 | Type de score | Durée du match | au temps ; hybride (N points **ou** M minutes) ; aux points | **au temps** | Obl. (pré-rempli) | C, EC (tours pas encore joués) | P27, P28, P31 |
| P27 | Durée de jeu | — | 8 à 20 min | **12 min** | Obl. si au temps ou hybride | C, EC | d = P27 + P31 |
| P28 | Points par set et écart | Pour « aux points » et « hybride » | 15, 21 ou 25 ; set sec ou 2 points d'écart | 25, sec | Obl. si aux points ou hybride | C, EC | P26 |
| P29 | Fin au signal | Ce qu'on fait quand le temps sonne | on finit l'échange en cours ; arrêt immédiat | **on finit l'échange** ; en hybride, l'équipe qui mène gagne | Av. | C, EC | P26 |
| P30 | Égalité au temps | Règle pour un score à égalité | point en or ; match nul accepté (compte ½ victoire) | **point en or** (environ 1 min, pris sur le battement) | Av. | C, F | P59 |
| P31 | Battement entre deux tours | Annonce des équipes, changement de terrain, report des scores | 3 à 10 min | **5 min** | Av. | C, EC | d |

Notes :
- **P21** : la règle 2J BLVB fixe « 5 minimum sur le terrain » en 6×6 [Règle, règlement 2025].
- **P22** : la rotation des remplaçants au service est ma proposition.
- **P23 en 4×4** : la valeur est inférieure à la règle **[Règle]** FFVB 4×4 mixte (« 2 féminins, 2 masculins », RPE Volley Outdoor 2025/2026, art. 23.1.2). Raison : faisabilité dans un club où les joueuses sont souvent un tiers des effectifs (exemple au §3).
- **P24** : je déconseille le point (c). Le −8 de la règle 1J sanctionne une équipe qui s'est présentée incomplète ; en mêlée, c'est le tirage qui compose, pas l'équipe.

### Étape 4. Tours (calculés)

| # | Paramètre | Rôle | Calcul | Statut | Moment |
|---|---|---|---|---|---|
| P32 | Tours par période (N) | Programme | **N = ⌊(P13 − P12 − P16 − P17 − P18) × (1 − P19) ÷ d⌋**, avec d = P27 + P31 (au temps) ou durée estimée d'un set + P31 (aux points) | Calc. modifiable | C, J, **EC** (ajouter ou retirer un tour) |
| P33 | Tours du tournoi | Total | somme des N | Calc. | — |
| P34 | Horaire de chaque tour | Affichage | début + accueil + i × d (+ pause) | Calc. | EC (recalé sur l'heure réelle) |
| P35 | Dernier tour possible | Aide le jour J | P13 − P18 − d | Calc. | J |

**Exemples vérifiés**, avec d = 12 + 5 = 17 min et une marge de 10 % :

| Période | Temps utile | × 0,9 | ÷ 17 | Tours | Marge réelle |
|---|---|---|---|---|---|
| Soirée 19h30-22h30, pas la dernière | 180 − 20 = 160 | 144 | 8,47 | **8** | 160 − 8 × 17 = 24 min |
| Même soirée, dernière période (clôture 10 min) | 180 − 20 − 10 = 150 | 135 | 7,94 | **7** | 150 − 119 = 31 min |
| Entraînement 20h-22h | 120 − 20 = 100 | 90 | 5,29 | **5** | 100 − 85 = 15 min |
| Après-midi 14h-18h, pause 15 min, clôture 10 min | 240 − 20 − 15 − 10 = 195 | 175,5 | 10,3 | **10** | 195 − 170 = 25 min |
| Soirée 19h30-22h30 aux points (25 points sec, set estimé à 20 min, donc d = 25) | 160 | 144 | 5,76 | **5** | 35 min, mais chaque tour attend le match le plus long |

Une mêlée sur deux soirées compte donc **8 + 7 = 15 tours**.

**Si un tour déborde** : avec un match au temps, le débordement se limite au point en or ou à l'échange en cours. Le site recale les horaires suivants (P34). Si la marge est consommée, il propose de supprimer le dernier tour plutôt que de raccourcir la durée de jeu, ce qui est plus lisible pour les joueurs.

### Étape 5. Inscriptions

| # | Paramètre | Rôle | Valeurs | Défaut | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P36 | Ouverture | — | date et heure | à la publication | Fac. | C | — |
| P37 | Clôture | Fin de l'inscription libre (le responsable peut toujours inscrire un membre, même le jour J) | date et heure | **la veille de la 1re période, à 12h** | Obl. (pré-rempli) | C, I | P11 |
| P38 | Inscription par période | Un joueur peut ne venir qu'à une soirée | à tout le tournoi ; période par période | **période par période** (question posée seulement s'il y a plusieurs périodes) | Fac. | C, puis figé à la 1re inscription | P59 (classement à la moyenne), P63 |
| P39 | Maximum de participants, par période | Éviter trop de repos | nombre, ou sans limite | **Calc. : T × 2 × k_max** | Calc. modifiable | C, I | P15, P20, P22 |
| P40 | Minimum pour maintenir la période | Alerte avant le jour J | nombre | **Calc. : 2 × k_min**, et au moins 2 × q joueuses | Av. | C | P20, P21, P23 |
| P41 | Liste d'attente | Au-delà de P39 | oui ; non | **oui** ; promotion automatique dans l'ordre d'arrivée jusqu'à la clôture, puis manuelle ; message à la personne promue | Fac. | C, I | P39 |
| P42 | Désistement par le joueur | — | jusqu'à une date limite | **jusqu'au début de la période** ; message au responsable après la clôture | Av. | C | P37 |
| P43 | Qui peut s'inscrire | — | membres du club pour la saison P3 | — | Fixe [Décidé D74, D75] | — | P2, P3 |
| P44 | Catégorie de jeu demandée | Nécessaire au tirage | — | **obligatoire à l'inscription si q > 0 ou si P25 n'est pas « tous » ; sinon non demandée** | Calc. | — | P23, P25 ; [Décidé D65] |

Exemples de calcul pour P39 :
- 6×6 sur 2 terrains : 2 × 2 × 8 = **32** ;
- 4×4 sur 3 terrains : 3 × 2 × 5 = **30**.

Note sur P44 : sans contrainte de mixité, ne pas demander la catégorie respecte la minimisation voulue par D65.

**Affichage pendant les inscriptions (calculé, pour le responsable seulement)** :
- nombre d'inscrits et de joueuses ;
- équipes par tour, terrains utilisés ;
- joueurs au repos par tour ;
- matchs attendus par joueur ;
- alerte de faisabilité de la mixité (voir §3).

### Étape 6. Tirage (réglages avancés, sauf P46 et P58)

**Ordre de priorité fixe**, non exposé :
1. tailles d'équipe ;
2. minimum de joueuses ;
3. équité des repos ;
4. équilibre des niveaux ;
5. éviter les mêmes coéquipiers ;
6. éviter les mêmes adversaires.

| # | Paramètre | Rôle | Valeurs | Défaut | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P45 | Répartition des joueuses | Les distribuer également, avec un écart d'au plus 1 entre équipes | — | toujours actif | Fixe | — | P23 |
| P46 | Équilibrer les niveaux | Des équipes de force proche | oui ; non | **oui** | Fac. | C, EC | P47, P48 |
| P47 | Niveau d'un joueur | Source du niveau | déduit de la **poule de son équipe dans la phase en cours** du championnat (A = 4, B = 3…) | meilleure poule si le joueur a plusieurs équipes ou clubs ; **niveau médian** s'il n'a pas d'équipe ; poule de la saison précédente si la phase n'a pas encore de poules | Av. | Calc. à J | P3 ; données du championnat |
| P48 | Niveau ajusté par le responsable | Corriger un cas (joueur sans équipe, retour de blessure) | niveau A à D, pour ce tournoi seulement | aucun | Fac. | I, J, EC | jamais affiché aux autres joueurs |
| P49 | Éviter les mêmes coéquipiers | Brassage | oui ; non | **oui**, en minimisant les répétitions sans les interdire (voir la note) | Av. | C, EC | P46 |
| P50 | Éviter les mêmes adversaires | Brassage | oui ; non | **oui**, priorité plus basse | Av. | C, EC | — |
| P52 | Surnombre | Ce qu'on fait des joueurs en trop | remplaçants d'abord, jusqu'à k_max, puis repos ; repos d'abord | **remplaçants d'abord** | Av. | C, EC | P22 |
| P53 | Roulement des repos | Qui se repose | celui qui a joué le plus de matchs, puis au sort ; jamais deux repos de suite si on peut l'éviter ; calcul séparé pour joueuses et joueurs | ce fonctionnement | Av. (fixe en v1) | — | P52 |
| P54 | Priorité aux retardataires | Rattraper les matchs manqués | oui ; non | **oui**, conséquence directe de P53 | Av. | C | P53 |
| P55 | Moment du tirage | — | tour par tour, **un tour à l'avance** ; toute la période à l'avance | **un tour à l'avance** : le tour n + 1 est tiré quand le tour n commence, sur la liste des présents à ce moment, et retiré si le pointage change avant son début | Av. | C | P58 |
| P56 | Publication du tirage | — | automatique ; après validation | **automatique**, retouche possible (échanger deux joueurs) tant que le tour n'a pas commencé | Av. | C, EC | P55 |
| P57 | Graine du tirage | Refaire le tirage, l'expliquer en cas de contestation | — | conservée | Calc. | — | — |
| P58 | Pointage des présents | Qui est tiré | case par joueur, avec un bouton « tous présents » | **obligatoire avant le 1er tirage de chaque période** ; arrivées et départs pointés en cours | Obl. | J, EC | P53, P55 |

Notes :
- **P47** : la règle 7J du BLVB confirme que le groupe A est le plus fort (« Le joker ne doit pas provenir du groupe A pour renforcer une équipe du Groupe B »).
- **P49, répétitions inévitables** : un joueur gagne k − 1 coéquipiers par tour. Il peut rencontrer P − 1 personnes différentes. Les répétitions deviennent donc inévitables après ⌊(P − 1) / (k − 1)⌋ tours. Exemple : 24 joueurs en 6×6, ⌊23/5⌋ = **4 tours**. Sur 15 tours, il y aura forcément des répétitions.
- **P55, pourquoi un tour à l'avance** : le tirage ne dépend pas des résultats. On peut donc tirer à l'avance et annoncer les équipes pendant le tour précédent, sans attendre la saisie des scores.

### Étape 7. Classement individuel

| # | Paramètre | Rôle | Valeurs | Défaut | Statut | Moment | Dépendances |
|---|---|---|---|---|---|---|---|
| P59 | Critère principal | — | **pourcentage de victoires** (nul = ½) ; nombre de victoires | **pourcentage**, affiché « 7 victoires sur 10 matchs ». C'est la « moyenne si le nombre de matchs est inégal » ; quand tous ont joué autant, il revient au même que le nombre de victoires | Av. | C, F | P30, P38 |
| P60 | 1er départage | — | différence de points moyenne par match, plafonnée | ce critère | Av. | C, F | P61 |
| P61 | Plafond de la différence de points | Un 25-5 ne décide pas tout | 0 (sans plafond) à 25 | **10 points par match** | Av. | C, F | P26 |
| P62 | Départages suivants | — | points marqués en moyenne par match, puis ex aequo ou tirage au sort | **points marqués, puis ex aequo** (même rang) | Av. | C, F | — |
| P63 | Minimum de matchs pour être classé | Éviter qu'un joueur venu pour 2 matchs gagnés finisse premier | nombre | **Calc. : ⌈tours du tournoi ÷ 3⌉**, soit 5 pour 15 tours | Calc. modifiable | C, F | P33, P38 |
| P64 | Classement à la fin de chaque période | Publier un état intermédiaire | oui ; non | **oui** | Av. | C | — |
| P65 | Classement par catégorie | Prix pour les joueuses et pour les joueurs | non ; oui | **non** | Av. | C | **risque au regard de D65**, question 2 |

Notes :
- **P59 à P62 figés après le 1er résultat** : je le propose pour éviter les contestations.
- **P61** : un match au temps de 12 min donne environ 15 à 20 points par équipe (estimation, pas une mesure).
- **P63** : les joueurs sous ce minimum apparaissent à part, avec leurs résultats.
- **Score avec points de départ** (seulement si P24 = c) : on saisit le score final, retard de départ inclus, et la différence de points se calcule dessus.
- Le classement reste bruité : le présenter comme un jeu (voir `formules-tournoi.md` §3.12).

### Étape 8. Absences, retards, blessures

Ce sont surtout des règles de fonctionnement, avec deux réglages.

| # | Situation | Comportement par défaut | Réglage |
|---|---|---|---|
| P66 | Arrivée en cours de période | Le joueur est pointé et entre au prochain tirage (ou à celui d'après si le tour n + 1 est déjà commencé) ; il est prioritaire pour jouer (P54) | — |
| P67 | Départ anticipé | Le joueur est dépointé et n'est plus tiré ; ses matchs comptent ; il est classé s'il atteint P63 | — |
| P68 | Joueur pointé mais absent au début du tour | Le responsable échange avec un joueur au repos (retouche P56), sinon l'équipe joue en sous-nombre | — |
| P69 | Blessure ou départ pendant un match | **Le match continue en sous-nombre** ; un joueur au repos peut entrer, de la même catégorie si possible | Av. : remplacement autorisé oui (défaut) ou non |
| P70 | Pour qui compte le match | **Pour les joueurs tirés** ; le remplaçant entré en cours de match ne le compte pas, mais garde sa priorité de jeu | Av. |
| P71 | Équipe qui passe sous k_min ou sous le minimum de joueuses en cours de match | **Pas de forfait** : on finit le match. Le règlement BLVB, lui, prévoit le forfait d'une équipe réduite à 4 (règles 2J et 6J), mais en mêlée la composition est imposée | — |
| P72 | Score non saisi à la fin du tour | **Le tour suivant se tire quand même** (le tirage ne dépend pas des résultats). Le responsable saisit plus tard, jusqu'à la clôture du tournoi | — |
| P73 | Correction d'un score | Par le responsable jusqu'à la clôture du tournoi, le classement est recalculé | — |

P72 compte beaucoup : le responsable est souvent aussi joueur (question 4).

---

## 3. Cas limites : calculs et comportement

**Calcul du tour**, avec P présents, F joueuses et T terrains :
- matchs **M = min(T, ⌊P / (2 × k_min)⌋)**, réduit à ⌊F / (2q)⌋ si P24 = (b) ;
- équipes **E = 2M** (toujours un nombre pair) ;
- joueurs placés = min(P, E × k_max), le reste au repos ;
- matchs attendus par joueur ≈ N × placés / P.

| Cas | Exemple vérifié | Comportement par défaut | Alternative |
|---|---|---|---|
| Nombre de joueurs qui n'est pas un multiple de k | 6×6, 2 terrains, P = 22 : M = min(2, ⌊22/10⌋ = 2) = 2, donc 4 équipes de 6, 6, 5 et 5 | Sous-nombre jusqu'à k_min, puis remplaçants jusqu'à k_max | Repos d'abord (P52) |
| Trop de joueurs | 6×6, 2 terrains, P = 34 : 4 équipes de 8 (6 sur le terrain + 2 remplaçants), 2 au repos | Roulement des repos (P53) | Plafond P39 et liste d'attente |
| Nombre impair d'équipes possibles | 6×6, 2 terrains, P = 19 : ⌊19/10⌋ = 1, donc **un seul match**, 2 équipes de 8 et 3 au repos | Alerte : « avec 1 joueur de plus, 2 matchs » ; proposer le 4×4 | k_min = 4 ce jour-là, soit 4 équipes de 5, 5, 5 et 4 |
| Pas assez de joueuses | 6×6, 2 terrains, P = 24, F = 6, q = 2. (a) 4 équipes avec 2, 2, 1 et 1 joueuses, **24 placés**. (b) M = min(2, ⌊6/4⌋ = 1) = 1, 2 équipes de 8, **8 au repos** | (a), avec une alerte au responsable | Baisser q pour cette période (P23 modifiable en cours) |
| Même cas en 4×4 | 4×4, 3 terrains, P = 26, F = 9. Avec q = 2 : il faudrait 12 joueuses pour 6 équipes ; (a) donne 2, 2, 2, 1, 1, 1 ; (b) donne M = ⌊9/4⌋ = 2, soit 4 équipes, 20 placés, **6 au repos**. Avec q = 1 : 6 équipes de 5, 5, 4, 4, 4, 4, tout le monde joue | (a), d'où le défaut q = 1 en 4×4 | — |
| Aucune joueuse présente alors que q > 0 | — | Alerte : la mêlée se joue sans mixité pour cette période | Annuler la période |
| Un seul terrain | 6×6, P = 20 : 2 équipes de 8, 4 au repos par tour ; 16 / 20 = 80 % sur la feuille, 12 / 20 = 60 % sur le terrain | Alerte au-delà de 2 × k_max ; conseiller des tours courts (8 à 10 min) pour que l'attente reste brève | 4×4 sur deux terrains en travers, si le gymnase le permet |
| Trop peu d'inscrits | moins de 2 × k_min (10 en 6×6) | Alerte à la clôture (P40) : annuler ou passer en 4×4 | — |
| Terrain perdu en cours (poteau, filet) | T passe de 3 à 2 | P15 modifié en cours ; le tour suivant est retiré avec moins d'équipes | — |
| Match aux points qui s'éternise | tous les terrains attendent | Alerte à la création si P26 = « aux points » : conseiller l'hybride | — |

---

## 4. Ce qu'on calcule au lieu de le demander

- Club (P2) et saison (P3).
- Titre pré-rempli (P1) et résumé des règles (P5).
- Tours par période et horaires (P32 à P35).
- Places maximales (P39) et minimum pour jouer (P40).
- Catégorie de jeu exigée ou non (P44).
- Niveau de chaque joueur (P47).
- Équipes, terrains utilisés, repos, matchs attendus par joueur.
- Faisabilité de la mixité.
- Minimum de matchs pour être classé (P63).
- Graine du tirage (P57).

## 5. Ce qu'on cache derrière des valeurs par défaut (réglages avancés)

- **Temps** : P16 à P19, P31.
- **Équipes** : P21, P22, P24, P25, P52, P53, P54.
- **Score** : P29, P30.
- **Tirage** : P49, P50, P55, P56.
- **Classement** : P59 à P62, P64, P65.
- **Absences et blessures** : P42, P69, P70.

**Reste visible : environ 12 champs.**
- P1 Titre.
- P11 à P15 pour chaque période : date, heures, lieu, terrains.
- P20 Format.
- P23 Minimum de joueuses.
- P26 et P27 Type de score et durée de jeu.
- P37 Clôture des inscriptions.
- P38 Inscription par période (seulement s'il y a plusieurs périodes).
- P46 Équilibrer les niveaux.

## 6. Parcours suggéré à l'ergonome [Proposition]

1. **Le tournoi** : titre et description.
2. **Quand et où** : périodes répétables, avec un bouton « ajouter une période » qui recopie la précédente.
3. **Le jeu** : format, minimum de joueuses, type de score et durée de jeu. Un **encadré calculé** affiche tours par période, heure de fin et places.
4. **Inscriptions** : clôture, maximum pré-rempli, inscription par période.
5. **Vérifier et publier** : récapitulatif, avec les réglages avancés repliés et regroupés par thème (temps, équipes, tirage, classement).

**Le jour J**, dans l'ordre :
1. pointage ;
2. tirage du tour 1 ;
3. tour en cours (terrains, équipes, minuteur) ;
4. saisie des scores (un champ par terrain) ;
5. classement.

Les paramètres modifiables en cours sont accessibles depuis l'écran du tour : terrains, durée, nombre de tours, minimum de joueuses.

**Équipes affichées aux participants** : noms seulement, sans marque de catégorie ni de niveau (D65). Les compteurs restent visibles du responsable seul.

---

## 7. Questions à trancher

1. **Saison des membres** : pour une mêlée de fin juin ou de début septembre, quels membres peuvent s'inscrire, ceux de la saison de la date du tournoi ou de la saison en cours sur le site ? Je propose la saison qui contient la date de la 1re période. Cela suppose que les membres de la nouvelle saison soient déjà repris (reprise D75 depuis les compositions d'équipe).
2. **D65 et l'affichage** : confirmez-vous que la catégorie n'apparaît jamais aux participants (équipes sans compteurs) et qu'il n'y a **pas de classement par catégorie** en v1 ? Un classement « meilleure joueuse » la rendrait visible des membres du club, ce qui va au-delà de D65.
3. **Niveau déduit du championnat** : accepte-t-on d'utiliser la poule de l'équipe du joueur pour équilibrer, en ne la montrant qu'au responsable ? Que faire d'un membre sans équipe : niveau médian par défaut, ou niveau saisi par le responsable ?
4. **Saisie quand le responsable joue** : la saisie après coup (P72) suffit-elle en v1, ou faut-il avancer les « membres choisis pour la saisie » que D74 renvoie à plus tard ?
5. **Mêlée réservée à une catégorie** (P25) : la propose-t-on en v1 ? D74 ne parle que du minimum de joueuses.
6. **Minimum de joueuses par défaut en 4×4** : 1 (faisable dans un club loisir), ou 2 comme la FFVB ?
7. **Joueuses en nombre insuffisant** : confirmez-vous « répartir au mieux et prévenir », sans sanction ni points de départ ?
8. **Classement** : pourcentage de victoires plafonné et minimum de matchs (P59 à P63), ou un barème à points comme au championnat (3, 2, 1, 0, règle 1D) ?
9. **Minuteur** : le site doit-il fournir un minuteur commun avec un signal, ou le responsable chronomètre-t-il lui-même ? Le format au temps en dépend.

**Fichiers lus** :
- `/home/amoul/projects/blvb/docs/espace-club/formules-tournoi.md`
- `/home/amoul/projects/blvb/docs/wizard-saison/decisions.md` (D65, D74, D75)
- `/home/amoul/projects/blvb/src/Reglement/Reprise/reglement-2025.html` (règles 1J à 7J, 1R)
