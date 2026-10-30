# Wizard de création de saison : lots

Chaque lot est livrable seul : l'application reste utilisable et les saisons existantes continuent
de fonctionner. Ordre : socle, puis formats utiles au BLVB (D7), volley d'abord (D13), règlement en
dernier (D14). Une branche par lot : `formats-competition-lotN`, fusionnée dans
`formats-competition` (D15).

États : ⬜ à faire · 🟡 en cours · ✅ terminé

| Lot | Contenu | État |
|---|---|---|
| 0 | Cadrage : décisions, plan, lots, méthode | ✅ |
| 1 | Socle : notation des places ; `RegleSport` avec l'implémentation `Volley` (reprend `RegleScore`) ; interface `GenerateurFormat` avec les formats actuels branchés dessus. **Aucun changement de comportement.** | ✅ |
| 2 | Classement paramétrable : barème par phase, départage configurable (quotient de sets, mini-classement à 3 équipes ou plus), sans écran (D16 à D19) | ✅ |
| 3 | Règles de passage en données + vérification (D5) ; `cloturerEtBasculer` réécrit ; migration des saisons existantes (D9, D20 à D28) | ✅ |
| 4 | Wizard, architecture : point de départ (copie ou modèle « BLVB actuel »), saison et sport, phases, passages, classement, récapitulatif ; fin du YAML et de `SaisonListener` (D6) | ✅ |
| 5 | Wizard, mise en place : composition des poules, journées, génération des matchs, enchaînement des phases suivantes | ✅ |
| 6 | Formats BLVB : aller-retour (A2), format de match par phase, élimination complète avec barrages, exemptions et placement standard (B1), petite finale (B2), journée des finales (C3), barrages de montée et descente (C4) ; modèle « BLVB avec barrages » | ✅ |
| 7 | Autres championnats : brassage puis niveau (A4), serpentin (A5), géographique (A6) | ⬜ |
| 8 | Autres éliminations et hybrides : classement intégral (B3), consolante (B4), aller-retour et set en or (B6), play-offs et play-downs (C2), meilleurs Xe (C5) | ⬜ |
| 9 | Formats particuliers : plateaux (A7), suisse (A8), échelle (A9), double élimination (B5), handicap (C6) | ⬜ |
| 10 | Autres sports : représentation neutre du score ; sports à buts (match nul), sports à points, sports de raquette en match simple ; saisie et affichages par sport | ⬜ |
| 11 | Règlement en ligne généré à partir des paramètres : branche `reglement`, voir `reglement.md` (D55 à D59) ; R1 à R5 faits et fusionnés dans `formats-competition` (22/10/2026), R6 en attente | 🟡 |

Étude à part, hors lots : rencontres par équipes des sports de raquette (une rencontre = plusieurs
matchs individuels), qui changent le modèle lui-même (D13).

---

Le détail coché des lots 0 à 6 (terminés) est dans [`archive/lots-0-6.md`](archive/lots-0-6.md).

## Lot 11 : règlement par saison 🟡 (branche `reglement`, R1 à R5 fusionnés dans `formats-competition` le 22/10/2026 ; R6 en attente ; détail dans `reglement.md`)
La branche `reglement` part de `formats-competition-lot6` : elle contient le lot 6 et se fusionnera avec lui (D15).
- [x] R1 à R4 : données, éléments calculés, page publique, page d'édition (TipTap complet, onglet HTML, images) ; relus
- [x] R5 : reprise du règlement 2025 (`app:reglement:importer`, RGL-23), ancien gabarit supprimé ; checklist de mise en
      production dans `deploiement-reglement.md`
- [x] `prosemirror-view` monté en 1.42.3 (faille XSS au collage)
- [ ] R6 : schémas dessinés dans un canvas (bibliothèque à choisir, décision à consigner)
- [ ] Essais restants dans un navigateur : conflit entre deux onglets, historique et restauration, Firefox et Safari
- [x] TipTap monté en version 3 (3.31.4, faille moyenne de `@tiptap/core` corrigée), vérifié dans Chromium, Firefox et WebKit
- [x] Éditeur : menu « Plus » sur téléphone, avertissement des boutons Précédent / Suivant, compteur de l'historique
- [ ] En production (après la fusion par Francis) : corriger les données 2025, puis lancer `app:reglement:importer`

## Fermetures de gymnase 🟡 (branche `parametres-site`, hors numérotation du chantier ; D60)
- [x] Lot 1 : saisie par les responsables de club et les admins ; FGY-01 à FGY-09
- [x] Lot 2 : placement automatique (générateurs et replacement), signalement sur la mise en place ; FGY-12 à FGY-16
- [ ] Lot 3 : placement manuel et alertes (formulaires et glisser-déposer du calendrier admin) ; signalement aussi sur l'ancienne
      page « Optimiser » des outils techniques, qui ne le montre pas pour les formats sans optimiseur
- [x] **Calendrier des gymnases** (`/calendrierlieu`, demande de l'utilisateur, 30/10/2026) : date libre marquée « Gymnase
      fermé » avec le motif, match sans score signalé « à déplacer » ; GYM-06
- [ ] **Calendrier par équipe** : y signaler aussi un match placé dans un gymnase fermé, sinon le joueur peut se déplacer pour
      rien (relevé par l'ergonome, à traiter avec le lot 3)
- [x] Auteur d'une fermeture enregistré, visible des admins seulement ; FGY-11
- [ ] À trancher : combien de temps garder une fermeture passée, son auteur et son motif (aucun écran ne les montre plus, sauf
      en base) : purge périodique, ou liste complète côté admin avec suppression (relevé par la sécurité)
- [ ] À la fusion de `conditions-utilisation-v2` : y mentionner l'auteur des fermetures, avec cette durée

## Paramètres du site et éditeur de la fiche club 🟡 (demande de l'utilisateur, 30/10/2026 ; D62, D63)
Dans cet ordre.
1. [x] **Socle « Paramètres du site »** : page `/admin/parametres`, table `parametres_site` à une ligne
2. [x] **Connexion limitée** : vérification, activation, tableau de bord, commande `app:connexion-limitee:desactiver`
3. [x] **Adresse de contact** : pied de page, refus d'une fermeture de gymnase. Reste la page des conditions d'utilisation,
   à brancher à la fusion de `conditions-utilisation-v2` (elle n'existe pas sur cette branche ; le lien du pied de page y est corrigé)
4. [x] **Logo du site** : PNG ou WebP, retour au logo d'origine
5. [ ] **Éditeur TipTap sur la fiche du club** : indépendant des paramètres, branche à part depuis `main`. Remplace le
   champ de texte de `ClubFicheType` ; la description devient du HTML nettoyé (reprise des textes existants, affichage de
   `front/club/show.html.twig`) ; partie commune à extraire de `reglement_editeur_controller.js`. Les responsables de club
   ne sont pas admins : relecture sécurité

## Tableau de bord de l'admin ⬜ (demande de l'utilisateur, 30/10/2026)
Analyse de l'ergonome, maquettes et données : `tableau-de-bord.md`. Rien n'est décidé ni codé.
- [ ] Trancher les 8 points du §3 de `tableau-de-bord.md` (PO6 de `decisions.md`), puis consigner la décision (D64)
- [ ] Réaliser la page, avec relecture webdesigner, tests et `tests/SPECIFICATIONS.md`
- [x] Texte faux « Aucun match n'est placé dans un gymnase pendant sa fermeture » corrigé (lot 2 des fermetures)

## À faire hors lots (relevé en relecture)
- [x] Essais de connexion limités (D61, LIM-01 à LIM-10), sur la branche `parametres-site` ; à vérifier sur le serveur
      avant la mise en production (`deploiement-reglement.md`)
- [x] Page « Modifier la poule » refaite (D52) : nom et niveau, équipes en lecture seule avec lien vers la mise en place,
      phase commencée ou clôturée verrouillée côté serveur ; relue par l'ergonome et le webdesigner ; POU-01 à POU-05
- [x] Contraste des alertes de l'admin (texte coloré 3:1 au lieu de 4,5:1) et icônes succès / info
      (webdesigner, D28) : fait sur `main` et `prod` (`af97240`), gabarit commun `admin/_alerte.html.twig`
- [x] Avertissements des règles de passage avant « Clôturer » (ergonome, D28) : fait (D37, PHA-10)
- [ ] Autres messages joints par « ; » (surcharges des gymnases, `optimiserTout`) : liste comme D28
- [ ] Page d'une saison hors wizard : la recentrer sur le suivi (liste avec colonne « État » et bouton principal unique,
      suppressions en masse masquées une fois la saison lancée, show renvoyant un brouillon vers le wizard,
      « Créer la saison suivante ») : avis de l'ergonome et décisions à prendre dans decisions.md, PO1
- [ ] Équipe qui se retire, forfait général : règle de forfait (decisions.md, PO2)
- [x] Expéditeur des e-mails : un seul, `blvb@vblc.fr` (le compte authentifié sur le SMTP d'OVH, qui refusait
      `admin@blvb.fr` et `tech@vblc.fr`), réglable par `MAILER_FROM` ; fait sur `main` (`ad55678`) et `prod`
      (`41ba275`), test MAI-01 ; ramené dans `formats-competition-lot6` le 16/10
- [ ] Import Excel des utilisateurs trop lent sur le serveur (erreur 500 au-delà d'une dizaine de lignes ; il
      fonctionne 10 lignes par 10 lignes) : chaque compte reçoit un mot de passe aléatoire haché avec le coût
      normal (≈ 0,3 s par compte), jamais utilisé. Décision de l'utilisateur (16/10/2026) : générer ces mots de
      passe plus simplement ; l'utilisateur choisit le sien par « J'ai oublié mon mot de passe ». Proposition :
      compte créé sans mot de passe utilisable. Sur `main`, puis `prod`
- [ ] Site en panne en production (16/10/2026) : la branche `prod` a été vérifiée sur le poste de développement
      (image de production construite, pages publiques en 200, assets servis sous `/blvb`) ; la cause est côté
      serveur. En attente de `docker compose ps` et `docker compose logs --tail=80 php` sur le serveur (Francis)

- [ ] Contraste des libellés de formulaire (`.label` de daisyUI, ≈ 4,4:1, limite AA) : correction globale proposée par
      le webdesigner (`color-mix(... currentColor 80% ...)` hors couche dans `app.css`), à décider
- [ ] Texte à 12 px dans les autres `fieldset` de l'étape Phases (daisyUI) : leur appliquer `.fieldset-formulaire`
- [x] Journée des finales en 2 sets gagnants : durée proposée selon le format, 120 minutes en 3 sets et 75 en 2 sets (D49, SET-05)

## Critères de fin d'un lot
- tests unitaires et d'intégration du lot écrits et verts, suite complète verte ;
- documentation des tests à jour (`tests/SPECIFICATIONS.md`, et `tests/recette/README.md` si la
  recette change), dans le même commit que les tests ;
- format BLVB actuel inchangé (calendriers, classements, passages) ;
- relecture `ergonome` / `webdesigner` pour les écrans, `securite` pour les formulaires ;
- `lots.md` et le journal de `methode.md` à jour.
