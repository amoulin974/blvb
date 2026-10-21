# Règlement : maquettes des éléments calculés (webdesigner, 20/10/2026)

## 0. Commun
- Chaque bloc : `<section class="reglement-bloc" data-bloc="…" aria-label="… (calculé)">`. `.reglement-bloc` (app.css) neutralise les puces/marges et la largeur 70ch de `#article-reglement`, `container-type: inline-size` (requêtes @container : s'adapte à la largeur de l'article, pas de l'écran).
- Titres internes h3 (phase), h4 (tableau), hors sommaire (tocbot ne lit que les h2) et sans ancre automatique.
- Couleurs : pas de jaune accent ; turquoise secondary (4,15:1) seulement en filet ; texte secondaire base-content/80 ; fonds primary/10. Jamais d'information portée par la seule couleur.
- Flèches : `→` aria-hidden + « vers » pour lecteur d'écran.

## 1. [schema_phases] (phase=CODE facultatif)
Choix : chaque poule liste SES SORTIES (où vont ses équipes), groupées par rang — répond à « je finis 3e de B, où vais-je ? ». L'image actuelle faisait l'inverse (composition de chaque poule d'arrivée) ; la composition n'est gardée que pour les matchs de barrage et les places des tableaux finaux.

Ordinateur :
```
PHASE 1                                                    (h3)
Championnat aller simple · 01/09/2025 → 11/01/2026 · 3 sets gagnants
┌ Poule A · niveau 1 · 8 équipes ──┐ ┌ Poule B · niveau 2 · 8 équipes ──┐
│ du 1er à l'antépénultième        │ │ 1er et 2e → Poule A, phase 2     │
│   → Poule A, phase 2             │ │ du 3e à l'antépénultième         │
│ avant-dernier et dernier         │ │   → Poule B, phase 2             │
│   → Poule B, phase 2             │ │ avant-dernier et dernier → …     │
└──────────────────────────────────┘ └──────────────────────────────────┘
BARRAGES
Un match par barrage · 01/02 → 08/02 · reçoit : poule de niveau supérieur
┌ Barrage AB ──────────────────────────────────────────────┐
│ avant-dernier de la poule A contre 2e de la poule B       │
│ vainqueur → Poule A, phase 2 · perdant → Poule B, phase 2 │
└───────────────────────────────────────────────────────────┘
PHASE 2 … (sorties vers la saison prochaine : « 1er et 2e → niveau 1 la saison prochaine »)
PHASE FINALE
Élimination directe · 27/04 → 22/05/2026 · le mieux classé reçoit
Une phase finale ne change pas les poules des autres phases.
```
- `ol` des phases (l'ordre porte la succession), poules en grille 2 colonnes max (cartes), triées par niveau ; effectif réel sinon « 8 équipes prévues ».

Tableaux à élimination : HTML unique, `ol` des tours puis `ol` des matchs numérotés (« Quart 1 », car les tours suivants y renvoient). En colonnes si la largeur ≥ 40rem et ≤ 4 tours, sinon liste.
```
TABLEAU 1 · ÉLITE DES AS                                           (h4)
9 équipes de la poule A (phase 2), placées selon leur classement.
Les 7 premiers sont exemptés du barrage.
BARRAGE          QUARTS DE FINALE       DEMI-FINALES         FINALE
sem. du 20/04    sem. du 27/04          sem. du 11/05        ven. 22/05/2026 18 h 30
┌ Barrage 1 ┐    ┌ Quart 1 ───────┐    ┌ Demi 1 ────────┐    ┌ Finale ─────────┐
│ 8e        │    │ 1er            │    │ vainq. quart 1 │    │ vainq. demi 1   │
│ 9e        │    │ vainq. barr. 1 │    │ vainq. quart 2 │    │ vainq. demi 2   │
└───────────┘    └────────────────┘    └────────────────┘    └─────────────────┘
                 Quart 2 : 4e / 5e      Demi 2 : …           Petite finale (3e place)
                 Quart 3 : 3e / 6e                           perdant demi 1 / perdant demi 2
                 Quart 4 : 2e / 7e
```
- Places en clair (« 1er », ou « 1er poule A » si plusieurs poules d'origine), jamais « 1er P2.A ». Exemption écrite (pas une case vide). Pas de traits de liaison.

Téléphone (375 px) : une colonne, sans défilement horizontal :
```
PHASE 1
Championnat aller simple
01/09/2025 → 11/01/2026 · 3 sets gagnants
┌ POULE A · niveau 1 · 8 équipes ┐
│ du 1er à l'antépénultième      │
│   → Poule A, phase 2           │
└────────────────────────────────┘
TABLEAU 1 · ÉLITE DES AS
Barrage · sem. du 20/04
  Barrage 1 : 8e contre 9e
Quarts de finale · sem. du 27/04
  Quart 1 : 1er contre vainqueur du barrage 1
  …
Finale · vendredi 22/05/2026, 18 h 30
  Finale : vainqueur demi 1 contre vainqueur demi 2
  Petite finale (3e place) : perdant demi 1 contre perdant demi 2
```
Tout reste ouvert (pas de `details` : gêne l'impression et la recherche). Barrages de fin de saison en dernier, en nommant la phase d'origine.

## 2. [bareme] (phase=)
Un tableau par format distinct (côte à côte sur ordinateur, empilés sur téléphone) :
```
┌ Phases 1 et 2 · 3 sets gagnants ──┐ ┌ Phase finale · 2 sets gagnants ─┐
│ RÉSULTAT                 │ POINTS │ │ Victoire 2-0            │   …    │
│ Victoire 3-0 ou 3-1      │   3    │ │ Victoire 2-1            │        │
│ Victoire 3-2             │   2    │ │ Défaite 1-2             │        │
│ Défaite 2-3              │   1    │ │ Défaite 0-2             │        │
│ Défaite 1-3 ou 0-3       │   0    │ │ Forfait                 │        │
│ Forfait                  │  -1    │
Forfait : l'adversaire marque les points d'une victoire 3-0 (2-0 en 2 sets gagnants).
```
`table` avec `caption`, en-têtes de ligne et de colonne. Phases sans points (élimination, barrage) : une phrase (« Phase finale, barrages : pas de points ; le vainqueur passe au tour suivant (3 sets gagnants). »).

## 3. [departage] (sans paramètre ; phase= → code inapplicable)
```
En cas d'égalité, les critères s'appliquent dans l'ordre :
on ne passe au suivant que si les équipes sont encore à égalité.
 1. Points — Les points du barème de la saison.
 2. Différence de sets — Sets gagnés moins sets perdus, sur toute la poule.
 3. Confrontation directe — Le vainqueur du dernier match joué entre deux équipes à égalité passe devant.
 4. Ordre alphabétique — en dernier recours.
```
Liste numérotée, aides reprises de `CritereDepartage::aide()`.

## 4. [calendrier] (phase=)
Choix : tableau à 2 colonnes, une ligne par semaine, regroupé par phase (la grille semaines × poules de l'image ne tient pas sur téléphone ; une frise ne donne pas la semaine cherchée). Même HTML ordinateur et téléphone.
```
SEMAINE DU   │ PROGRAMME
PHASE 1 · championnat · 01/09/2025 → 11/01/2026
13/10/2025   │ Journée 1, toutes les poules
20/10 → 02/11│ Pas de match : vacances de la Toussaint      (fond gris + icône + texte)
03/11/2025   │ Journée 2, toutes les poules
15/12/2025   │ Journée 8, poules C et D
05/01/2026   │ Poules A et B : journée 9 · poules C et D : j. 10
PHASE FINALE · 20/04 → 22/05/2026
20/04/2026   │ Barrages des tableaux 3 et 4
27/04/2026   │ Quarts de finale, tous les tableaux
▌22/05/2026  │ Journée des finales : finales, tous les tableaux
▌            │ vendredi 22 mai 2026, 18 h 30, gymnase Nelson-Paillou (Angresse)
```
Phases qui se chevauchent : chaque phase garde son groupe (une semaine peut apparaître deux fois). Dates courtes (dd/MM/yy) sur téléphone.

## 5. Encart admin (code inapplicable)
```
▌ (!) Élément calculé non affiché (visible des administrateurs seulement)
▌ [calendrier phase=FIN] : aucune phase « FIN » dans cette saison.
▌ Valeurs possibles : P1, P2, F.            [Modifier le règlement]
```
Apparence de `admin/_alerte.html.twig` (warning), `role="note"`, masqué à l'impression.

## 6. Impression
Sommaire, encart admin, en-tête et pied masqués ; pas de coupure dans une carte, un match, un groupe du calendrier ; tableaux finaux en colonnes si ≤ 4 tours.

## Questions du webdesigner
Pour l'ergonome :
1. Sorties par poule d'origine (« où vais-je ? ») ou composition par poule d'arrivée (comme l'image actuelle) ?
2. Saison prochaine : « niveau 1 » est-il compris du public (la saison 2025 a des niveaux 0 à 3) ? Ou « niveau de la poule A » ?
3. Les aides du départage sont écrites pour l'admin : faut-il un libellé public ?
4. Préfixes « monte / reste / descend » devant les sorties vers la saison prochaine ?
Pour l'utilisateur :
5. Le barème doit-il montrer les points du match interrompu (règle 1R), ou cela reste dans le texte libre ?
6. Tableaux : afficher les noms des équipes une fois connues ? (proposition : non, le règlement décrit le format)
7. (technique) rendu de la source d'une règle sans sa destination ; calcul des places d'un tableau depuis les règles.
8. Jours fériés absents des données (seules les fermetures le sont) : laissés au texte libre ?

---

## Relecture de l'ergonome (20/10/2026) : « oui avec conditions », intégrées à la réalisation

**Commun (indispensable)** : « → » seulement pour une destination ; une période s'écrit « du 01/09/2025 au 11/01/2026 ».
Aucune abréviation (« semaine du 20/04 », « vainqueur du barrage 1 », « journée 10 »).

**Schéma des phases**
- Indispensable : pas d'« antépénultième » (`Place::enClair` est écrit pour l'admin) ; variante publique : rangs en
  chiffres si l'effectif est connu (« du 1er au 6e », « 7e et 8e »), sinon « les 2 derniers », « tous sauf les 2 derniers ».
- Indispensable : dans une poule qui a les deux, sous-titres « Phase finale » et « Saison prochaine » séparés.
- Indispensable : « Barrage poules A et B » (pas « Barrage AB ») ; « L'équipe de la poule la plus haute reçoit. »
- Souhaitable : « Les 7 premiers vont directement en quart de finale. » ; « 1er de la poule A » ; « La phase finale ne
  compte pas pour les montées et descentes. »
- Réponses aux questions 1 à 4 : sorties par poule d'origine (oui) ; jamais « niveau 1 » : « au niveau de la poule A la
  saison prochaine » (poule du même niveau), à défaut « niveau 2 (sur 4, 1 = le plus haut) », jamais le niveau 0 ; aides
  du départage utilisables telles quelles ; mot « monte / reste / descend » (calculé en comparant les niveaux, rien si un
  niveau manque), ex. « 1er et 2e : montent en poule A (phase 2) ».

**Barème** : deux lignes « Victoire par forfait » et « Forfait (équipe absente) » (plus de note) ; une phase à
élimination n'a pas de tableau, seulement la phrase.

**Départage** : introduction « Les équipes d'une poule sont classées selon ces critères, dans l'ordre. On ne passe au
critère suivant qu'en cas d'égalité. » ; aide du 4e critère reprise telle quelle.

**Calendrier** : nom de la fermeture tel que saisi (données 2025 à corriger : « Vacances de Noël »…) ; « Cette semaine »
écrit sur la semaine en cours ; « lundi 13/10 » (facultatif).

**Encart admin** : « Élément calculé non affiché au public », « Cet encart n'est visible que des administrateurs. »,
valeurs possibles avec leur nom (« P1 (Phase 1), P2 (Phase 2), F (Finale) »).

**Impression** : nom de la saison et « Mis à jour le … » imprimés.

**Données 2025 relevées** (à corriger dans le back-office, hors code) : noms des fermetures ; tableaux de la phase finale
nommés « Poule A » à « Poule D » ; pas d'option « journée des finales » (date et gymnase de la finale écrits en dur dans
l'ancien gabarit : à renseigner avant la reprise R5) ; une journée de la poule C (phase F) le 25/05/2026, après la finale
du 22/05.
