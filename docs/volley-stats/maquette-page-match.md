# Volley Stats : maquette de la page match (webdesigner, 31/10/2026)

Maquette HTML statique, mobile d'abord (360-390 px), dans la charte « Monogramme × Océan & ballon ». Elle suit le parcours de
l'ergonome (`page-match.md`) et D66 / D67. Ce n'est pas du code de l'application : c'est le rendu de référence pour le
développement. Fichier : `page-match.html`, à ouvrir à côté du CSS compilé du site (`app.built.css`). Une barre en haut
fixe la largeur à 360 px, 390 px ou large. Les polices sont chargées depuis `assets/fonts` du dépôt.

## 1. États montrés (empilés, chacun titré)

| État | Contenu |
|---|---|
| (a) En-tête | « ← Eroak (AOVB) » (retour à la fiche équipe), « Page du match », titre `h1` en capitales « Contre Biarritz A2 (BVB) », date et heure en Barlow Condensed marine (comme « Prochain match »), domicile ou extérieur et gymnase (lien), carte du capitaine adverse avec « Appeler », puis le rappel « Visible de l'équipe seulement » (icône cadenas Heroicons au trait, texte en gras). Le fragment `_gymnase_ferme.html.twig` vient sous le gymnase si besoin. |
| (b) Joueurs présents | Titre « Joueurs présents », compteur « 9 sur 12 » (annoncé : `aria-live`), légende « Qui est là ? Cochés d'après le match du 23/10 ». Grille de 2 colonnes de cases à cocher de 48 px ; « Enregistré » après chaque touche. |
| (c) Composition du set 1 | Terrain (filet en haut, 6 cases), joueurs du banc à toucher, « Tourner d'un poste », « Tout effacer », score de départ 0–0 / 0–8 / 8–0, premier service « Nous servons / Nous recevons » (aucun choix fait d'avance), « Commencer le set 1 » refusé tant qu'il reste quelque chose à faire, avec le texte « Reste à faire : 1 place à remplir, premier service à choisir. ». **Interactif** : la case active avance seule de poste en poste ; toucher une case remplie la rend active (« à changer »), toucher la case active la désactive. « Commencer à 5 joueurs » n'apparaît que s'il reste une place vide et plus personne sur le banc. |
| (d) Set en cours | « Set 1 en cours · en 25 points · parti de 0–0 », score du set (« pour l'équipe seulement ») avec « +1 point » de chaque côté et « Annuler le dernier point », terrain en lecture avec « Au service » sur le poste 1, « Sur le banc : … », « Remplacer un joueur », « Terminer le set ». |
| (d') Remplacement | Terrain dont les cases sont des boutons, joueur qui sort marqué comme la case active (mention « sort »), « Remplacer Inès Bo. par… » (boutons radio), pas de motif (« blessure » serait une donnée de santé, spécification §6), « Annuler » / « Valider », puis le message de 10 s « Théo Ga. remplace Inès Bo. · Annuler le remplacement ». |
| (e) Après le match | « Relevé des sets » : une carte par set (« Set 1 », score et « gagné » / « perdu » écrits, « Composition du set 1 au départ · nous servions · parti de 0–0 », mini-terrain, remplacements). Le set 3 est marqué « en 15 points », commencé à 5 avec la « Place libre ». Tableau « Sets joués » par joueur. Carte « Score officiel » : rappel qu'il se saisit à part, bouton « Saisir le score officiel » (ouvre la fenêtre existante `_score_partie.html.twig`). |

Ordre réel de la page : avant le match (a) + (b) + (c) ; pendant (a) + (d) ; après (a) + (e). Le relevé des sets déjà
joués peut aussi s'afficher pendant le match, sous le set en cours.

## 2. Choix et justification par la charte

- **Terrain** : fond turquoise pâle (`secondary` à 12 % sur `base-100`), contour 2 px `secondary`. Le turquoise est la
  couleur secondaire de la charte : il sépare le terrain du reste de la carte sans prendre le marine (marque, case active)
  ni le jaune (actions). Cases blanches, bordure 1 px `secondary` (4,1:1 sur blanc, au-dessus des 3:1 demandés pour le
  contour d'un composant).
- **Filet** : bande marine, « FILET » en blanc (12,6:1). Elle donne l'orientation (avant en haut, côté adverse au-delà).
  Pas de ligne des 3 m : l'écart entre les rangées et les noms de postes « Avant / Arrière » suffisent (élément retiré à
  l'autocritique).
- **Case** : en haut le numéro et le nom du poste en Barlow Condensed 12 px en capitales (« 1 · Arrière droit » ; le
  numéro donne l'ordre de service, c'est lui que suit la case active). En bas le nom du joueur en Barlow Condensed gras
  16 px, coupé où il faut (`overflow-wrap: anywhere`). Hauteur minimale de 72 px.
- **Trois états de case, jamais par la couleur seule** :
  - remplie : bordure fine pleine et nom du joueur ;
  - vide : bordure en pointillé et « Place vide » (ou « Place libre » pour un set commencé à 5) ;
  - active : bordure marine de 3 px, fond marine à 8 % et texte « À choisir » (ou « à changer », « sort ») ; `aria-current="true"`.
  Le jaune n'est pas utilisé pour la case active : il est réservé au logo et aux boutons d'action, et le marine est
  plus contrasté (12,6:1).
- **Serveur** : étiquette « Au service », blanc sur turquoise foncé (`secondary` mêlé à 70 % de noir, 7:1 environ ; le
  turquoise de base, 4,1:1, ne suffit pas pour un texte de 12 px). Pendant la composition, elle n'apparaît que si l'on a
  choisi « Nous servons ».
- **Boutons** : jaune `btn-accent` pour les actions qui font avancer (« Commencer le set 1 », « Valider », « Saisir le
  score officiel ») ; marine plein `btn-primary` pour « +1 point » (action répétée, bien visible) ; contour marine pour
  les autres ; « Tout effacer » en `btn-ghost btn-danger-discret` (existant). Bouton refusé : `aria-disabled` et le style
  existant `.btn[aria-disabled="true"]`. Le bouton reste atteignable au clavier et décrit par « Reste à faire… ».
- **Choix** (présents, score de départ, premier service, remplaçant) : la carte de choix du wizard
  (`.choix-carte`) en version sur une ligne. La case ou le bouton radio reste visible, ce qui donne l'état sans la
  couleur. Le focus clavier reste celui de `.choix-carte`.
- **Typographie** : Barlow pour le texte ; Barlow Condensed pour les titres en capitales (`uppercase tracking-wide`),
  les noms sur le terrain, la date et les scores. Les scores sont en chiffres tabulaires (`score-set`, 44 px, marine).
  Équipes : « Eroak (AOVB) », titre « Contre Biarritz A2 (BVB) » en capitales par la classe `uppercase`.
- **Pas de score officiel ni de total de sets** (« 2–1 ») sur la page : seulement le score de chaque set, et « gagné » ou
  « perdu », pour l'équipe. Ainsi rien ne ressemble au score officiel, qui garde sa fenêtre.

## 3. Largeur, cibles, accessibilité

- **Trois colonnes à 360 px** : 360 − 32 (marges de `main`) − 26 (carte `p-3` et bordure) − 12 (contour et marge du
  terrain) − 8 (deux écarts) = 282 px, soit **94 px par case** et 84 px de texte. « Marie-Charlotte De. » passe sur
  deux lignes (« Marie- / Charlotte De. », environ 80 px en Barlow Condensed gras 16 px). Le nom du poste passe parfois
  sur deux lignes (« 6 · Arrière / centre »). À 390 px, 104 px par case. Rien ne déborde : les colonnes valent
  `minmax(0, 1fr)` et le nom peut se couper au besoin. Les rangées s'allongent si un nom prend trois lignes.
  *Vérification en navigateur à faire* : aucun Chromium sans tête ne fonctionne dans cet environnement. En ouvrant la
  maquette avec `#mesure-360` ou `#mesure-390` à la fin de l'adresse, la console liste les débordements, les cibles de
  moins de 48 px et la largeur des cases.
- **Cibles de 48 px** : cases du terrain (72 px), choix sur une ligne (`min-height: 3rem`), tous les boutons de la page
  (`.page-match .btn`, 48 px au lieu des 44 px de l'admin), lien de retour (`min-h-12`). Les boutons « Tourner d'un
  poste » / « Tout effacer » et « Remplacer un joueur » / « Terminer le set » s'empilent sous 640 px : côte à côte, à
  144 px, leur texte ne tiendrait pas, car `.btn` ne passe pas à la ligne.
- **Lecteur d'écran** : chaque case est un `<button>`. Son nom vient de son texte (« 1 · Arrière droit, Au service, Paul
  Da., modifier »). La place suivante est annoncée (« Place suivante : 1, Arrière droit », `aria-live="polite"`). Le banc est un
  groupe nommé par sa consigne (« Joueur pour la place 1 · Arrière droit : »). Les cases du DOM suivent l'ordre visuel
  (4, 3, 2 puis 5, 6, 1) pour que la tabulation suive l'écran ; la case active, elle, suit l'ordre des postes.
- **Focus visible** : boutons et liens daisyUI (contour natif), `.choix-carte:has(:focus-visible)` (existant), contour
  marine de 2 px décalé sur `.terrain-case`.
- **Contrastes vérifiés** : poste en `base-content` à 80 % (8:1 sur blanc, plus de 7:1 sur le fond actif) ; libellés à 70 %
  (5,9:1) ; « Au service » (7:1 environ) ; marine sur jaune des boutons d'action (7:1 environ) ; « Enregistré » en
  `texte-succes` (existant, AA).

## 4. Classes à créer dans `app.css` (définies dans le `<style>` de la maquette, partie 3)

| Classe | Rôle | Réemploi possible |
|---|---|---|
| `.page-match .btn` | boutons de 48 px sur la page match | autres écrans de saisie au gymnase (stats) |
| `.choix-carte.choix-ligne` | variante sur une ligne de `.choix-carte` (case ou radio visible, 48 px) | tout choix court du site public ou du wizard |
| `.terrain`, `.terrain-filet`, `.terrain-grille` | demi-terrain, filet en haut, grille 3 × 2 | rotations, comparaison des rotations (D66), fiche équipe |
| `.terrain-case` (+ `[data-etat="vide"]`, `[aria-current="true"]`) | case d'un poste : remplie, vide, active | saisie des actions (touche du joueur) |
| `.terrain-poste`, `.terrain-nom` | numéro et nom du poste, nom du joueur | idem |
| `.terrain-service` | étiquette « Au service » | serveur en cours pendant la saisie des stats |
| `.terrain-compact` | terrain en lecture (relevé, 48 px de haut) | historique, export |
| `.score-set` | score d'un set en grand | saisie des stats |

À réutiliser tels quels : `.choix-carte` (focus), `.btn[aria-disabled="true"]`, `.btn-danger-discret`, `.texte-succes`,
`badge`/`alert`/`table` daisyUI, la carte `bg-base-100 border border-base-300 rounded-box`, le motif « étiquette en petites
capitales grises » de la fiche équipe (`text-xs font-semibold uppercase tracking-wider text-(--color-base-content)/70`).
Utilitaires `grid-cols-3` et `min-h-12` : absents du CSS compilé actuel, ils sont générés par Tailwind dès qu'un gabarit
les emploie (rien à écrire). Pour les gabarits Twig, un fragment `volley_stats/_terrain.html.twig` (variables : postes,
case active, serveur, compact) servirait aux trois terrains de la page.

## 5. À arbitrer

Questions de parcours ou de libellés, à poser à l'ergonome :
1. **Score du set point par point** (« +1 point », « Annuler le dernier point ») ou score final seulement, saisi à « Terminer
   le set » ? Le point par point donne le serveur et la rotation en direct (base du mode intermédiaire, D66). Le score
   final est plus léger tant que les stats ne sont pas là. La maquette montre le point par point.
2. **« Nous recevons »** : afficher « Servira en premier » sur le poste 2 (celui qui servira après la première rotation) ?
   C'est utile, mais cela ajoute une règle à expliquer. La maquette n'affiche rien dans ce cas.
3. **« Terminer le set »** : confirmation avec le score (« Terminer le set 1 sur 25–23 ? »), ou proposition automatique quand
   un score de fin est atteint (25 ou 15 points, 2 d'écart) ?
4. **Liste des présents** : tous les joueurs de l'équipe (maquette), ou seulement les présents du match précédent avec
   « Ajouter un joueur de l'équipe » (`page-match.md` §3) ? Avec la liste complète, ce bouton ne sert plus.
5. **Libellés de la maquette à valider** : « Page du match », « Reprise du set 1 du 23/10 », « Reste à faire : … », « Sur
   le banc : … », « à changer », « sort », « parti de 0–0 », « Sets joués ».
6. **Message « Annuler le remplacement »** : toast en bas d'écran, comme la confirmation de « Mon équipe », avec un délai
   de 10 s. Il faut un moyen de le rappeler pour les utilisateurs lents (WCAG 2.2.1) : par exemple, le dernier
   remplacement reste annulable depuis le relevé du set.
7. **Tableau « Sets joués »** : sur la page match seulement, ou repris sur la fiche équipe (D66, « stats sur la fiche de
   l'équipe ») ?
