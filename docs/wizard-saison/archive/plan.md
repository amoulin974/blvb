# Wizard de création de saison : plan technique

Les choix qui fondent ce plan sont dans `decisions.md` (D1 à D15).

---

## 1. État des lieux (main, commit `12b4fd4`)

### Création d'une saison
- `SaisonController::new` enregistre la saison ; `SaisonListener::postPersist` appelle
  `CompetitionCreator::creerPhasesEtPoules`, qui lit `config/app/saison.yaml` : 3 phases
  (Phase 1, Phase 2, Phase finale) de 4 poules A à D, niveaux 1 à 4, dates relatives (`01/09/n`),
  2 montées et 2 descentes écrites en dur.
- Le wizard existe à l'état d'ébauche : `SaisonWizardController` (une route
  `/admin/saison/{id}/wizard/etape/{step}`) et `templates/admin/saisonwizard/layout.html.twig`
  (4 étapes : Architecture, Composition des poules, Calendrier, Génération des matchs ; contenu
  commenté, rien de fonctionnel).

### Modèle de données
| Entité | Champs utiles ici |
|---|---|
| `Saison` | nom, dates, favori ; barème : `points_victoire_forte`, `_faible`, `points_defaite_forte`, `_faible`, `points_nul`, `points_forfait` |
| `Phase` | nom, dates, `type` (`PhaseType` : `CHAMPIONNAT`, `FINALE`), `ordre`, `close` |
| `Poule` | nom, `niveau`, `nb_montee_defaut`, `nb_descente_defaut`, équipes, journées, classements |
| `Partie` | journée, poule, lieu, équipes (reçoit / se déplace), sets gagnés, `parent_match1`, `parent_match2` (arbre des finales), nom |
| `Journee` | numéro, dates, poule, nom |
| `Classement` | poule, équipe, points, sets gagnés / perdus, position |

### Logique métier
- **Score** : `RegleScore` (3 sets gagnants, règle 1R des matchs interrompus, forfait = -1).
- **Classement** : `ClassementService::mettreAJourClassementPoule`, départage fixe :
  points → différence de sets → confrontation directe → ordre alphabétique.
- **Passage de phase** : `PhaseService::cloturerEtBasculer`, niveau ± 1 selon
  `nb_montee_defaut` / `nb_descente_defaut`, avec replis si la poule cible n'existe pas.
- **Journées** : `JourneeService::creerJournees`, aiguillage sur `PhaseType`.
- **Matchs** : `PartieService` ; championnat aller simple (`genererPairingsRoundRobin`, optimiseur
  de lieux et de breaks) ; finales par `generateMatchJourneePhaseFinale` (arbre via `parent_match`).
- Autres usages de `PhaseType` : `FrontController`, `PhaseFormType`, `ImportOldDataCommand`,
  `ChargerBaseRecetteCommand`, `PhaseFactory`.

### Ce qui manque par rapport au catalogue
Aller-retour (A2), constitution des poules (A4, A5, A6), plateaux (A7), suisse (A8), échelle (A9),
variantes d'élimination (B2 à B6), barrages et play-offs (C2, C4, C5), handicap (C6), règles de
passage paramétrables (D), barème par phase et départage configurable (E).

---

## 2. Modèle cible

Stockage mixte (D8) : entités pour ce qui touche aux équipes et aux matchs, JSON pour les options.

### Sport (D13)
Interface `RegleSport`, une implémentation par sport, choisie par la saison (`Saison.sport`) :
- forme du score et validation (volley : sets, règle 1R, forfait) ;
- résultat d'un match : victoire, défaite, nul (si le sport l'autorise), forfait, avec une
  **catégorie de barème** (volley : victoire forte / faible, défaite forte / faible) ;
- statistiques de départage disponibles (volley : sets, points de sets ; buts : buts marqués et
  encaissés ; points : points marqués et encaissés) ;
- résultats possibles en élimination (pas de nul : prolongation, set en or, tirs au but…) ;
- formats de match proposés par phase (volley : 3 ou 2 sets gagnants, nombre de sets fixe).

Premier sport : `Volley` (reprend `RegleScore`). Le score en base reste d'abord dans
`Partie.nb_set_gagnant_*` ; une représentation neutre (score et détail en JSON) est introduite
lors de l'arrivée du deuxième sport, pas avant.

### Saison
- `sport` (D13).
- Barème par défaut : colonnes existantes, conservées.
- `departage` : liste ordonnée de critères (`points`, `victoires`, `quotient_sets`,
  `difference_sets`, `quotient_points`, `confrontation_directe`, `forfaits`, `tirage_au_sort`,
  `alphabetique`).
- Gestion de l'exempt (aucun point / victoire fictive).

### Phase
- `type` (`PhaseType`) **est** le format de la phase (D42, qui modifie D33 : pas de renommage en `format`).
  Valeurs réalisées : `CHAMPIONNAT`, `FINALE` (élimination), `BARRAGE` ; prévues, dans l'ordre de D7 :
  suisse, plateaux, échelle.
- `options` (JSON, `Phase.options`, réalisé au lot 6) : propres au format. Réalisées : `reception` (barrage),
  `petite_finale` (tableau). Exemples : `aller_retour`, `retour` (miroir/décalé),
  `taille_tableau`, `placement` (standard/serpentin/tirage), `petite_finale`, `classement_integral`,
  `consolante`, `double_elimination`, `confrontation` (sec/aller-retour + set en or), `reception`
  (mieux classé/niveau supérieur/neutre), `nb_rondes` (suisse), `taille_plateau`.
- `bareme` : surcharge facultative du barème de la saison (ex. barrage ou tableau sans points).
- `format_match` : parmi ceux que propose le sport de la saison.

### Poule
- `code` (A, B…), `niveau`, taille attendue.
- `nb_montee_defaut` / `nb_descente_defaut` : remplacés par les règles de passage, supprimés
  après migration (D9).

### RegleDePassage (nouvelle entité)
- phase source, ordre ;
- `source` : notation des places (`P1.A#1..-3`, `BAR1.AB.vainqueur`, meilleurs Xe) ;
- destination : poule d'une phase suivante, ou **saison suivante** (montées et descentes différées) ;
- `placement` (rang, serpentin, tirage) ; `points_conserves` (0, 50 %, 100 %, contre les qualifiés).

### Modèles (D10)
Fournis par l'application (classes PHP ou fichiers versionnés), non modifiables par
l'administrateur : « BLVB actuel » (reprend `config/app/saison.yaml`), « BLVB avec barrages »
(partie F2), puis d'autres au fil des formats. Appliquer un modèle crée les paramètres de la saison,
qui se modifient ensuite librement.

### Pas de places provisoires (D12)
Le calendrier d'une phase se génère quand ses équipes sont connues ; les tableaux gardent
`Partie.parent_match1/2`.

---

## 3. Services

| Service | Rôle |
|---|---|
| `NotationPlace` (nouveau) | Analyse et résolution de la notation : `P1.A#-2` → équipe, à partir des classements et des résultats. Purement unitaire. |
| `VerificationSaison` (nouveau) | Contrôles de D5 ; rend une liste d'erreurs lisibles pour le wizard. |
| `GenerateurFormat` (interface, nouveau) | Une implémentation par format (journées + matchs). Remplace les `if PhaseType` de `JourneeService` et `PartieService`. L'optimiseur de lieux existant reste utilisé par les formats en poules. |
| `ClassementService` | Départage lu dans la saison ; confrontation directe à 3 équipes ou plus (mini-classement). |
| `PhaseService::cloturerEtBasculer` | Applique les `RegleDePassage` au lieu du calcul niveau ± 1. |
| `RegleScore` | Devient l'implémentation `Volley` de `RegleSport`, paramétrée par le format de match de la phase. |
| `CompetitionCreator` + `SaisonListener` | Retirés (D6) ; remplacés par la création depuis un modèle ou une copie (D10). |
| Règlement (dernier lot, D14) | Rendu du schéma des phases, du barème et du départage à partir des paramètres. |

---

## 4. Wizard (D10, D11)

1. **Point de départ** : copie d'une saison existante, ou modèle.
2. **Saison** : nom, dates, sport, indisponibilités.
3. **Phases** : liste ordonnée ; pour chacune, format, options, format de match, dates, poules ou
   tableaux.
4. **Passages** : pour chaque phase, d'où viennent les équipes et où elles vont.
5. **Classement** : barème (par saison, surcharges par phase) et départage.
6. **Vérification et récapitulatif** : schéma des phases, erreurs de D5, validation.
7. **Composition des poules** de la première phase (reprise des écrans existants).
8. **Journées et calendrier**.
9. **Génération des matchs** (optimiseur existant).

Les étapes 7 à 9 reviennent pour chaque phase suivante, après la clôture de la précédente.

Chaque étape enregistre la saison en brouillon ; on peut revenir en arrière sans perte.

---

## 5. Points d'attention

- **Non-régression** : le format BLVB actuel, exprimé en paramètres, doit produire exactement les
  mêmes calendriers, classements et passages qu'aujourd'hui (tests de comparaison).
- **Données existantes** : migration Doctrine + commande de reprise (D9) ; prévenir l'agent
  `deploiement`.
- **Hypothèses « volley »** dans le front et la saisie (affichage « 3–2 », API de score) : à faire
  passer par `RegleSport` au fil des lots, pour préparer les autres sports.
- **Usages de `PhaseType`** à remplacer partout (front, formulaires, commandes, fabriques).
- **Base de recette** (`bin/recette`, `app:recette:charger`) à adapter pour charger des saisons
  aux formats variés.
