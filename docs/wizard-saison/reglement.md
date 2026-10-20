# Règlement par saison : plan de mise en œuvre

Branche `reglement`, créée depuis `formats-competition-lot6` le 20/10/2026 ; elle tient lieu de lot 11
(`lots.md`). Décisions : D14, D55 à D57. **Proposition, à valider par l'utilisateur.**

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
  du bloc ; un code inconnu ou sans objet (pas de journée des finales…) n'affiche rien au public et un encart discret
  pour les admins. Un code court entre accents graves (`` `[bareme]` ``) n'est pas remplacé, pour pouvoir en parler.
- **Blocs** : interface `BlocReglement` (`code`, `libelle`, `rendre(Saison, ?Phase)`), services tagués comme
  `GenerateurFormat` ; un gabarit Twig par bloc dans `templates/reglement/blocs/`.
  - `schema_phases` : phases dans l'ordre, poules et niveaux, passages en clair (`RegleDePassage::__toString`),
    barrages, tableaux finaux ; reprend le schéma vertical du récapitulatif du wizard (D29) ;
  - `bareme` : points par résultat et forfait (`Bareme::dePhase`), nombre de sets gagnants ; un tableau par format
    distinct, phases regroupées ;
  - `departage` : critères de `Saison.departage` dans l'ordre, avec l'aide courte du wizard ;
  - `calendrier` : dates des phases, journées par semaine, semaines de fermeture, journée des finales (date,
    gymnase).
- **Éditeur** : TipTap par l'importmap (contrôleur Stimulus `reglement_editeur`), barre d'outils réduite ; les codes
  courts s'écrivent comme du texte. Bouton « Aperçu » qui rend la page publique.
- **Panneau « Éléments calculés »** de la page d'édition (demande de l'utilisateur) : pour chaque bloc, son nom, son
  code court (avec les variantes par phase de la saison), un bouton « Copier » et un bouton « Insérer » (à la position du
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
| R1 | Données : `Reglement`, `ReglementVersion`, migration ; nettoyage HTML ; copie dans `CopieSaison` ; tests | ⬜ |
| R2 | Blocs : interface, registre, 4 blocs, `RenduReglement` (codes courts, ancres) ; tests unitaires et d'intégration | ⬜ |
| R3 | Page publique : `/reglement` selon la saison affichée, brouillon refusé, saison sans règlement (message), sommaire, lien `#regle-1r` de la fenêtre de score ; relu ergonome + webdesigner | ⬜ |
| R4 | Édition admin : page par saison, éditeur, panneau « Éléments calculés » (codes courts et rendus), aperçu, envoi d'images, historique et restauration, liens depuis la saison et le wizard ; relu ergonome + webdesigner + sécurité | ⬜ |
| R5 | Reprise : commande `app:reglement:importer` (simulation puis application) qui verse le gabarit actuel dans la saison en cours, images conservées ; suppression du gabarit en dur ; checklist de déploiement (agent `deploiement`) | ⬜ |

Chaque étape : tests, `tests/SPECIFICATIONS.md` dans le même commit, journal de `methode.md`.

## Points à trancher en cours de route

- Texte de la règle 1R en 2 sets gagnants (validation du comité attendue, lot 6f-1) : reste du texte libre.
- Rendu du schéma des phases sur téléphone (webdesigner).
