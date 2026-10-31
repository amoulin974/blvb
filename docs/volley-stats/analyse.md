# Volley Stats : analyse des besoins

Saisie des compositions et des statistiques des joueurs pendant les matchs du championnat. **Phase d'analyse : rien n'est
conçu ni codé.** Cadrage consigné dans `docs/wizard-saison/decisions.md` (D66) ; ce dossier rassemble le reste : état des
lieux, besoins, contraintes, risques et questions encore ouvertes. Démarré le 31/10/2026, branche `volley-stats`.

Toutes les situations de jeu et leurs conséquences : `situations.md`. Page match et composition par set (avis de
l'ergonome) : `page-match.md`.

Maquette existante : artefact « Volley Stats » (https://claude.ai/artifact/L8djveZYGiXZWvPAXdqFbP, septembre 2026).

## 1. État des lieux de la maquette

Page autonome, sans lien avec le site : ni compte, ni équipe, ni match du championnat ; noms et numéros saisis à la main ;
enregistrement sur l'appareil (et dans la base de l'artefact).

| Fait | Détail |
|---|---|
| Tableau d'affichage | score du set, sets gagnés, scores des sets précédents, serveur, « Annuler », « Terminer le set » |
| Composition par set | 6 postes dans l'ordre de rotation, premier service ; reprise de la composition du set précédent |
| Rotation | calculée à chaque reprise de service ; passeur avant repéré (joueurs cochés « passeur ») |
| Remplacements | sortant / entrant, depuis le banc |
| Saisie | trois touches : joueur, action, résultat ; raccourcis « Service » (serveur) et « Passe » (passeur avant) |
| Actions | service (ace, en jeu, faute) ; réception notée 0 à 3 ; passe (faute de main) ; attaque (point, défendue, bloquée, faute) ; block (point, touche, faute) ; défense (réussie, ratée) ; point ou faute adverse |
| Journal | liste des actions, suppression au milieu du match avec recalcul du score |
| Statistiques | par joueur et pour l'équipe, tous sets ou un set : points +/−, service, réception (moyenne, % 2+), attaque (efficacité), block, défense |
| Export | CSV des stats et du journal |

**Absent** : libéro, temps morts, actions de l'adversaire (seulement « point adverse »), mixité et règles du championnat,
enchaînement de plusieurs matchs et saison, droits d'accès, publication, charte « Océan & ballon » (palette et police propres).

## 2. Cadrage (réponses de l'utilisateur, 31/10/2026, D66)

- **Qui saisit** : un remplaçant sur le banc ou un accompagnant, sur son téléphone.
- **Usages** : progresser en équipe, choisir les compositions, suivi individuel sur la saison, animation du championnat
  (classements individuels publiés sur le site).
- **Visibilité** : publique, avec l'accord de chaque joueur (case dans son profil, décochable) ; sans accord, ses stats
  restent visibles de son équipe et il apparaît « Joueur n° 7 » en public.
- **Compositions et rotations** : visibles de l'équipe seulement (information tactique).
- **Détail** : au choix de l'équipe, un mode rapide et un mode complet ; les classements publics ne reposent que sur ce
  que les deux modes ont en commun.
- **Participation** : volontaire ; les classements publics n'affichent que les équipes qui saisissent, avec un minimum de
  matchs saisis.
- **Réseau** : présent dans tous les gymnases d'après l'utilisateur.
- **Score officiel** : jamais alimenté par la saisie des stats ; il se saisit comme aujourd'hui.
- **Matchs** : championnat seulement (matchs programmés sur le site).
- **Corrections** : celui qui a saisi et le capitaine, pendant 7 jours ; ensuite figé, seul l'admin corrige.
- **Règles 1J (filles) et 4J (licenciés FFVB)** : pas de contrôle dans la première version, mais le modèle de données le
  prévoit (il demandera la catégorie de jeu de D65 et l'information « licencié FFVB »).

## 3. Utilisateurs et parcours

| Qui | Ce qu'il fait | Contexte |
|---|---|---|
| **Saisisseur** (remplaçant, accompagnant) | prépare la composition, suit le match, saisit chaque action | debout ou assis au bord du terrain, téléphone à une main, échanges de quelques secondes, bruit, il regarde le jeu plus que l'écran |
| **Capitaine** | désigne qui saisit, choisit le mode, corrige pendant 7 jours, consulte les stats pour composer | après le match, souvent sur téléphone |
| **Joueur** | consulte ses stats, accepte ou refuse leur publication | profil sur le site |
| **Visiteur** | consulte les classements individuels et les stats publiées | site public |
| **Admin** | corrige après 7 jours, retire une saisie aberrante, règle les seuils des classements | back-office |

Parcours principal à décrire en détail lors de l'atelier ergonomie : avant le match (ouvrir le match du jour, joueurs
présents, numéros, composition du set 1), pendant (actions, remplacements, fin de set, composition suivante), après (fin du
match, relecture, corrections, publication).

## 4. Besoins fonctionnels (première version, à prioriser)

**Indispensable**
1. Ouvrir la saisie depuis un match du championnat de son équipe ; joueurs proposés depuis la composition de l'équipe sur
   le site (`MembreEquipe`), avec numéro de maillot.
2. Composition de chaque set (6 postes, premier service), rotation calculée, remplacements.
3. Deux modes de saisie (rapide, complet), choisis par l'équipe ; « Annuler » toujours à portée.
4. Fin de set, fin de match ; reprise après fermeture de la page ou changement d'appareil sans rien perdre.
5. Stats par match et cumulées sur la saison, pour l'équipe et par joueur.
6. Accord de publication dans le profil du joueur ; classements publics limités aux joueurs qui ont accepté.
7. Corrections pendant 7 jours par le saisisseur et le capitaine.

**À discuter**
- Libéro (règles propres de remplacement, pas de service), temps morts, cartons.
- Plusieurs saisisseurs en même temps (un pour le score et la rotation, un pour les actions).
- Export CSV, comme la maquette.
- Comparaison des rotations (quelle rotation marque le plus) pour aider aux compositions.
- Stats affichées sur la fiche publique de l'équipe.

## 5. Contraintes

- **Données personnelles (RGPD)** : des statistiques de performance attachées à des joueurs nommés sont des données
  personnelles. Finalité à écrire (suivi sportif, animation du championnat) ; base légale : le consentement pour la
  publication, le service rendu à l'équipe pour la saisie interne ; durée de conservation à fixer ; conditions d'utilisation
  à compléter ; droit de retrait (décocher l'accord retire aussitôt le joueur des pages publiques). À relire par l'agent
  `securite`.
- **Équité des classements** : saisie volontaire et par des bénévoles ; un classement individuel n'est juste que si les
  saisies sont comparables. D'où : statistiques publiques limitées au socle commun des deux modes, minimum de matchs saisis,
  mention « d'après les matchs saisis par les équipes ».
- **Terrain** : saisie au téléphone, à une main, sans quitter le jeu des yeux : grandes cibles, peu de touches par action,
  pas de saisie au clavier pendant le match, correction immédiate.
- **Réseau** : présent d'après l'utilisateur. *Proposition à valider* : garder malgré tout chaque action sur l'appareil
  jusqu'à sa réception par le serveur (une coupure de quelques secondes ne doit rien perdre), sans aller jusqu'au hors-ligne
  complet.
- **Charte** : « Océan & ballon » (agent `webdesigner`), au lieu de la palette de la maquette.
- **Règlement** : 1J, 3J, 4J ne sont pas contrôlés au début, mais les données de composition doivent permettre de le faire.

## 6. Données (première esquisse, à valider)

- **Saisie de match** : un match du championnat (`Partie`), l'équipe suivie, le mode, le saisisseur, l'état (en cours,
  terminée, figée), dates.
- **Joueur du match** : membre de l'équipe (`MembreEquipe`), numéro de maillot, poste habituel (passeur, libéro).
- **Set** : numéro, composition de départ (6 postes), premier service.
- **Action** : ordre, set, joueur (ou adversaire), type, résultat, point pour qui ; remplacement (sortant, entrant).
- Les stats ne sont pas stockées : elles se calculent depuis les actions (comme la maquette), ce qui garde les corrections
  cohérentes.

Les deux équipes d'un même match peuvent saisir chacune de leur côté : deux saisies distinctes, jamais fusionnées.

## 7. Risques

| Risque | Effet | Parade envisagée |
|---|---|---|
| Saisie trop lourde | abandon au bout d'un set, stats partielles | mode rapide, essai sur un vrai match avant de généraliser |
| Peu d'équipes volontaires | classements vides | seuils bas la première saison, communication du bureau |
| Erreurs de saisie | stats fausses publiées | relecture en fin de match, corrections 7 jours, retrait par l'admin |
| Publication mal comprise | plainte d'un joueur | accord explicite, retrait immédiat, texte clair dans le profil |
| Comparaison injuste entre équipes | contestation | socle commun, minimum de matchs, mention de la source |

## 8. Questions tranchées et questions ouvertes

**Tranchées** (utilisateur, 31/10/2026, ajoutées à D66) :
- **Qui saisit** : un membre de l'équipe, avec son compte. Un accompagnant utilise le compte d'un joueur : pour le site,
  c'est ce joueur qui saisit (le journal de la saisie garde le compte utilisé).
- **Première version** : un seul saisisseur à la fois, pas de libéro, pas de temps morts.
- **Pas de numéro de maillot** : le joueur est désigné par son prénom et les premières lettres de son nom (« Camille Ar. »),
  autant de lettres qu'il faut pour distinguer deux joueurs de la même équipe.
- **Conservation** : tant que le joueur participe à une saison, plus une saison ; au-delà, ses actions et ses stats sont
  effacées.
- **Stats publiques** : choisies plus tard, sur le catalogue du §9.

- **Joueur sans accord de publication** : présent dans les classements publics, mais anonyme. Il n'y a pas de numéro de
  maillot : libellé à fixer, par exemple « Joueur anonyme · EQUIPE A1 ».
- **Trois modes emboîtés** : rapide (une saisie par échange), **intermédiaire** (chaque touche, trois issues : le jeu
  continue, point gagné, point perdu, sans qualité), complet (chaque touche notée). **Première version : le mode
  intermédiaire seulement.** Tableau des stats par mode au §9.
- **Classements publics** : par critère au choix du visiteur (meilleur serveur, meilleur passeur…), soit en nombre, soit en
  pourcentage d'actions réussies.
- **Première version** : comparaison des rotations, export CSV et stats sur la fiche de l'équipe en font partie.

**Conséquences, à valider** :
- Un critère du seul mode complet (passe, réception, efficacité en attaque) ne classe que les joueurs des équipes en mode
  complet ; le classement le dit (« équipes en saisie complète »). Cela remplace la règle « socle commun seulement » de D66.
- Un pourcentage exige un minimum d'actions (sans quoi 1 service réussi sur 1 donne 100 %) : seuil par critère, réglé par
  l'admin, par exemple 20 services ou 10 réceptions.

- **Page des classements** : plusieurs classements sur la même page : meilleur serveur, meilleur passeur, meilleur attaquant,
  meilleur réceptionneur ; formules proposées dans `situations.md` §12.

- **Classements** par phase et par poule ; formules de `situations.md` §12 validées ; réceptionneur classé en mode
  intermédiaire sur le % de réceptions sans faute.

**Ouvertes** :
1. Barème des notes du mode complet, à six ou quatre niveaux (`situations.md` §1) : sans urgence, le mode complet vient
   après l'intermédiaire.

## 9. Catalogue des statistiques possibles (pour choisir les stats publiques)

Le **mode intermédiaire**, développé en premier, note chaque touche de l'équipe avec trois issues seulement (le jeu
continue, point gagné, point perdu) : il donne tout ce que donne le mode rapide, plus le nombre d'actions par compétence,
les pourcentages sans faute, les efficacités au service et en attaque et les passes décisives ; pas la qualité de la
réception ni de la passe.

Le **mode rapide** ne note que la fin de chaque échange : qui marque, comment (ace, attaque, block), ou qui fait la faute
(service, attaque, filet ou ligne, autre), ou point et faute adverses. Le **mode complet** note toutes les actions, comme
la maquette (service, réception notée 0 à 3, passe, attaque, block, défense). Les sets joués se déduisent des compositions.

| Statistique | Définition | Rapide | Complet | Socle commun |
|---|---|:-:|:-:|:-:|
| Sets joués | sets où le joueur est dans la composition ou entré en remplacement | oui | oui | oui |
| Points marqués | points gagnés sur une action du joueur | oui | oui | oui |
| Dont aces, attaques gagnantes, blocks gagnants | détail des points marqués | oui | oui | oui |
| Fautes | points perdus sur une faute du joueur | oui | oui | oui |
| Dont fautes de service, d'attaque | détail des fautes | oui | oui | oui |
| Solde | points marqués − fautes | oui | oui | oui |
| Points par set joué | points marqués ÷ sets joués : compare des joueurs qui n'ont pas joué autant | oui | oui | oui |
| Services | services tentés, % de services en jeu | non | oui | non |
| Réception | réceptions, note moyenne sur 3, % de réceptions notées 2 ou 3 | non | oui | non |
| Attaque | attaques tentées, efficacité (points − fautes − bloquées) ÷ tentatives | non | oui | non |
| Block | blocks gagnants, touches, fautes | non | oui | non |
| Défense | défenses tentées, réussies | non | oui | non |
| Passe | passes, fautes de main | non | oui | non |
| Rotation | points marqués et perdus dans chaque rotation (équipe) | oui | oui | oui (équipe seulement, jamais publique : D66) |

Candidats naturels aux classements publics : points marqués, aces, blocks gagnants, solde, points par set joué (tous du
socle commun) ; les statistiques du seul mode complet resteraient dans l'équipe, ou feraient l'objet d'un classement à part
des équipes en mode complet.

## 10. Suite proposée

1. Répondre aux questions ouvertes du §8 et choisir les stats publiques dans le catalogue du §9.
2. Atelier ergonomie (agent `ergonome`) : parcours détaillé du saisisseur, mode rapide et mode complet, sur téléphone.
3. Relecture RGPD (agent `securite`) : publication, consentement, conservation, conditions d'utilisation.
4. Spécification de la première version et des classements publics ; décisions dans `decisions.md`.
5. Maquette dans la charte (agent `webdesigner`), puis essai sur un vrai match avant tout développement.
