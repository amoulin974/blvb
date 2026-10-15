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

## D20. Codes des phases et des poules : automatiques, modifiables
**Décision** : phases `P1`, `P2`… dans l'ordre, `F` pour une phase finale ; poules : la lettre tirée
du nom (« Poule A » → `A`), sinon `A`, `B`, `C`… dans l'ordre des niveaux. Calculés par la migration
et à la création ; modifiables plus tard dans le wizard. Uniques dans la saison (phase) et dans la
phase (poule).
*(2026-10-13, lot 3)*

## D21. Règles des saisons existantes : le schéma 2025 (F1)
**Décision** : la migration génère, pour les saisons existantes, les règles du schéma 2025 :
- entre deux phases de championnat : montées et descentes de la poule (`nb_montee_defaut`,
  `nb_descente_defaut`) vers le niveau voisin, les autres équipes au même niveau ; si le niveau
  voisin n'existe pas, l'équipe reste à son niveau ;
- vers une phase finale : chaque poule vers le tableau de son niveau, dans l'ordre du classement ;
  les montées et descentes de la poule sont enregistrées **pour la saison suivante** (D22).
**Conséquence assumée** : la clôture de la phase 2 ne fait plus changer les équipes de tableau
(c'était le cas avec le calcul niveau ± 1) ; le comportement de la saison en cours change sur ce point.
*(2026-10-13, lot 3)*

## D22. Montées et descentes pour la saison suivante : enregistrées au lot 3, appliquées au lot 4
**Décision** : une règle peut avoir pour destination un niveau de la **saison suivante**. Le lot 3
les enregistre et les vérifie ; le wizard les appliquera en créant la saison suivante par copie
(lot 4), pour pré-remplir ses poules.
*(2026-10-13, lot 3)*

## D23. Points conservés : le champ existe, seule la remise à zéro est appliquée
**Décision** : chaque règle porte « points conservés » ; seule la remise à zéro (comportement actuel)
est appliquée au lot 3. Les autres options (tout, moitié, contre les qualifiés) viendront avec les
play-offs (lot 8).
*(2026-10-13, lot 3)*

## D24. Vérification en échec : avertir et continuer
**Décision** : si la vérification des règles échoue à la clôture (équipe sans destination ou avec
deux, poule ou rang inexistant…), la clôture se fait quand même et un avertissement liste les
problèmes. Règle d'application, pour un résultat prévisible : les règles s'appliquent dans l'ordre ;
une équipe déjà placée ne l'est pas une seconde fois ; une règle impossible à résoudre est ignorée ;
les équipes restées sans destination sont nommées dans l'avertissement.
*(2026-10-13, lot 3)*

## D25. Zones ▲ ▼ du classement : colonnes conservées jusqu'au wizard
**Décision** : `nb_montee_defaut` et `nb_descente_defaut` restent en base pour l'affichage des zones
de montée et de descente sur la page publique du classement ; le gabarit ne change pas au lot 3.
Les zones seront déduites des règles avec le wizard.
*(2026-10-13, lot 3)*

## D26. Phase sans règles enregistrées : règles par défaut
**Décision** : une phase créée sans règles (à la main dans l'admin, ou par les tests) reçoit, à la
clôture, les règles par défaut de D21, calculées à la volée. Les saisons créées par l'application
enregistrent leurs règles.
**Pourquoi** : aucune clôture ne doit perdre d'équipe parce qu'une règle manque.
*(2026-10-13, lot 3)*

## D27. Champ `format` de la phase : reporté au lot 4
**Décision** : `PhaseType` (championnat, finale) reste le type de la phase au lot 3 ; son
remplacement par un champ `format` se fera avec le wizard, car il touche le formulaire de phase de
l'admin.
*(2026-10-13, lot 3)*

## D28. Message de clôture : relu par l'ergonome et le webdesigner
**Décision** : sans problème, « Phase clôturée : les équipes ont été réparties dans la phase
suivante. » ; avec des avertissements, une seule alerte orange : un titre (« Phase clôturée, mais
certaines équipes n'ont peut-être pas été réparties comme prévu. Vérifiez les poules de la phase
suivante. »), une ligne par problème (liste à puces, les équipes sans poule en premier), puis un
conseil (corriger avec « Modifier la poule », régénérer les matchs, signaler au « responsable du
site »). Les règles sont écrites en clair, la notation entre crochets pour le responsable du site
(« 1er et 2e de la Poule A (Phase 1) vers la Poule A (Phase 2) [P1.A#1..2] »).
**Reporté** (choix de l'utilisateur) : contraste insuffisant de toutes les alertes de l'admin et
icône des messages de succès (modification à part) ; avertissements affichés avant de cliquer sur
« Clôturer » (avec le wizard).
*(2026-10-13, lot 3)*

## D29. Écrans du wizard : proposition de l'ergonome et du webdesigner retenue
**Décision** : `ecrans.md` devient le cahier des charges des écrans ; ses 19 questions sont tranchées
comme recommandé (13/10/2026) :
1. brouillon créé dès l'étape 1, enregistré à chaque changement d'étape ;
2. à la copie, dates et périodes de fermeture décalées d'un an, bandeau « à vérifier » ;
3. copie d'une saison non terminée permise, avec avertissement, poules non pré-remplies ;
4. « nombre d'équipes prévu » ajouté aux poules (facultatif, pré-rempli à la copie) ;
5. passages en mode simple par défaut (montées, descentes, « les autres »), mode détaillé en option ;
6. notation des places au récapitulatif seulement, masquée par défaut ;
7. codes des phases et poules dans des « Options avancées » repliées de l'étape 3 ;
8. « Modifier la saison » ouvre le wizard (étapes 2 à 5) tant qu'aucun match n'est joué, ensuite
   seulement nom, dates et périodes de fermeture ;
9. formats pas encore disponibles masqués ;
10. sport en texte fixe « Volley-ball » tant qu'il n'y en a qu'un ;
11. à la création, les erreurs de vérification bloquent la validation, les avertissements non
    (différent de la clôture, D24) ;
12. brouillon invisible sur le site, ne peut pas être la saison affichée, supprimable ;
13. nom proposé « Saison AAAA-AAAA+1 » ;
14. stepper en deux groupes : « Organisation » (1-6) puis « Mise en place » (lot 5) ;
15. réordonner par boutons Monter / Descendre seulement ;
16. « points conservés » caché jusqu'au lot 8 ;
17. schéma : matrice par niveaux sur ordinateur, flux vertical sur téléphone ;
18. « Valider la saison » visible même avec des erreurs, désactivé avec la raison écrite ;
19. libellés « Passages » (étape 4) et « Périodes de fermeture » (terme déjà en usage), modifiables à
    la relecture.
*(2026-10-13, lot 4)*

## D30. Effectif d'une poule différent du nombre prévu : avertissement
**Décision** : à la création d'une saison, une poule qui reçoit plus ou moins d'équipes que son
« nombre d'équipes prévu » produit un avertissement, qui n'empêche pas de valider.
**Pourquoi** : des poules de 8 et 9 équipes sont courantes au BLVB ; le nombre prévu est indicatif.
*(2026-10-13, relecture du wireframe)*

## D31. Code d'une phase de barrages : BAR, BAR2…
**Décision** : complète D20 : une phase de barrages reçoit le code `BAR` (puis `BAR2`, `BAR3`…), comme
`P1` pour un championnat et `F` pour une phase finale ; notation `BAR.AB.vainqueur`.
*(2026-10-13, relecture du wireframe)*

## D32. Barrages à l'écran : une carte par match
**Décision** (ergonome, relecture du wireframe) : à l'étape « Passages », chaque match de barrage est
saisi sur une seule carte : qui joue (« avant-dernier de la Poule A contre le 2e de la Poule B ») et où
vont le vainqueur et le perdant. En base, ce sont toujours des règles de passage (vers la phase de
barrages, puis depuis elle). Pour chaque poule, une ligne par rang rend visibles les équipes qui jouent
un barrage ; le nombre de montées directes reste un choix. Format réalisé au lot 6.
*(2026-10-13, relecture du wireframe)*

## D33. Champ `format` de la phase : reporté au lot 6 (modifie D27)
**Décision** : le wizard du lot 4 ne propose que deux types de phase (championnat, tableau à
élimination), qui correspondent exactement à `PhaseType` ; `PhaseType` est donc conservé. Son
remplacement par un champ `format` se fera au lot 6, avec l'arrivée des barrages (D31, D32).
**Pourquoi** : un seul refactoring, au moment où un troisième format en a réellement besoin.
*(2026-10-13, lot 4)*

## D34. Modifier une saison créée : jusqu'à son lancement, enregistrement à chaque étape (précise D29)
**Décision** (relectures ergonome et sécurité du lot 4) :
- le wizard reste ouvert sur une saison créée tant qu'elle n'est pas **lancée** : aucun match
  programmé (même sans score), aucune phase clôturée, aucun classement enregistré. Ensuite, seuls le
  nom, les dates et les périodes de fermeture se modifient ;
- chaque étape enregistre en la quittant, comme pour un brouillon ; un bandeau le rappelle.
  « Enregistrer et quitter » renvoie à la vérification tant que la saison contient une erreur ; le
  bouton final s'appelle « Terminer » ;
- un brouillon, dans la liste des saisons, ne propose que « Reprendre » et « Supprimer » (D29.12).
**Pourquoi** : supprimer une poule d'une saison dont le calendrier est généré emportait les matchs
programmés, les abonnements `.ics` et, après une clôture, provoquait une erreur 500 ; une saison
visible sur le site ne doit pas rester incohérente.
*(2026-10-13, lot 4)*

## D35. Mise en place : écrans dédiés dans le wizard
**Décision** : les étapes Poules, Journées et Matchs (groupe « Mise en place », D29.14) sont de
nouveaux écrans du wizard, qui réutilisent les services existants (`JourneeService`, `PartieService`,
optimiseur) avec un parcours guidé et un bilan par étape. Les écrans d'administration actuels
(poule, journée, partie) restent disponibles pour les retouches.
**Pourquoi** : un parcours continu avec vérification globale, plutôt qu'un renvoi vers des pages éclatées.
*(2026-10-14, lot 5)*

## D36. Composition des poules : pré-remplie, ajustable
**Décision** : pour une saison copiée d'une saison terminée, les équipes de la phase 1 sont placées
d'après les montées et descentes enregistrées pour la saison suivante (D22) ; l'administrateur ajuste
(équipes nouvelles, retraits). L'écart entre effectif réel et « nombre d'équipes prévu » reste un
avertissement (D30).
*(2026-10-14, lot 5)*

## D37. Enchaînement des phases : retour au wizard
**Décision** : après la clôture d'une phase, le wizard rouvre Poules → Journées → Matchs pour la phase
suivante. Les avertissements des règles de passage s'affichent **avant** de cliquer sur « Clôturer »
(point reporté de D28).
*(2026-10-14, lot 5)*

## D38. Étape Poules : version relue du wireframe retenue
**Décision** : `ecrans-mise-en-place.md`, §7, remplace les parties contradictoires du §3 : « Déplacer »
déplie une liste et un bouton « Valider » (ergonome), tableau de synthèse des effectifs au-delà de
4 poules (webdesigner), « Retirer de la poule », pas d'action « Ne participe plus », équipes de la
saison copiée proposées d'abord.
**Pourquoi** : relecture de l'ergonome et du webdesigner ; les deux divergences ont été tranchées par
l'utilisateur.
*(2026-10-14, lot 5)*

## D39. Étape Journées : version relue du wireframe retenue
**Décision** : `ecrans-mise-en-place.md`, §14, complète les §8 à §13. Libellés « Générer les journées »,
« Régénérer les journées » (confirmation chiffrée, `btn-ghost`) et « Ajuster dans le calendrier » ; dates
« 14/09 → 20/09 » ; calendrier existant renvoyé tel quel, lien conservé sur téléphone avec une aide ;
aucune génération à l'ouverture de l'écran.
**Pourquoi** : relecture de l'ergonome et du webdesigner ; les divergences ont été tranchées par
l'utilisateur.
*(2026-10-14, lot 5)*

## D40. Étape Matchs : version relue retenue ; la clôture ne crée plus journées ni matchs
**Décision** : `ecrans-mise-en-place.md`, §21, complète les §15 à §20 (attente du calcul annoncée sans
traitement en arrière-plan, rapport recalculé à l'affichage, génération par poule limitée aux poules sans
match, équipe sans gymnase en avertissement). La clôture d'une phase applique les règles de passage puis
renvoie à l'étape Poules de la phase suivante ; elle **ne crée plus** les journées ni les matchs (D37).
**Pourquoi** : la création automatique se faisait sans que personne ne relise la répartition des équipes ;
choix de l'utilisateur après relecture de l'ergonome.
*(2026-10-14, lot 5)*

## D41. Optimisation des matchs : priorités fixes, replacement des matchs à venir, poule par poule
**Décision** (après avis de l'ergonome) :
- **Pas de pondération** des critères : les priorités restent fixes (aucun gymnase surchargé, puis alternance
  domicile / extérieur, puis moins de déplacements de suite) et sont **expliquées à l'écran**, dans la fenêtre de
  génération.
- **« Replacer les matchs à venir »**, pour la phase entière comme pour une poule, dès que des matchs sont joués.
  Les matchs avec un score saisi sont conservés tels quels (adversaires, journée, domicile ou extérieur, gymnase,
  date) et comptent dans l'occupation des gymnases ; les autres sont supprimés puis recréés dans leur journée, sans
  jamais rejouer une rencontre. L'administrateur doit **cocher** « Je confirme qu'aucun match déjà joué n'attend
  la saisie de son score » (contrôle côté serveur) ; le nombre de matchs sans score à date passée est signalé.
- **Régénération d'une poule** non réservée aux phases sans match joué : elle reste possible pour une poule qui
  n'a aucun match joué, même quand d'autres poules de la phase en ont. Une poule ou une phase avec des matchs joués
  ne se régénère plus en entier (scores et classement perdus) : on y replace les matchs à venir. Le message de
  régénération d'une poule dit que le résultat d'ensemble sera au mieux égal à un calcul de toute la phase.
- **Équipe qui se retire, forfait général** : sujets traités à part (règle de forfait), pas dans l'optimisation.
**Pourquoi** : les besoins réels apparaissent après le début de la saison (gymnase indisponible, erreur de
composition, report) et ne doivent pas détruire les scores ; une pondération n'a pas de sens concret pour le bureau
et permettrait des calendriers injouables.
*(2026-10-14, lot 5)*

---

## Points ouverts

### PO1. Page d'une saison hors wizard : recentrer sur le suivi (avis de l'ergonome, 14/10/2026)
**Situation** : depuis la liste des saisons, le nom d'une saison et le bouton « Piloter » mènent à la même page
(`admin/saison/show`), qui reprend en doublon des actions du wizard (composer les poules, créer les journées,
générer les matchs, optimiser, modifier la poule, calendrier des journées) et en propose de dangereuses qui
contournent ses garde-fous (modifier ou supprimer une phase, supprimer les poules, journées ou matchs en masse,
générer sans relecture, optimiser sans la case de confirmation de D41). Pour un brouillon, le nom est un lien vers
cette page alors que la ligne ne propose que « Reprendre » et « Supprimer » (D34).

**Avis de l'ergonome** (« oui avec conditions » : une page de suivi par saison est pertinente, pas un second outil de
construction concurrent du wizard) :
- *Brouillon* : nom non cliquable ; seuls « Reprendre » et « Supprimer » ; la page show redirige vers le wizard (garde
  serveur).
- *Validée, sans matchs* : le clic ouvre l'écran « Mise en place » à l'étape à faire ; la page show reste en lecture ;
  « Modifier » mène au wizard (étapes 2 à 6).
- *En cours* : clic = page de suivi (scores, changement de date ou de gymnase d'un match, « Clôturer la phase »,
  « Replacer les matchs à venir ») ; suppressions en masse masquées ; « Modifier la phase » et « Modifier la poule »
  deviennent des liens vers l'étape du wizard ; suppression d'un match isolé conservée, avec confirmation.
- *Terminée* : page en lecture seule, seul « Modifier » (nom, dates) reste, et « Créer la saison suivante à partir de
  celle-ci » (D22, D36) s'ajoute.
- *Liste* : colonne « État » (« Brouillon · étape 3 sur 6 », « Validée · à mettre en place », « En cours · Phase 2 »,
  « Terminée ») ; un seul bouton principal (« Reprendre la création », « Continuer la mise en place », « Suivre la
  saison », « Consulter ») ; le nom mène à la même cible ; « Piloter » disparaît.
- *Libellés de la page* : « Composer les poules » → « Mise en place des poules » ; « Modifier la saison » → « Nom et
  dates » ; accords « 1 match » / « 2 matchs » dans les confirmations.

**Décisions à prendre par l'utilisateur** (aucune n'est actée) :
1. Masquer ou supprimer les suppressions en masse (phase, poule, journées, matchs) dès que la saison est lancée ?
   (recommandé : oui, cohérent avec D34 et D41)
2. « Modifier la phase » et « Modifier la poule » : liens vers les étapes du wizard (recommandé) ou conservés « pour
   les retouches » (D35) ?
3. Le clic sur une saison en cours ouvre-t-il la page de suivi (recommandé) ou le wizard ?
4. Ajouter « Créer la saison suivante » sur une saison terminée ?
5. Saisie des scores : où les capitaines saisissent-ils aujourd'hui (l'ergonome n'a pas trouvé le point d'entrée sur
   la page show) et faut-il un raccourci « Saisir un score » ? **À vérifier dans le code avant de décider.**

### PO2. Équipe qui se retire, forfait général
Traités à part de l'optimisation (D41) : règle de forfait (score réglementaire), à cadrer avec le bureau.

### PO3. Questions de détail des lots suivants
Notation des « meilleurs Xe », options exactes de chaque format, écrans du wizard : elles apparaîtront lot par lot.
