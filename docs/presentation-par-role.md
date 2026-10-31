# Présenter les fonctionnalités selon le rôle : avis de l'ergonome (31/10/2026)

**Rien n'est décidé ni réalisé** : questions du §5 à trancher, puis décision à consigner dans
`docs/wizard-saison/decisions.md`.

## Verdict

Partir des droits réels de chaque compte, informer dans le site à l'endroit où l'on s'en sert, et un seul courriel par
rôle à la rentrée. Pas de visite guidée. D'abord, boucher deux trous de navigation (« Mon profil » sur téléphone, « Mon
équipe » pour les joueurs qui ne sont pas capitaines).

## 1. Qui peut faire quoi, et où

| Rôle | Ce qu'il peut faire | Où |
|---|---|---|
| Visiteur | Calendrier, classement, équipes, clubs, règlement ; « Choisir mon équipe » (navigateur) ; « Ajouter à mon agenda » | Menu, fiche équipe |
| Joueur connecté | En plus : composition et contact du capitaine ; « Page du match » ; « Sets joués » | Fiche équipe seulement |
| Capitaine | En plus : saisir le score de ses matchs ; « Gérer la composition » ; « Mon équipe » automatique ; rappel 2 jours après un match sans score | Calendrier, fiche équipe, Mon profil |
| Responsable de club | En plus : « Mon club », fiche du club (éditeur, D63), fermetures de gymnase (D60), scores et pages du match de toutes les équipes du club, stats de ses joueurs (D68) | Menu du compte, fiche club |
| Admin | Tableau de bord « À faire » (D64), « Scores à saisir », règlement, Volley Stats, paramètres du site | Menu de l'admin |

**Trous de découvrabilité**, du plus grave au moins grave :
1. « Mon profil » absent du menu sur téléphone (`base_front.html.twig`, menu latéral), alors que c'est la seule page qui
   explique les droits.
2. « Mon équipe » n'est choisie automatiquement que pour les capitaines (`MonEquipeExtension::equipeDefaut`) : un joueur ne
   découvre jamais « Page du match ».
3. Le bouton « Saisir » n'apparaît qu'une fois le match passé : le capitaine ne sait pas d'avance qu'il pourra saisir.
4. « Page du match » seulement sur la fiche équipe (ni accueil ni calendrier).
5. Fermetures et fiche du club accessibles seulement par « Mon club ».
6. Vocabulaire : « résultat » et « score » mélangés ; garder « score ».

## 2. Moyens de présentation

| Moyen | Avis |
|---|---|
| Bloc « Ce que vous pouvez faire », calculé d'après les droits | **Oui** : le socle, ne vieillit pas |
| Encart « Nouveau » à l'endroit concerné, refermable | **Oui**, un seul à la fois, avec une date de fin |
| Courriel de rentrée par rôle | **Oui**, une fois par saison |
| Phrase d'aide sous le titre (modèle : page Fermetures) | **Oui**, écrans complexes seulement |
| Page d'aide par rôle | Oui, courte, sans captures d'écran |
| Fiche PDF A4 (réunion de rentrée des clubs) | Oui, imprimée depuis la page d'aide |
| Vidéo courte | Seulement pour la saisie des actions (Volley Stats), 60 à 90 s |
| Visite guidée | **Non** (intrusive sur téléphone) |

**Quand** : première connexion → encart d'accueil « Ce que vous pouvez faire », une fois ; début de saison → courriel par
rôle, encart de nouveau si les droits ont changé ; nouvelle fonctionnalité → encart « Nouveau » un mois, et une ligne dans
le courriel suivant.

## 3. Message principal par rôle

- **Joueur** (accueil, puis fiche équipe) : « Vous êtes dans l'équipe [Nom]. Sur la fiche de votre équipe, ouvrez la page du
  match pour noter qui joue chaque set et suivre vos statistiques. »
- **Capitaine** (accueil, puis calendrier) : « Après chaque match, saisissez le score : le bouton « Saisir » apparaît sur le
  match dans le calendrier. Vous gérez aussi la composition de votre équipe depuis sa fiche. »
- **Responsable de club** (« Mon club ») : « Vous pouvez saisir le score de toutes les équipes du club, modifier la fiche du
  club et indiquer les fermetures de vos gymnases. Signalez une fermeture dès que vous la connaissez : le championnat en tient
  compte pour programmer les matchs. »
- **Admin** (tableau de bord) : « Commencez par la liste « À faire » : elle vous dit ce qui attend. Le règlement, Volley Stats
  et les paramètres du site sont dans le menu de gauche. »

## 4. À éviter

Liste de nouveautés écrite à la main (elle ment dès que les droits changent) ; fenêtre surgissante ou visite guidée ;
encarts empilés ou sans date de fin ; captures d'écran dans l'aide.

## 5. Questions à trancher

1. Qui envoie le courriel de rentrée : un bouton du tableau de bord, ou un message écrit par le bureau ?
2. Y a-t-il une réunion des responsables à la rentrée (fiche PDF) ?
3. Encart fermé : retenu par compte (en base) ou seulement dans le navigateur ?
4. « Mon équipe » choisie automatiquement pour tous les membres, pas seulement les capitaines ?
5. Volley Stats annoncé avant la fusion du paragraphe des conditions d'utilisation (D68) ?

Points d'attention : « Mon Profil » → « Mon profil » dans `base_front.html.twig` ; apparence des encarts : webdesigner.
