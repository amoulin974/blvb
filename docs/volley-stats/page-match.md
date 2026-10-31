# Volley Stats : page match et composition par set, avis de l'ergonome (31/10/2026)

Proposition de l'utilisateur : commencer par la composition de l'équipe à chaque set, sur une **page match** propre à une
équipe pour un match du championnat, visible de ses membres, du responsable du club et des admins. **Rien n'est décidé ni
codé** ; les questions du §5 sont à trancher, puis à consigner dans `decisions.md`.

## 1. Point de départ

Bon point de départ : rotation, serveur attendu et « sets joués » en dépendent ; écran court, facile à essayer sur un vrai
match ; utile sans les stats. Réserves :
1. **Vocabulaire** : « Composition » désigne déjà les joueurs de l'équipe pour la saison (`front/equipe.html.twig`,
   `front/equipe_composition/index.html.twig`). Toujours écrire « Composition du set 1 », et « Joueurs présents » pour le
   match. Renommer l'existant en « Joueurs de l'équipe » : à décider à part.
2. **Un set = une suite d'événements dès le départ** (départ, remplacement, fin de set) : la saisie des actions s'y ajoutera
   sans rien refaire.
3. **Pas de second score officiel** : la page renvoie vers la fenêtre existante (`_score_partie.html.twig`).

## 2. La page match

- **Entrées**, pour les seules personnes autorisées : bouton « Préparer le match » sur la carte « Prochain match » de la
  fiche équipe ; lien « Page du match » sur la ligne du match dans le calendrier de l'équipe. Pas « Mon équipe » (équipe
  favorite gardée dans le navigateur, choisie par n'importe quel visiteur).
- **Avant** : date, heure, gymnase (alerte de fermeture existante), adversaire, contact du capitaine adverse, « Joueurs
  présents », « Composition du set 1 ».
- **Pendant** : set en cours, composition sur le terrain, « Remplacer un joueur », « Terminer le set ».
- **Après** : relevé des sets et des compositions, puis « Saisir le score officiel » (fenêtre existante).
- **Utile sans les stats** : présents, compositions gardées d'un match à l'autre, sets joués par joueur, un seul endroit
  pour le match.

## 3. Composition sur téléphone

Ni listes déroulantes (12 touches au moins, doublons possibles), ni étape « qui joue » séparée (les remplaçants se
déduisent : présents moins les six placés).

- **Écran A, une fois par match, « Qui est là ? »** : cases à cocher de 48 px, « Camille Ar. » ; cochés d'office : les
  présents du match précédent ; « Ajouter un joueur de l'équipe ».
- **Écran B, « Composition du set 1 »** : terrain, filet en haut ; la case en surbrillance avance seule dans l'ordre de
  service (poste 1 à 6) : six noms à toucher ; chaque case reste modifiable.
- **Écran C, « Premier service »** : « Nous servons » / « Nous recevons », prérempli à partir du set 2 selon la règle du
  championnat ; « Commencer le set 1 ».
- **Sets suivants** : préremplis avec le départ du set précédent ; « Tourner d'un poste », « Tout effacer ».
- **Remplacement** : toucher le joueur, « Remplacer par… », « Valider » ; motif « Blessure » facultatif ; « Annuler le
  remplacement » pendant 10 s.
- **Set à 5** : « Commencer à 5 joueurs », case « Place libre » remplissable en cours de set.
- **Score de départ** : « 0–0 · 0–8 · 8–0 » (règle 1J non contrôlée, mais nécessaire pour savoir quand le set finit).

```
Set 2 · contre BIARRITZ A2
------- FILET -------
[Avant gauche][Avant centre][Avant droit ]
[ Léa Mo.    ][ Paul Da.   ][ Inès Bo.   ]
[Arrière gau.][Arrière ctr.][Arr. droit  ]
[ Jon Et.    ][ Camille Ar.][▶ à choisir ]
                             (au service)
Toucher le joueur pour cette place :
( Marc Lu. ) ( Sara Ib. ) ( Théo Ga. )
[Tourner d'un poste]   [Tout effacer]
Premier service : (Nous servons) (Nous recevons)
[ Commencer le set 2 ]   (désactivé tant qu'une place est vide)
```

Accessibilité : chaque case est un bouton (« Arrière droit, au service : Camille Ar., modifier ») ; la case suivante est
annoncée (`aria-live`).

## 4. Droits et visibilité

- **Responsable du club** : la fiche équipe ne montre aujourd'hui l'effectif qu'aux membres et aux admins
  (`peutVoirMembres`) ; lui ouvrir la page match est nouveau : lecture seule proposée.
- **Modification** : saisisseur et capitaine (D66) ; autres membres en lecture.
- **Capitaine adverse** : aucun accès ; chaque équipe a sa page. Refus en « page introuvable ».
- **Accompagnant** : le compte d'un joueur donne accès à tout ce compte ; un lien de saisie temporaire limité au match
  serait à étudier.

## 5. Questions à trancher

1. Qui modifie : tout membre de l'équipe, ou seulement le capitaine et un saisisseur désigné ? Responsable du club en
   lecture seule ?
2. Premier service au règlement BLVB : tirage au sort à chaque set, ou alternance ? Et au 5ᵉ set ?
3. À 5 joueurs (règle 5J), que se passe-t-il au tour de service de la place libre ?
4. En 2 sets gagnants, le 3ᵉ set se joue-t-il en 15 ? Des matchs où tous les sets se jouent ?
5. Un renfort hors de l'équipe peut-il être ajouté au gymnase, et par qui ?
6. Noter le score de chaque set (25–21) sur la page match, en interne ?
7. Un lien de saisie temporaire pour l'accompagnant ?

Points d'attention : enregistrement de chaque étape aussitôt sur le serveur ; cibles de 48 px, trois colonnes d'environ
110 px à vérifier avec des noms longs ; couleurs du terrain et de la case active : webdesigner.
