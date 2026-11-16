# Relecture sécurité et données personnelles : spécification de la mêlée

**Aucun constat critique.** Les principes sont bons : droits par saison de la mêlée, 404, objets cherchés dans la mêlée de l'adresse, `private, no-store`, niveau et catégorie non envoyés aux participants, schéma strict des réglages. En revanche **huit constats importants** sont à régler dans le texte avant de coder : sur plusieurs points, la spécification est moins précise que celle de Volley Stats.

Relecture du 16/11/2026 sur documents et code existant, rien n'étant codé ; enregistrée telle quelle. Les avis du §10 portent sur les questions d'avant D82.

Fichiers lus :
- `/home/amoul/projects/blvb/docs/espace-club/specification-melee.md`
- `/home/amoul/projects/blvb/docs/wizard-saison/decisions.md` (D65, D74 à D81)
- `/home/amoul/projects/blvb/docs/espace-club/parcours-melee-v3.md` (§4 et §6)
- `/home/amoul/projects/blvb/docs/volley-stats/specification-page-match.md`
- `/home/amoul/projects/blvb/src/Security/FeuilleMatchVoter.php`
- `/home/amoul/projects/blvb/src/Security/ClubVoter.php`
- `/home/amoul/projects/blvb/src/Controller/Front/EquipeCompositionController.php`
- `/home/amoul/projects/blvb/src/Form/Front/AjouterJoueurType.php`
- `/home/amoul/projects/blvb/src/Command/PurgerPagesMatchCommand.php`

---

## Constats importants

### I1. Un capitaine ouvre l'accès aux mêlées du club à n'importe qui, sans retour possible (§2 brique 1, lignes 33 à 36)

- **Scénario** : le capitaine d'une équipe du club X n'est pas responsable. Il ajoute à sa composition un joueur quelconque du site : `AjouterJoueurType` propose tous les joueurs, et `EquipeCompositionController::ajouter` (ligne 95) ne demande que d'être capitaine. La règle « Entretien » crée alors le `membre_club`. Ce compte voit les mêlées du club, les inscrits et les tableaux par catégorie. Retiré de l'équipe le lendemain, il reste membre, puisque « le retirer d'une équipe ne retire pas le membre ».
- **Second effet** : `Equipe.club` n'est pas daté. Rattacher une équipe à un club, ou la changer de club, décide de qui est repris comme membre.
- **Correction à écrire** :
  - Un membre d'origine `equipe` est retiré automatiquement quand il n'a plus aucune composition dans une équipe du club pour la saison, sauf si le responsable l'a confirmé (`origine = manuel`).
  - La section « Membres » montre l'origine et la date d'ajout.
  - Retirer un membre supprime ses inscriptions aux dates non jouées, après confirmation.
  - « Participant » est défini : il a une inscription à cette mêlée, liste d'attente comprise ou non, à trancher. Dire aussi si un ancien membre garde la vue des mêlées jouées.
  - La reprise se fait une fois, datée ; un changement de club d'une équipe ne déplace pas les membres.

### I2. Le responsable peut déclarer membre n'importe quel joueur, puis lire et écrire sa catégorie de jeu (§2 briques 1 et 2, lignes 37 et 41 à 43)

- **Scénario** : le responsable du club B ajoute « à la main » une joueuse du club A, sans son accord. La règle « visible du responsable de ses clubs » lui montre sa catégorie. « Saisissable par le responsable pour un membre de son club » lui permet de la remplir ou de la changer sur la fiche commune `joueur.categorie_jeu`. Cette valeur sert ensuite aux mêlées du club A.
- **Fuite existante à ne pas étendre** : `EquipeCompositionController::creer` (lignes 141 à 164) relie la fiche au compte qui a le même e-mail et l'annonce (« liée au compte existant »). Reproduit dans « créer une fiche », cela permet au responsable de savoir si une adresse a un compte.
- **Correction à écrire** :
  - Le responsable ne voit la catégorie d'un membre que dans une mêlée de son club qui en a besoin : inscription, pointage, tirage. Elle n'apparaît ni dans la liste des membres ni dans la recherche.
  - Il peut remplir une catégorie vide, jamais remplacer celle saisie par le joueur. Garder l'auteur de la saisie.
  - Le joueur peut corriger ou effacer sa catégorie depuis son profil.
  - Un ajout manuel prévient par courriel le joueur qui a un compte, avec un bouton « Quitter ce club ».
  - La recherche ne rend que nom et prénom. La section « Membres » n'affiche ni e-mail ni téléphone, conformément au modèle de droits : le responsable ne voit jamais les coordonnées des simples joueurs.
  - Message identique que l'e-mail saisi corresponde ou non à un compte.

### I3. Inscription par le responsable : la catégorie est rendue visible sans que le joueur ait vu l'avertissement (§2 ligne 44, §6 ligne 134)

- **Scénario** : le responsable inscrit une membre à une mêlée féminine ou dans le tableau des joueuses. Sa catégorie est enregistrée et montrée à tout le club. Le texte « l'inscription le dit avant le bouton » ne s'affiche que pour celui qui s'inscrit lui-même.
- **Correction à écrire** :
  - Inscrire quelqu'un dans un mode qui montre la catégorie envoie au joueur un courriel : qui l'a inscrit, ce qui sera visible, lien vers la page où se désinscrire.
  - L'écran du responsable l'avertit avant de valider.
  - L'inscription par le responsable respecte les mêmes règles que l'inscription personnelle : mêlée réservée, catégorie, maximum d'inscrits. Un dépassement est possible seulement s'il est explicite.
  - Sans catégorie connue, pas d'affectation d'office à un tableau.
  - Reprendre la règle H33 du parcours : ne jamais enregistrer « masculine » pour quelqu'un qui est refusé d'une mêlée féminine.

### I4. Classement par catégorie ou deux tableaux forcés après les inscriptions (D79, D80 ; §7 ligne 148)

- **Scénario** : une joueuse s'inscrit à une mêlée mixte où sa catégorie n'est vue que du responsable. Le responsable active ensuite le classement par catégorie. Sa catégorie est affichée au club à l'instant du changement, et le courriel arrive après. En passant d'une mêlée « ouverte » à deux tableaux, des inscrits sans catégorie n'ont pas de tableau.
- **Correction à écrire** (la décision D79 n'est pas remise en cause, seulement ses garde-fous) :
  - Le courriel part avant l'effet. La nouvelle vue (classement par catégorie, équipes par tableau) n'est montrée qu'après un délai (par exemple 48 h) ou au début de la première date, au premier des deux.
  - Le courriel contient le lien vers la désinscription, qui reste possible même après la fin des inscriptions dans ce cas.
  - Les inscrits sans catégorie passent en « à confirmer » : ni tableau ni classement par catégorie tant qu'ils n'ont pas répondu.
  - Le changement est interdit une fois la première date commencée.
  - L'auteur et la date du changement sont enregistrés.

### I5. Saison de référence et écran « Membres » : règles absentes (§2 ligne 52, §6)

- **Scénarios** :
  - Le tableau du §6 ne couvre que la mêlée. Rien ne dit qui gère les membres. `ClubVoter` suit la saison **courante** alors que `MeleeVoter` suivra la saison **de la mêlée**. Un responsable de la saison passée pourrait, selon la règle retenue, modifier les membres d'une saison close, ou à l'inverse perdre la main sur sa mêlée.
  - « Saison = celle qui contient la première date » : une mêlée d'été hors de toute saison n'a pas d'ayant droit. Une saison encore en brouillon du wizard n'a pas de responsable. Déplacer la première date changerait d'un coup la population autorisée.
- **Correction à écrire** (comme `specification-page-match.md` §4) :
  - La saison est fixée à la création et n'est jamais recalculée. Toutes les dates doivent tomber dans cette saison, qui doit être publiée ; sinon la création est refusée.
  - La saison du menu (session) n'intervient jamais.
  - Membres : voir = responsable de la saison S et admin ; modifier = responsable de S, seulement tant que S n'est pas terminée ; saisons passées en lecture seule.
  - Ajouter un gel, comme `FeuilleMatchVoter` : scores et pointage modifiables jusqu'à 7 jours après `terminee`, puis admin seul.

### I6. Ordre des contrôles et 404 : à préciser, sinon l'existence d'une mêlée se devine (§6 lignes 135 et 139)

- **Scénario** : un membre d'un autre club envoie un POST sur `/…/melee/41/…`. Selon l'ordre, il reçoit 403 (jeton invalide), 400 (validation) ou 404 : la différence révèle que la mêlée 41 existe. De même, le visiteur est en 404 pour les pages mais en 401 pour le JSON : si le 401 n'est rendu que pour une mêlée existante, il sert d'oracle. Enfin, un membre dont la session a expiré et qui suit le lien d'un courriel reçoit une 404 sans explication.
- **Correction à écrire** :
  1. Non connecté : redirection vers la connexion (401 en JSON), **avant toute lecture en base**, donc réponse identique que la mêlée existe ou non. Une règle `access_control` sur le préfixe des routes (avec la variante `/blvb/` de la prod) sert de seconde barrière.
  2. Mêlée cherchée dans le club de l'adresse, puis voter. Refus : `createNotFoundException`, la même réponse qu'un identifiant inexistant.
  3. Ensuite seulement jeton CSRF, puis validation.
  - Un brouillon est en 404 pour tout autre que le responsable et l'admin.
  - La fiche publique du club (`front_club_show`) ne porte aucun bloc ni compteur de mêlées : les mêlées vivent sur des pages séparées.

### I7. Écritures JSON du jour J : il manque l'idempotence et la concurrence de Volley Stats (§6 ligne 139)

- **Scénario** : le réseau coupe au gymnase et la file d'attente rejoue « Tirer le tour 4 ». Un second tirage, avec une autre graine, remplace les équipes déjà annoncées. Deux appareils (responsable et admin, ou deux responsables) pointent en même temps : la dernière écriture efface l'autre. Deux inscriptions simultanées dépassent `max_inscrits`.
- **Correction à écrire** (reprendre `specification-page-match.md` §5) :
  - Chaque écriture porte un `uuid` et la version connue de la date ou du tour. Réponse 409 avec l'état courant si elle a changé. Rejouer le même `uuid` est sans effet.
  - Tirage unique par (date, numéro), dans une transaction avec verrou.
  - Inscription et liste d'attente sous verrou de la date.
  - Jeton CSRF en en-tête `X-CSRF-TOKEN` sur **chaque** POST, formulaires du brouillon compris.
  - Aucun GET n'écrit : en particulier, le tirage « un tour à l'avance » ne se déclenche pas à l'affichage de la page.
  - Calculés par le serveur, jamais reçus : `graine` (aléatoire sûr), `tableau` quand la catégorie est connue, `rang_attente`, `etat`, `inscrit_par`, `saisi_par`, dates.
  - Pour l'inscription personnelle, le joueur vient de la session, jamais du corps de la requête.
  - Tout `joueur_id` reçu est un membre de ce club pour cette saison, sinon 400 neutre sans écho du nom.
  - Échange de joueurs : deux inscriptions présentes, de la même date et du même tableau.
  - Erreurs : exception de validation dédiée, message générique sinon.

### I8. Conservation : la purge ne couvre ni les membres ni les fiches créées par le responsable (§8)

- **Scénario** : le responsable crée la fiche d'un adhérent (nom, e-mail, téléphone, catégorie) qui n'a ni compte ni équipe. La mêlée est purgée après N+1. La fiche et le `membre_club` restent sans limite, et personne n'en a plus l'usage.
- **Correction à écrire** :
  - La commande de purge supprime aussi les `membre_club` de la saison, les brouillons jamais publiés et les mêlées annulées.
  - Une fiche joueur sans compte, sans composition et sans appartenance restante est supprimée ou signalée à l'admin.
  - `niveau_ajuste` et `present` sont effacés peu après la fin de la mêlée (par exemple 30 jours) : ils n'ont plus d'utilité.
  - Suppression d'un compte ou d'une fiche : `cree_par`, `inscrit_par`, `saisi_par` passent à vide. Les places deviennent « Joueur retiré », sans casser les matchs ni le classement des autres.
  - Conditions d'utilisation : finalité, qui voit quoi selon le mode, durée, droit d'opposition auprès de l'admin.

---

## Constats mineurs

### M1. Courriels (§7)

- Aucun message ne contient la catégorie, le tableau, le niveau ou la liste des inscrits. Exemple à éviter : l'objet « Place libérée dans le tableau des joueuses ».
- Un envoi par destinataire, à l'adresse vérifiée du compte.
- L'adresse `joueur.email` d'une fiche sans compte a été saisie par un tiers et n'est pas vérifiée : n'y envoyer qu'un message neutre, ou rien.
- Le lien de désinscription mène à une page avec bouton POST après connexion, jamais à une désinscription en un clic par GET : les antivirus de messagerie ouvrent les liens.
- Journaux : ni nom, ni catégorie, ni corps de requête ou de message.

### M2. Textes libres (§2 ligne 53)

- Fixer une longueur maximale pour `mot_accueil` et `regles_particulieres` : seul le titre en a une.
- Pas de HTML ni de `|raw` ; retours à la ligne par `nl2br` après échappement.
- Titre sans caractère de contrôle ni saut de ligne : il part dans l'objet des courriels.
- Pas de lien cliquable, ou http(s) seulement avec `rel="noopener noreferrer"`.

### M3. Bornes des réglages (§2 lignes 67 à 75)

- Borner aussi `accueil`, `pause`, `cloture`, `marge`, `points`, `max_inscrits`, `estimation`, `min_matchs_classement`, `tours` et le nombre de dates par mêlée.
- Fixer le nombre de répartitions essayées par le tirage : une valeur extrême ne doit pas charger le serveur.
- `lieu_id` : dire s'il est limité aux gymnases du club.

### M4. Niveau (§4, §6 ligne 142)

- La poule est publique. La donnée sensible est `niveau_ajuste`, un jugement du responsable sur une personne : ne jamais la mettre dans un courriel ou un journal, et la mentionner dans les conditions d'utilisation (droit d'accès).
- L'« explication » du tirage et la graine restent côté responsable.
- Les routes JSON du responsable sont distinctes de celles des membres.
- Prévoir un test : la réponse à un membre ne contient ni clé de niveau ni clé de catégorie.

### M5. Compteurs par catégorie en mêlée mixte

- **Scénario** : la page d'inscription affiche « 1 joueuse, 13 joueurs » à côté de la liste des inscrits. La catégorie se déduit.
- **Correction** : hors des trois cas prévus, les effectifs par catégorie sont réservés au responsable.

### M6. Terrains et calendrier public (§3 ligne 105)

- Écrire explicitement que la mêlée ne réduit pas les terrains libres du calendrier public par gymnase et n'entre pas dans l'optimisation. Sinon la date, le lieu et l'existence d'une mêlée se lisent en creux.
- Si l'occupation doit un jour être visible : un « créneau occupé » anonyme, par une décision consignée.
- La catégorie n'apparaît nulle part ailleurs : fiche équipe, profil vu par un tiers, exports et import Excel réservés à l'admin.

### M7. Compte prêté le jour J

- **Scénario** : le responsable confie son téléphone pour le pointage ou les scores. Le porteur a accès aux niveaux, aux catégories et à la gestion des membres.
- **Correction** : l'écrire sur l'écran et dans les conditions d'utilisation, comme pour Volley Stats. Donner la priorité aux « membres choisis pour la saisie » prévus par D74.

### M8. Section « Tests à prévoir » absente

À ajouter, sur le modèle de Volley Stats §7 :
- Droits : membre d'un autre club, membre de la saison précédente, responsable d'une autre saison, joueur membre de deux clubs, brouillon, visiteur.
- Références : identifiant d'une date, d'un tour, d'un match ou d'une inscription d'une autre mêlée.
- Écritures : rejeu d'un `uuid`, 409, POST sans jeton.
- Isolement : rien dans `Partie`, `Classement`, l'ICS, les API ; en-têtes `private, no-store`.
- Purge.
- Jeux d'essai sans donnée réelle.

---

## Avis sur les questions ouvertes du §10

1. **Refuser le classement par catégorie en restant au général : oui, à prévoir.** Une case sur l'inscription, modifiable par le joueur. C'est la contrepartie raisonnable du forçage de D79. Le joueur reste au classement général et n'apparaît pas dans les listes par catégorie. Limite à écrire : dans un petit effectif, l'absence se déduit ; l'aide doit le dire. En deux tableaux ou en mêlée réservée, ce refus n'a pas de sens : la seule opposition possible est de ne pas s'inscrire.

2. **Fiche d'un adhérent sans compte : pas dans la première version**, ou seulement sans catégorie et hors des mêlées qui la rendent visible. La personne ne peut ni lire l'avertissement, ni se désinscrire, ni corriger sa catégorie. C'est le même raisonnement que « pas d'invités sans compte » (§8). Si le besoin est confirmé : fiche avec nom et prénom seuls, pas de coordonnées, information donnée par le responsable (texte à lui afficher), et suppression à la purge (I8).

3. **Purge** : même commande et même échéance que Volley Stats (saison N supprimée une fois N+1 terminée), comme durée **maximale**. Une seule règle est plus simple à expliquer et à tester. Faire plus court pour ce qui ne sert plus après la soirée (`niveau_ajuste`, pointage, brouillons, mêlées annulées), et étendre la purge aux membres et aux fiches orphelines (I8).

4. **2,35 m** : aucun enjeu de sécurité ni de données. Garder « hauteur mixte », sans la présenter comme officielle.

---

## Vérifié et jugé correct

- Droits du responsable **pour la saison de la mêlée**, cohérents avec `FeuilleMatchVoter`.
- 404 plutôt que 403.
- Objets cherchés dans la mêlée de l'adresse.
- `private, no-store`.
- Aucune écriture dans `Partie` ni `Classement`.
- Schéma strict des réglages.
- Textes échappés.
- Niveau et catégorie non envoyés aux participants hors des cas prévus.
- Pas d'invités sans compte.
- Aucun secret ni donnée réelle dans les documents relus.
