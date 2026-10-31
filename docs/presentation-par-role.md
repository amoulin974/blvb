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
| Joueur connecté | En plus : composition et contact du capitaine ; « Page du match » ; « Sets joués » ; « Mon équipe » automatique (D69) | Fiche équipe seulement |
| Capitaine | En plus : saisir le score de ses matchs ; « Gérer la composition » ; rappel 2 jours après un match sans score | Calendrier, fiche équipe, Mon profil |
| Responsable de club | En plus : « Mon club », fiche du club (éditeur, D63), fermetures de gymnase (D60), scores et pages du match de toutes les équipes du club, stats de ses joueurs (D68) | Menu du compte, fiche club |
| Admin | Tableau de bord « À faire » (D64), « Scores à saisir », règlement, Volley Stats, paramètres du site | Menu de l'admin |

**Trous de découvrabilité**, du plus grave au moins grave :
1. (Réglé par D69.) « Mon profil » absent du menu sur téléphone (`base_front.html.twig`, menu latéral), alors que c'est la seule page qui
   explique les droits.
2. (Réglé par D69.) « Mon équipe » n'est choisie automatiquement que pour les capitaines (`MonEquipeExtension::equipeDefaut`) : un joueur ne
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
3. (Tranché le 01/11/2026 : par compte, en base, avec le rôle pour lequel il a été vu ; la migration partira avec les autres, Francis ne les a pas encore appliquées.) Encart fermé : retenu par compte (en base) ou seulement dans le navigateur ?
4. (Réglé par D69 : oui.) « Mon équipe » choisie automatiquement pour tous les membres, pas seulement les capitaines ?
5. Volley Stats annoncé avant la fusion du paragraphe des conditions d'utilisation (D68) ?

Points d'attention : « Mon Profil » → « Mon profil » dans `base_front.html.twig` ; apparence des encarts : webdesigner.

## 6. Page « Ce que vous pouvez faire » à la première connexion (avis complémentaire)

**Verdict** : oui, mais sous la forme d'un encart en haut de l'accueil, affiché une fois, qui mène à une page « Ce que
vous pouvez faire ». Pas de page ouverte d'office : la connexion ramène à la page d'origine (`use_referer`), et une page
imposée couperait l'élan de quelqu'un venu saisir un score.

**Corrections de la proposition** :
1. La liste « joueur » est en fait celle du visiteur (rien ne demande d'être connecté) : le dire, puis mettre en avant ce
   que le compte ajoute.
2. Pas de rappel de la liste précédente : « Vous pouvez faire tout ce que fait un joueur (voir la liste), et aussi : ».
3. Un compte à plusieurs rôles voit une seule page qui empile ses sections sans répétition ; rôles calculés d'après les
   droits (`SaisieScoreService::droits`, `clubsResponsable`, `estMembre`).
4. En tête, ce qui est propre au rôle (capitaine : saisir le score d'abord).
5. Un lien par ligne (zone tactile de 44 px), aucun quand l'action se fait sur place.
6. 4 à 5 lignes par section au plus.
7. Encart fermé retenu en base ; réaffiché seulement si le rôle change.
8. Lien « Ce que vous pouvez faire » dans « Mon profil » et le menu du compte (ajouter d'abord « Mon profil » au menu
   sur téléphone).

**Vérifié dans le code** : calendrier par gymnase (terrains libres, complet, fermé) ; le nom du gymnase ouvre Google Maps
(le site ne calcule pas l'itinéraire) ; téléphone du capitaine adverse réservé aux capitaines et responsables d'un club de
la poule et aux admins, dans la liste des matchs de la fiche équipe ; classement aussi sur la fiche équipe.

**Liste réécrite**

*Sans compte, tout le monde peut :* choisir son équipe (le site s'ouvre ensuite sur elle) ; voir les matchs et le
classement de son équipe sur sa fiche ; ajouter les matchs à son agenda depuis la fiche de l'équipe ; ouvrir la carte d'un
gymnase en touchant son nom ; voir les terrains libres de chaque gymnase dans « Calendrier par gymnase ».

*Avec votre compte de joueur, vous pouvez aussi :* voir la composition de votre équipe et le contact de votre capitaine ;
ouvrir la page du match pour noter qui joue chaque set ; suivre les sets joués par chacun, sur la fiche de votre équipe.

*Capitaine, vous pouvez aussi :* saisir le score après chaque match (le bouton « Saisir » apparaît sur le match) ; appeler
le capitaine de l'équipe adverse (son téléphone est dans la liste des matchs) ; gérer la composition de votre équipe ;
recevoir un rappel par courriel si un score manque 2 jours après le match.

*Responsable de club, vous pouvez aussi :* saisir le score de toutes les équipes du club ; modifier la fiche du club
(« Mon club ») ; indiquer les fermetures de vos gymnases dès que vous les connaissez ; voir les statistiques des joueurs
de vos équipes.

Volley Stats n'est annoncé qu'une fois le paragraphe des conditions d'utilisation fusionné (D68).

## 7. Bouton « ? » flottant en bas à droite (avis complémentaire)

**Verdict** : non sur tout le site. Le bas de l'écran est déjà pris : boutons « Point pour eux » de la page du match (une
touche ratée = action fausse dans la file d'attente), toast « Mon équipe », barre d'enregistrement du règlement,
navigation du wizard sur mobile ; le bouton remonte au-dessus du clavier et cache les champs. Peu découvert (pris pour une
publicité ou un robot), dernier dans l'ordre du clavier alors que visible en premier. Assistant conversationnel : non
(coût, fiabilité sur le règlement, données personnelles, trop peu de questions).

**Alternative** :
1. Lien « Aide » dans le menu du compte et le menu latéral sur téléphone, vers la page d'aide à l'ancre de la page en cours.
2. Sur les écrans complexes seulement (page du match, fermetures, fiche du club, saisie du score) : lien « Comment ça
   marche ? » à droite du titre, ouvrant un `<dialog>` ; jamais pendant la saisie des actions.
3. Contact du bureau dans le pied de page et la page d'aide, tiré des paramètres du site (D62).

Un seul fragment Twig d'aide par écran, réutilisé par le `<dialog>` et la page d'aide. Forme du lien : webdesigner.
