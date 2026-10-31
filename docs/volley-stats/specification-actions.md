# Volley Stats : spécification de la saisie des actions (mode intermédiaire)

Deuxième brique. Décisions : D66, D68 ; parcours : `saisie-actions.md` ; maquette : `maquette-saisie-actions.md` ;
situations : `situations.md`. S'appuie sur la page du match (`specification-page-match.md`) sans en changer les principes :
journal d'événements par set, rien de stocké en double, droits de `FeuilleMatchVoter`, file d'attente des saisies (déjà
réalisée). Rédigé le 31/10/2026, relu par l'agent `securite` le même jour (constats intégrés, §3 bis, §5, §7, §9).

## 1. Principes

- **Une action = un événement du journal du set**, comme les points et les remplacements. Score et rotation restent
  calculés par `EtatSet` à partir des seuls événements `point`, `correction`, `rotation`, `remplacement` : la saisie des
  actions ne change rien au score.
- **Le point garde son événement** ; il dit en plus sur quoi il porte (`sur`) : l'action qui le termine, le service, ou une
  faute de règlement.
- **Un échange** = les événements entre deux points (ou une balle à rejouer). Il n'est pas stocké : il se déduit du journal.
- **Le service n'est jamais saisi** (D68) : il se déduit de la rotation (serveur au poste 1 quand nous servons).
- **Rien n'est public dans cette brique** : stats visibles de l'équipe et du responsable de son club (D68, précision du
  31/10/2026, à écrire dans les conditions d'utilisation) ;
  les classements publics, avec l'accord de chaque joueur (D66), viendront dans la brique suivante.

## 2. Données

### Nouvelle colonne `feuille_match_set.saisie`

`score` (le score seulement) ou `touches` (le score et les touches des joueurs), choisi au moment de commencer le set (D68).
Les sets existants valent `score`.

### Nouveaux types d'événements

| Type | `donnees` | Effet sur le score |
|---|---|---|
| `action` | `{action, joueurs}` : `action` ∈ réception, passe, attaque, défense, block, renvoi ; `joueurs` : 1 identifiant de `FeuilleMatchJoueur` sur le terrain, 2 pour un block à deux (D68) | aucun |
| `point` (existant) | `{pour, sur?, detail?, sansDetail?}` : `sansDetail: true` = « Point sans détail » (échange non détaillé, aucune déduction d'ace ni de faute de service, D68) ; `sur` = uuid de l'action qui termine l'échange, ou `service`, ou absent (point sans action de nos joueurs : faute ou point adverse) ; `detail` = `contree` ou `faute` pour un point perdu sur une attaque (D68), ou le motif d'une faute de règlement (§3) | inchangé |
| `rejouer` | `{}` : balle à rejouer, clôt l'échange sans point ; ses actions ne comptent pas (D68) | aucun |
| `modification` | `{cible, joueurs?, action?, retirer?}` : corrige une action d'un échange terminé (D68) | aucun |

Sur un set `score`, seuls les types de la page du match sont acceptés (comme aujourd'hui).

## 3. Règles

- **Joueurs** : identifiants de `FeuilleMatchJoueur` de cette page **et sur le terrain** au moment de l'action (calculé par
  `EtatSet`) ; block à deux : deux joueurs distincts, **seulement pour un block gagnant** (D68) ; un joueur arrière ne contre pas (faute
de règlement, refusé à la saisie).
- **Point portant sur une action** (`sur`) : l'action doit être la dernière de l'échange en cours ; `point gagné` sur
  réception, passe, défense ou renvoi = point marqué par le joueur, pas compté en attaque (D68).
- **Point portant sur le service** : seulement quand nous servons et qu'aucune action n'a encore été saisie dans l'échange :
  `pour: nous` = ace, `pour: eux` = faute de service ; le serveur est celui de la rotation.
- **Fautes de règlement** (menu « Faute de… ») : point `{pour: eux, detail: filet|pied|double|quatre_touches|position|autre}`,
  avec `sur` = l'action du joueur fautif (saisie juste avant, action « faute » non nécessaire) ou sans `sur` pour une faute
  d'équipe (quatre touches, position ou rotation).
- **Attaque contrée** : point perdu `{detail: contree}` si le contre adverse marque ; si le ballon contré revient chez nous et
  reste jouable, le jeu continue et la prochaine action est une défense (nouveau cycle de trois touches, D68), sans issue
  particulière.
- **Le block ne compte pas comme une touche** (D68) : utile seulement à la prédiction (après notre block, trois touches).
- **Remplacement** refusé pendant un échange qui a déjà des actions (« Notez d'abord le point de l'échange en cours »).
- **Modification** : la cible est une action d'un échange terminé ; on ne change jamais l'issue d'un échange ancien
  (« Corriger le score ») ; pas d'ajout de touche oubliée (D68). « Précédent » annule la dernière saisie, quelle qu'elle soit.
- **Prédiction de l'action** (côté téléphone seulement, jamais imposée) : l'adversaire sert → réception ; après réception ou
  défense → passe ; après passe → attaque ; après notre attaque, notre block, notre renvoi ou notre service si le jeu
  continue → défense (block proposé à côté).

## 3 bis. Validation (relecture sécurité)

- **`sur` et `cible` cherchés parmi les événements de CE set** (jamais dans toute la base), sinon 400 neutre ; la cible est
  une `action` active, hors échange rejoué ; `sur` = la dernière action de l'échange en cours ; `cible` = une action d'un
  échange terminé ; les joueurs d'une modification sont contrôlés sur le terrain **au moment de l'action ciblée** (état
  recalculé jusqu'à elle).
- **Set terminé** : seul `modification` y est accepté (toujours sous `MODIFIER` : gel vérifié, admin toujours) ; pas de
  « Précédent / Suivant » sur un set terminé (une modification se défait par une autre) ; `dernierOrdre` contrôlé de même.
- **Schéma strict** : `joueurs` liste d'entiers distincts sans `null`, 1 joueur (2 pour `block` seulement) ; `action` dans une
  liste fermée ; `detail` `contree|faute` seulement avec `pour: eux` et un `sur` qui est une attaque ; motifs de faute
  seulement avec `pour: eux` ; aucun `detail` avec `pour: nous` ; `sur: service` refusé si la place au service est libre ;
  sur un set `score`, `sur`, `detail`, `action`, `rejouer`, `modification` refusés ; `saisie` ∈ {score, touches}, donnée à
  « commencer », figée ensuite ; action refusée une fois le score de fin atteint ; `correction`, `rotation` et remplacement
  refusés pendant un échange qui a déjà des touches.
- **Modifier l'action qui a fait le point** (la retirer, changer son type) : refusé (« Corriger le score »).
- **Plafond** : 1 500 événements par set, au-delà refus (téléphone qui boucle).
- **Messages** : un joueur n'est nommé que si son identifiant appartient à cette page ; sinon message neutre.
- **File d'attente** (corrigée le 31/10/2026) : session expirée ou portail du wifi (redirection, réponse qui n'est pas du
  JSON, 401/403/404) → la file s'arrête **sans se vider** et demande de se reconnecter ; le serveur répond 401 aux écritures
  JSON non connectées ; une saisie refusée (400) abandonne les saisies en attente du même set, en disant combien.

## 4. Statistiques (calculées, jamais stockées)

Service `StatsFeuille` : depuis les événements actifs (non annulés, hors échanges rejoués, modifications appliquées), par
joueur et par set ou par match :

| Stat | Calcul |
|---|---|
| Services | échanges où nous servons, crédités au serveur de la rotation ; aces (`sur: service`, pour nous), fautes (`sur: service`, pour eux) |
| Réceptions | tentées, fautes (point pour eux portant sur la réception, ou ace subi touché) ; % sans faute |
| Passes | tentées, fautes ; **passes décisives** : passe suivie, dans la même possession, d'une attaque gagnante |
| Attaques | tentées, gagnantes, fautes, contrées ; efficacité (gagnantes − fautes − contrées) ÷ tentées |
| Blocks | gagnants (chaque bloqueur crédité), fautes |
| Défenses, renvois | tentés, fautes |
| Points marqués | aces, attaques, blocks gagnants, et points gagnés sur une autre action |
| Sets joués | comme aujourd'hui |

**Échange détaillé** : tout échange dont le point n'est pas « sans détail » (D68) ; un point sans touche ni `sansDetail` se
lit comme un point sans faute de nos joueurs (ace subi, faute de service adverse, ace ou faute de notre serveur selon `sur`). **Taux de
détail** d'un set = échanges détaillés ÷ échanges ; un set compte dans les classements publics (brique suivante) s'il est
en `touches` et au-dessus du seuil réglé par l'admin.

## 5. Administration (D68)

- **`parametres_site.seuil_echanges_detailles`** : pourcentage, 80 par défaut.
- **Page `/admin/volley-stats`** sur le modèle de `ParametresSiteController` : `#[IsGranted('ROLE_ADMIN')]` sur la classe,
  route sous `/admin`, formulaire Symfony (CSRF), `IntegerType` + `NotNull` + `Range(0, 100)`, colonne entière non nulle,
  POST puis redirection 303 ; la section du tableau de bord n'affiche que des comptages, sans nom de joueur.
- **Menu de l'admin** : nouvelle entrée « Volley Stats » (page `/admin/volley-stats`) avec ce seuil ; plus tard les minimums
  d'actions des pourcentages (D66).
- **Tableau de bord** : une section « Volley Stats », contenu à proposer par l'ergonome (par exemple équipes qui notent leurs
  matchs cette saison, sets détaillés).

## 6. Interface (voir la maquette)

« Que notez-vous ? » au début du set ; actions prédites ; terrain = pavé des joueurs ; carte de la touche en attente ;
« Point gagné », « Point perdu » (puis « Contrée / Faute » pour une attaque), « Le jeu continue » implicite ; « Point pour
nous / Point pour eux » ; « Balle à rejouer » ; « Faute de… » ; historique regroupé par échange ; stats de l'équipe et par
joueur sous le relevé des sets.

## 7. Tests à prévoir

- Actions refusées : joueur d'une autre page, joueur sur le banc, block à trois, action inconnue, set en mode `score`.
- Point sur une action qui n'est pas la dernière de l'échange, ou sur le service quand l'adversaire sert : refusé.
- Remplacement pendant un échange : refusé.
- Balle à rejouer : ses actions ne comptent pas, le serveur ne change pas.
- Modification : changer le joueur, l'action, retirer ; refus sur l'échange en cours ; « Précédent » l'annule.
- Statistiques sur un match construit à la main : aces, fautes de service, % sans faute en réception, passes décisives,
  efficacité d'attaque, block à deux (deux crédités), point marqué en défense, taux de détail.
- Seuil : réglage par l'admin seulement ; valeur hors 0–100 refusée.

Tests ajoutés par la relecture : `sur` ou `cible` pris dans un autre set ; joueur entré après l'action ciblée ; point
refusé et modification acceptée sur un set terminé ; modification refusée après le gel sauf pour l'admin ; page admin
refusée à un joueur et à un responsable de club, valeurs -1, 101, décimale, texte et jeton absent refusés ; aucune stat ni
touche sur la fiche équipe publique, le calendrier, l'ICS, l'API du score, le classement ; réponses `private, no-store`.

## 9. Données personnelles

Stats de performance individuelles, internes à l'équipe et au responsable de son club dans cette brique (D68) : à ajouter aux conditions d'utilisation (finalité,
qui les voit, durée ; publication plus tard avec accord, D66), page à brancher à la fusion de `conditions-utilisation-v2`.
Conservation (D66) : les touches sont dans le journal des pages du match, donc supprimées avec elles par la commande prévue
(`specification-page-match.md` §6, feuilles de la saison N supprimées à la fin de N+1), **encore à réaliser** avant l'essai
sur un vrai match.

## 8. Ordre de réalisation

1. Colonne `saisie`, nouveaux types d'événements, validation, tests.
2. `StatsFeuille` et ses tests (sur des journaux construits en mémoire).
3. Interface de saisie (maquette), essai dans le navigateur.
4. Stats de l'équipe sur la page du match ; seuil dans l'admin et section du tableau de bord.
5. Relectures ergonome, webdesigner, sécurité ; essai sur un vrai match avant de généraliser.
