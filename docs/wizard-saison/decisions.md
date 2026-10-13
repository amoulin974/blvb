# Wizard de création de saison : décisions de conception

Chaque décision indique ce qui est retenu et pourquoi. Les questions qui restent à poser sont
listées à la fin, dans « Points ouverts ».
Une décision prise n'est rediscutée que si un élément nouveau apparaît ; on la modifie alors ici
en gardant la trace de l'ancienne.

---

## D1. Rôle de l'outil
**Décision** : le wizard sert à **créer** une saison. Il doit savoir proposer tous les formats du
catalogue ; l'administrateur choisit au moment de la création. Les « points à trancher » de la
partie F2 de `formats-competition.md` ne sont donc pas des décisions à prendre ici : ce sont des
**options** que le wizard doit offrir (nombre de montées directes, qui reçoit un barrage, etc.).
**Pourquoi** : le règlement change chaque année ; l'outil ne doit pas figer un choix du bureau.
*(2026-10-13)*

## D2. Méthode de travail
**Décision** : pas de Spec Kit ; sa démarche est reprise en version légère dans
`docs/wizard-saison/` (décisions, plan, lots, méthode).
**Pourquoi** : `formats-competition.md` sert déjà de spécification ; Spec Kit imposerait sa
structure, ses modèles en anglais et ses noms de branches, différents des conventions du projet.
*(2026-10-13)*

## D3. Une saison = une copie de son format
**Décision** : chaque saison porte ses propres paramètres. Créer une saison à partir d'une autre
**copie** ses paramètres ; modifier une saison ne change jamais les saisons passées.
**Pourquoi** : les saisons archivées doivent rester consultables avec *leurs* règles
(`formats-competition.md`, partie G).
*(2026-10-13)*

## D4. Règles de passage écrites avec la notation des places
**Décision** : les règles de passage entre phases utilisent la notation de la partie G :
`PHASE.POULE#rang`, rang négatif compté depuis la fin, plage `#1..-3`,
`PHASE.MATCH.vainqueur` / `.perdant`, et une notation à définir pour les « meilleurs Xe ».
Dans le wizard, l'administrateur ne tape pas cette notation : il la construit avec des listes ;
la notation est la forme enregistrée et affichée en résumé.
**Pourquoi** : une seule représentation, testable unitairement, lisible dans le règlement.
*(2026-10-13)*

## D5. Vérification avant validation
**Décision** : une saison ne peut être validée que si la vérification passe : chaque équipe a
exactement une destination à chaque passage, les tailles de poules attendues sont respectées,
chaque référence (phase, poule, match) existe.
**Pourquoi** : c'est ce contrôle qui aurait détecté l'erreur 1 de la partie F4.
*(2026-10-13)*

## D6. Fin de `config/app/saison.yaml` et de la création automatique
**Décision** : la création automatique des phases à l'enregistrement d'une saison
(`SaisonListener` → `CompetitionCreator`) est remplacée par le wizard. Le format BLVB actuel devient
un **modèle** proposé au départ du wizard.
**Pourquoi** : le YAML est global et figé, il contredit D3.
*(2026-10-13)*

## D7. Ordre des formats : BLVB d'abord
**Décision** : d'abord les formats utiles au BLVB (A1, A2, A3, B1, B2, C1, C3, C4), puis le reste du
catalogue, lot par lot. Le modèle de données est conçu **dès le départ** pour tous les formats.
**Pourquoi** : livrer tôt ce qui sert, sans devoir refaire le modèle ensuite.
*(2026-10-13)*

## D8. Stockage mixte
**Décision** : entités Doctrine pour ce qui est relié aux équipes et aux matchs (phases, poules,
règles de passage) ; colonne JSON pour les options propres à chaque format.
**Pourquoi** : intégrité en base là où elle compte, sans migration à chaque nouvelle option.
*(2026-10-13)*

## D9. Migration des saisons existantes
**Décision** : les saisons en base sont migrées vers le nouveau modèle, avec des paramètres qui
reproduisent exactement le comportement actuel (2 montées, 2 descentes, départage actuel). Un seul
chemin de code ensuite.
**Pourquoi** : éviter deux logiques parallèles à maintenir.
*(2026-10-13)*

## D10. Point de départ : copie ou modèle
**Décision** : le wizard démarre soit par la **copie d'une saison existante**, soit par un
**modèle**. Les modèles sont **fournis par l'application** et fixes ; la saison créée à partir d'un
modèle ou d'une copie se modifie librement. Pas de saison vierge, pas d'édition des modèles par
l'administrateur.
*(2026-10-13)*

## D11. Le wizard couvre toute la création
**Décision** : architecture (phases, formats, passages, classement), puis composition des poules,
journées et génération des matchs, comme dans l'ébauche à 4 étapes.
*(2026-10-13)*

## D12. Pas de places provisoires dans les calendriers
**Décision** : le calendrier d'une phase se génère quand ses équipes sont connues. Les tableaux
d'élimination gardent leur arbre (`Partie.parent_match1/2`).
**Pourquoi** : l'optimisation des gymnases, cœur de l'outil, reste exacte.
*(2026-10-13)*

## D13. Choix du sport, volley-ball d'abord
**Décision** : la saison choisit son **sport**, qui détermine la saisie du score, les résultats
possibles (match nul ou non), les catégories du barème et les statistiques de départage. Le
volley-ball est fait en premier ; les autres sports (à buts, à points, de raquette) suivent.
Dès le socle, ces règles passent par une abstraction **« règle du sport »**, dont le volley est la
première implémentation.
**Pourquoi** :
- *Intérêt* : le BLVB joue au volley, on livre vite ce qui sert ; la logique actuelle
  (`RegleScore`, règle 1R) sert de référence de non-régression ; les formats de compétition sont
  presque indépendants du sport.
- *Risque* : le volley est partout (`Partie.nb_set_gagnant_*`, `Classement.setGagnes/setPerdus`,
  barème victoire forte/faible, forfait codé `-1`, saisie, API, affichages « 3–2 », départage).
  Ajouter un sport après coup sans abstraction obligerait à reprendre toutes ces couches. Le match
  nul casse l'hypothèse « un vainqueur par match » (tableaux, règles `.vainqueur`).
- *Parade* : l'abstraction posée tôt, à coût faible ; les formats à élimination exigent un
  départage sans nul (prolongation, tirs au but…) déclaré par le sport.
- *Hors périmètre initial* : les **rencontres par équipes** des sports de raquette (une rencontre
  = plusieurs matchs individuels) changent le modèle lui-même ; elles feront l'objet d'une étude à
  part.
*(2026-10-13)*

## D14. Règlement généré
**Décision** : le règlement en ligne (schéma des phases, barème, départage) est généré à partir des
paramètres, en dernier lot.
*(2026-10-13)*

## D15. Une branche par lot
**Décision** : `formats-competition-lotN`, créée depuis `formats-competition` et fusionnée dedans à
la fin du lot (comme `tests-lot1` à `tests-lot4`). `formats-competition` est fusionnée dans `main`
quand un ensemble cohérent est prêt.
*(2026-10-13)*

## D16. Égalité à 3 équipes ou plus : deux critères distincts
**Décision** : le critère « confrontation directe » reste tel quel (dernier match joué entre les deux
équipes, comparaison deux à deux). Un nouveau critère **« mini-classement »** est proposé : points,
puis différence de sets, calculés sur les seuls matchs joués entre les équipes à égalité.
L'administrateur choisit l'un ou l'autre dans la liste de départage.
**Pourquoi** : les saisons existantes gardent exactement leur classement (D9) ; le mini-classement
règle le cas où la comparaison deux à deux tourne en rond (A bat B, B bat C, C bat A).
*(2026-10-13, lot 2)*

## D17. Critères de départage proposés
**Décision** : points, différence de sets, **quotient de sets** (nouveau, règle FFVB),
confrontation directe, mini-classement (D16), ordre alphabétique. Pas de nombre de victoires, de
forfaits ni de tirage au sort pour l'instant. Départage par défaut, celui d'aujourd'hui : points,
différence de sets, confrontation directe, ordre alphabétique. L'ordre alphabétique est toujours
appliqué en dernier, pour un classement identique d'un calcul à l'autre.
*(2026-10-13, lot 2)*

## D18. Équipe exempte : aucun point, sans option
**Décision** : comportement actuel conservé, non paramétrable.
*(2026-10-13, lot 2)*

## D19. Pas d'écran avant le wizard
**Décision** : le lot 2 fait le modèle (barème et départage de la saison, barème surchargeable par
phase) et les calculs, sans écran. Les saisons existantes gardent leurs valeurs ; la saisie se fera
dans le wizard (lot 4). Pas d'interface, donc pas de relecture ergonome / webdesigner pour ce lot.
*(2026-10-13, lot 2)*

---

## Points ouverts

Aucun pour l'instant. Les questions de détail apparaîtront lot par lot (notation des « meilleurs
Xe », options exactes de chaque format, écrans du wizard).
