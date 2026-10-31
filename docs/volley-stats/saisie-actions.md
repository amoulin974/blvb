# Volley Stats : saisie des actions en mode intermédiaire, avis de l'ergonome (31/10/2026)

Deuxième brique (D66 : chaque touche de notre équipe, trois issues, sans qualité). **Rien n'est décidé ni codé** : les
questions du §5 sont à trancher, puis à consigner dans `decisions.md`.

## Verdict

Tenable **à cinq conditions**, sinon déconseillé (joueur, action, issue à chaque touche ≈ 9 touches d'écran pour un
échange de 5 secondes) :
1. **l'action est prédite** : le saisisseur ne touche que le joueur ;
2. **« le jeu continue » est implicite** : toucher le joueur suivant suffit ;
3. **le service n'est jamais saisi** : il se déduit de la rotation ;
4. **saisie après le point**, pendant les 10 à 20 s entre deux échanges ;
5. **un échange peut rester non détaillé** : on note seulement le point, comme aujourd'hui.

**Défaut bloquant du code actuel** : `envoyer()` ignore une saisie tant que la précédente n'a pas reçu de réponse
(`feuille_match_controller.js`) ; à quatre touches en trois secondes, des touches seraient perdues sans message. Il faut
d'abord la file d'attente locale de la spécification (§5) : affichage immédiat, envois les uns après les autres.

## 1. Touches par échange

| Échange | Touches |
|---|---|
| Faute de service adverse | 1 (« Point pour nous ») |
| Ace subi sans contact | 1 (« Point pour eux ») |
| Ace subi sur Léa | 2 |
| Notre ace ou notre faute de service | 1 |
| Service adverse → réception → passe → attaque gagnante | 4 |
| Même échange, attaque défendue puis point adverse | 4 |
| Échange long, deux attaques | 7 à 8 (« non détaillé » en recours) |

Moyenne ≈ 3. **Prédiction** : l'adversaire sert → Réception ; après réception ou défense → Passe ; après passe → Attaque ;
après notre attaque, notre block ou notre service, si le jeu continue → Défense (Block à côté). Recalculée après chaque
touche. Pas de passe ajoutée d'office (fausserait le classement des passeurs) ; passe décisive = passe suivie, dans la même
possession, d'une attaque gagnante.

## 2. Parcours sur « Set en cours »

- **L'action d'abord (prédite), puis le joueur.**
- **Le terrain devient le pavé des joueurs** : ses six cases sont des boutons pendant le jeu (désignation par le nom, D67).
- **Touche en attente** : carte « Inès Bo. · Attaque » avec « Point gagné », « Point perdu », « Le jeu continue » ; toucher
  un autre joueur vaut « Le jeu continue ».
- **Le point découle de l'issue** : « Point gagné / perdu » crée l'événement `point` existant, avec une clé `sur`
  (l'action, ou `service`) ; score et rotation inchangés dans `EtatSet`. Les boutons « Point pour nous / Point pour eux »
  restent : point sans faute de nos joueurs (faute adverse, attaque adverse gagnante).
- **Historique** avec le terme précis : ace, faute de service, attaque gagnante, ace subi.
- **« Personne »** se déduit (point pour eux sans touche pendant leur service = ace subi) ; **« Équipe »** seulement pour les
  fautes collectives.
- **Menu « Faute de… »** : filet, pied sous le filet, double touche ou balle tenue (puis le joueur) ; quatre touches,
  position ou rotation (équipe) ; autre faute.
- **« Balle à rejouer »** : clôt l'échange sans point ; ses touches restent visibles mais ne comptent pas.
- **Ballon qui repasse le filet** : changer l'action (Défense, Block) avant de toucher le joueur.
- **Remplacement** refusé pendant un échange : « Notez d'abord le point de l'échange en cours. »

## 3. Corrections

- **« Précédent » annule toujours la dernière touche d'écran** (action au milieu d'un échange, ou le point : la carte
  revient pour choisir une autre issue).
- **Action ancienne** : depuis l'historique, « Changer le joueur », « Changer l'action », « Retirer cette touche »
  (événement `modification`, annulable). **L'issue d'un ancien échange ne se change pas** (décalerait score et rotation) :
  « Corriger le score ».
- **Historique regroupé par échange** : « 12–10 · Point pour nous · Inès Bo. attaque gagnante (Léa Mo. réception, Paul Da.
  passe) ».

## 4. Maquette (360 px)

```
SET 2 · en 25 points
EROAK 12          BIARRITZ A2 10
Au service : Jon Et.
Prochaine touche :
[Réception][ Défense ][  Block  ]
[  Passe  ][●Attaque ][Faute de…]
---------- FILET ----------
[ Léa Mo. ][ Paul Da. ][ Inès Bo.]
[ Jon Et. ][Camille Ar][ Marc Lu.]
Inès Bo. · Attaque
[ Point gagné ]  [ Point perdu ]
[      Le jeu continue        ]
[Point pour nous][Point pour eux]
        Balle à rejouer
[← Précédent]      [Suivant →]
Échange en cours : Léa Mo. réception ·
Paul Da. passe · Inès Bo. attaque
[Remplacer un joueur][Corriger le score]
▸ Historique du set (34 échanges)
```

Score sur une ligne compacte ; actions en boutons `aria-pressed`, prédiction annoncée (`aria-live`). Au début de chaque
set : « Que notez-vous ? » → « Le score seulement » (écran actuel) ou « Le score et les touches des joueurs ».

## 5. Questions à trancher

1. Saisie après le point et échanges non détaillés acceptés ? Seuil d'échanges détaillés pour compter dans les classements
   (ex. 80 %) ?
2. Le block compte-t-il comme une touche au BLVB (anciennes règles) ?
3. Ballon facile renvoyé en 3ᵉ touche : « Attaque » ou action « Renvoi » à part ?
4. Attaque contrée et faute d'attaque : à distinguer ?
5. Block à deux : un seul joueur crédité ou chacun ?
6. « Le score seulement » : choisi à chaque set ou pour le match ? Par n'importe quel saisisseur ?
7. Corriger les actions anciennes pendant le set, ou à la relecture ? Ajouter une touche oubliée (non proposé en V1) ?
8. Touches d'une balle à rejouer : comptées ou non (proposition : non) ?
9. Point gagné sur une réception, une passe ou une défense (ballon renvoyé qui tombe chez l'adversaire) : « point marqué » ?

Points d'attention : la passe est la touche la plus oubliée (fiabilité du classement des passeurs, à dire sur la page
publique) ; changement de saisisseur en cours de set (à essayer sur un vrai match) ; couleurs de l'action choisie et de la
carte en attente : webdesigner.
