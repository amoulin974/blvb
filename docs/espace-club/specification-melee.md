# Espace club : spécification technique de la mêlée (première version)

Périmètre : D74 à D81 (`docs/wizard-saison/decisions.md`). Parcours : `parcours-melee-v2.md` et `parcours-melee-v3.md` ;
calcul : `calcul-melee.md` et `calcul-melee-complement.md` ; maquette fil de fer : voir `README.md`. Rédigée le 16/11/2026,
**à faire relire par l'agent `securite` avant tout développement**. Rien n'est réalisé.

## 1. Principes

- **Trois briques, dans cet ordre** : les membres d'un club (D75), la catégorie de jeu (D65), la mêlée. Chacune se livre et
  se fusionne seule.
- **Rien n'est public** : une mêlée n'est vue que des membres du club, de ses participants, du responsable du club et des
  admins (D74). Elle n'écrit jamais dans `Partie`, `Classement` ni dans les pages du championnat.
- **Le programme n'est pas stocké, il se recalcule** : on enregistre les réglages et leur état (par défaut, fixé, calculé),
  pas les scénarios. Les scénarios, le verdict « qui joue », le programme égal et les propositions du soir sortent d'un
  service de calcul pur, sans base (comme `EtatSet`).
- **Le tirage est stocké** (équipes de chaque tour), avec sa graine : il doit pouvoir être relu et expliqué.
- **Le site propose, le responsable décide** : aucune proposition (programme du soir, retard, terrains variables) ne
  s'applique seule (D78, D80).
- **Enregistrement par étape** du brouillon, comme le wizard de saison.

## 2. Données

### Brique 1 : `membre_club` (D75)

| Champ | Type | Règle |
|---|---|---|
| `id` | int | |
| `club_id` | FK `club` | |
| `saison_id` | FK `saison` | |
| `joueur_id` | FK `joueur` | unique avec `club_id` et `saison_id` ; un joueur peut être membre de plusieurs clubs |
| `origine` | chaîne | `equipe` (repris d'une composition) ou `manuel` (ajouté par le responsable) |

- **Reprise** (migration ou commande) : chaque `MembreEquipe` dont l'équipe a un club donne un `membre_club` (club de
  l'équipe, saison de la composition, `origine = equipe`).
- **Entretien** : ajouter un joueur à la composition d'une équipe du club crée le membre s'il manque ; le retirer d'une
  équipe ne retire pas le membre (le responsable le retire à la main, sauf s'il est encore dans une équipe du club).
- **Écran** : sur « Mon club », section « Membres » (liste, ajouter un joueur existant ou créer une fiche, retirer).

### Brique 2 : catégorie de jeu (D65)

- `joueur.categorie_jeu` : `feminine`, `masculine` ou vide. Facultative sur la fiche ; demandée à l'inscription à une mêlée
  mixte, réservée ou en deux tableaux, et saisissable par le responsable pour un membre de son club.
- Visible du joueur, du responsable de ses clubs et des admins ; **rendue visible aux membres du club et aux participants**
  par une mêlée réservée, en deux tableaux ou avec classement par catégorie (D77, D78) : l'inscription le dit avant le bouton.

### Brique 3 : la mêlée

**`melee`**

| Champ | Type | Règle |
|---|---|---|
| `id`, `club_id`, `saison_id` | | saison = celle qui contient la première date (H1) |
| `titre`, `mot_accueil`, `regles_particulieres` | texte | titre 80 caractères ; textes libres échappés à l'affichage |
| `etat` | chaîne | `brouillon`, `publiee`, `inscriptions_closes`, `en_cours`, `terminee` |
| `etape` | int | étape du parcours de création (brouillon) |
| `format` | int | joueurs sur le terrain : 6, 4, 3, 2 |
| `mixite` | chaîne | `mixte`, `ouverte`, `feminine`, `masculine`, `deux_tableaux` |
| `min_joueuses` | int | 1 à `format`, en mode `mixte` seulement |
| `classement_par_categorie` | bool | mode `mixte` seulement |
| `equilibrer_niveaux` | bool | défaut vrai |
| `reglages` | JSON | voir ci-dessous |
| `fin_inscriptions` | date et heure | avant le début de la première date |
| `inscription_par_date` | bool | |
| `liste_attente` | bool | |
| `cree_par_id`, `modifie_le` | | |

`reglages` (schéma strict, clés inconnues refusées), chaque valeur avec son état `defaut`, `fixe` ou `calcule` :
`objectif` (`egal`, `longs`, `nombre`), `duree_match` (8 à 20, défaut 10), `changement_equipe` (3 à 10, défaut 5),
`joueurs_en_trop` (`repos` ou `remplacants`, **par tableau** en deux tableaux), `fin_match` (`temps` ou `temps_ou_points`
avec `points`), `egalite` (`point_en_or` ou `nul`), `accueil`, `pause`, `cloture`, `marge`, `estimation` (joueurs, joueuses),
`max_inscrits` (par tableau en deux tableaux), `hauteur_filet` (une valeur, ou une par tableau), `repartition_terrains`
(`fixe`, `groupes`, `repartis`), `eviter_coequipiers`, `eviter_adversaires`, `plafond_ecart` (10), `min_matchs_classement`.

**`melee_date`** : `melee_id`, `date`, `ouverture`, `fermeture`, `lieu_id`, `terrains` (1 à 6) et `terrains_origine`
(`creneau` ou `fixe`), `tours` (calculé ou fixé), `etat` (`a_venir`, `en_cours`, `terminee`), `debut_reel`.

**`melee_inscription`** : `melee_id`, `joueur_id` (membre du club pour la saison), `melee_date_id` (une ligne par date si
inscription par date), `tableau` (`joueuses`, `joueurs` ou vide), `rang_attente` (vide si inscrit), `inscrit_par_id`,
`inscrit_le`, `present` (pointage), `niveau_ajuste` (vide, ou A à D pour cette mêlée), unique par joueur et par date.

**`melee_tour`** : `melee_date_id`, `numero`, `graine`, `etat` (`tire`, `en_cours`, `termine`), `debut_reel`,
`duree_match` et `terrains_joueuses` / `terrains_joueurs` appliqués à ce tour (ils peuvent changer en cours).

**`melee_match`** : `melee_tour_id`, `terrain`, `tableau`, `score_a`, `score_b` (vides tant que non saisis), `saisi_par_id`.

**`melee_place`** : `melee_match_id` (vide pour un joueur au repos : alors `melee_tour_id`), `inscription_id`, `equipe`
(`a` ou `b`), `remplacant` (bool). Une ligne par joueur et par tour.

## 3. Calcul du programme (service pur, sans base)

`ProgrammeMelee::scenarios(reglages, dates, effectifs)` rend, pour chaque date : le verdict « qui joue à chaque tour », les
trois cartes, le programme égal s'il existe, les conflits et leurs options, et en deux tableaux les trois répartitions des
terrains. Règles : `calcul-melee.md` §1 à §3 et `calcul-melee-complement.md` §1 à §5, avec les décisions :

- 10 min imposées à la seule carte conseillée tant que la durée est « par défaut » (D80) ; changement d'équipe jamais
  parcouru (D78), proposé seulement en dernière option d'un conflit ;
- programme égal : nombre de tours multiple du cycle, durée la plus proche de 10 min sur le bouton, l'autre en variante
  (D81) ; absent quand il n'existe pas ;
- de 16 à 19 joueurs en 6 contre 6 avec un terrain vide : 4 contre 4 mis en avant ; équipes de 3 admises en 4 contre 4 (D81) ;
- terrains variables entre tableaux proposés à partir de 2 matchs d'écart, avec l'explication ; tours groupés en fin de
  soirée par défaut (D81) ;
- retard : alerte seulement si la salle serait rendue après la fermeture ; raccourcir avec 5 min de réserve ; heure de fin
  modifiable (D81).

Terrains pré-remplis (D76) : capacité minimale des créneaux du lieu qui couvrent la plage, moins les matchs du championnat
programmés ce jour-là dans ce gymnase ; alerte si le gymnase est fermé (D60).

Le même service sert à la création, à la clôture des inscriptions, au pointage et en cours de soirée. **Tests unitaires** sur
les exemples chiffrés des deux documents de calcul (24, 26, 28, 30 joueurs ; 13 à 19 ; exemples A à F des deux tableaux).

## 4. Tirage

`TirageMelee::tirer(tour, présents, historique, graine)` : priorités fixes (tailles d'équipe, minimum de joueuses ou
tableau, équité des repos, équilibre des niveaux, coéquipiers déjà vus, adversaires déjà vus). Méthode : plusieurs milliers
de répartitions aléatoires issues de la graine, on garde la meilleure ; résultat reproductible avec la même graine.
Roulement des repos : ceux qui ont le plus joué se reposent, puis celui dont le repos est le plus ancien ; un roulement par
tableau. Niveau : poule de l'équipe du joueur dans la phase en cours (la meilleure s'il en a plusieurs), niveau médian
sinon, remplacé par `niveau_ajuste`. Le tour suivant est tiré un tour à l'avance et retiré si le pointage change avant son
début. Échange de deux joueurs possible tant que le tour n'a pas commencé, à l'intérieur d'un tableau.

## 5. Classement individuel

Calculé, jamais stocké : pourcentage de victoires (égalité = demi-victoire si elle est admise), puis écart de points moyen
par match plafonné, puis points marqués en moyenne, puis ex æquo. Un remplaçant compte le match de son équipe. Joueurs sous
le minimum de matchs listés à part. Vues : générale ; par catégorie si l'option est activée ; par tableau en deux tableaux.

## 6. Droits (voter `MeleeVoter`)

| Action | Qui |
|---|---|
| Créer, modifier, publier, pointer, tirer, saisir les scores, clôturer | responsable du club **pour la saison de la mêlée**, admin |
| Voir la mêlée publiée, les inscrits, les équipes, le classement | membres du club pour cette saison, participants, responsable, admin |
| S'inscrire, se désinscrire | membre du club pour cette saison, pour lui-même, jusqu'à la fin des inscriptions (désinscription jusqu'au début de la date) |
| Inscrire ou retirer un membre | responsable, admin, à tout moment avant la fin de la date |
| Tout le reste (visiteur, membre d'un autre club) | **404**, jamais 403 : l'existence d'une mêlée ne se devine pas |

- Références directes contrôlées : une date, une inscription, un tour, un match sont toujours cherchés **dans la mêlée de
  l'adresse**.
- Écritures : jeton CSRF ; JSON pour le jour J (pointage, tirage, scores) avec 401 si la session a expiré, comme la page du
  match ; file d'attente des saisies côté téléphone réutilisée.
- Réponses `private, no-store`. Aucune donnée de mêlée dans les pages publiques, l'agenda, les API du championnat.
- Niveau et, hors des trois cas de D77 et D78, catégorie de jeu : jamais envoyés au navigateur d'un participant (ni dans le
  HTML ni dans le JSON).

## 7. Messages

Courriels (file existante) : place libérée en liste d'attente ; date, heure ou lieu modifiés ; mêlée annulée ; classement par
catégorie ou deux tableaux ajoutés après des inscriptions (D80) ; alerte au responsable à la clôture si l'effectif minimal
manque.

## 8. Données personnelles

Catégorie de jeu (D65) : finalité, visibilité selon le mode de la mêlée, à écrire dans les conditions d'utilisation avant
tout usage réel. Mêlées et inscriptions supprimées avec la même règle que les pages du match (saison N supprimée une fois
N+1 terminée) : commande de purge à étendre. Pas d'invités sans compte dans cette version.

## 9. Ordre de réalisation

1. **Membres du club** : table, reprise, écran « Membres » de « Mon club », tests des droits.
2. **Catégorie de jeu** : champ, fiche du joueur, saisie par le responsable, tests de visibilité.
3. **Calcul du programme** : service pur et ses tests unitaires (aucun écran).
4. **Création d'une mêlée** : les 5 étapes, brouillon, publication ; composants partagés (cartes de programme, bloc « Qui
   joue », encadré de conflit, barre de résumé).
5. **Inscriptions** : page du membre, liste d'attente, messages.
6. **Jour J** : pointage et programme du soir, tirage, tour et compte à rebours, scores, retard.
7. **Classement** ; **deux tableaux** et terrains variables (peuvent suivre en second lot si la première mise en service se
   fait en mêlée mixte).
8. Relectures ergonome, webdesigner, sécurité à chaque brique ; essai sur une vraie soirée avant de généraliser.

## 10. Questions tranchées (D82)

- Classement par catégorie : un joueur peut s'en retirer en restant au classement général (`melee_inscription.hors_categorie`,
  case à l'inscription et sur sa page de la mêlée).
- Membres : le responsable peut créer la fiche d'un adhérent sans compte ; il l'inscrit lui-même.
- Purge : même règle que Volley Stats.
- 2,35 m : choix du championnat, libellé « hauteur mixte ».
- Passage en deux tableaux d'une mêlée « ouverte à tous » après des inscriptions : le message aux inscrits leur demande de
  choisir leur tableau ; le responsable peut le faire à leur place. Un inscrit sans tableau n'est pas tiré.
