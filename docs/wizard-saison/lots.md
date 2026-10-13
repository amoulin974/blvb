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
| 4 | Wizard, architecture : point de départ (copie ou modèle « BLVB actuel »), saison et sport, phases, passages, classement, récapitulatif ; fin du YAML et de `SaisonListener` (D6) | 🟡 |
| 5 | Wizard, mise en place : composition des poules, journées, génération des matchs, enchaînement des phases suivantes | ⬜ |
| 6 | Formats BLVB : aller-retour (A2), format de match par phase, élimination complète avec barrages, exemptions et placement standard (B1), petite finale (B2), journée des finales (C3), barrages de montée et descente (C4) ; modèle « BLVB avec barrages » | ⬜ |
| 7 | Autres championnats : brassage puis niveau (A4), serpentin (A5), géographique (A6) | ⬜ |
| 8 | Autres éliminations et hybrides : classement intégral (B3), consolante (B4), aller-retour et set en or (B6), play-offs et play-downs (C2), meilleurs Xe (C5) | ⬜ |
| 9 | Formats particuliers : plateaux (A7), suisse (A8), échelle (A9), double élimination (B5), handicap (C6) | ⬜ |
| 10 | Autres sports : représentation neutre du score ; sports à buts (match nul), sports à points, sports de raquette en match simple ; saisie et affichages par sport | ⬜ |
| 11 | Règlement en ligne généré à partir des paramètres | ⬜ |

Étude à part, hors lots : rencontres par équipes des sports de raquette (une rencontre = plusieurs
matchs individuels), qui changent le modèle lui-même (D13).

---

## Lot 0 : cadrage ✅
- [x] Branche `formats-competition`
- [x] État des lieux du code
- [x] `methode.md`, `decisions.md`, `plan.md`, `lots.md`
- [x] Réponses aux questions de conception, reportées dans `decisions.md` (D7 à D15)

## Lot 1 : socle ✅
- [x] Créer la branche `formats-competition-lot1`
- [x] `NotationPlace` : analyse et résolution (`P1.A#-2`, `#1..-3`, `.vainqueur`, `.perdant`),
      tests unitaires. Code dans `src/Competition/` (`Place`, `NotationPlace`, `ResolveurPlace`,
      interface `ResultatsPhases`, sans implémentation Doctrine avant le lot 3, donc pas encore
      déclaré dans `config/services.yaml`) ; tests dans `tests/Unitaire/Competition/`.
- [x] `RegleSport` + `Volley` ; `RegleScore` et ses appelants passent par elle ; tests existants verts.
      `src/Competition/Sport/` : `RegleSport`, `Volley` (ex-`RegleScore` + points de
      `ClassementService` + encodage du forfait), `Bareme` (lu dans la saison), `Sports` (toujours
      le volley tant que `Saison.sport` n'existe pas, lot 4). `RegleScore` supprimé ; test déplacé
      dans `tests/Unitaire/Competition/Sport/VolleyTest.php`.
- [x] `GenerateurFormat` ; championnat et finale actuels branchés dessus ; `JourneeService` et
      `PartieService` ne testent plus `PhaseType`. `src/Competition/Format/` : interface
      `GenerateurFormat` (journées, matchs simples, `estOptimisable`), `Championnat` (A1) et
      `Elimination` (B1) qui reprennent le code existant à l'identique, `Formats` (choix du
      générateur, services tagués `app.generateur_format`), `DateMatch` (ex-`calculerDateMatch`,
      toujours exposé par `PartieService`). L'optimiseur reste dans `PartieService` pour les formats
      optimisables. `PhaseType` reste utilisé par `supporte()` jusqu'au champ `format` (lot 3).
- [x] Tests de non-régression : calendriers et classements du format actuel identiques. Couverts
      par les tests existants (`GenerationMatchsTest`, `OptimiseurTest`, `JourneeServiceTest`,
      `ClassementServiceTest`), inchangés et verts ; `FormatsTest` ajouté.

## Lot 2 : classement paramétrable ✅
- [x] Branche `formats-competition-lot2` ; décisions D16 à D19
- [x] Départage : liste ordonnée de critères enregistrée par saison (`Saison.departage`, JSON) ;
      enum `App\Competition\Classement\CritereDepartage` (`points`, `difference_sets`,
      `quotient_sets`, `confrontation_directe`, `mini_classement`, `alphabetique` toujours en dernier)
- [x] `ClassementService` : un seul comparateur construit à partir de la liste de la saison (identique
      à l'ancien avec la liste par défaut) ; mini-classement précalculé pour chaque groupe d'équipes à
      égalité sur les critères par équipe qui le précèdent
- [x] Barème surchargeable par phase (`Phase.bareme`, JSON, valeur par valeur) ; `Bareme::dePhase`
- [x] Migration `Version20261013100000` : colonnes `saison.departage` (départage actuel inscrit dans
      les saisons existantes, D9) et `phase.bareme`. **À appliquer au déploiement seulement** ; en
      local, `doctrine:migrations:migrate` avant de lancer le site sur cette branche
- [x] Tests : CLA-12 à CLA-16 (quotient de sets, mini-classement, barème de phase, liste de
      départage) ; tests existants verts ; `tests/SPECIFICATIONS.md` à jour
- [x] Pas d'écran (D19)

## Lot 3 : règles de passage en données ✅
- [x] Branche `formats-competition-lot3` ; décisions D20 à D27
- [x] Codes : `Phase.code`, `Poule.code` (D20), uniques dans la saison / la phase ;
      `App\Competition\CodesCompetition` (attribue les codes manquants, appelé à la création de saison) ;
      migration `Version20261013120000` (même calcul pour les saisons existantes)
- [x] Entité `RegleDePassage` : phase source, ordre, source (notation des places), destination
      (poule d'une phase suivante, ou niveau de la saison suivante, D22), placement, points conservés
      (D23, enum `PointsConserves`)
- [x] `ReglesParDefaut` : règles du schéma 2025 (D21), utilisées par la migration
      `Version20261013130000` et la création de saison (`CompetitionCreator`, qui relie maintenant
      phases et poules des deux côtés) ; clôture d'une phase sans règles (D26) : étape suivante
- [x] `ResultatsSaison` : `ResultatsPhases` branché sur Doctrine (classements ; issues de match au
      lot 6)
- [x] `VerificationPassages` (D5) : notation, phase et poule existantes, chaque rang une seule
      destination ; messages en clair (`Place::enClair`, `RegleDePassage::__toString`)
- [x] `PhaseService::cloturerEtBasculer` applique les règles (D24 : avertir et continuer ; D26 :
      règles par défaut si la phase n'en a pas) et rend les avertissements ; message de clôture relu
      par l'ergonome et le webdesigner (D28), alerte à liste dans `base_admin.html.twig`
- [x] Règles par défaut des petites poules : la montée l'emporte (nombre d'équipes connu)
- [x] Migration : colonnes de codes, table des règles, règles générées pour les saisons existantes
      (D21). **À appliquer au déploiement seulement**
- [x] Tests (PHA-*, nouvelles règles, vérification, migration) ; `tests/SPECIFICATIONS.md` à jour

## Lot 4 : wizard, architecture 🟡
- [x] Branche `formats-competition-lot4`
- [x] Sport de la saison : `Saison.sport` (migration `Version20261013140000`), registre `Sports` (D13)
- [x] Modèles de saison : `App\Competition\Modele`, modèle « Format BLVB actuel » ;
      `config/app/saison.yaml` supprimé, `CompetitionCreator` délègue au modèle (D6, D10)
- [x] Copie d'une saison : `App\Competition\CopieSaison` (D3, D10, D22)
- [x] Proposition d'écrans de l'ergonome et du webdesigner : `ecrans.md`
- [x] Questions de `ecrans.md` §6 tranchées comme recommandé (D29)
- [x] Brouillon : `Saison.brouillon`, `Saison.etapeWizard`, `Poule.nbEquipesPrevu` (migration
      `Version20261013150000`) ; brouillon invisible sur le site et jamais « affiché sur le site »
      (`SaisonRepository::findPubliees`, `findPubliee`, `findPlusRecentePubliee`)
- [ ] ~~`Phase.format` à la place de `PhaseType` (D27)~~ : reporté au lot 6 (D33)
- [x] Écrans du wizard : 6 étapes (départ, saison, phases, passages, classement, vérification et validation)
- [x] « Modifier la saison » (D29, question 8) : wizard en mode édition tant que la saison n'est pas
      lancée (D34) ; la page de modification ne garde que nom, dates et périodes de fermeture
- [x] Relecture ergonome + webdesigner + sécurité des écrans réalisés, retours intégrés :
      garde-fou « saison lancée » et quitter avec erreurs (D34), agenda `.ics` d'un brouillon en 404,
      saisies bornées et robustes (WIZ-14, WIZ-15) ; poules en cartes et schéma vertical sur téléphone,
      stepper cliquable sur téléphone, barre du bas allégée, icônes au lieu des glyphes, cibles de
      44 px, pluriels, textes sans article devant un nom saisi, règles en clair dans les messages,
      aperçu et avertissement de la saison copiée dès l'étape 1, brouillon supprimable depuis la liste
- [ ] Reporté au lot 5 (relecture) : carte « Point de départ » du récapitulatif (le départ n'est pas
      enregistré), « Brouillon enregistré à 14 h 32 », écarts d'effectif dans le schéma (« 9 / 8 »),
      aide courte sous chaque critère de départage, liens de l'alerte d'erreur de l'étape 3 vers les
      champs ; vérifier dans le navigateur les utilitaires Tailwind écrasés par daisyUI (webdesigner)
- [x] Fin de la création automatique : `SaisonListener` supprimé, « Créer une saison » ouvre le wizard (D6)

## À faire hors lots (relevé en relecture)
- [x] Contraste des alertes de l'admin (texte coloré 3:1 au lieu de 4,5:1) et icônes succès / info
      (webdesigner, D28) : fait sur `main` et `prod` (`af97240`), gabarit commun `admin/_alerte.html.twig`
- [ ] Avertissements des règles de passage affichés avant de cliquer sur « Clôturer » (ergonome,
      D28) : avec le wizard (lots 4-5)
- [ ] Autres messages joints par « ; » (surcharges des gymnases, `optimiserTout`) : liste comme D28

## Critères de fin d'un lot
- tests unitaires et d'intégration du lot écrits et verts, suite complète verte ;
- documentation des tests à jour (`tests/SPECIFICATIONS.md`, et `tests/recette/README.md` si la
  recette change), dans le même commit que les tests ;
- format BLVB actuel inchangé (calendriers, classements, passages) ;
- relecture `ergonome` / `webdesigner` pour les écrans, `securite` pour les formulaires ;
- `lots.md` et le journal de `methode.md` à jour.
