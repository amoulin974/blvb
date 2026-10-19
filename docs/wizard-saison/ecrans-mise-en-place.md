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

---

# Étape « Journées » (lot 5) : proposition à relire

Cahier des charges : D35, D37. Réutilise `JourneeService::creerJournees` (qui délègue au générateur du
format de la phase) et le calendrier existant (`admin_journee_getjourneecalendar`) pour les retouches.

## 8. Contexte technique

- Une journée appartient à **une poule**. Championnat (A1) : une journée par semaine, du lundi au
  dimanche, en sautant les semaines d'indisponibilité de la saison ; nombre = `n-1` (n pair) ou `n`
  (n impair). Si la première poule a le même effectif et des journées, ses dates sont recopiées.
  Élimination (B1) : une journée par tour (barrage, quarts, demies, finale), nommées.
- Erreurs déjà détectées par les générateurs : « phase trop courte » (avec ou sans indisponibilités),
  « moins de deux équipes ».
- `creerJournees` **supprime** les journées existantes et, par cascade, leurs matchs et scores :
  l'écran doit prévenir quand des matchs existent.
- Ordre des poules : générer d'abord la plus grande évite que la copie de dates de la première poule
  donne un nombre de journées faux ; à vérifier (le générateur ne copie que si l'effectif est égal).

## 9. Place dans le parcours

```
Mise en place   ✓ Poules  ● Journées  ○ Matchs
```
- Accessible quand la composition des poules est enregistrée (aucune poule vide).
- Boutons du bas : « Précédent : Poules », « Enregistrer et quitter », « Suivant : Matchs » (désactivé
  avec la raison écrite tant qu'une poule n'a aucune journée).
- Si les poules changent après coup (équipe ajoutée ou retirée), le bilan signale les poules dont le
  nombre de journées ne correspond plus à l'effectif.

## 10. Écran : ordinateur

```
┌ Journées de la Phase 1 ────────────────────────────────────────────────────────────┐
│ Du 14/09/2026 au 31/01/2027 · 20 semaines, dont 3 d'indisponibilité (Toussaint,    │
│ Noël, ...).  4 poules : 3 avec leurs journées, 1 sans journée.                      │
│                                                                                    │
│ ⚠ Poule D : aucune journée pour l'instant.                                         │
│ ⚠ Poule C : 9 journées créées, 9 attendues, mais la dernière tombe le 24/01 …      │
│                                                                                    │
│ [Générer les journées manquantes]   (une action pour toutes les poules sans journée)│
│                                                                                    │
│ ┌ Poule A · 8 équipes ──── 7 / 7 journées ✓ ┐ ┌ Poule B · 8 équipes ── 7 / 7 ✓ ┐   │
│ │ J1  14/09 → 20/09                         │ │ J1 …                          │   │
│ │ J2  21/09 → 27/09                         │ │                               │   │
│ │ …                                         │ │                               │   │
│ │ [Ajuster dans le calendrier] [Regénérer]  │ │ [Ajuster…] [Regénérer]        │   │
│ └───────────────────────────────────────────┘ └───────────────────────────────┘   │
│ ┌ Poule C · 9 équipes ─── 9 / 9 ✓ ┐ ┌ Poule D · 9 équipes ─── 0 / 9 ⚠ ┐            │
│ │ …                              │ │ Aucune journée. [Générer]        │            │
│ └────────────────────────────────┘ └──────────────────────────────────┘            │
│                                                                                    │
│ [Précédent : Poules]        [Enregistrer et quitter]        [Suivant : Matchs]     │
└────────────────────────────────────────────────────────────────────────────────────┘
```

### Blocs
1. **Bilan** : période de la phase, nombre de semaines utilisables (indisponibilités retirées), nombre
   de poules avec / sans journées. Lien vers les périodes de fermeture de la saison (étape 2 du wizard).
2. **Alertes** (une seule alerte à liste, `admin/_alerte.html.twig`) : poule sans journée (bloquant pour
   « Suivant »), nombre de journées ≠ attendu pour l'effectif actuel, journée en dehors des dates de la
   phase, journée sur une semaine d'indisponibilité (avertissements, non bloquants).
3. **Une carte par poule** : titre (nom, effectif, « créées / attendues » avec icône d'état), liste des
   journées (numéro, nom pour les phases à élimination, dates), actions :
   - « Générer les journées » si aucune ;
   - « Ajuster dans le calendrier » : page existante, retour au wizard ensuite ;
   - « Régénérer » : confirmation (`admin/_confirmation.html.twig`) qui dit ce qui sera perdu (journées,
     et matchs et scores s'il y en a). Les équipes sont conservées.
4. **« Générer les journées manquantes »** : une action pour toutes les poules sans journée ; ne touche
   jamais aux poules qui en ont déjà.
5. Phase à élimination : même écran, journées nommées (« Barrage », « Quarts de finale », …), tableau
   de dates simplifié ; pas de « semaines d'indisponibilité » dans le bilan si le format ne les saute pas.

## 11. Écran : téléphone
- Cartes repliées (`<details name="poules">`), comme l'étape Poules ; effectif « 7 sur 7 journées » en
  toutes lettres dans l'en-tête ; alerte des poules sans journée en haut.
- Dates affichées « lun. 14 sept. → dim. 20 sept. » ; une ligne par journée, pas de tableau.
- « Ajuster dans le calendrier » : le calendrier plein écran n'est pas adapté au téléphone ; l'action
  reste disponible mais l'écran propose en premier « Régénérer » et la liste.

## 12. États et messages
| Situation | Affichage |
|---|---|
| Aucune poule n'a de journée | Info : « Les journées ne sont pas encore créées. » + bouton « Générer les journées manquantes » |
| Génération réussie | Succès : « 7 journées créées pour la Poule A. » (une ligne par poule) |
| Phase trop courte | Erreur de la poule concernée, texte du générateur reformulé : « La phase est trop courte : il faut 9 semaines, il n'y en a que 7 une fois les fermetures retirées. Allongez la phase (étape Phases) ou réduisez les fermetures. » |
| Poule avec matchs | « Régénérer » affiche le nombre de matchs et scores perdus ; sinon texte court |
| Saison lancée (matchs saisis) | Lecture seule, comme l'étape Poules (D34) |

## 13. Questions pour les relecteurs
1. **Génération groupée ou par poule** : un seul bouton « Générer les journées manquantes » suffit-il, ou
   faut-il aussi un bouton par poule ? Faut-il générer à l'ouverture de l'écran sans clic ?
2. **Calendrier existant** : le renvoyer tel quel (page hors wizard) ou l'embarquer dans l'étape ?
3. **Des journées identiques pour toutes les poules** : la copie de dates entre poules de même effectif
   est invisible aujourd'hui. Faut-il l'expliquer à l'écran (« Les poules de 8 équipes ont les mêmes
   dates ») ?
4. **Libellés** : « Générer », « Régénérer », « Ajuster dans le calendrier », « journées attendues ».
5. **Alertes** : les semaines d'indisponibilité chevauchées sont-elles un avertissement ou une erreur ?
6. **Accessibilité** : liste de dates en `ul` ou tableau ; annonces après génération.

## 14. Relecture de l'étape Journées (ergonome et webdesigner, 14/10/2026)

Verdict des deux : cohérent avec l'étape Poules, à réaliser avec les corrections ci-dessous.
Les points marqués **À trancher** divergent entre les relecteurs.

**Parcours et mécanique (accord)**
- Boutons : « Générer les journées manquantes » (groupé, `btn-primary`, sous l'alerte, absent quand plus
  aucune poule n'est sans journée) **et** « Générer » par poule sans journée. Rien n'est généré à
  l'ouverture de l'écran.
- Le calendrier existant est renvoyé tel quel, avec un lien « Retour à l'étape Journées ».
- Refaire les journées : bouton `btn-ghost`, jamais `btn-error` ; la confirmation (`admin/_confirmation.html.twig`)
  chiffre ce qui est perdu (« 7 journées et 28 matchs, avec leurs scores »), texte court s'il n'y a pas de
  matchs ; bouton de confirmation « Supprimer et refaire ».
- Chevauchement d'une semaine de fermeture : avertissement non bloquant, qui nomme la journée et la fermeture.
- Alerte de poule sans journée : bloquante pour « Suivant : Matchs », raison écrite à côté (« La Poule D
  n'a aucune journée. Générez-les pour continuer. »). En saison vierge : alerte `info` seule + bouton,
  pas d'erreur en plus.
- Effectif : « 7 journées sur 9 prévues » (même tournure que « 34 sur 36 prévues »), accords calculés.
- Mot « fermeture » comme à l'étape 2 (pas « indisponibilité »). Une phrase d'introduction relie
  « journée » et « semaine » (une journée = une semaine de matchs).
- Alertes : phrases entières (« La Poule C se termine le 24/01, après la fin de la phase (17/01). »),
  « phase trop courte » : poule nommée, lien « Modifier la phase » dans l'alerte, message repris dans la carte.
- Dates identiques : une aide courte « Mêmes dates que la Poule A. » **seulement** quand la copie a eu lieu
  (effectif égal) ; ne rien promettre sinon.
- Après un changement de composition des poules : alerte visible dès l'arrivée, aucun écrasement automatique.
- Phase à élimination : une phrase d'aide sur le sens des noms de tour.
- Après action : message `role="status"` et focus sur la carte (`#poule-N`), pas d'`aria-live`.
- Lecture seule (saison lancée) : alerte `info` en haut, boutons masqués.

**Apparence (accord)**
- Partial `_journees_carte.html.twig` calqué sur la carte de poule ; `<details id="poule-N" name="poules">`
  avec le contrôleur `poules-pliables` existant ; toutes ouvertes à partir de `lg`.
- Carte sans journée : ouverte, `border-2 border-(--color-primary)`, « Générer » en `btn-primary btn-sm min-h-11`.
- Badge `badge-outline`, `badge-ecart` si écart, icône + texte caché (« conforme », « incomplète »,
  « aucune journée »).
- Liste de dates en `ul divide-y`, numéro en `font-display`, nom de tour sans largeur fixe ; pas de
  hauteur maximale. Tableau de synthèse seulement au-delà de 4 poules.
- Mobile : actions `flex-col`, boutons pleine largeur, « Enregistrer et quitter » sur une seconde ligne ;
  stepper à 9 éléments sans défilement horizontal.

**Tranché par l'utilisateur (14/10/2026, D39)** : libellés « Régénérer les journées » et « Ajuster dans le
calendrier » (webdesigner) ; dates « 14/09 → 20/09 » sur tout l'écran (année si elle change) ; lien du
calendrier conservé sur téléphone avec la phrase d'aide. Reste à trancher : le point 4.

**Options étudiées**
1. **Libellés** : « Refaire les journées » (ergonome) ou « Régénérer les journées » (webdesigner) ;
   « Modifier les dates » (ergonome) ou « Ajuster dans le calendrier » (webdesigner).
2. **Format des dates** : « 14/09/2026 » partout (ergonome) ou « 14 sept. → 20 sept. » avec `<time>`
   (webdesigner) ; le jour de la semaine est inutile pour une journée lundi-dimanche.
3. **Calendrier sur téléphone** : lien relégué ou masqué (ergonome) ou conservé avec la phrase « Le calendrier
   s'utilise mieux sur un grand écran. » (webdesigner).
4. **Ordre de génération** (§8, dernier point) : à vérifier dans le code avant de développer, avec un test
   sur des poules de tailles différentes.

---

# Étape « Matchs » (lot 5) : proposition à relire

Cahier des charges : D35, D37. Réutilise `PartieService` (`creerCalendrierOptimise` pour la phase,
`creerCalendrierOptimisePourPoule` pour une poule, `createCalendar` sans optimisation), la page d'optimisation
(`admin_phase_optimiser`) et le calendrier des matchs (`admin_partie_getpartiecalendar`).

## 15. Contexte technique

- Championnat : l'optimiseur traite **toutes les poules de la phase ensemble** (gymnases partagés), choisit qui
  reçoit pour éviter les surcharges de gymnase puis limiter les « breaks » ; il peut durer une dizaine de
  secondes. Élimination : matchs générés par le format, hors optimiseur, placés comme occupation fixe.
- Chaque génération **supprime les matchs existants** de la poule (ou de la phase), scores et réglages manuels compris.
- Matchs prévus pour une poule de `n` équipes : championnat `n(n-1)/2`, élimination `n-1`.
- Le rapport renvoyé donne, par poule, le nombre de breaks et la liste des surcharges de gymnase.
- Aujourd'hui, la **clôture d'une phase** crée aussitôt les journées et les matchs de la phase suivante
  (`PhaseController::cloturer`). Cela contredit D37 (retour au wizard Poules → Journées → Matchs) : voir question 4.

## 16. Place dans le parcours

```
Mise en place   ✓ Poules  ✓ Journées  ● Matchs
```
- Accessible quand chaque poule a ses journées. Boutons du bas : « Précédent : Journées », « Enregistrer et
  quitter », « Terminer » (principal) qui renvoie à la saison avec « La Phase 1 est prête. ».
- « Terminer » est désactivé, raison écrite, tant qu'une poule n'a aucun match.

## 17. Écran : ordinateur

```
┌ Matchs de la Phase 1 ──────────────────────────────────────────────────────────────┐
│ 4 poules, 118 matchs prévus. 90 matchs créés. 1 poule sans match.                   │
│                                                                                    │
│ ⚠ Poule D : aucun match pour l'instant.                                            │
│ ⚠ Gymnase G1 surchargé le 21/09 (3 matchs pour 2 prévus).                          │
│ ⚠ 2 équipes n'ont pas de gymnase : Samoussas (TOSSE), Tosse 2 (TOSSE).             │
│                                                                                    │
│ [Générer les matchs de la phase]  (optimisation de toutes les poules ; ≈ 10 s)     │
│ Voir l'alternance domicile / extérieur et la charge des gymnases                   │
│                                                                                    │
│ ┌ Poule A · 8 équipes ── 28 sur 28 matchs ✓ ┐ ┌ Poule B · 8 équipes ── 28 / 28 ✓ ┐ │
│ │ 3 breaks · aucune surcharge               │ │ …                                │ │
│ │ [Modifier dans le calendrier]             │ │                                  │ │
│ │ [Régénérer les matchs de la poule]        │ │                                  │ │
│ └───────────────────────────────────────────┘ └──────────────────────────────────┘ │
│ ┌ Poule D · 9 équipes ── aucun match ⚠ ┐                                           │
│ │ [Générer les matchs de la poule]     │                                           │
│ └──────────────────────────────────────┘                                           │
│ [Précédent : Journées]   [Enregistrer et quitter]   [Terminer]                      │
└────────────────────────────────────────────────────────────────────────────────────┘
```

### Blocs
1. **Bilan** : matchs créés sur matchs prévus, poules sans match.
2. **Alertes** (une seule alerte à liste) : poule sans match (bloquant pour « Terminer »), surcharges de
   gymnase du dernier calcul, équipes sans gymnase (leurs matchs à domicile n'ont pas de lieu), nombre de
   matchs différent du prévu.
3. **Action principale** « Générer les matchs de la phase » : pour toutes les poules à la fois (obligatoire
   pour partager les gymnases). Confirmation chiffrée si des matchs existent (nombre de matchs et de scores
   perdus). Mention « patientez sans recharger la page » pendant le calcul.
4. **Cartes de poule** : effectif, « 28 sur 28 matchs », breaks et surcharges de la poule, « Modifier dans le
   calendrier » (calendrier existant), « Régénérer les matchs de la poule » (confirmation chiffrée), ou
   « Générer les matchs de la poule » si elle n'en a aucun.
5. **Lien** vers la page d'optimisation existante (alternance et charge des gymnases) pour l'analyse fine.
6. Phase à élimination : même écran ; une phrase explique que le tableau est placé dans l'ordre du
   classement de la phase précédente (têtes de série) ; pas de breaks.

## 18. Téléphone
- Cartes repliées, effectif en toutes lettres ; alertes avant les cartes ; boutons pleine largeur ; le lien
  d'analyse et le calendrier gardent l'aide « s'utilise mieux sur un grand écran ».

## 19. États et messages
| Situation | Affichage |
|---|---|
| Aucun match | Info « Les matchs ne sont pas encore créés. » + bouton « Générer les matchs de la phase » |
| Génération réussie | Succès : « 90 matchs créés pour 4 poules. » ; surcharges en avertissement |
| Poule à moins de deux équipes | « La Poule E n'a pas assez d'équipes pour avoir des matchs. » |
| Journées absentes ou en nombre inattendu | Renvoi vers l'étape Journées (lien dans l'alerte) |
| Phase lancée (scores saisis, clôturée) | Lecture seule comme les étapes Poules et Journées |

## 20. Questions pour les relecteurs et pour l'utilisateur
1. **Durée du calcul** : suffit-il de prévenir (« patientez ») comme la page d'optimisation actuelle, ou faut-il
   un traitement en arrière-plan avec écran d'attente ?
2. **Génération groupée seule ou aussi par poule** : par poule, l'optimiseur ne regarde plus que les autres
   poules déjà placées ; est-ce acceptable à ce niveau de l'écran ?
3. **Rapport** : afficher breaks et surcharges de la dernière génération (non conservés en base) ou les
   recalculer à l'affichage à partir des matchs existants (déjà fait par `OptimisationService`) ?
4. **Clôture d'une phase** : faut-il retirer la création automatique des journées et des matchs de la phase
   suivante (D37) pour renvoyer au wizard Poules → Journées → Matchs, ou garder l'automatique et n'ouvrir le
   wizard que pour les retouches ?
5. **Équipe sans gymnase** : avertissement seul, ou blocage de « Terminer » ?
6. **Libellés** : « Générer les matchs de la phase », « Régénérer les matchs de la poule », « Modifier dans le
   calendrier », « Terminer ».

## 21. Relecture de l'étape Matchs (ergonome et webdesigner, 14/10/2026)

Verdict des deux : cohérent avec les étapes Poules et Journées, réutilise leurs composants (`journees.html.twig`).

**Parcours et mécanique (accord)**
- **Attente du calcul** : prévenir, sans traitement en arrière-plan. La confirmation annonce « une dizaine de
  secondes » ; au clic, le bouton passe en « Calcul en cours… » (désactivé, `loading-spinner`) avec une zone
  `role="status"` visible, pour éviter le double envoi. À revoir si le calcul dépasse 20 à 30 s.
- **Transaction** : suppression puis création des matchs dans une transaction, avec un message d'échec
  « Le calcul n'a pas abouti. Aucun match n'a été modifié. » (à vérifier dans le code).
- **Rapport** : breaks et surcharges **recalculés à l'affichage** (`OptimisationService`), pas conservés.
- **Génération par poule** : « Générer les matchs de la poule » seulement pour une poule sans match, avec
  l'aide « Ce calcul tient compte des matchs déjà créés dans les autres poules. » ; « Régénérer » reste
  possible, la confirmation précise que les autres poules ne bougent pas.
- **Équipe sans gymnase** : avertissement qui nomme l'équipe (« Nom (ABRÉV) ») et la conséquence (« ses
  matchs à domicile n'ont pas de lieu »), avec lien ; ne bloque pas « Terminer ». Seule une poule sans match bloque.
- **Alertes** : au plus deux alertes `_alerte` (erreur : poules sans match, journées absentes ; avertissement :
  surcharges, équipes sans gymnase, écart de nombre), lignes en ancres `#poule-N`.
- **Libellés** : « Générer les matchs de la phase » (« Régénérer… » quand des matchs existent, en
  `btn-ghost` via confirmation), « Régénérer les matchs de la poule », « Ajuster dans le calendrier »
  (comme l'étape Journées), « Terminer la mise en place » (« Terminer » sur téléphone), « déplacements de
  suite » au lieu de « breaks », lien « Analyser l'alternance et la charge des gymnases ».
- **Messages** : bilan « 90 matchs créés sur 118 prévus. 1 poule sans match. » ; confirmation chiffrée
  (matchs, et scores seulement s'il y en a ; `irreversible` si des scores sont saisis) ; surcharge en phrase
  entière (« 3 matchs pour 2 possibles », date JJ/MM) ; succès « 90 matchs créés pour 4 poules. ».
- **Apparence** : cartes `<details name="poules">` comme Journées (bordure primaire si aucun match) ;
  tableau de synthèse au-delà de 4 poules ; « Générer la phase » juste après le bilan sur téléphone ;
  état par icône + texte caché, jamais la couleur seule ; ne pas recopier les tableaux colorés de la page
  d'optimisation ; lien d'analyse avec « s'utilise mieux sur un grand écran » sur téléphone.
- **Élimination** : si le classement de la phase précédente n'est pas calculé, avertir « Les têtes de série
  ne sont pas connues. »

**Tranché (D40)** : la clôture ne crée plus journées ni matchs. Avis : ergonome : retirer la création
automatique des journées et des matchs de la phase suivante (D37) et renvoyer vers l'étape Poules ;
webdesigner : si l'automatique est gardé, afficher un bandeau d'information à l'entrée de l'étape.
