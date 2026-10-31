# Volley Stats : maquette de la saisie des actions, mode intermédiaire (webdesigner, 31/10/2026)

Maquette HTML statique, mobile d'abord (360-390 px), dans la charte « Monogramme × Océan & ballon ». Elle suit le parcours de
l'ergonome (`saisie-actions.md`) et les décisions de D68, qui l'emportent. Ce n'est pas du code de l'application : c'est le
rendu de référence pour le développement. Fichier : `saisie-actions.html`, à côté de `page-match.html` et du CSS compilé
(`app.built.css`), avec les mêmes polices (`fonts/`). Une barre en haut fixe la largeur (360 px par défaut, 390 px, large)
et trace en pointillé rouge, dans l'état (b), la limite de ce qu'on voit sur un écran de 360 × 640 (584 px une fois ôtée la barre d'adresse de 56 px). Les états (b) à (g) sont interactifs : prédiction, carte en attente,
issues, block à deux, fautes, « Précédent / Suivant », file d'attente et coupure du réseau (bouton de la maquette).

## 1. États montrés (empilés, chacun titré)

| État | Contenu |
|---|---|
| (a) Début du set | « Que notez-vous pendant le set 2 ? » : deux cartes de choix (`.choix-carte`, bouton radio visible) « Le score seulement » / « Le score et les touches des joueurs », avec une phrase d'explication chacune ; le choix du set précédent est coché et dit (« Choix du set 1. »). Rappel du seuil : « au moins **80 %** de ses échanges doivent être détaillés (seuil fixé par le BLVB) », valeur lue dans le réglage de l'admin (D68). Sous la composition, le score de départ et le premier service, avant « Commencer le set 2 ». |
| (b) Échange en cours | Zone de saisie complète : titre « Set 2 en cours · en 25 points · score et touches », score compact, « Au service : Biarritz A2 » et l'état d'envoi ; grille « Prochaine touche, si le jeu continue » (Réception, Passe, Attaque, Renvoi / Défense, Block, « Faute de… ») avec l'action prédite enfoncée ; terrain dont les six cases sont les boutons des joueurs ; carte « EN ATTENTE Inès Bo. · Attaque » (étiquette et titre sur une ligne) avec « Point gagné », « Point perdu », « Le jeu continue » ; « Point pour nous / Point pour eux » ; « Balle à rejouer » (bouton à contour marine, demande de l'utilisateur) ; « Précédent / Suivant » ; résumé « Échange en cours : Léa Mo. réception · Paul Da. passe · Inès Bo. attaque (issue à choisir) » ; « Remplacer un joueur » (refusé pendant un échange, avec la raison) / « Corriger le score » ; historique replié. |
| (c) Point perdu sur une attaque | La carte demande « Inès Bo. · Attaque : comment ? » : « Contrée » (le contre adverse marque) ou « Faute » (dehors, filet…). Terrain, actions et points sont refusés (`aria-disabled`, raison annoncée) jusqu'au choix ; « Précédent » revient à la carte en attente. |
| (d) Block gagnant à deux | Après « Point gagné » sur un block : « Marie-Charlotte De. · et un deuxième bloqueur ? Touchez-le sur le terrain. », bouton « Un seul bloqueur ». La case du premier bloqueur est marquée « block » ; les deux autres avants portent « au block ? » ; les arrières sont refusés (« Un joueur arrière ne peut pas contrer. »). Chaque bloqueur est crédité (D68). |
| (e) Menu « Faute de… » | Le menu prend la place de la carte, sous le pouce. Deux groupes titrés par ce qu'ils déclenchent : « D'un joueur, puis touchez-le » (Filet, Pied sous le filet, Double touche, Balle tenue, Autre faute) et « De l'équipe, point pour eux tout de suite » (Quatre touches, Position ou rotation), puis « Fermer ». Après une faute de joueur, le bouton devient « Faute : filet » (enfoncé) et la carte dit « Filet : touchez le joueur fautif. », avec « Ce n'était pas une faute ». |
| (f) Historique | Résumé du repli : « 22 échanges · 18 détaillés (82 %), il en faut 80 % ». Un échange par ligne, le plus récent en haut : score après l'échange (colonne fixe, Barlow Condensed marine), issue en gras (« Point pour nous »), touche décisive au terme précis (« Inès Bo. attaque gagnante », « ace subi », « faute de l'équipe : quatre touches », « Marie-Charlotte De. et Inès Bo. block gagnant », « sans détail »), autres touches dessous. « Balle à rejouer · touches non comptées » : score grisé et touches barrées. Correction : les touches de l'échange sont des boutons ; la touche choisie ouvre « Changer le joueur », « Changer l'action », « Retirer cette touche » et le rappel « L'issue d'un échange terminé ne change pas ici : « Corriger le score ». ». |
| (g) File d'attente et réseau | Chaque saisie s'affiche aussitôt ; tant que le serveur ne l'a pas reçue, elle porte « · envoi… » (italique) dans le résumé et dans l'historique, et l'état d'envoi dit « Envoi… (2) » ; une fois reçue, la mention disparaît et l'état revient à « ✓ Enregistré ». Réseau coupé : l'état dit « Hors ligne · 3 gardées » (gras, texte orange foncé) et les touches « · gardée ici » ; un encadré (`role="alert"`) « Pas de réseau : 3 saisies gardées sur ce téléphone. Continuez à noter : elles partiront dans l'ordre dès le retour du réseau. Nouvel essai dans 4 s. » avec « Réessayer maintenant ». |

## 2. Hiérarchie : ce qui tient sous le pouce à 360 px

Ordre du haut vers le bas, du moins touché au plus touché : on lit le haut, on joue avec le pouce en bas.

1. **Score compact** (lecture seule) : « EROAK 12–10 BIARRITZ A2 » sur une ligne (chiffres en Barlow Condensed 32 px,
   marine, tabulaires), puis « Au service : … » et l'état d'envoi sur la même ligne. Les grands « +1 point » de l'écran
   « score seulement » disparaissent : le point découle de l'issue.
2. **Actions** : rarement touchées (l'action est prédite). Deux rangées de 48 px en quatre colonnes : la première suit le
   cycle normal dans l'ordre de lecture (Réception, Passe, Attaque, Renvoi à côté de l'attaque), la seconde les moins
   fréquentes (Défense, Block, « Faute de… » sur deux colonnes, car c'est un menu).
3. **Terrain** : touché à chaque touche. Cases de 56 px de haut au lieu de 72 ; poste abrégé en petit au-dessus
   (« 2 · Av. droit », comme le relevé compact : il sert à se repérer sur le vrai terrain, le nom complet est lu au lecteur
   d'écran) ; la case du joueur en attente porte l'action (« attaque »).
4. **Carte de la touche en attente** : la place est réservée même sans touche (consigne en pointillé : « Touchez le joueur
   qui réceptionne. »), pour que rien ne saute sous le doigt entre deux touches. Les trois issues sont sur une rangée.
5. **Point pour nous / Point pour eux**, en bas de la partie visible.

Sous le pli (on y va pendant les 10 à 20 s entre deux échanges) : « Balle à rejouer », « Précédent / Suivant », le résumé
de l'échange, l'alerte réseau, les outils et l'historique.

**Mesures** (Chromium 1.49 sans tête de l'image Playwright, mesurées depuis le haut de la zone ; ouvrir la maquette avec
`#mesure-360` ou `#mesure-390` pour les relire dans la console) :

| | 360 px | 390 px |
|---|---|---|
| bas du score et du service | 84 px | 84 px |
| bas du terrain | 395 px | 395 px |
| bas de la carte en attente | 511 px | 511 px |
| bas de « Point pour eux » | **567 px** | 567 px |
| bas de « Précédent / Suivant » | 679 px | 679 px |
| largeur des actions / des cases | 73 / 94 px | 80 / 104 px |
| hauteur des cases (avants / arrières) | 66 / 56 px | 66 / 56 px |

Tout ce qui sert à chaque échange tient donc dans 584 px, le plus petit écran courant (360 × 640, barre d'adresse ôtée) ;
sur un téléphone de 360 × 780, « Précédent / Suivant » est visible aussi. Aucun débordement horizontal, aucune cible de
moins de 48 px. Le menu « Faute de… » (358 px) et la carte du block à deux (159 px) dépassent la place réservée : ils
poussent la suite, ce qui est acceptable pour une étape choisie (pas de saut au milieu d'un geste répété).
Pour tenir, trois réglages ont été faits après une première mesure à 611 px : mention courte dans la case (« attaque »),
postes abrégés, étiquette et titre de la carte sur une ligne, écarts de 8 px.

Au début du set, la page se place sur la zone de saisie (`scrollIntoView`, `scroll-margin-top`) : l'en-tête du match reste
au-dessus, on y remonte d'un geste.

## 3. Couleurs et états, jamais par la couleur seule

| Élément | Rendu | Repère sans la couleur |
|---|---|---|
| Action prédite ou choisie | Marine plein, texte blanc (12,6:1), coche Heroicons dans l'angle | coche, `aria-pressed="true"`, libellé « Prochaine touche » annoncé (`aria-live`) |
| Autres actions | Contour marine (`btn-outline btn-primary`) | — |
| Carte de la touche en attente | Bordure marine 2 px pleine, fond marine à 6 % ; texte marine 18 px | étiquette « Touche en attente », titre « Inès Bo. · Attaque » |
| Carte sans touche (consigne) | Bordure en pointillé, marine à 60 %, fond blanc | trait pointillé, phrase « Touchez le joueur qui… » |
| Case du joueur en attente | Case active existante (bordure marine 3 px, fond marine à 8 %) | mention « attaque, en attente » dans la case, `aria-current` |
| Case refusée (arrière au block, terrain verrouillé) | Fond `base-200`, bordure en pointillé serré, nom à 70 % | pointillé, `aria-disabled`, raison annoncée au toucher |
| Issues | « Point gagné », « Point perdu » en contour marine, à égalité ; « Le jeu continue » en contour neutre (`btn-outline` sans couleur : il est implicite) | libellés |
| Point pour nous / pour eux | Marine plein `btn-primary` (comme « +1 point ») | libellés ; `aria-label` avec le nom de l'équipe |
| « envoi… » | Italique, texte à 70 % (5,9:1) | mot écrit |
| « gardée ici », « Hors ligne » | Gras, orange foncé `texte-attention` (AA) ; encadré `alerte-reseau` à bord gauche orange 4 px, icône triangle | mots écrits, icône |
| « Enregistré » | `texte-succes` (existant) avec coche | mot écrit |
| Balle à rejouer (historique) | Score à 70 %, touches barrées | « touches non comptées » écrit |

Le jaune `accent` reste réservé aux boutons qui font avancer (« Commencer le set 2 ») : à chaque touche, il deviendrait du
bruit et se confondrait avec le logo. Pas de vert / rouge pour « Point gagné / perdu » : deux libellés clairs suffisent, et le
rouge serait lu comme une erreur de saisie. Retirés à l'autocritique : les en-têtes de colonne « 1ʳᵉ / 2ᵉ / 3ᵉ touche »
au-dessus de la grille (une ligne sous le pouce pour une information que la prédiction donne déjà) et l'icône animée
d'envoi (le mot « envoi… » suffit, sans mouvement).

## 4. Cibles, accessibilité

- **Cibles de 48 px** partout (`.page-match .btn`, cases de 56 px, `btn-multiligne` à 48 px minimum, `summary` en `min-h-12`).
  Actions : 72 px de large à 360 px, libellés en Barlow Condensed 16 px (« Réception » tient) ; issues et fautes : libellé
  sur deux lignes au besoin (`btn-multiligne`), jamais coupé.
- **Lecteur d'écran** : chaque case est un `<button>` dont le nom commence par le joueur (« Inès Bo. attaque, en attente,
  2 Avant droit ») ; le nom vient avant le poste dans le DOM et le poste est affiché au-dessus par `order: -1` (abrégé
  à l'écran, `aria-hidden`, en entier en `sr-only`). Après
  chaque saisie, une seule annonce polie : « Inès Bo., attaque. Prochaine touche, si le jeu continue : Défense. », « Point
  pour nous, Inès Bo. attaque gagnante. 13–10. ». Les boutons refusés gardent le focus (`aria-disabled`) et annoncent leur
  raison. L'alerte réseau est un nœud stable `role="alert"` dont le texte ne change que si le nombre de saisies gardées
  change ; le compte à rebours est `aria-hidden` (sinon il serait lu chaque seconde).
- **Focus** : contour natif daisyUI sur les boutons, contour marine décalé sur `.terrain-case` (existant). **Le rendu par
  `innerHTML` perd le focus** : le contrôleur doit le remettre sur le même bouton après chaque rendu (la maquette le fait
  par `data-act` / `data-val`), sinon la saisie au clavier ou au lecteur d'écran repart du haut à chaque touche.
- **Pas de saut de mise en page** sous le doigt : carte de hauteur réservée, état d'envoi sur la ligne du service. Seule
  l'alerte réseau s'insère, sous le pli.

## 5. Classes à créer dans `app.css` (définies dans le `<style>` de la maquette, partie 3b)

| Classe | Rôle | Réemploi possible |
|---|---|---|
| `.score-compact`, `.score-compact-equipe`, `.score-compact-chiffres` | score du set sur une ligne | en-tête du relevé, score en direct d'un match sur la fiche équipe |
| `.btn-bascule` (+ `.coche`) | bouton `aria-pressed` : marine plein et coche | filtres du site public, choix de vue des stats |
| `.actions-grille` | grille de quatre boutons étroits | tout pavé de choix rapides |
| `.btn-multiligne` | bouton dont le libellé passe sur deux lignes | colonnes étroites (wizard, admin sur téléphone) |
| `.terrain-jeu`, `.terrain-mention`, `.terrain-case[aria-disabled]` | terrain pendant le jeu : cases plus basses, nom d'abord, mention, case refusée | remplacement (`terrain-mention` remplace les `block text-sm font-semibold` du contrôleur) |
| `.touche-carte` (+ `[data-etat="consigne"]`), `.touche-titre` | carte de la touche en attente, place réservée | toute étape « en attente de confirmation » |
| `.envoi-cours`, `.envoi-garde`, `.alerte-reseau` | état d'une saisie dans la file d'attente | file d'attente de la page match en mode « score seulement », saisie du score officiel |
| `.echange`, `.echange-score`, `.echange[data-compte="non"]` | historique regroupé par échange | relevé des sets, export |

À réutiliser tels quels : `.choix-carte`, `.terrain*`, `.terrain-service`, `.btn[aria-disabled="true"]`, `.texte-attention`,
`.texte-succes`, `.btn-danger-discret`, `details.depliant`. Utilitaires Tailwind absents du CSS compilé actuel, générés dès
qu'un gabarit les emploie : `grid-cols-4`, `col-span-2`, `line-through`.

## 6. À arbitrer

Questions de parcours ou de libellés, à poser à l'ergonome :
1. **Échange sans touche : déduit ou « sans détail » ?** Pendant leur service, « Point pour eux » sans touche est déduit
   en « ace subi », « Point pour nous » en « faute de service adverse » ; pendant le nôtre, en ace ou faute de service du
   serveur. Mais un échange volontairement non détaillé (D68) donne la même saisie : il serait compté comme détaillé
   et fausserait aces et seuil. Faut-il un bouton « Point sans détail » (une rangée de plus) ou une règle (par exemple :
   la déduction ne vaut que si le saisisseur n'a sauté aucun échange dans le set) ? La maquette déduit, et montre
   « sans détail » dans l'historique comme cas à prévoir.
2. **« Point gagné » sur la carte et « Point pour nous » dessous** : deux paires de boutons de point. Faut-il une
   étiquette au-dessus de la seconde (« Point sans touche à noter ») ? Elle coûte une ligne sous le pouce.
3. **Changer l'action de la touche en attente** : la grille vaut pour la *prochaine* touche ; pour corriger « Attaque » en
   « Renvoi », il faut « Précédent » puis l'action et le joueur (trois touches). Rendre le titre de la carte touchable
   (« Inès Bo. · Attaque ▾ ») ?
4. **Libellés des termes de l'historique** à valider : « attaque gagnante », « attaque contrée », « faute d'attaque »,
   « renvoi gagnant », « point sur réception / passe / défense », « réception manquée », « passe manquée », « défense
   manquée », « renvoi manqué », « block perdu », « faute de l'équipe : quatre touches », « faute de position »,
   « sans détail », « touches non comptées », « gardée ici », « Hors ligne », « Ce n'était pas une faute », « Un seul
   bloqueur », « au block ? ».
5. **Choix « Que notez-vous ? »** : coché d'après le set précédent (maquette) ou rien d'avance, comme le premier service ?
   Le seuil s'affiche-t-il dès ce choix (maquette) ou seulement dans l'historique ?
6. **Passer au « score seulement » en cours de set** (saisisseur débordé) : rien n'est prévu ; les échanges non détaillés
   le permettent déjà, au prix du seuil.
7. **Block à deux sur un block qui ne marque pas** (le jeu continue) : un seul bloqueur noté (maquette), ou la même
   question du deuxième bloqueur ?
8. **Nouveau cycle après une attaque contrée récupérée** (D68) : la prédiction propose « Défense » ; faut-il écrire le
   rang de la touche (« 1ʳᵉ touche ») au-dessus de la grille pour aider à repérer les quatre touches ? La maquette ne
   l'affiche pas (une ligne de plus).
