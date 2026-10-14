# Wizard de création de saison : écrans de la mise en place (lot 5)

Wireframe de l'étape **Poules**, à faire relire par l'ergonome et le webdesigner avant réalisation.
Cahier des charges : D35 à D37 (`decisions.md`). Les étapes Journées et Matchs seront dessinées après
validation de celle-ci.

## 1. Contexte technique (état du code)

- `Poule.equipes` : relation plusieurs-à-plusieurs avec `Equipe`. Une équipe n'a **pas** de lien direct
  avec une saison ; elle existe globalement (nom, club, gymnase). Elle est « dans la saison » parce
  qu'elle figure dans une poule d'une phase.
- À la copie d'une saison terminée, `CopieSaison::preRemplir` place déjà les équipes dans les poules de
  la première phase d'après les montées et descentes « saison suivante » (D22). Si la saison copiée n'est
  pas terminée ou depuis un modèle, les poules sont vides, avec un avertissement.
- `Poule.nbEquipesPrevu` (facultatif) sert de repère : écart = avertissement non bloquant (D30).
- Ce qui existe déjà côté admin : formulaire de poule avec une liste à choix multiples des équipes
  (`PouleType`), peu adaptée à une composition guidée.
- Une équipe ne peut être que dans **une** poule par phase.
- Il n'y a pas d'état « équipe active » : la liste de toutes les équipes de la base comprend donc des
  équipes qui ne jouent plus.

## 2. Place dans le parcours

Stepper en deux groupes (D29.14) :

```
Organisation  ✓ Départ · ✓ Saison · ✓ Phases · ✓ Passages · ✓ Classement · ✓ Vérification
Mise en place   ● Poules  ○ Journées  ○ Matchs
```

- Accessible une fois la saison **validée** (fin de l'étape 6). Le message de validation existant propose
  déjà « Prochaine étape : composer les poules de la Phase 1 ».
- Concerne **une phase à la fois** : la première phase non clôturée dont les poules sont à composer.
  Après la clôture de la phase précédente, la phase suivante rouvre Poules → Journées → Matchs (D37).
- Boutons du bas, comme les étapes 1 à 6 : « Précédent », « Enregistrer et quitter » (discret),
  « Suivant : Journées » (principal). Chaque changement est enregistré à chaque étape (D34).

## 3. Écran « Poules » : ordinateur

```
┌ Nouvelle saison : Saison 2026-2027 ───────────────── [Brouillon / Saison validée] ┐
│ Organisation  ✓ ✓ ✓ ✓ ✓ ✓      Mise en place  ● Poules ○ Journées ○ Matchs         │
├────────────────────────────────────────────────────────────────────────────────────┤
│ Poules de la Phase 1                                                               │
│ 34 équipes placées sur 36 prévues. 2 équipes ne sont dans aucune poule.            │
│                                                                                    │
│ ⚠ 2 équipes ne sont dans aucune poule. (lien : « Voir les équipes à placer »)      │
│ ⚠ Poule C : 9 équipes pour 8 prévues.                                              │
│                                                                                    │
│ ┌ Poule A · niveau 1 ───────────── 8 / 8 ✓ ┐ ┌ Poule B · niveau 2 ─────── 8 / 8 ✓ ┐ │
│ │ Eroak (AOVB)        Montée de B  [Déplacer▾][Retirer]│ │ ...                     │ │
│ │ Kayou (AOVB)        Même niveau  [Déplacer▾][Retirer]│ │                         │ │
│ │ ...                                              │ │                             │ │
│ │ [+ Ajouter une équipe]                           │ │ [+ Ajouter une équipe]      │ │
│ └──────────────────────────────────────────────────┘ └─────────────────────────────┘ │
│ ┌ Poule C · niveau 3 ──────────── 9 / 8 ⚠ ┐ ┌ Poule D · niveau 4 ──────── 7 / 8 ⚠ ┐ │
│ └──────────────────────────────────────────┘ └──────────────────────────────────────┘ │
│                                                                                    │
│ ┌ Équipes à placer (2) ───────────────────────────────────────────────────────────┐ │
│ │ Samoussas (TOSSE)   Nouvelle        [Placer dans : Poule ▾] [Placer]            │ │
│ │ Tosse 2 (TOSSE)     Nouvelle        [Placer dans : Poule ▾] [Placer]            │ │
│ └──────────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                    │
│ [Précédent]            [Enregistrer et quitter]            [Suivant : Journées]     │
└────────────────────────────────────────────────────────────────────────────────────┘
```

### Blocs

1. **Bilan** : phrase de synthèse (« 34 équipes placées sur 36 prévues ») et alertes (D30) : effectifs
   différents du prévu, équipes à placer. Pas de blocage : on peut passer aux journées avec des
   écarts (avertissement), mais pas avec une poule **vide**.
2. **Une carte par poule** (grille 2 colonnes à partir de `lg`, 1 colonne sinon) :
   - titre : nom, niveau, effectif « réel / prévu » avec icône d'état (✓ égal, ⚠ différent) ;
   - une ligne par équipe : nom affiché, **origine** (« Montée de la Poule B », « Descente de la
     Poule A », « Même niveau », « Nouvelle », « Ajoutée à la main »), actions « Déplacer vers… » (liste
     des autres poules) et « Retirer » (renvoie l'équipe dans « Équipes à placer », ne la supprime pas) ;
   - « Ajouter une équipe » : liste de recherche parmi les équipes à placer et les équipes de la base
     (voir question 2).
3. **Équipes à placer** : équipes que la saison attend mais qui ne sont dans aucune poule de la phase
   (équipes retirées, ou nouvelles équipes créées à la main). Chaque ligne a une liste « Placer dans… ».
   Lien « Créer une nouvelle équipe » vers la page existante, qui revient ensuite au wizard.
4. **Pas de glisser-déposer** (D29.15) : uniquement des listes et des boutons.

## 4. Écran « Poules » : téléphone

- Une carte par poule, repliée par défaut, avec son effectif dans l'en-tête ; une seule ouverte à la fois.
- Chaque équipe : nom sur toute la largeur, puis boutons « Déplacer » et « Retirer » (cibles de 44 px).
- « Équipes à placer » **en haut**, avant les poules, tant qu'elle n'est pas vide.
- Barre du bas collante (comme les étapes 1 à 6).

## 5. États et messages

| Situation | Affichage |
|---|---|
| Saison copiée d'une saison terminée | Origines renseignées ; bandeau info : « Les équipes ont été placées d'après les montées et descentes de la saison 2025-2026. Vérifiez-les. » |
| Saison copiée d'une saison non terminée, ou modèle | Poules vides, toutes les équipes de la saison copiée dans « Équipes à placer » ; bandeau : « Les montées et descentes ne sont pas connues : composez les poules. » |
| Poule vide | Erreur bloquante : « La Poule D n'a aucune équipe. » ; « Suivant » désactivé avec la raison écrite (comme « Valider la saison », D29.18) |
| Effectif ≠ prévu | Avertissement non bloquant (D30) |
| Équipe déjà dans une autre poule de la phase | Impossible : « Déplacer » la retire de l'ancienne poule |
| Saison lancée (matchs, classements) | Écran en lecture seule : composition affichée, modification refusée avec la raison (D34) |

## 6. Questions pour les relecteurs

1. **Équipes proposées** : comme une équipe n'a pas de lien direct avec la saison, que proposer dans
   « Ajouter une équipe » ? (a) toutes les équipes de la base, triées par club ; (b) d'abord celles de la
   saison copiée, puis les autres sous un lien « Voir toutes les équipes » ; (c) seulement celles de la
   saison copiée.
2. **Équipes de la saison copiée qui ne reviennent pas** : faut-il une action explicite « Ne participe
   plus », ou suffit-il de ne pas les placer ?
3. **Origine d'une équipe** : est-ce utile à l'écran (« Montée de la Poule B ») ou du bruit ? Sur téléphone,
   faut-il la garder ?
4. **« Déplacer vers… »** : liste déroulante dans chaque ligne, ou un seul menu par équipe qui s'ouvre ?
5. **Vue d'ensemble** : faut-il un résumé en tableau des effectifs par poule en haut (niveau, prévu,
   réel), en plus des cartes, quand il y a plus de 4 poules ?
6. **Libellés** : « Équipes à placer », « Retirer », « Déplacer vers… », « Placer dans… ». À corriger si
   le vocabulaire du bureau est différent (« affecter », « répartir » ?).
7. **Accessibilité** : annonce des changements pour un lecteur d'écran après un déplacement (région
   `aria-live`) ; ordre de tabulation ; focus après « Retirer ».

## 7. Relecture (ergonome et webdesigner, 14/10/2026) : version retenue

Les corrections ci-dessous **remplacent** ce qui les contredit plus haut (voir D38).

**Parcours et mécanique**
- Bilan : « 34 équipes placées sur 36 prévues. 2 équipes ne sont dans aucune poule. » (accords calculés).
  Sans « nombre prévu » : « 8 équipes », sans « / ».
- « Déplacer » : un bouton par ligne qui déplie (`<details>`, sans JavaScript) la liste « Vers la poule… »
  et un bouton « Valider ». Même mécanique pour « Placer dans… » des équipes à placer. Chaque liste est
  liée à son bouton ; libellés accessibles par ligne (« Déplacer Eroak (AOVB) vers la poule… »).
  Aucune liste n'agit dès le choix.
- « Retirer de la poule » (aide : « L'équipe revient dans Équipes à placer »), sans icône de poubelle,
  texte visible aussi sur téléphone.
- « Ajouter une équipe » : d'abord les équipes de la saison copiée, puis « Voir toutes les équipes ».
  Pas d'action « Ne participe plus » ; aide : « Une équipe laissée ici ne jouera pas cette phase. »
- Origines : « Montée de la Poule B » / « Descente de la Poule A » (flèche haut ou bas, sans couleur),
  « Reste en Poule C », « Nouvelle équipe ». Conservées sur téléphone, en petit texte sous le nom.
- Après chaque action : message `role="status"` (« Eroak (AOVB) est dans la Poule B. ») et focus sur
  la carte concernée (ancre `#poule-N`). Pas de `aria-live`.
- Bandeau de copie : « Vérifiez ce placement. »
- « Suivant » désactivé s'il reste une poule vide, avec la raison écrite (« La Poule D n'a aucune
  équipe. Ajoutez-en une pour continuer. »).
- « Créer une nouvelle équipe » : prévenir « Vous reviendrez ici ensuite. »
- Lecture seule (saison lancée) : la raison s'affiche en haut de page.
- Toujours afficher le club à côté du nom d'une équipe.

**Apparence**
- Alertes : une seule alerte à liste (`warning` pour les écarts et les équipes à placer, `info` pour le
  bandeau de copie, `error` pour une poule vide), via `admin/_alerte.html.twig`, avec ancres.
- États ✓ / ⚠ : icône (`coche` / `alerte`) et texte caché (« écart », « conforme »), jamais la couleur
  seule ; effectif en badge `badge-outline`, `badge-ecart` si écart.
- Titre de carte : `font-display uppercase tracking-wide text-(--color-primary)` ; niveau et effectif
  en texte secondaire. Cartes de poule : `rounded-box border border-base-300 p-3`, pas de
  `.admin-carte` imbriquée ; lignes en `ul` avec `divide-y`.
- Boutons de ligne : `btn-ghost btn-sm`, 44 px (`.btn-deplacer`) ; « Placer » en `btn-primary btn-sm` ;
  aucun jaune dans les lignes.
- Téléphone : cartes en `<details name="poules">` (une seule ouverte, en-tête de 44 px avec nom, effectif
  en toutes lettres et chevron) ; « Équipes à placer » avant les poules dans le DOM, bordure primaire,
  ouverte tant qu'elle contient des équipes ; boutons `flex-1` ; vérifier l'absence de défilement
  horizontal du stepper à 9 éléments.
- Synthèse des effectifs au-delà de 4 poules : **tableau** (Poule · Niveau · Prévu · Réel · État),
  `table-admin`, empilé sur téléphone.

**À créer** : partial `admin/saisonwizard/_poule_carte.html.twig` ; icône de retrait (« croix ») si
aucune icône proche n'existe ; classe `.badge-origine` seulement si le motif revient aux étapes
Journées et Matchs.
