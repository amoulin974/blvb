# Règlement par saison : plan de mise en œuvre

Branche `reglement`, créée depuis `formats-competition-lot6` le 20/10/2026 ; elle tient lieu de lot 11
(`lots.md`). Décisions : D14, D55 à D58 ; maquettes : `reglement-maquettes.md`.

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
| R4 | Édition admin : page par saison, éditeur, panneau « Éléments calculés » (codes courts et rendus), aperçu, envoi d'images, historique et restauration, liens depuis la saison et le wizard ; relu ergonome + webdesigner + sécurité | ⬜ |
| R5 | Reprise : commande `app:reglement:importer` (simulation puis application) qui verse le gabarit actuel dans la saison en cours, images conservées ; suppression du gabarit en dur ; checklist de déploiement (agent `deploiement`) | ⬜ |
| R6 | Schémas dessinés : générer les schémas du règlement (enchaînement des phases, tableaux à élimination, éventuellement calendrier) avec une bibliothèque JavaScript qui dessine dans un canvas, à partir des mêmes données que les éléments calculés (exposées en JSON par la page) ; bibliothèque à choisir et décision à consigner (demande de l'utilisateur, 20/10/2026) ; relu ergonome + webdesigner (accessibilité : le HTML actuel reste l'alternative textuelle, impression) | ⬜ |

Chaque étape : tests, `tests/SPECIFICATIONS.md` dans le même commit, journal de `methode.md`.

## Points à trancher en cours de route

- **Reportés des relectures de R2** (20/10/2026) : bouton « Modifier le règlement » dans l'encart des codes
  inapplicables (R4, quand la page d'édition existe) ; ancre `#regle-1r` présente dans le texte importé (R5), sinon le
  lien du barème est mort ; nom et « Mis à jour le … » imprimés (R3) ; dates courtes sur téléphone dans le calendrier
  (facultatif). Ergonome : « Phase Finale » écrit « Phase finale » dans les modèles `ModeleBlvbActuel` et
  `ModeleBlvbAvecBarrages`, la recette et les fixtures (fait le 20/10/2026, choix de l'utilisateur ; les saisons déjà
  créées gardent leur nom).
- **Reportés de R3** : jeton CSRF sur `POST /set-saison` (absent avant la branche ; sans conséquence avec le cookie de
  session `samesite=lax`, facultatif) ; au déploiement, vérifier que le retour du sélecteur de saison reste sous `/blvb`
  (redirection vers `getBaseUrl()` + chemin, proxy avec `x-forwarded-prefix`) ; état vide : lien « Rédiger le
  règlement » pour un admin (R4).
- **Données 2025 à corriger dans le back-office** (relevé à l'aperçu) : journées de la phase finale aux noms et dates
  incohérents (avant le début de la phase, dates limites identiques d'un tour à l'autre) ; journée 3 des poules C et D le
  16/02/2026, pendant « Vacance d'hiver » ; noms des fermetures ; tableaux nommés « Poule A » à « Poule D ».

- Texte de la règle 1R en 2 sets gagnants (validation du comité attendue, lot 6f-1) : reste du texte libre.
- Rendu du schéma des phases sur téléphone (webdesigner).
