# Tableau de bord de l'admin : proposition de l'ergonome (30/10/2026)

À réaliser. **Points du §3 tranchés le 31/10/2026 : voir D64 dans `decisions.md`**, qui l'emporte sur les recommandations
ci-dessous (questions 3 et 5 en particulier) ; l'aide sur le placement (bloc A) est abandonnée. Suivi : `lots.md`.

Analyse fondée sur le code et la base de recette, sans voir l'écran. Les chiffres « en local » sont ceux de la recette, ceux
des maquettes sont des exemples.

## 1. Constat

La page date d'avant le wizard : elle compte des fiches, alors qu'un admin a besoin de savoir quoi faire.

**Obsolète**
- Tuiles Équipes, Joueurs, Utilisateurs, Gymnases : totaux toutes saisons (67, 250, 130, 15 en local), qui ne permettent aucune décision.
- « Gymnases : Adresses et créneaux » : les fermetures ne sont pas citées.
- Carte de saison : « N phases » ne dit pas où en est la saison, et la phrase « c'est depuis la page de la saison… » est un mode d'emploi affiché en permanence.
- Trois noms pour une même saison : « Piloter la saison », « Saison en cours » (menu), « Saison affichée sur le site ». Le menu dit « en cours » même si la saison est finie.

**Manquant**
- Saisons en préparation : `findCourante()` exclut les brouillons et ne retient qu'une saison ; une saison validée mais pas affichée n'apparaît pas non plus.
- Aucune saison : la page montre des tuiles et aucun bouton « Créer une saison ».
- Clubs : pas de tuile, alors que l'entrée existe au menu.
- Règlement : aucun signal.
- Fermetures : un match en conflit n'est signalé qu'une fois, à l'enregistrement.
- Scores manquants, phase à mettre en place après une clôture (D37), adresse de contact vide (D62).

**Inutile** : « Outils techniques » en bas de page, doublon du menu vers des écrans que PO1 juge risqués (à retirer de cette page seulement).

**À garder** : l'alerte sur la limitation de connexion, texte compris (D62, PAR-02). Le logo ne mérite aucun signal : il a un logo d'origine en repli.

## 2. Proposition

La page répond à trois questions, dans cet ordre : y a-t-il quelque chose à faire ? où en est la saison ? où aller pour le reste ?

### Blocs, par ordre de priorité

**A. « À faire »** : une phrase et un bouton par ligne, 5 lignes au plus, puis « Voir les N autres » (`<details>`). Sans rien à faire : « Rien à faire pour l'instant. »

| # | Condition | Libellé (singulier / pluriel) | Bouton et cible |
|---|---|---|---|
| 1 | Match sans score dans un gymnase fermé | « 1 match est programmé dans un gymnase fermé. » / « 2 matchs sont programmés dans un gymnase fermé. » | « Voir les matchs » : déplie la liste, puis « Déplacer » vers le calendrier de la poule |
| 2 | Des saisons publiées, aucune favorite | « Aucune saison n'est choisie pour le site. » | « Choisir la saison » : liste des saisons |
| 3 | Phase non clôturée, une poule sans équipes, journées ou matchs | « Phase 2 : la mise en place n'est pas terminée. » | « Continuer la mise en place » |
| 4 | Phase clôturable, plus aucun match sans score, pas la dernière | « Phase 1 : tous les scores sont saisis. Vous pouvez la clôturer. » | « Aller à la clôture » : `#phase_id` |
| 5 | Scores absents depuis plus de 2 jours | « 1 score manque depuis plus de 2 jours. » / « 8 scores manquent depuis plus de 2 jours. » | « Voir les matchs » : déplie les 5 plus anciens |
| 6 | Pas de règlement | « Saison 2026-2027 : le règlement n'est pas encore rédigé. » | « Rédiger le règlement » (libellé existant) |
| 7 | Limitation désactivée | « Les essais de connexion ne sont pas limités. » (existant) | « Vérifier et activer la limitation » (existant) |
| 8 | Adresse de contact vide | « Aucune adresse de contact : le pied de page n'en affiche pas, et les responsables de club écrivent aux adresses de connexion des admins. » | « Renseigner l'adresse » |

Tant que les lots 2 et 3 des fermetures ne sont pas faits, la ligne 1 porte cette aide : « Le placement ne tient pas encore compte des fermetures : vérifiez le gymnase choisi. »

Accessibilité : `<section>` avec titre `<h2>`, liste `<ul>`, pas de `role="alert"` au chargement ; chaque bouton nomme son objet en `sr-only` ; l'urgence ne repose pas sur la couleur seule.

**B. Carte de la saison principale** : nom, état, repère, bouton unique.

**C. Autre saison active**, en carte réduite (exemple : la saison affichée est en cours et la suivante en préparation).

**D. « Fermetures de gymnase »** : les 3 prochaines, avec lien vers le gymnase ; masqué s'il n'y en a aucune ; lien vers « Gymnases » s'il y en a plus de 3.

**E. « Accès rapides »**, 6 tuiles (`.admin-carte`) :
- Équipes : « 24 engagées cette saison »
- Joueurs : « 180 cette saison »
- Clubs : « 14 clubs · 3 sans responsable »
- Gymnases : « 15 gymnases · 1 fermé aujourd'hui »
- Utilisateurs : « 130 comptes »
- Paramètres du site : « Connexion, contact, logo »

### Selon le moment de la saison

| Moment | Carte principale | Bouton principal | Lignes propres à ce moment |
|---|---|---|---|
| Aucune saison | « Aucune saison pour le moment » / « Créez la première saison : le site affichera ensuite son calendrier et son classement. » | « Créer une saison » | Lignes 7 et 8 seulement |
| Brouillon | « Saison en préparation » / « Brouillon · étape 3 sur 6 : Phases » / « Modifiée le 28 octobre · invisible sur le site » / « 2 points à corriger avant de valider » | « Reprendre la création » | Ligne 6 |
| Validée, à mettre en place | « Saison validée · à mettre en place » / « Phase 1 : poules faites · journées à faire · matchs à faire » | « Continuer la mise en place » | Lignes 1, 3, 6 ; clubs sans responsable et équipes sans capitaine (point 5 du §3) |
| Prête, pas affichée | « Saison prête, pas encore affichée sur le site » | « Afficher sur le site » (avec confirmation) | Idem |
| En cours | « Saison affichée sur le site » / « Phase 2 en cours · du 15 mars au 4 juillet » / « Matchs joués : 10 sur 59 » / « Prochain match : mardi 3 novembre » | « Suivre la saison » ; lien « Règlement » | Toutes |
| Terminée | « Saison terminée » / « 59 matchs joués sur 59 » | « Créer une saison » s'il n'y en a pas d'autre en préparation, sinon « Consulter la saison » | Lignes 5 et 6 retirées |

### Maquette ordinateur (saison en cours)

```
Tableau de bord
+---------------------------------------------+------------------------------+
| À faire (3)                                 | SAISON AFFICHÉE SUR LE SITE  |
| 2 matchs sont programmés dans un gymnase    | Saison 2026-2027             |
| fermé.                    [Voir les matchs] | Phase 2 en cours             |
| Le placement ne tient pas encore compte...  | du 15 mars au 4 juillet      |
| 8 scores manquent depuis plus de 2 jours.   | Matchs joués : 10 sur 59     |
|                           [Voir les matchs] | Prochain match : mar. 3 nov. |
| Les essais de connexion ne sont pas limités.| [Suivre la saison]           |
|        [Vérifier et activer la limitation]  | Règlement · Toutes les saisons|
+---------------------------------------------+------------------------------+
| EN PRÉPARATION  Saison 2027-2028 · Brouillon · étape 3 sur 6 : Phases      |
|                                                   [Reprendre la création]  |
+----------------------------------------------------------------------------+
| Fermetures de gymnase                                                      |
| Du 10 au 14 novembre · Gymnase de Bidart · Travaux         [Voir le gymnase]|
+----------------------------------------------------------------------------+
| Accès rapides                                                              |
| [Équipes 24 engagées]   [Joueurs 180]            [Clubs 14 · 3 sans resp.] |
| [Gymnases 15 · 1 fermé] [Utilisateurs 130]       [Paramètres du site]      |
+----------------------------------------------------------------------------+
```

### Maquette téléphone (une colonne)

```
Tableau de bord
+----------------------------+
| À faire (3)                |
| 2 matchs sont programmés   |
| dans un gymnase fermé.     |
| [ Voir les matchs        ] |
| ---------------------------|
| 8 scores manquent depuis   |
| plus de 2 jours.           |
| [ Voir les matchs        ] |
| ---------------------------|
| > Voir l'autre point       |
+----------------------------+
| SAISON AFFICHÉE SUR LE SITE|
| Saison 2026-2027           |
| Phase 2 en cours           |
| Matchs joués : 10 sur 59   |
| [ Suivre la saison       ] |
| Règlement                  |
+----------------------------+
| EN PRÉPARATION             |
| Saison 2027-2028           |
| Étape 3 sur 6 : Phases     |
| [ Reprendre la création  ] |
+----------------------------+
| Fermetures de gymnase      |
| 10-14 nov. · Bidart        |
+----------------------------+
| Accès rapides              |
| [Équipes  ] [Joueurs     ] |
| [Clubs    ] [Gymnases    ] |
| [Comptes  ] [Paramètres  ] |
+----------------------------+
```

- Cibles tactiles : les boutons ont déjà 44 px (`main .btn`, `pointer: coarse`) ; les liens simples comme « Règlement » et « Voir le gymnase » doivent passer en `.btn`.
- Tuile « Comptes » : ce raccourci téléphone garde le mot « Utilisateurs » si la largeur le permet, comme le menu.
- La hiérarchie visuelle et les niveaux d'urgence reviennent au webdesigner.

## 3. Points à trancher (recommandation de l'ergonome entre parenthèses)

1. **Définition de « terminée »** : `close` ne suffit pas, car `saison/show` n'offre jamais « Clôturer » sur la dernière phase (`not loop.last`). (Date de fin dépassée, ou dernière phase avec des matchs tous joués.) Faut-il aussi proposer la clôture de cette phase ?
2. **Saison principale** quand la saison affichée est finie et qu'une autre se prépare. (La saison en préparation devient la carte principale.)
3. **Saisie depuis « Voir les matchs »** : aujourd'hui, le calendrier admin d'une poule, ou `/calendrier` en public, sans filtre et lié à la saison en session. (Commencer par le calendrier admin et l'essayer sur téléphone ; s'il est malcommode, créer une page « Scores à saisir » réutilisant `_score_modal`.)
4. **Seuil des scores manquants**. (2 jours, comme le rappel automatique : pas d'alerte pour un match joué la veille.)
5. **Clubs sans responsable, équipes sans capitaine** : en local, 3 clubs sur 14 ont un responsable pour la saison 6 ; une ligne permanente finirait ignorée. (En ligne « À faire » pendant la mise en place seulement, sinon chiffre dans la tuile.)
6. **« Suivre la saison » et « Créer la saison suivante »** relèvent de PO1, non tranché. (Adopter « Suivre la saison » vers `admin_saison_show` ; garder « Créer une saison » tant que PO1.4 n'est pas décidé.)
7. **Menu « Saison en cours »**. (Renommer en « Saison affichée », exact même quand elle est finie.)
8. **Rappel de D60 avant la génération des matchs** : « Avant de générer les matchs, demandez aux clubs leurs fermetures de gymnase. » (À ajouter seulement après le lot 2 : aujourd'hui, les fermetures n'influencent pas le placement.)

## 4. Données

**Déjà disponible** : `SaisonRepository::findCourante`, `estLancee` ; `ClubRepository::nombreEquipesEngagees` ; `ReglementRepository::findDeSaison` ; `PhaseService::estCloturable` ; `VerificationSaison::verifier` ; `ParametresSite` ; `Saison::etapeWizard`, `modifieLe`, `SaisonWizardController::ETAPES`.

**Nouvelles requêtes, sans migration**
1. Service d'état de saison : brouillon, à mettre en place, prête, en cours, terminée. La phase en cours est la première non clôturée par `ordre` ; ne pas réutiliser `getPhaseActuelle`, fondé sur les dates, qui retombe sur la première phase.
2. État de mise en place : comptages légers par poule (équipes, journées, matchs) ; éviter `CompositionPoules::etat()`, qui charge toutes les équipes.
3. Saisons actives : brouillons et saisons validées hors saison affichée.
4. `PartieRepository` : matchs joués et total par saison ; matchs sans score depuis plus de 2 jours ; prochain match ; matchs sans score dans un gymnase fermé (critères de `matchsProgrammes`, jointure sur `FermetureGymnase` par gymnase et dates).
5. `FermetureGymnaseRepository` : fermetures en cours et à venir de tous les gymnases (`enCoursOuAVenir` exige une liste de gymnases).
6. Comptages de la saison : clubs engagés sans responsable, équipes engagées sans capitaine, joueurs de la saison (`MembreEquipe`).

**Non calculable aujourd'hui, donc non proposé** : « nouvelle fermeture signalée » (ni date de saisie ni auteur, D60) ; comptes bloqués par la limitation (compteurs en cache seulement, D61) ; état « terminée » en base (il n'existe pas).

## 5. Points d'attention

1. **Texte actuellement faux, indépendant de la refonte** : « Aucun match n'est placé dans un gymnase pendant sa fermeture. » dans `templates/admin/lieu/fermetures.html.twig` (ligne 9) et `templates/front/club/fermetures.html.twig` (ligne 18). Tant que les lots 2 et 3 ne sont pas faits, proposition pour l'admin : « Les fermetures ne bloquent pas encore le placement des matchs : vérifiez le gymnase quand vous placez un match. » Pour les responsables, garder « Club {{ club.nom }}. » puis : « Indiquez vos fermetures le plus tôt possible : les admins en tiennent compte pour placer les matchs. »
2. **« Afficher sur le site » change les droits** : le rôle de responsable et la désignation suivent `findCourante()`. Il faut une confirmation : « Les responsables de club désignés pour une autre saison perdent leurs droits tant qu'ils ne sont pas désignés pour celle-ci. » On ne peut donc pas désigner les responsables d'une saison avant de l'afficher : la ligne « clubs sans responsable » ne peut porter que sur la saison affichée, à moins de permettre cette désignation à l'avance. La route `favorite` renvoie aussi vers la liste des saisons, pas vers le tableau de bord.
3. **Avant réalisation** : D64 dans `decisions.md` ; `tests/SPECIFICATIONS.md` (PAR-02 et nouveaux tests) ; relecture webdesigner ; vérifier que le cron des rappels est en place (`deploiement-reglement.md`) avant d'afficher un texte promettant un rappel automatique.
4. La fiche de l'agent ergonome décrit des admins sur ordinateur ; ils sont souvent sur téléphone pendant la saison.
