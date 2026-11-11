# Espace club : où en est-on (11/11/2026)

Branche `espace-club` (depuis `main` 1165e41). **Rien n'est réalisé : analyse et maquette seulement.** Décisions D74 à D77
dans `docs/wizard-saison/decisions.md`.

## Ce qui est décidé

- **D74** : tournois internes du club ; durée en périodes paramétrables (« dates » à l'écran) ; équipes formées et joueurs
  seuls ; extérieurs plus tard ; minimum de joueuses par équipe ; licenciés FFVB non gérés ; saisie par le responsable ;
  visibles des membres du club et des participants ; pas de paiement.
- **D75** : lien joueur / club / saison (plusieurs clubs possibles), repris des compositions d'équipe ; première version =
  **les mêlées seulement**, rien d'autre dans l'espace club.
- **D76** : non-mixte possible ; terrains pré-remplis depuis la capacité du créneau du gymnase ; le responsable fixe ce qu'il
  veut, le site calcule le reste.
- **D77** : deux tableaux le même soir (joueuses / joueurs), terrains partagés, mêmes tours.

## Documents, dans l'ordre de lecture

1. `formules-tournoi.md` : recherche sur les formules de tournoi (agent organisation-competition).
2. `parametres-melee.md` : les 73 paramètres d'une mêlée, défauts, calculs, cas limites.
3. `calcul-melee.md` : modèle de calcul « fixer ou faire calculer », scénarios côte à côte.
4. `parcours-melee.md` (v1) puis `parcours-melee-v2.md` (création en 5 étapes, pointage, soirée) : avis de l'ergonome.

Maquette fil de fer (9 écrans + étape Programme) : https://claude.ai/artifact/4ZibXrhAGdyYNdkAQPe47h (privée ; version 14,
à jour de D77 pour la création ; jour J et inscription encore en v1).

## Reprendre ici

1. **Questions à trancher par l'utilisateur** :
   - celles de `calcul-melee.md` (fin du document) : objectif par défaut, durée de match par défaut, remplaçants ou repos,
     ajustement au pointage proposé ou appliqué, écart joueuses / joueurs, hauteurs de filet (à vérifier), promesse de
     brassage « le moins possible » ;
   - hypothèses de l'ergonome : H1 à H10 (`parcours-melee.md`) et H11 à H24 (`parcours-melee-v2.md`), surtout H3 (niveau
     tiré de la poule), H9 (minuteur sans son), H10 (« date » pour une période), H13 (changement d'équipe réglé, pas
     calculé), H16 (remplaçants par défaut), H21 (« Aux points seulement » retiré).
   - D77 et D65 : jouer dans le tableau des joueuses révèle sa catégorie aux participants ; texte d'aide et conditions
     d'utilisation à prévoir.
2. **Maquette** : redessiner le jour J (pointage avec « programme du soir », tour en retard, deux tableaux) et l'inscription
   (mêlée réservée, deux tableaux) ; faire relire par l'ergonome et le webdesigner.
3. **Spécification** puis développement par étapes, en commençant par les membres du club (D75, reprise depuis
   `MembreEquipe`), puis la catégorie de jeu (D65), puis la mêlée.
