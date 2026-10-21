# Règlement : maquettes de la page d'édition (R4, webdesigner, 20/10/2026)

## Principes
- base_admin.html.twig, en-tête admin/_entete.html.twig, cartes admin-carte, contenu max-w-7xl.
- ≥ xl : éditeur sur 2 colonnes, panneau « Éléments calculés » sur 1 colonne, collé à l'écran pendant le défilement ; en dessous : une colonne, panneau sous l'éditeur (lien « Aller aux éléments calculés ↓ »).
- Aperçu : onglets « Rédiger / Aperçu » en tête de l'éditeur ; l'aperçu rend le texte NON enregistré (POST, RenduReglement), mise en page publique ; ligne « Aperçu du texte non enregistré. Le public voit la version publiée. »
- Un seul bouton jaune : « Enregistrer » (publie aussitôt, D56).
- Historique sur une page à part (bouton d'en-tête « Historique (12) »).

## Ordinateur (≥ 1280 px)
```
← Saison 2026-2027                                   [Historique (12)] [Voir la page publique]
RÈGLEMENT DE LA SAISON 2026-2027
Publié · mis à jour le 20 octobre 2026 à 14 h 32 par A. Moulin

▌(!) 2 codes ne sont pas affichés au public
▌ [calendrier phase=FIN] : aucune phase « FIN » dans cette saison.  [Aller au code]
▌ [departage phase=P2] : l'élément n'accepte pas de paramètre.     [Aller au code]
▌ Liste établie au dernier enregistrement ou au dernier aperçu.

┌─ éditeur (2 col) ───────────────────────────────┐ ┌─ ÉLÉMENTS CALCULÉS ────────────────────┐
│ [ Rédiger ] [ Aperçu ]                          │ │ Écrivez le code court dans le texte :   │
│ barre d'outils : [Titre][Sous-titre] | [G][I][</>] | [•≡][1≡] | [Lien][Image]  │ il est remplacé par l'élément,│
│ RÈGLE 1R — DURÉE DES MATCHS                     │ │ recalculé à chaque affichage. Mis en    │
│ Les matchs se jouent en 3 sets gagnants…        │ │ forme « code », il reste écrit tel quel.│
│ ▌[bareme]   ← code reconnu (filet turquoise)    │ │ ▼ SCHÉMA DES PHASES                     │
│ Pour citer un code : `[bareme]` (forme code)    │ │   description                           │
│ barre du bas (collée) :                         │ │   Code : [schema_phases phase=P2]       │
│ Modifications non enregistrées    [Enregistrer] │ │   Variante [Phase 2 (P2) ▾]             │
└─────────────────────────────────────────────────┘ │   [Copier] [Insérer]                    │
                                                    │   Paramètres : phase — Limite l'élément │
                                                    │   à une phase… Valeurs : P1 (Phase 1),  │
                                                    │   P2 (Phase 2)… Par défaut : toute la   │
                                                    │   saison                                │
                                                    │   ▸ Rendu pour la saison (Phase 2)      │
                                                    │ ▸ BARÈME DES POINTS                     │
                                                    │ ▸ DÉPARTAGE DES ÉQUIPES À ÉGALITÉ       │
                                                    │ ▸ CALENDRIER DE LA SAISON               │
                                                    └─────────────────────────────────────────┘
```
Onglet Aperçu : la barre d'outils disparaît, texte rendu comme la page publique (éléments calculés, encarts des codes inapplicables).

## Téléphone
En-tête, boutons « Historique (12) » et « Page publique » ; encart des codes inapplicables ; éditeur avec barre d'outils sur 2 lignes (boutons 44 px, titres abrégés [T][S], nom complet en aria-label/title), barre « Non enregistré [Enregistrer] » collée en bas ; lien « Aller aux éléments calculés ↓ » ; panneau en details fermés (description, code pleine largeur, variantes, Copier/Insérer côte à côte, paramètres en dl, rendu replié). « Insérer » reprend la dernière position du curseur.

## Composants
- Codes inapplicables : admin/_alerte warning, liste code + raison, « Aller au code ».
- Éditeur : onglets tablist ; barre role=toolbar (roving tabindex), boutons aria-pressed ; barre d'enregistrement sticky bottom, état aria-live, btn-accent « Enregistrer » (aria-disabled s'il n'y a rien à enregistrer, « Aucune modification »).
- Fenêtres Lien / Image : dialog daisyUI.
- Panneau : aside + h2 ; un details par élément ; code en <code> bg-base-200 sélectionnable ; select « Variante » (« Toute la saison », puis « Phase 2 (P2) »…), absent sans paramètre (« Aucun paramètre. ») ; paramètres en dl ; Copier / Insérer ; rendu en turbo-frame paresseux, rechargé quand la variante change.
- Historique : page à part, table-admin Date · Auteur · Actions (Consulter, Restaurer), cartes sur téléphone ; Restaurer par admin/_confirmation (« Restaurer et publier »). Version consultée : lecture seule, bandeau « Version du … par … ; les éléments calculés sont ceux d'aujourd'hui. », « Restaurer cette version ».
- CSS : .reglement-article (typographie commune page publique / éditeur / aperçu / panneau / version), .reglement-editeur (min 60vh, focus-within visible), .reglement-code-court (code reconnu dans l'éditeur : filet turquoise, fond base-200, mono ; un code mis en forme « code » garde le style <code> ordinaire).
- 11 icônes Heroicons à ajouter.

## Comportements
- Barre : Titre (h2), Sous-titre (h3), Gras, Italique, Code, Liste à puces, Liste numérotée, Lien, Image ; raccourcis Ctrl+B/I/E, Ctrl+Maj+8/7, Ctrl+K, Ctrl+S.
- Lien : champ « Adresse », aide « Une règle du règlement : #regle-1r » ; http(s), mailto, #ancre, chemin du site ; « Retirer le lien » ; refus : « Adresse non reconnue : commencez par https://, mailto: ou #. »
- Image : sélecteur (jpg, png, webp), contrôle type et 2 Mo avant envoi (et type réel au serveur) ; « Envoi de l'image… » ; puis fenêtre « texte de remplacement » obligatoire, case « Image décorative (pas de texte) » ; erreurs : « L'image dépasse 2 Mo (3,4 Mo). Réduisez-la avant de l'envoyer. », « Format non accepté : JPG, PNG ou WebP. », « L'envoi a échoué, réessayez. »
- Code court : texte libre ; décoration mise à jour pendant la frappe ; bouton « Code » pour citer.
- Insérer : le code seul dans un nouveau paragraphe, après le bloc du curseur (sinon à la fin) ; focus rendu à l'éditeur ; annonce « Code [bareme phase=P2] inséré. »
- Copier : presse-papier, « Copié » 2 s ; échec : sélection + « Sélectionné : Ctrl+C ».
- États : « Publié · enregistré à 14 h 32 », « Modifications non enregistrées », « Enregistrement… », « Enregistré et publié à 14 h 35 », erreur (role=alert) « Échec de l'enregistrement : vos modifications sont toujours dans l'éditeur. Réessayez. » ; codes inapplicables recalculés après enregistrement.
- Quitter avec modifications : confirmation « Quitter sans enregistrer ? Vos modifications seront perdues. »
- Aller au code : sélectionne la première occurrence ; absent : « Ce code n'est plus dans le texte ; enregistrez pour mettre la liste à jour. »
- Restaurer : « Restaurer la version du 12 octobre 2026 à 9 h 10 (A. Moulin) ? » ; « Le texte actuel est remplacé et publié aussitôt. Il est gardé dans l'historique. Les éléments calculés restent ceux de la saison aujourd'hui. » ; flash « Version du … restaurée et publiée. »
- Lien depuis l'encart public avec ?code=… (Aller au code déclenché).

## Accès
- admin/saison/show : action d'en-tête « Règlement » (btn-ghost) à côté de « Modifier la saison ».
- Récapitulatif du wizard : carte « Règlement » (« Rédigé, mis à jour le … » / « Pas encore rédigé ») + lien « Modifier le règlement ».
- Encart public des codes inapplicables : « Modifier le règlement ».
- État vide de la page publique, admin seulement : btn-accent « Rédiger le règlement ».
- Option : entrée « Règlement » dans le menu admin.

## Questions du webdesigner pour l'ergonome
1. Barre : « Titre / Sous-titre » ou « Titre de partie / Titre de règle » ?
2. Un sélecteur de variante par élément (plutôt que tous les codes par phase avec leurs boutons) : convient-il ?
3. Codes inapplicables mis à jour à l'enregistrement et à l'aperçu seulement, ou pendant la frappe ?
4. Entrée « Règlement » dans le menu admin ?
5. Historique sur une page à part ou section repliée sous l'éditeur ?
6. « Enregistrer » ou « Enregistrer et publier » ?

## Questions pour l'utilisateur (à ne pas trancher par l'ergonome, avis bienvenu)
1. « Voir la page publique » : /reglement montre la saison du sélecteur ; paramètre de saison pour les admins ? brouillon : aperçu seulement ?
2. Règlement d'une saison en brouillon modifiable dès le wizard ?
3. Deux admins en même temps : refuser l'enregistrement si une autre version a été publiée entre-temps ?
4. Images : texte de remplacement obligatoire ? Nettoyage des images inutilisées maintenant ou plus tard ?
5. Historique : conserver toutes les versions ou les N dernières ?

---

## Relecture de l'ergonome (20/10/2026) : « oui avec conditions », intégrées à la réalisation

**Indispensable**
1. Saison en brouillon : statut « Brouillon : visible par le public quand la saison sera lancée », bouton « Enregistrer »,
   pas de « Voir la page publique », pas de phrase « Le public voit la version publiée ».
2. Fermeture de l'onglet : `beforeunload` (message du navigateur) ; le texte « Quitter sans enregistrer ? Vos
   modifications seront perdues. » seulement pour les liens internes (`turbo:before-visit`).
3. Restauration : « La version choisie remplace le texte actuel et est publiée aussitôt. Le texte actuel reste dans
   l'historique. Les éléments calculés suivent les paramètres actuels de la saison. » ; bandeau d'une version consultée :
   « Version du … par …. Les éléments calculés suivent les paramètres actuels de la saison. »
4. Codes inapplicables : pluriel calculé (« 1 code n'est pas affiché au public », « 2 codes ne sont pas affichés au
   public ») ; raisons avec la solution (« … Phases possibles : P1, P2. », « cet élément n'accepte pas de paramètre :
   écrivez [departage]. ») ; « Liste mise à jour à chaque enregistrement et à chaque aperçu. »

**Souhaitable**
5. Lien : « contact@… » → mailto:, « www.… » → https:// ; erreur « Adresse non reconnue. Exemples :
   https://www.ffvolley.org, contact@blvb.fr ou #regle-1r. »
6. Image : « Description de l'image (lue aux personnes malvoyantes) » ; réduction automatique dans le navigateur des
   images trop lourdes, sinon « L'image pèse 3,4 Mo, le maximum est 2 Mo. Réduisez-la avant de l'envoyer. » ; description
   modifiable et image retirable après coup.
7. Accès : « Modifier le règlement » (ou « Rédiger le règlement » s'il est vide) partout.
8. Un seul vocabulaire : « Publié · mis à jour le … à … », « Mis à jour et publié à 14 h 35 ».
9. Introduction du panneau : « Placez un élément dans le texte avec « Insérer ». Sur la page publique, il est remplacé par
   l'élément, toujours à jour avec les paramètres de la saison. » ; « tel quel » dans l'aide du bouton Code.
10. « Afficher pour » au lieu de « Variante » ; « Voir le résultat » au lieu de « Rendu pour la saison ».
- Barre d'outils : « Titre de partie » / « Titre de règle » (téléphone : « Partie », « Règle ») ; bouton Code : « Code
  (écrit tel quel) ». Panneau : « Insérer » action principale, « Copier » secondaire ; paramètres repliés sous « Écrire le
  code à la main » ; code affiché qui suit le choix (aria-live). Barre d'enregistrement visible dans l'onglet Aperçu.
  Historique : ligne « Version publiée » sans bouton Restaurer.
- Réponses aux questions du webdesigner : 1 « Titre de partie / Titre de règle » ; 2 un sélecteur par élément ; 3 codes
  inapplicables mis à jour à l'enregistrement et à l'aperçu ; 4 entrée « Règlement » dans le menu admin ; 5 historique sur
  une page à part ; 6 « Enregistrer et publier » (saison visible), « Enregistrer » (brouillon).

**Facultatif** : style « à vérifier » sur les codes inapplicables dans l'éditeur ; raccourcis dans les infobulles.
Attention : « Aller au code » sur téléphone : faire défiler avant de donner le focus (clavier).
