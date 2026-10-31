# Volley Stats : toutes les situations de jeu et leurs conséquences

Point de vue d'un joueur et d'un analyste de haut niveau (« scout »), adapté au championnat BLVB. But : que l'interface de
saisie sache traiter **toutes** les situations d'un match, sans en inventer à la volée au bord du terrain. Rédigé le
31/10/2026 pour l'analyse des besoins (`analyse.md`, D66) ; rien n'est encore décidé sur l'interface elle-même.

## 1. Principes

- **On ne saisit que son équipe.** Les actions de l'adversaire n'apparaissent que par leur effet : un ace adverse est une
  réception ratée chez nous (ou un « ace subi » sans joueur si personne n'a touché le ballon), une faute adverse donne un
  point sans action d'un de nos joueurs.
- **Chaque échange se termine par un seul point**, pour nous ou pour l'adversaire, sauf la balle à rejouer (aucun point).
- **Rotation** : quand l'équipe qui reçoit gagne le point, elle récupère le service et tourne d'un poste (« side-out »).
  Gagner le point sur son propre service ne fait pas tourner. L'interface en déduit le serveur et la place de chacun.
- **Barème des notes**, celui des logiciels d'analyse professionnels (Data Volley), réutilisé pour toutes les
  compétences :

| Note | Sens général | Exemple en réception |
|:-:|---|---|
| `#` | parfait, ou point direct | passeur servi à la main, toutes les attaques possibles |
| `+` | positif | passeur un peu déplacé, au moins deux attaquants possibles |
| `!` | jouable, sans avantage | passe haute obligée vers un seul attaquant |
| `-` | négatif | ballon à peine jouable, relance « cadeau » à l'adversaire |
| `/` | très mauvais, renvoyé chez l'adversaire | réception qui repasse directement le filet |
| `=` | faute, point perdu | ace subi, ballon dehors |

  Le **mode complet** peut proposer ces six notes, ou une version à quatre niveaux pour des bénévoles (`#`, `+`, `-`, `=`) :
  à trancher en atelier ergonomie. Le **mode rapide** ne garde que ce qui termine l'échange (`#` qui marque, `=` qui perd).

- **Colonnes des tableaux** : *Échange* = continue ou se termine ; *Point* = pour qui ; *Crédité* = joueur ou équipe à qui
  revient l'action dans les stats ; *Mode* = R (rapide), C (complet).

## 2. Service (notre équipe sert)

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Ace : ballon au sol chez l'adversaire, ou touché sans pouvoir être rejoué | fin | nous | serveur | R C | `#` |
| Service gagnant indirect : réception adverse renvoyée directement, on marque sur le retour | continue | — | serveur `/` | C | le point va à l'action suivante (attaque, block) |
| Service qui met en difficulté : l'adversaire n'a qu'une attaque possible | continue | — | serveur `+` | C | |
| Service en jeu, sans effet | continue | — | serveur `!` | C | en rapide, rien à saisir |
| Service facile : l'adversaire construit librement | continue | — | serveur `-` | C | |
| Faute de service : filet, dehors, antenne | fin | adversaire | serveur `=` | R C | |
| Faute de pied : ligne de fond mordue, ou service hors de la zone | fin | adversaire | serveur `=` | R C | type « faute de règlement » possible |
| Service non frappé dans les 8 secondes | fin | adversaire | serveur `=` | C | règlement BLVB |
| Écran : les joueurs au service masquent le serveur | fin | adversaire | équipe | C | faute collective |
| Mauvais serveur (ordre de rotation non respecté) | fin | adversaire | équipe | R C | l'interface doit le prévenir : elle connaît le serveur attendu |

## 3. Réception (l'adversaire sert)

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Réception parfaite | continue | — | réceptionneur `#` | C | |
| Réception positive | continue | — | réceptionneur `+` | C | |
| Réception jouable, une seule attaque possible | continue | — | réceptionneur `!` | C | |
| Réception négative, relance obligée | continue | — | réceptionneur `-` | C | |
| Réception renvoyée directement chez l'adversaire | continue | — | réceptionneur `/` | C | souvent suivie d'un point adverse |
| Ace subi : ballon touché, injouable ou dehors | fin | adversaire | réceptionneur `=` | R C | |
| Ace subi sans contact (ballon tombé entre deux joueurs) | fin | adversaire | équipe, ou joueur de la zone | R C | « personne » doit être possible |
| Faute de service adverse | fin | nous | équipe adverse | R C | aucun de nos joueurs crédité |
| Réception en double touche ou portée (sifflée) | fin | adversaire | réceptionneur `=` | C | règle BLVB à vérifier : touches en réception |
| Faute de position à la réception (joueurs mal placés au moment du service adverse) | fin | adversaire | équipe | C | |
| Contre du service adverse | fin | adversaire | joueur | C | interdit au BLVB : « un service ne peut pas être contré » |

## 4. Passe (deuxième touche)

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Passe parfaite : attaquant face à un block seul ou à aucun | continue | — | passeur `#` | C | |
| Passe bonne | continue | — | passeur `+` | C | |
| Passe jouable, attaquant gêné | continue | — | passeur `!` | C | |
| Passe mauvaise : l'attaquant ne peut que remettre | continue | — | passeur `-` | C | |
| Passe qui repasse le filet | continue | — | passeur `/` | C | |
| Faute de main : double touche, balle tenue | fin | adversaire | passeur `=` | R C | |
| Passe dehors ou dans le filet, ballon perdu | fin | adversaire | passeur `=` | R C | |
| **Passe décisive** : passe suivie d'un point d'attaque | — | — | passeur | C | statistique déduite, pas une saisie : sert au « meilleur passeur » |
| Deuxième touche offensive du passeur (feinte, attaque) | selon l'issue | | passeur, comme une attaque | R C | à saisir comme une attaque |
| Passe faite par un autre joueur que le passeur | continue | — | ce joueur | C | |

## 5. Attaque

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Attaque gagnante : ballon au sol, ou block-out (touche du block puis dehors) | fin | nous | attaquant `#` | R C | |
| Attaque qui met en difficulté : défense adverse à peine jouable | continue | — | attaquant `+` | C | |
| Attaque défendue | continue | — | attaquant `!` | C | |
| Attaque reprise par notre couverture après le block adverse | continue | — | attaquant `-` | C | voir couverture (§8) |
| Attaque contrée : block adverse gagnant | fin | adversaire | attaquant `/` | R C | compte comme une attaque perdue |
| Faute d'attaque : dehors, filet, antenne | fin | adversaire | attaquant `=` | R C | |
| Attaque d'un joueur arrière en zone avant, ballon au-dessus du filet | fin | adversaire | attaquant `=` | C | faute de règlement |
| Attaque directe du service adverse en zone avant, au-dessus du filet | fin | adversaire | attaquant `=` | C | faute de règlement |
| Attaque dans l'espace adverse (ballon frappé de l'autre côté) | fin | adversaire | attaquant `=` | C | règles du filet BLVB (anciennes règles) |
| Feinte ou balle placée gagnante | fin | nous | attaquant `#` | R C | même chose qu'une attaque gagnante ; type « feinte » facultatif |

## 6. Block (contre)

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Block gagnant : ballon au sol chez l'adversaire | fin | nous | bloqueur(s) `#` | R C | à deux ou trois : un seul crédité, ou chacun (règle à choisir) |
| Block qui ralentit, ballon repris par notre défense | continue | — | bloqueur `+` | C | |
| Touche de block, ballon repris par l'adversaire | continue | — | bloqueur `!` | C | |
| Block-out subi : ballon touché au block puis dehors | fin | adversaire | bloqueur `=` | R C | point pour l'attaquant adverse |
| Faute de filet au block | fin | adversaire | bloqueur `=` | R C | anciennes règles : tout contact avec le filet est fautif |
| Pénétration : pied ou autre partie du corps dans le camp adverse | fin | adversaire | joueur `=` | C | règlement BLVB |
| Block d'un joueur arrière | fin | adversaire | joueur `=` | C | |
| Block d'une attaquante (contre des filles) | fin | adversaire | bloqueur `=` | C | **règle 3J du BLVB** : interdit sauf entente avant le match ; option du match |
| Block dans l'espace adverse avant la frappe | fin | adversaire | bloqueur `=` | C | |

## 7. Défense (réception d'attaque)

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Défense parfaite, contre-attaque construite | continue | — | défenseur `#` | C | |
| Défense jouable | continue | — | défenseur `+` / `!` | C | |
| Défense renvoyée directement | continue | — | défenseur `/` | C | |
| Défense ratée : ballon au sol ou injouable | fin | adversaire | défenseur `=`, ou équipe | R C | « personne » possible (ballon entre deux joueurs) |
| Faute de main en défense (double, portée), si sifflée | fin | adversaire | défenseur `=` | C | |

## 8. Relance, couverture, ballons faciles

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Ballon facile envoyé chez l'adversaire (« free ball » donnée) | continue | — | joueur | C | souvent après une mauvaise réception |
| Ballon facile reçu de l'adversaire, bien joué | continue | — | joueur `#` / `+` | C | se note comme une réception |
| Couverture d'attaque réussie (ballon contré récupéré) | continue | — | joueur | C | |
| Quatre touches | fin | adversaire | équipe | R C | faute collective |
| Ballon qui touche le plafond ou un obstacle | fin | adversaire | dernier joueur | C | règle locale du gymnase possible |

## 9. Fautes et décisions d'arbitrage

| Situation | Échange | Point | Crédité | Mode | Notes |
|---|---|---|---|:-:|---|
| Faute de filet (hors block) | fin | adversaire | joueur | R C | |
| Pénétration sous le filet | fin | adversaire | joueur | R C | |
| Faute de position ou de rotation | fin | adversaire | équipe | R C | |
| Double faute (les deux équipes en même temps) | fin | **aucun** : balle à rejouer | — | R C | même serveur, score inchangé |
| Balle à rejouer : ballon d'un autre terrain, interférence, désaccord | fin | **aucun** | — | R C | |
| Point accordé après réclamation, ou décision corrigée | — | change de camp | — | R C | se traite en corrigeant le dernier point |
| Sanction avec point (retard de jeu, comportement) | fin | adversaire | équipe ou joueur | C | pas de carton au règlement 2025 : prévoir « sanction » générique |

## 10. Situations de match

| Situation | Effet | Notes |
|---|---|---|
| Composition de départ d'un set | 6 joueurs dans l'ordre des postes, premier service | contrôle des règles 1J et 4J plus tard (D66) |
| Remplacement | un joueur sort, un autre entre au même poste | pas de limite au règlement 2025 à vérifier ; pas de libéro (D66) |
| Remplacement exceptionnel (blessure) | même chose, motif « blessure » | règle 6J : à 5 joueurs, le match s'arrête |
| Set commencé à 5 joueurs | composition incomplète, un joueur peut entrer en cours de set | règle 5J ; si c'est une joueuse, règle 2J aux sets suivants |
| Départ à −8 | le set commence à 0–8 (ou 8–0) | **règle 1J** : 5 garçons pour 1 fille ; à saisir à la composition |
| Fin de set | 25 points **secs** (sans deux points d'écart), 5ᵉ set en 15 secs | règlement BLVB ; format de match par phase (D48) |
| Match interrompu | score arrêté, règle 1R | coupure, fermeture du gymnase, blessure à 4 |
| Forfait | match non joué ou abandonné | rien à saisir en stats ; le score officiel reste à part (D66) |
| Temps mort | ignoré dans la première version | D66 |
| Retour en arrière | annuler le dernier point, ou corriger une action ancienne | indispensable au bord du terrain |

## 11. Ce que cela impose à l'interface

1. **Un échange = une suite d'actions qui se termine par un point** (ou une balle à rejouer). En mode rapide, une seule
   saisie par échange : qui termine, comment. En mode complet, chaque touche de notre équipe, et l'interface propose la
   suite logique (après notre service : réception adverse invisible, donc défense ou block ; après une réception : passe…).
2. **« Personne » et « équipe »** doivent être des choix possibles : ballon entre deux joueurs, faute collective, faute de
   rotation.
3. **Point sans action de nos joueurs** : faute de service adverse, faute adverse, attaque adverse gagnante, block adverse.
4. **Balle à rejouer** : un échange sans point.
5. **Fautes de règlement** regroupées derrière une touche « Faute de… » pour ne pas encombrer l'écran principal.
6. **Options du match** : contre des filles autorisé ou non (3J), départ à −8 (1J), format de match (D48).
7. **Serveur attendu** toujours affiché, et alerte si la composition saisie ne correspond plus.
8. **Correction** : annuler le dernier point en une touche ; modifier une action plus ancienne depuis le journal.

## 12. Classements publics (page unique, D66)

Plusieurs classements sur la même page, chacun en nombre ou en pourcentage ; les pourcentages ne retiennent que les joueurs
au-dessus d'un minimum d'actions réglé par l'admin. *Formules proposées, à valider* :

| Classement | En nombre | En pourcentage | Mode |
|---|---|---|---|
| Meilleur serveur | aces | services sans faute ÷ services ; ou efficacité (aces − fautes) ÷ services | nombre : R C ; % : C |
| Meilleur passeur | passes décisives | passes `#` et `+` ÷ passes | C |
| Meilleur attaquant | attaques gagnantes | attaques gagnantes ÷ attaques ; ou efficacité (gagnantes − fautes − contrées) ÷ attaques | nombre : R C ; % : C |
| Meilleur réceptionneur | réceptions `#` | réceptions `#` et `+` ÷ réceptions | C |
| *Possibles aussi* : meilleur contreur, meilleur défenseur, solde, points par set | | | |

En mode rapide, seuls les classements « en nombre » de service et d'attaque sont calculables ; les autres ne classent que
les équipes en saisie complète (D66).
