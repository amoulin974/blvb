# BLVB : consignes pour Claude Code

Site de gestion du championnat de volley BLVB (Symfony 7.4, PHP 8.4, Doctrine, Twig, Tailwind/daisyUI, MySQL 8.4,
Docker). Tout est en français : code métier, libellés, messages de commit (`domaine : description en minuscules`).

## Chantier en cours : formats de compétition et wizard de création de saison

Branches `formats-competition` et `formats-competition-lotN`. Lots 1 à 6 et lot 11 (règlement par saison, R1 à R5)
fusionnés dans `main` le 22/10/2026 ; R6 (schémas dessinés) en attente sur la branche `reglement`. Mise en production :
`docs/wizard-saison/deploiement-reglement.md` (Francis).
**Avant de reprendre ce chantier**, lire dans l'ordre :
1. `docs/wizard-saison/methode.md` (objectif, méthode, journal, reprise sur un autre poste) ;
2. `docs/wizard-saison/decisions.md` (aucune décision de conception rediscutée sans raison nouvelle) ;
3. `docs/wizard-saison/lots.md` (état des lots), puis `plan.md`.

Le catalogue des formats est dans `docs/formats-competition.md`. La version de référence du journal est celle de la
branche du lot en cours ; la copie sur `main` peut être en retard. Règlement par saison : `docs/wizard-saison/reglement.md`.

## Règles permanentes

- **Tests** : toute modification de test (ajout, suppression, renommage, changement de ce qui est vérifié) met à jour
  `tests/SPECIFICATIONS.md` (et `tests/recette/README.md` si la recette change) dans le même commit.
- **Interfaces** : dès qu'un écran, formulaire, gabarit Twig ou libellé affiché est réalisé ou modifié, passer les agents
  `ergonome` et `webdesigner` avant le commit ; `securite` pour les formulaires, API et droits.
  Les fiches sont dans `.claude/agents/` (si l'agent n'est pas chargé, lancer un agent général qui lit la fiche).
- **Branche `prod`** : ne jamais fusionner ni pousser dedans, Francis seul s'en charge. On s'arrête à `main` ;
  l'agent `deploiement` rend la checklist à lui transmettre.
- **Migrations** du chantier : appliquées en production au déploiement seulement.
- **Pas de décision de conception** sans l'avoir consignée dans `decisions.md`.

## Environnement

- Le dépôt est dans WSL (`~/projects/blvb`). Depuis Windows, git refuse le chemin `\\wsl.localhost\…`
  (« dubious ownership ») : lancer git dans WSL (`wsl.exe -d Ubuntu -- bash -c 'cd ~/projects/blvb && git …'`).
- Tests et qualité, dans Docker : `docker compose exec php composer tests` et `composer qualite`.
- Après un changement de branche : `bin/base etat` puis `bin/base aligner`.
- CSS : `tailwind:build` après chaque changement de style ; ne pas lancer `asset-map:compile` en local.
