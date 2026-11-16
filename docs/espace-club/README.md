# Espace club : où en est-on (16/11/2026)

Branche `espace-club` (depuis `main` 1165e41). **Rien n'est réalisé : analyse et maquette seulement.** Décisions D74 à D82
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
3. `calcul-melee.md` : modèle de calcul « fixer ou faire calculer », scénarios côte à côte. Puis `calcul-melee-complement.md` (après D78 à
   D80) : programme égal, pointage, retard, 13 à 19 joueurs, deux tableaux à terrains variables, filet.
4. `parcours-melee.md` (v1) puis `parcours-melee-v2.md` (création en 5 étapes, pointage, soirée) : avis de l'ergonome.
5. `parcours-melee-v3.md` : ce qui change après D77 et D78 (bloc « Qui joue à chaque tour », jour J et inscription en deux
   tableaux, classement par catégorie, filet) ; hypothèses H25 à H33 et incohérences avec le modèle de calcul (§5).

6. `specification-melee.md` : spécification technique (données, calcul, tirage, droits, ordre de réalisation), à faire relire
   par l'agent `securite`.

Maquette fil de fer (10 écrans) : https://claude.ai/artifact/4ZibXrhAGdyYNdkAQPe47h (privée ; version 3 du parcours au
16/11/2026 pour les étapes 2 et 3, le jour J et l'inscription ; étapes 1, 4 et 5 encore en version 2).

## Reprendre ici

1. **Décisions à prendre après la relecture sécurité** (`relecture-securite-melee.md`) : retrait automatique des membres
   venus d'une équipe (I1) ; catégorie de jeu vue et saisie par le responsable, courriel à un membre ajouté à la main (I2) ;
   courriel au joueur inscrit par le responsable dans une mêlée qui montre la catégorie (I3) ; délai avant d'afficher un
   classement par catégorie ou des tableaux ajoutés après coup (I4) ; saison fixée à la création, gel 7 jours après la fin
   (I5) ; purge étendue aux membres et aux fiches sans compte (I8) ; fiche d'un adhérent sans compte, déconseillée par la
   relecture alors que D82 l'accepte.
2. **Maquette** (10 écrans en version 3, corrigée après la relecture de l'ergonome) : relecture du webdesigner ; états encore
   à dessiner (écart joueuses / joueurs, pointage irréalisable, 16 à 19 présents).
3. **Développement** dans l'ordre du §9 de `specification-melee.md`, en commençant par les membres du club.
4. À prévoir : conditions d'utilisation pour la catégorie de jeu rendue visible (D77, D78).
