# Wizard de création de saison : proposition d'écrans (lot 4)

Synthèse des propositions de l'ergonome et du webdesigner (13/10/2026), à valider par l'utilisateur
avant réalisation. Les questions à trancher sont à la fin ; une fois tranchées, elles deviennent des
décisions dans `decisions.md` et ce document sert de cahier des charges des écrans.
Après réalisation, les écrans repassent en relecture (ergonome et webdesigner).

---

## 1. Parcours

- **Entrée** : « Créer une saison » dans la liste des saisons ouvre l'étape 1. Le formulaire actuel de
  création (`saison/new` + `SaisonType`) disparaît comme écran de création (D6).
- **Brouillon** : la saison est enregistrée dès l'étape 1 validée (la copie ou le modèle est appliqué
  tout de suite) ; chaque changement d'étape enregistre. Dans la liste des saisons : badge
  « Brouillon », bouton « Reprendre ». Un brouillon n'est jamais visible sur le site.
- **Étapes** :
  - **Organisation** (lot 4) : 1 Départ · 2 Saison · 3 Phases · 4 Passages · 5 Classement · 6 Vérification ;
  - **Mise en place** (lot 5) : Poules · Journées · Matchs, accessibles après validation.
- **Navigation** : « Précédent » (sans perte), « Enregistrer et quitter » (discret), « Suivant »
  (principal) ; dès l'étape 2, « Aller à la vérification » (une copie peut se relire sans tout
  parcourir). Les étapes déjà vues sont cliquables dans le stepper ; chaque navigation enregistre.
- **Vérification** : une seule vérification bloquante, à l'étape 6 ; avant, les problèmes s'affichent
  sans empêcher d'avancer.

## 2. Structure de page (webdesigner)

- Gabarit `admin/saisonwizard/layout.html.twig` sur `base_admin` ; en-tête `admin/_entete`
  (« Nouvelle saison », badge « Brouillon · non visible sur le site ») ; contenu dans une
  `.admin-carte` (l'ombre de l'ébauche est contraire à la charte).
- **Stepper** : `steps` de daisyUI, dans `<nav aria-label="Étapes de création de la saison"><ol>` ;
  états faite (✓), en cours (`aria-current="step"`), à corriger (!), à venir ; état aussi dit en
  `sr-only`. Sur téléphone : pastilles seules + « Étape 3 sur 6 · Phases ».
- **Barre du bas** collante sur téléphone (`.wizard-navigation`) ; « Valider la saison » en
  `btn-accent` (le jaune reste réservé à l'action décisive).
- **Erreurs** : alerte `error` en haut avec liens vers les champs ; `form_errors` reliés par
  `aria-describedby`.

## 3. Étapes

### 1. Départ — « Comment voulez-vous commencer ? »
Deux cartes de choix (radio, `.choix-carte`) :
- **Copier une saison existante** (par défaut ; saison en cours présélectionnée). Aide : « Les phases,
  poules, passages, barème et départage sont recopiés. Les équipes qui montent ou descendent à la fin
  de cette saison seront placées dans les bonnes poules de la Phase 1. » Si la saison n'est pas
  terminée : avertissement « les poules de la Phase 1 seront à composer ».
- **Partir d'un modèle** : « Format BLVB actuel » (seul modèle pour l'instant).

### 2. Saison
Nom (proposé « Saison 2026-2027 », unique), dates (copie : décalées d'un an), sport (texte fixe
« Volley-ball », seul sport pour l'instant), périodes sans match (collection ; copie : décalées d'un
an, bandeau « vérifiez les vacances scolaires »).

### 3. Phases
Une carte repliable par phase, dans l'ordre (boutons Monter / Descendre, pas de glisser-déposer) :
nom, type (« Championnat : toutes les équipes de la poule se rencontrent » / « Tableau à
élimination : le perdant est éliminé » ; seuls les formats disponibles sont montrés), dates, poules
(tableau : nom, niveau, nombre d'équipes prévu ; cartes sur téléphone), « Options avancées »
repliées (codes). « Ajouter une phase ». Suppression confirmée, avec ses conséquences.

### 4. Passages
Une section par transition (« De la Phase 1 vers la Phase 2 »).
- **Mode simple** (par défaut), une ligne par poule, pré-remplie : « Montent : [2] premiers →
  [Poule A] · Descendent : [2] derniers → [Poule C] · Les autres → [Poule B] » ; vers la phase finale :
  « Tout le classement, dans l'ordre → [Tableau 1] » + « Montées et descentes pour la saison
  suivante » (D22).
- **Mode détaillé** (« Personnaliser les règles de cette poule ») : « Les [1er] à [2e] de la [Poule A]
  vont en [Poule A (Phase 2)] », destinations groupées par phase + « Niveau N la saison prochaine ».
- Contrôle en direct par poule de destination (« 8 prévues, 8 reçues ✓ ») ; erreurs en clair
  (« Le 3e de la Poule B n'a pas de destination »). « Rétablir les règles par défaut » (confirmé).
- La notation `P1.A#1..2` n'est jamais tapée.

### 5. Classement
Barème en tableau (Résultat | Points ; libellés du sport, pas de « match nul » au volley ; aide du
forfait) ; barème propre à une phase (case à cocher, raccourci « Aucun point (tableau, barrage) »,
champ vide = valeur de la saison) ; départage en liste ordonnée (Monter / Descendre / Retirer,
« Ajouter un critère », ordre alphabétique figé en dernier avec un cadenas, aides de la
confrontation directe et du mini-classement).

### 6. Vérification
Bilan (erreurs bloquantes avec lien « Corriger », avertissements non bloquants) ; **schéma de la
saison** (flux vertical sur téléphone, matrice par niveaux à partir de `lg` : P1 | P2 | Finale |
Saison suivante) ; récapitulatif par étape avec lien « Modifier » ; point de départ (« Copie de la
saison 2025-2026 : 32 équipes déjà placées dans les poules de la Phase 1 ») ; « Valider la saison »
(`aria-disabled` + raison écrite s'il reste des erreurs). Après validation : « Prochaine étape :
composer les poules de la Phase 1. »

## 4. Composants à créer
`.choix-carte`, `.wizard-navigation`, `.liste-ordonnee` (+ contrôleur Stimulus), `.schema-phase` ;
icônes `fleche-haut`, `fleche-bas`, `fleche-droite`, `copie`, `coche`, `restaurer` ; gabarits
`saisonwizard/etapes/_depart|_saison|_phases|_passages|_classement|_verification` et `_schema`
(réutilisable par le règlement généré, D14).

## 5. Relevés au passage
- Fautes dans `SaisonType` / `saison/_form` à ne pas reprendre (« Nombre de point gagné », « defaite »,
  « null », « Nom de la saison2 », libellés contradictoires « victoire bonifiée » / « complète »).
- Le « nombre d'équipes prévu » d'une poule n'existe pas en base ; la vérification D5 en a besoin.

---

## 6. Questions à trancher (recommandation des relecteurs entre parenthèses)

1. Brouillon créé dès l'étape 1, enregistré à chaque changement d'étape ? *(oui)*
2. À la copie, dates et périodes sans match décalées d'un an ? *(oui, avec un bandeau « à vérifier »
   sur les vacances)* — c'est le comportement actuel de `CopieSaison`.
3. Copier une saison pas encore terminée ? *(oui, avec avertissement ; poules de la Phase 1 non
   pré-remplies)* — comportement actuel de `CopieSaison`.
4. Ajouter un « nombre d'équipes prévu » aux poules (facultatif, pré-rempli à la copie) ? *(oui)*
5. Passages : mode simple (montées, descentes, « les autres ») par défaut, mode détaillé en option ? *(oui)*
6. Notation `P1.A#1..2` : au récapitulatif seulement, masquée par défaut (« Afficher les codes
   techniques ») ? *(oui)*
7. Codes des phases et poules modifiables dans des « Options avancées » repliées de l'étape 3 ? *(oui)*
8. « Modifier la saison » après la création : ouvre le wizard (étapes 2 à 5) tant qu'aucun match
   n'est joué, puis seulement nom, dates et périodes sans match ? *(oui)*
9. Formats pas encore disponibles : masqués (plutôt que grisés « bientôt ») ? *(masqués)*
10. Sport : texte fixe « Volley-ball » tant qu'il n'y a qu'un sport ? *(oui)*
11. À la création, la vérification **bloque** sur les erreurs (équipe sans destination ou avec deux,
    poule inexistante, dates incohérentes) et laisse passer les avertissements — différent de la
    clôture qui avertit et continue (D24) ? *(oui)*
12. Brouillon invisible sur le site, impossible à « afficher sur le site » avant validation,
    supprimable depuis la liste ? *(oui)*
13. Nom proposé « Saison AAAA-AAAA+1 », calculé depuis la date de début ? *(oui)*
14. Stepper en deux groupes : « Organisation » (1-6) puis « Mise en place » (poules, journées, matchs,
    lot 5) accessible après validation ? *(oui)*
15. Réordonner phases, règles et critères par boutons Monter / Descendre seulement (pas de
    glisser-déposer) ? *(oui)*
16. Champ « points conservés » (D23) : caché jusqu'au lot 8 ? *(caché)*
17. Schéma de la saison : matrice par niveaux sur ordinateur, flux vertical sur téléphone ? *(oui)*
18. « Valider la saison » visible même avec des erreurs, désactivé avec la raison écrite ? *(oui)*
19. Libellés : « Passages » ou « Montées et descentes » pour l'étape 4 ? « Périodes sans match » ou
    « Périodes de fermeture » ? *(à trancher ; l'ergonome propose « Passages », la doc actuelle dit
    « Périodes de fermeture »)*
