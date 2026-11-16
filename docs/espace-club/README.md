# Espace club : où en est-on (16/11/2026)

Branche `espace-club` (depuis `main` 1165e41). **Rien n'est réalisé : analyse et maquette seulement.** Décisions D74 à D80
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
- **D78** : réglages par défaut (objectif « tout le monde joue autant », matchs de 10 min, joueurs en trop au repos,
  changement d'équipe 5 min, filet 2,43 / 2,24 / 2,35 m réglable), classement par catégorie possible, questions tranchées.

## Documents, dans l'ordre de lecture

1. `formules-tournoi.md` : recherche sur les formules de tournoi (agent organisation-competition).
2. `parametres-melee.md` : les 73 paramètres d'une mêlée, défauts, calculs, cas limites.
3. `calcul-melee.md` : modèle de calcul « fixer ou faire calculer », scénarios côte à côte.
4. `parcours-melee.md` (v1) puis `parcours-melee-v2.md` (création en 5 étapes, pointage, soirée) : avis de l'ergonome.
5. `parcours-melee-v3.md` : ce qui change après D77 et D78 (bloc « Qui joue à chaque tour », jour J et inscription en deux
   tableaux, classement par catégorie, filet) ; hypothèses H25 à H33 et incohérences avec le modèle de calcul (§5).

Maquette fil de fer (9 écrans + étape Programme) : https://claude.ai/artifact/4ZibXrhAGdyYNdkAQPe47h (privée ; version 14,
à jour de D77 pour la création ; jour J et inscription encore en v1).

## Reprendre ici

0. **Modèle de calcul** à corriger par l'expert (§5 de `parcours-melee-v3.md`, D78 à D80 : 10 min sur la carte conseillée,
   repos par défaut, terrains variables d'un tour à l'autre en deux tableaux).
1. **Maquette** : redessiner le jour J (pointage avec « programme du soir », tour en retard, deux tableaux) et l'inscription
   (mêlée réservée, deux tableaux), et reporter D78 dans l'étape Programme (10 min par défaut, repos par défaut, message au
   changement du battement, filet, classement par catégorie) ; relectures ergonome et webdesigner.
2. **Spécification** puis développement par étapes, en commençant par les membres du club (D75, reprise depuis
   `MembreEquipe`), puis la catégorie de jeu (D65), puis la mêlée.
3. À prévoir : texte d'aide et conditions d'utilisation pour la catégorie de jeu rendue visible par les deux tableaux (D77) et
   le classement par catégorie (D78).
