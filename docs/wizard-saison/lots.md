# Wizard de création de saison : lots

Chaque lot est livrable seul : l'application reste utilisable et les saisons existantes continuent
de fonctionner. Ordre : socle, puis formats utiles au BLVB (D7), volley d'abord (D13), règlement en
dernier (D14). Une branche par lot : `formats-competition-lotN`, fusionnée dans
`formats-competition` (D15).

États : ⬜ à faire · 🟡 en cours · ✅ terminé

| Lot | Contenu | État |
|---|---|---|
| 0 | Cadrage : décisions, plan, lots, méthode | ✅ |
| 1 | Socle : notation des places ; `RegleSport` avec l'implémentation `Volley` (reprend `RegleScore`) ; interface `GenerateurFormat` avec les formats actuels branchés dessus. **Aucun changement de comportement.** | 🟡 |
| 2 | Classement paramétrable : barème par phase, départage configurable, mini-classement à 3 équipes ou plus, gestion de l'exempt | ⬜ |
| 3 | Règles de passage en données + vérification (D5) ; `cloturerEtBasculer` réécrit ; migration des saisons existantes (D9) | ⬜ |
| 4 | Wizard, architecture : point de départ (copie ou modèle « BLVB actuel »), saison et sport, phases, passages, classement, récapitulatif ; fin du YAML et de `SaisonListener` (D6) | ⬜ |
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

## Lot 1 : socle 🟡
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
- [ ] `GenerateurFormat` ; championnat et finale actuels branchés dessus ; `JourneeService` et
      `PartieService` ne testent plus `PhaseType`
- [ ] Tests de non-régression : calendriers et classements du format actuel identiques

## Critères de fin d'un lot
- tests unitaires et d'intégration du lot écrits et verts, suite complète verte ;
- format BLVB actuel inchangé (calendriers, classements, passages) ;
- relecture `ergonome` / `webdesigner` pour les écrans, `securite` pour les formulaires ;
- `lots.md` et le journal de `methode.md` à jour.
