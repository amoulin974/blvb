# Règlement par saison : plan de mise en œuvre

Branche `reglement`, créée depuis `formats-competition-lot6` le 20/10/2026 ; elle tient lieu de lot 11
(`lots.md`). Décisions : D14, D55 à D59 ; maquettes : `archive/reglement-maquettes.md` (éléments calculés), `archive/reglement-maquettes-r4.md` (édition).

États : ⬜ à faire · 🟡 en cours · ✅ terminé

## Choix techniques proposés

- **Données** : entité `Reglement` (une par saison : `saison` 1-1, `contenu` HTML nettoyé, `majLe`, `majPar`) et
  `ReglementVersion` (saison, contenu, date, auteur) écrite à chaque enregistrement. Une migration.
- **Nettoyage** : `symfony/html-sanitizer` (nouvelle dépendance), liste blanche : titres h2 à h4, paragraphes,
  listes, gras, italique, liens, images du site (`/uploads/reglement/…`, `/images/reglement/…`), tableaux simples,
  sans attribut `data-*` ni script. Nettoyé à l'enregistrement **et** à l'affichage.
- **Codes courts** (D55) : chaque élément calculé s'insère par un code court écrit dans le texte, `[code]` ou
  `[code phase=P2]` (`phase` facultatif : une phase par son code, D20, sinon toute la saison) : `[schema_phases]`,
  `[bareme]`, `[departage]`, `[calendrier]`. À l'affichage, `RenduReglement` remplace chaque code court par le rendu
  du bloc ; un code inapplicable (code inconnu, phase absente, élément sans objet) n'affiche rien au public ; un admin
  connecté voit à sa place un encart d'avertissement avec la raison, et la page d'édition les liste en tête. Un code court mis en forme « code » (`<code>[bareme]</code>`) n'est pas remplacé, pour pouvoir en parler.
- **Blocs** : interface `BlocReglement` (`code`, `libelle`, `rendre(Saison, ?Phase)`), services tagués comme
  `GenerateurFormat` ; un gabarit Twig par bloc dans `templates/reglement/blocs/`.
  Chaque bloc déclare ses paramètres (nom, rôle, valeurs possibles calculées pour la saison, valeur par défaut) ;
  un paramètre inconnu ou une valeur invalide rend le code inapplicable. Rendu en HTML et CSS, aucune image générée.
  - `schema_phases` : phases dans l'ordre, poules et niveaux, passages en clair (`RegleDePassage::__toString`),
    barrages, et tableaux des phases finales (de gauche à droite, provenance de chaque place, petite finale) ;
    `phase=` limite à une phase (`[schema_phases phase=FIN]` : tableaux finaux seuls) ; reprend le schéma vertical
    du récapitulatif du wizard (D29) ;
  - `bareme` : points par résultat et forfait (`Bareme::dePhase`), nombre de sets gagnants ; un tableau par format
    distinct, phases regroupées ;
  - `departage` : critères de `Saison.departage` dans l'ordre, avec l'aide courte du wizard ;
  - `calendrier` : dates des phases, journées par semaine, semaines de fermeture, journée des finales (date,
    gymnase).
- **Éditeur** : TipTap par l'importmap (contrôleur Stimulus `reglement_editeur`), barre d'outils réduite ; les codes
  courts s'écrivent comme du texte. Bouton « Aperçu » qui rend la page publique.
- **Panneau « Éléments calculés »** de la page d'édition (demande de l'utilisateur) : pour chaque bloc, son nom, son
  code court (avec les variantes par phase de la saison), **la liste de ses paramètres** (nom, rôle, valeurs possibles
  dans la saison, valeur par défaut), un bouton « Copier » et un bouton « Insérer » (à la position du
  curseur), et son rendu actuel pour la saison, repliable. Sur téléphone, le panneau passe sous l'éditeur.
- **Images** : envoi en AJAX depuis l'éditeur (jeton CSRF, ROLE_ADMIN), contrôle du type réel, 2 Mo maximum, nom
  aléatoire, dossier `public/uploads/reglement` (paramètre comme `app.dossier_images_clubs`).
- **Ancres** : à l'affichage, chaque titre reçoit un `id` : code de la règle s'il commence par « RÈGLE xx », sinon
  le titre en minuscules sans accents ; doublons numérotés. Sommaire par `tocbotController` (existant).
- **Copie** : `CopieSaison` copie le contenu (pas l'historique) ; les codes courts `phase=` gardent le code de phase
  (D20), retrouvé dans la nouvelle saison.

## Étapes

| # | Contenu | État |
|---|---|---|
| R1 | Données : `Reglement`, `ReglementVersion`, migration `Version20261020164942` ; nettoyage HTML (`App\Reglement\EditionReglement`, sanitizer `app.reglement`) ; copie dans `CopieSaison` ; RGL-01 à RGL-03, CPS-04 | ✅ |
| R2 | Blocs : `BlocReglement`, `BlocsReglement`, `SchemaPhases` (avec `TableauElimination`), `BlocBareme`, `BlocDepartage`, `BlocCalendrier`, `RenduReglement` (codes courts, ancres, codes inapplicables), `Texte` (mots publics) ; RGL-04 à RGL-11 | ✅ |
| R3 | Page publique : `/reglement` selon la saison affichée (`FrontController::reglement`), brouillon refusé, saison sans règlement (message), saison passée signalée, sommaire h2 et h3 (`tocbotController.js`), impression (en-tête, menu et pied masqués sur tout le site), retour à la page d'origine après un changement de saison ; ancien gabarit gardé dans `front/reglement_2025.html.twig` pour R5 ; relu ergonome + webdesigner + sécurité ; RGL-12 à RGL-15 | ✅ |
| R4 | Édition admin (`ReglementController`, `admin/reglement/*.html.twig`, `reglement_editeur_controller.js` avec TipTap par l'importmap, en version 3.31.4 depuis le 22/10/2026, `ImagesReglement`, `ReglementExtension`) : éditeur, aperçu, panneau « Éléments calculés », images, historique et restauration, conflit entre deux admins, accès (saison, menu, wizard, encart public, état vide) ; RGL-16 à RGL-22. Relu par la sécurité, l'ergonome et le webdesigner (21/10/2026). Complété le 21/10 à la demande de l'utilisateur : toutes les options de TipTap sauf la couleur (alignements en menu, tableaux, souligné, surligné, exposant, indice, citation, séparation, annuler / rétablir), onglet HTML, image cliquable, images réduites avant l'envoi, description modifiable, codes inapplicables marqués « à vérifier » ; vérifié dans Chromium (Playwright) | ✅ |
| R5 | Reprise : commande `app:reglement:importer` (simulation, puis `--force` ; `--remplacer` si un règlement est déjà rédigé, l'ancien restant dans l'historique) qui verse le règlement 2025 (`src/Reglement/Reprise/reglement-2025.html`, balisage corrigé, texte inchangé ; images de `public/images/reglement` centrées avec leur description) dans la saison en cours ; ancien gabarit `front/reglement_2025.html.twig` supprimé ; RGL-23. Fait sur la branche ; reste à la lancer en production (voir « Reste à faire ») | ✅ |
| R6 | Schémas dessinés : générer les schémas du règlement (enchaînement des phases, tableaux à élimination, éventuellement calendrier) avec une bibliothèque JavaScript qui dessine dans un canvas, à partir des mêmes données que les éléments calculés (exposées en JSON par la page) ; bibliothèque à choisir et décision à consigner (demande de l'utilisateur, 20/10/2026) ; relu ergonome + webdesigner (accessibilité : le HTML actuel reste l'alternative textuelle, impression) | ⬜ |

Chaque étape : tests, `tests/SPECIFICATIONS.md` dans le même commit, journal de `methode.md`.


## Reste à faire (mis à jour le 21/10/2026, soir)

R1 à R5 sont faits sur la branche `reglement` (8 commits, poussée le 21/10/2026). Par ordre de priorité :

1. **R6, schémas dessinés** : bibliothèque JavaScript à choisir (canvas), décision à consigner dans `decisions.md` ;
   mêmes données que les éléments calculés, exposées en JSON ; le HTML actuel reste l'alternative textuelle et la version
   imprimée ; relecture ergonome + webdesigner.
2. **Essais dans un navigateur restants** (Playwright dans Docker, voir `tests/README.md`, ou à la main sur la base de
   recette `bin/recette reinitialiser --scenario=barrages`) : conflit entre deux onglets, historique et restauration,
   Firefox et Safari pour les dernières versions de l'éditeur (vérifiées seulement dans Chromium).
3. **Éditeur, points ouverts** :
   - fait le 22/10/2026 : menu « Plus » sur téléphone (souligné, surligné, exposant, indice, code, citation, séparation,
     saut de ligne, tableau ; signale une mise en forme active), barre en 2 lignes à 375 px ; page d'édition toujours
     chargée entièrement (Précédent / Suivant déclenchent l'avertissement du navigateur) ; « Historique (N) » compte la
     version publiée ; menus ouverts vers le haut et défilants s'ils manquent de place ; relu par l'ergonome et le
     webdesigner, vérifié dans Chromium, Firefox et WebKit ;
   - fait le 22/10/2026 : TipTap monté en 3.31.4 (faille moyenne de `@tiptap/core` 2.x corrigée, `importmap:audit`
     propre) ; éditeur vérifié dans Chromium, Firefox et WebKit (voir le journal de `methode.md`).
4. **Fin de branche** : fusion de `reglement` (avec le lot 6, voir `lots.md`) selon D15, puis `main` ; mise à jour de
   `lots.md` et du journal.
5. **En production, après la fusion par Francis** (checklist complète : `deploiement-reglement.md`) :
   - corriger d'abord les données 2025 dans le back-office : journées de la phase finale aux noms et dates incohérents
     (avant le début de la phase, dates limites identiques d'un tour à l'autre), journée 3 des poules C et D le 16/02/2026
     pendant « Vacance d'hiver », noms des fermetures, tableaux nommés « Poule A » à « Poule D », date et gymnase de la
     journée des finales ;
   - puis `app:reglement:importer` (simulation, puis `--force`), juste après le redémarrage (page publique vide avant) ;
   - vérifier que le retour du sélecteur de saison reste sous `/blvb` ;
   - retirer le mot de passe SMTP du `.env` de `prod` et le changer (avec Francis).
6. **Pour le bureau, dans l'éditeur** : corriger les coquilles du texte 2025, gardées telles quelles à l'import (« Point
   perdnant », « l'equipe emputée », « troisème », « palier à », « pour enforcer », « envoi le résultat ») ; remplacer à
   terme les images 2025 (schémas, calendrier) par les éléments calculés `[schema_phases]` et `[calendrier]`.

## Points à trancher

- Texte de la règle 1R en 2 sets gagnants : validation du comité attendue (lot 6f-1).
- Rendu du schéma des phases sur téléphone (webdesigner) ; dates courtes sur téléphone dans le calendrier (facultatif).
- Nom de la saison et « Mis à jour le … » sur la version imprimée (report de R2).
- Jeton CSRF sur `POST /set-saison` (facultatif : le cookie de session est `samesite=lax`).

Faits depuis les reports de R2 et R3 : bouton « Modifier le règlement » dans l'encart public des codes inapplicables,
lien « Rédiger le règlement » sur la page vide pour un admin, ancre `#regle-1r` dans le texte importé.
