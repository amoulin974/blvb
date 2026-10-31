# Volley Stats : spécification technique de la page match (première brique)

Périmètre : D67 (page match, joueurs présents, composition de chaque set, remplacements, score de chaque set pour l'équipe).
Parcours : `page-match.md` ; maquette dans la charte : `maquette-page-match.md`. Pas encore de saisie des actions (mode
intermédiaire, D66), mais le modèle l'accueille sans migration de structure. Relue par l'agent `securite` le 31/10/2026 : ses
constats sont intégrés ci-dessous (§4 à §7, §9). Rédigé le 31/10/2026.

## 1. Principes

- **Une page match par équipe et par match** : les deux équipes d'un match ont chacune la leur, jamais partagées ni
  fusionnées.
- **Le set est une suite d'événements** (départ, remplacement, point, fin) : la composition sur le terrain, le serveur et le
  score se **recalculent** depuis les événements, jamais stockés en double. La saisie des actions ajoutera des événements
  du même journal.
- **Rien ne sort de l'équipe** : ni score officiel (D66), ni classement, ni page publique (D67). La page n'écrit jamais dans
  `Partie` ni dans `Classement`.
- **Enregistrement immédiat** de chaque étape (pas de bouton « Enregistrer » global) : la page se reprend après fermeture ou
  sur un autre appareil.

## 2. Données (nouvelles tables)

### `feuille_match` : la page match d'une équipe

| Champ | Type | Règle |
|---|---|---|
| `id` | int | |
| `partie_id` | FK `partie` | le match du championnat |
| `equipe_id` | FK `equipe` | l'équipe qui tient la page ; doit être l'une des deux équipes du match |
| `mode` | chaîne | `intermediaire` (seul mode de la première version) ; `rapide`, `complet` ensuite |
| `etat` | chaîne | `preparation`, `en_cours`, `terminee` ; « figée » n'est pas stocké : le voter le calcule (§4) |
| `cree_le`, `modifie_le`, `terminee_le` | datetime | `terminee_le` lance le délai de 7 jours |
| unique | | (`partie_id`, `equipe_id`) |

Nom proposé : « feuille de match » côté code, « page du match » à l'écran ; à confirmer avec l'ergonome.

### `feuille_match_joueur` : joueurs présents

| Champ | Type | Règle |
|---|---|---|
| `feuille_id` | FK | |
| `membre_id` | FK `membre_equipe` | un membre de l'équipe **pour la saison du match** (pas de renfort, D67) |
| unique | | (`feuille_id`, `membre_id`) |

Retrait d'un joueur de l'équipe (page de composition de l'équipe) : `ON DELETE CASCADE` sur la présence ; les postes et
événements qui le citaient affichent « Joueur retiré » (calcul robuste aux identifiants disparus). Même règle pour
l'effacement de conservation.

Le libellé « Camille Ar. » se calcule à l'affichage (prénom + premières lettres du nom, autant qu'il faut pour distinguer
deux présents) : rien de nouveau n'est stocké sur le joueur.

### `feuille_match_set` : un set

| Champ | Type | Règle |
|---|---|---|
| `feuille_id` | FK | |
| `numero` | int | 1 à 5 |
| `postes` | JSON | 6 identifiants de `feuille_match_joueur` **de cette feuille**, dans l'ordre des postes 1 à 6, `null` pour la place libre (set à 5, une seule) |
| `premier_service` | chaîne | `nous` / `eux`, saisi à chaque set (D67) |
| `score_depart` | JSON | `[0, 0]`, `[0, 8]` ou `[8, 0]` (règle 1J, non contrôlée) |
| `points_fin` | int | 25, ou 15 pour le 5ᵉ set en 3 sets gagnants et le 3ᵉ en 2 sets gagnants (D48, D67) ; sets **secs** |
| `etat` | chaîne | `composition`, `en_cours`, `termine` |
| unique | | (`feuille_id`, `numero`) |

### `feuille_match_evenement` : le journal du set

| Champ | Type | Règle |
|---|---|---|
| `set_id` | FK | |
| `ordre` | int | croissant dans le set, **calculé par le serveur** ; unique (`set_id`, `ordre`) |
| `uuid` | chaîne | généré par le téléphone, unique : un envoi rejoué après une coupure est sans effet |
| `type` | chaîne | `remplacement`, `point` ; plus tard `action` (mode intermédiaire) |
| `donnees` | JSON | remplacement : `{sort, entre}` (**pas de motif** : « blessure » serait une donnée de santé, §6) ; point : `{pour: "nous" ou "eux"}` ; schéma strict |
| `saisi_par_id` | FK `user` | compte qui a saisi : aide à la correction, pas une preuve (un compte peut être prêté, D66) |
| `saisi_le` | datetime | |

Le score du set et la rotation se déduisent des événements `point` (rotation quand l'équipe gagne le point sur le service
adverse). Le score de chaque set est donc tenu ici, pour l'équipe seulement (D67).

## 3. Règles de calcul

- **Serveur** : poste 1 quand l'équipe sert ; la place libre tourne avec les autres (D67).
- **Fin de set** : premier à `points_fin` (sets secs, sans deux points d'écart) ; l'interface propose « Terminer le set »,
  elle ne termine pas d'elle-même (erreur de saisie possible).
- **Fin de match** : selon le format de la phase (`Phase::getSetsGagnants`, D48) ; proposée, jamais imposée.
- **Remplacement** : le sortant doit être sur le terrain, l'entrant présent et sur le banc ; une place libre se remplit par
  un « remplacement » sans sortant.
- **Composition du set suivant** : préremplie avec les `postes` de départ du set précédent (pas ceux de fin de set).

## 4. Droits

**Saison de référence = saison du match** (`partie.poule.phase.saison`) ; la saison choisie dans le menu (session) n'intervient
jamais. Saison introuvable : 404.

| Qui | Voir | Modifier |
|---|---|---|
| Membre de **l'équipe de la feuille** pour la saison du match (`MembreEquipe`) | oui | oui (D67), jusqu'au gel |
| Responsable du club de **l'équipe de la feuille** pour la saison du match (`ResponsableClub`) | oui | oui (D67), jusqu'au gel |
| Admin | oui | oui, même après le gel |
| Équipe adverse (membre, capitaine, responsable de son club), autre connecté | **404** | non |
| Visiteur non connecté | redirection vers la connexion (équipes et matchs sont publics : rien n'est révélé) | non |

- **`FeuilleMatchVoter`** (`VOIR`, `MODIFIER`) porte sur l'équipe de la feuille et le match, **jamais sur la `Partie` seule**.
  Règle : admin, ou `estMembre(user, equipe, saisonDuMatch)`, ou responsable de `equipe.club` pour `saisonDuMatch`.
  **Ne pas réutiliser `SaisieScoreService::peutSaisir`** (droit sur l'une ou l'autre équipe du match, et capitaines
  seulement) ni la règle `peutVoirMembres` de la fiche équipe (saison de la session).
- **Ordre des contrôles** : équipe ou match inexistant, équipe qui ne joue pas ce match, match sans adversaire → 404 pour
  tous ; puis visiteur → connexion ; puis connecté sans droit → 404 (`createNotFoundException` après le voter, pas le 403 de
  `#[IsGranted]`). La réponse ne dépend jamais de l'existence d'une feuille.
- **Gel** calculé par le voter, sans commande planifiée : `MODIFIER` refusé (sauf admin) quand `terminee_le` + 7 jours est
  passé, ou quand la date du match + 30 jours est passée pour une feuille jamais terminée. Rouvrir une feuille terminée :
  ayant droit pendant 7 jours, ensuite l'admin.
- **Cas acceptés** (D67), avec un test chacun : un joueur membre des deux équipes du match (contrainte
  `membre_equipe_unique` par équipe), un responsable de club dont deux équipes se rencontrent : ils voient les deux pages.
  `Equipe.club` n'étant pas daté, l'accès « responsable » suit le club actuel de l'équipe, comme pour le score.
- **Compte prêté** (D66, D67) : prêter son compte donne accès à ses coordonnées et à celles de l'équipe, au profil, et au
  score officiel si c'est le compte du capitaine. Écrit sur la page du match et dans les conditions d'utilisation (« se
  déconnecter ensuite », prêter de préférence un compte qui n'est pas celui du capitaine). Le lien de saisie limité au match
  reste la suite prévue.

## 5. Pages et adresses

| Adresse | Rôle |
|---|---|
| `GET /equipe/{equipe}/match/{partie}` | page du match, **en lecture seule** (rien n'est créé par un GET) |
| `POST …/preparer` | crée la feuille (bouton « Préparer le match ») ; idempotent : feuille existante relue en cas de conflit |
| `POST …/presents` | joueurs présents (crée la feuille si besoin) |
| `POST …/set/{numero}/composition` | postes, premier service, score de départ |
| `POST …/set/{numero}/commencer`, `…/terminer` | changements d'état |
| `POST …/set/{numero}/evenement` | remplacement, point ; renvoie l'état recalculé (JSON) |
| `POST …/set/{numero}/annuler` | retire l'événement désigné par son `uuid`, refusé s'il n'est plus le dernier |

**Validation** (serveur) :
- jeton CSRF sur chaque POST (en-tête `X-CSRF-TOKEN`, comme l'API du score) ;
- chaque identifiant de joueur reçu est un `feuille_match_joueur` **de cette feuille** ; pour `presents`, un `MembreEquipe`
  de l'équipe de la feuille pour la saison du match ; sinon 400, message neutre, sans écho de nom ;
- `numero` de 1 à 2 × `getSetsGagnants` − 1, set existant ou suivant ; `postes` : 6 entrées entières ou `null`, sans
  doublon, au plus une place libre ; `premier_service` ∈ {nous, eux} ; `score_depart` ∈ {[0,0], [0,8], [8,0]} ; `type` ∈
  {remplacement, point} ; `donnees` en schéma strict (clé inconnue refusée) ;
- calculés par le serveur, jamais reçus : `points_fin`, `ordre`, `saisi_par`, dates ;
- états : pas de point sur un set terminé, pas de « commencer » avec une place vide (hors set à 5 choisi) ;
- erreurs : exception de validation dédiée, message générique pour le reste (ne pas reprendre le `getMessage()` de l'API du
  score, qui renverrait du SQL).

**Concurrence** (deux téléphones, rejeu) : `ordre` calculé dans une transaction avec verrou sur le set ; chaque écriture
envoie le dernier `ordre` connu, 409 avec l'état courant s'il a changé ; `uuid` unique par événement.

**Cache** : page et réponses JSON en `Cache-Control: private, no-store`.

Entrées (D67, ergonome) : « Préparer le match » sur la carte du prochain match de la fiche équipe ; « Page du match » sur la
ligne du match dans le calendrier de l'équipe ; affichées selon le voter, calculé côté serveur (jamais d'après l'équipe
favorite du navigateur).

Technique : Twig pour la page, un contrôleur Stimulus pour la composition et le set en cours (comme `score-modal`), réponses
JSON pour les écritures ; file d'attente locale tant que le serveur n'a pas répondu (proposition d'`analyse.md` §5, à
valider), rendue sûre par l'`uuid`.

## 6. Données personnelles

- Données : présence à un match, place sur le terrain, remplacements, score des sets. **Aucun motif de remplacement** dans la
  première version : « blessure », rattachée à un joueur nommé et daté, serait une donnée de santé (RGPD art. 9). Si un motif
  est voulu plus tard : liste fermée non médicale (« choix de jeu », « autre »), sans texte libre.
- Base légale : intérêt légitime de l'équipe (organisation du jeu) ; le consentement reste réservé à la publication (D66).
- Conservation (D66), règle programmable : **les feuilles de la saison N sont supprimées à la fin de la saison N+1**, par une
  commande planifiée et testée.
- Conditions d'utilisation : finalité, personnes qui voient (équipe et responsable du club pour la saison du match), durée,
  droit d'accès et d'opposition auprès de l'admin (un joueur retiré de l'équipe n'y accède plus lui-même), risque du compte
  prêté.
- Journaux : ni nom ni corps de requête.

## 7. Tests à prévoir

- Droits : membre et responsable du club de l'équipe de la feuille voient et modifient ; admin aussi après le gel ;
  **capitaine adverse et responsable du club adverse : 404** ; autre connecté : 404 ; visiteur : connexion ; **membre de la
  saison précédente et responsable d'une autre saison : 404, quelle que soit la saison choisie dans le menu** ; équipe qui ne
  joue pas ce match : 404 ; gel à 7 jours après la fin et à 30 jours pour une feuille jamais terminée.
- Cas acceptés : joueur membre des deux équipes du match ; responsable d'un club dont deux équipes se rencontrent.
- Aucune feuille créée par un GET ; création idempotente par POST.
- Validation : identifiant d'une autre feuille refusé (400, sans nom) ; membre d'une autre équipe ou d'une autre saison
  refusé ; `postes` invalides (doublon, 5 ou 7 entrées, deux places libres) ; clé inconnue dans `donnees` ; point sur un set
  terminé.
- Composition : préremplissage du set suivant ; place libre.
- Calculs : rotation et serveur sur une suite de points (place libre comprise) ; fin de set à 25 et à 15 secs ; départ 0–8.
- Événements : remplacement valide et refusé ; annulation par `uuid`, refusée si ce n'est plus le dernier ; rejeu du même
  `uuid` sans effet ; ordre attendu périmé → 409.
- Joueur retiré de l'équipe : la page s'affiche (« Joueur retiré »).
- Isolement : rien dans `partie` ni `classement` ; aucune donnée de feuille dans le calendrier, la fiche équipe publique,
  l'ICS ni l'API du score ; en-têtes `private, no-store`.
- Conservation : la commande supprime les feuilles de la saison N après la saison N+1, et rien d'autre.

## 8. Ordre de réalisation proposé

1. Entités, migration, voter (§4), tests de droits et de validation.
2. Page match en lecture et joueurs présents.
3. Composition du set (terrain), premier service, score de départ.
4. Set en cours : points, remplacements, annulation, fin de set ; relevé des sets.
5. Entrées depuis la fiche équipe et le calendrier ; relectures ergonome, webdesigner, sécurité.
