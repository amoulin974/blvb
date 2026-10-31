/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Page du match d'une équipe (Volley Stats, D67 ; maquette docs/volley-stats/maquette-page-match.md) : composition de chaque
// set sur un terrain, set en cours (points, remplacements, correction du score, « Précédent » et « Suivant »), historique et
// relevé des sets. L'état vient du serveur (App\VolleyStats\VueFeuille) et y retourne à chaque saisie : rien n'est gardé
// dans le navigateur, la page se reprend sur un autre téléphone.

const LIBELLES = { 1: 'Arrière droit', 2: 'Avant droit', 3: 'Avant centre', 4: 'Avant gauche', 5: 'Arrière gauche', 6: 'Arrière centre' }
const ORDRE_VISUEL = [4, 3, 2, 5, 6, 1] // filet en haut : avant puis arrière, de gauche à droite
const DEPARTS = [[0, 0], [0, 8], [8, 0]]
// Saisie des touches (D68)
const ACTIONS = [['reception', 'Réception'], ['passe', 'Passe'], ['attaque', 'Attaque'], ['renvoi', 'Renvoi'], ['defense', 'Défense'], ['block', 'Block']]
const ACTION_NOM = { reception: 'réception', passe: 'passe', attaque: 'attaque', renvoi: 'renvoi', defense: 'défense', block: 'block' }
const FAUTES_JOUEUR = [['filet', 'Filet'], ['pied', 'Pied sous le filet'], ['double', 'Double touche'], ['tenue', 'Balle tenue'], ['autre', 'Autre faute']]
const FAUTES_EQUIPE = [['quatre_touches', 'Quatre touches'], ['position', 'Position ou rotation']]
const AVANTS = [1, 2, 3] // postes 2, 3, 4 (index dans l'ordre officiel)
const COURTS = { 1: 'Arr. droit', 2: 'Av. droit', 3: 'Av. centre', 4: 'Av. gauche', 5: 'Arr. gauche', 6: 'Arr. centre' }
const ARTICLE = { reception: 'une réception', passe: 'une passe', attaque: 'une attaque', renvoi: 'un renvoi', defense: 'une défense', block: 'un block' }
const FAUTE_NOM = { filet: 'faute de filet', pied: 'pied sous le filet', double: 'double touche', tenue: 'balle tenue', autre: 'faute' }
const FAUTE_COURTE = { filet: 'filet', pied: 'pied sous le filet', double: 'double touche', tenue: 'balle tenue', autre: 'autre' }

const echapper = (texte) => String(texte ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = crypto.getRandomValues(new Uint8Array(1))[0] % 16
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
}))
const tourner = (postes) => [...postes.slice(1), postes[0]]

export default class extends Controller {
    static targets = ['contenu', 'erreur', 'annonce']
    static values = { etat: Object, url: String, jeton: String, modifiable: Boolean, scoreOfficiel: String }

    connect() {
        this.brouillon = null // composition en cours de saisie (set pas encore commencé)
        this.mode = null // 'remplacement', 'correction'
        this.sortant = undefined
        this.envoiEnCours = false
        this.file = []
        this.serveur = this.etatValue
        this.rendre()
    }

    // --- Données dérivées ---------------------------------------------------------------------------------------------

    get feuille() { return this.etatValue }

    nom(id) {
        if (id === null) return 'Place libre'
        return this.feuille.joueurs.find((j) => j.id === id)?.nom ?? 'Joueur retiré'
    }

    dernierSet() { return this.feuille.sets[this.feuille.sets.length - 1] ?? null }

    matchGagne() {
        const [nous, eux] = this.feuille.setsGagnes
        return nous >= this.feuille.setsGagnants || eux >= this.feuille.setsGagnants
    }

    // Set à composer : le set en composition, ou le suivant si le dernier est terminé (et le match pas encore gagné)
    setACompose() {
        const dernier = this.dernierSet()
        if (dernier?.etat === 'composition') return dernier
        if ((!dernier || dernier.etat === 'termine') && !this.matchGagne()) {
            const numero = (dernier?.numero ?? 0) + 1
            return { numero, etat: 'nouveau', postes: dernier ? [...dernier.postes] : [null, null, null, null, null, null], premierService: null, scoreDepart: [0, 0], pointsFin: numero === 2 * this.feuille.setsGagnants - 1 ? 15 : 25 }
        }
        return null
    }

    dernierOrdre(set) {
        const h = set.historique.filter((l) => !l.enAttente)
        return h.length ? h[h.length - 1].ordre : null
    }

    // --- Rendu --------------------------------------------------------------------------------------------------------

    rendre() {
        // Le rendu remplace tout : le focus revient sur le bouton équivalent (même action, mêmes paramètres)
        const f = this.element.contains(document.activeElement) ? document.activeElement : null
        const cle = f?.dataset.action ? [...f.attributes].filter((a) => a.name === 'data-action' || a.name.endsWith('-param')).map((a) => `[${a.name}="${CSS.escape(a.value)}"]`).join('') : null
        this.rendreContenu()
        if (cle) this.contenuTarget.querySelector(cle)?.focus()
    }

    rendreContenu() {
        const morceaux = []
        const enCours = this.feuille.sets.find((s) => s.etat === 'en_cours')
        const aComposer = enCours ? null : this.setACompose()
        if (enCours) {
            morceaux.push(this.vueSetEnCours(enCours))
        } else if (aComposer && this.modifiableValue) {
            if (!this.brouillon || this.brouillon.numero !== aComposer.numero) {
                this.brouillon = { numero: aComposer.numero, postes: [...aComposer.postes], premierService: aComposer.premierService, scoreDepart: [...aComposer.scoreDepart], pointsFin: aComposer.pointsFin, active: null, saisie: this.dernierSet()?.saisie ?? 'score' }
                this.brouillon.active = this.premiereVide()
            }
            morceaux.push(this.vueComposition())
        } else if (this.matchGagne()) {
            morceaux.push('<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4"><h2 class="text-xl uppercase tracking-wide">Match terminé, d\'après les sets notés ici.</h2></section>')
        }
        morceaux.push(this.vueReleve())
        this.contenuTarget.innerHTML = morceaux.join('')
    }

    terrain(postes, { actif = null, cliquable = false, service = null, etiquette = '', compact = false, sortant } = {}) {
        const cases = ORDRE_VISUEL.map((p) => {
            const id = postes[p - 1]
            const vide = id === null
            const estActif = p === actif || (sortant !== undefined && id === sortant && !vide) || (sortant === null && vide)
            const nom = vide ? (estActif && actif !== null ? 'À choisir' : 'Place libre') : echapper(this.nom(id))
            const mention = estActif && sortant !== undefined ? '<span class="block text-sm font-semibold">sort</span>' : (estActif && !vide ? '<span class="block text-sm font-semibold">à changer</span>' : '')
            const serveur = p === 1 && service === 'nous' ? '<span class="terrain-service">Au service</span>' : ''
            const contenu = `<span class="terrain-poste">${p} · ${LIBELLES[p]}</span>${serveur}<span class="terrain-nom">${nom}${mention}</span>`
            const attributs = `class="terrain-case"${vide ? ' data-etat="vide"' : ''}${estActif ? ' aria-current="true"' : ''}`
            return cliquable
                ? `<button type="button" ${attributs} data-action="feuille-match#toucherPlace" data-feuille-match-poste-param="${p}" aria-label="${p}, ${LIBELLES[p]}${p === 1 && service === 'nous' ? ', au service' : ''} : ${vide ? 'place libre' : echapper(this.nom(id))}${estActif ? ', sélectionnée' : ''}">${contenu}</button>`
                : `<div ${attributs}>${contenu}</div>`
        }).join('')
        return `<div class="terrain${compact ? ' terrain-compact' : ''}"><p class="terrain-filet">Filet</p><div class="terrain-grille" role="group" aria-label="${echapper(etiquette)}, filet en haut">${cases}</div></div>`
    }

    vueComposition() {
        const b = this.brouillon
        const places = b.postes.filter((id) => id !== null)
        const banc = this.feuille.joueurs.filter((j) => !places.includes(j.id))
        const vides = b.postes.filter((id) => id === null).length
        const manque = []
        if (vides) manque.push(vides === 1 ? '1 place à remplir' : `${vides} places à remplir`)
        if (!b.premierService) manque.push('premier service à choisir')
        const consigne = b.active ? `Joueur pour la place ${b.active} · ${LIBELLES[b.active]} :` : (banc.length ? `Sur le banc : ${banc.map((j) => echapper(j.nom)).join(', ')}. Touchez une place du terrain pour la changer.` : 'Tous les joueurs présents sont placés.')
        const radio = (nom, valeur, coche, libelle) => `<label class="choix-carte choix-ligne"><input type="radio" class="radio radio-primary" name="${nom}-${b.numero}" ${coche ? 'checked' : ''} data-action="feuille-match#choisir" data-feuille-match-champ-param="${nom}" data-feuille-match-valeur-param="${valeur}"> <span class="${nom === 'depart' ? 'font-display text-lg font-bold' : 'font-medium'}">${libelle}</span></label>`
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-compo">
            <div><h2 id="titre-compo" class="text-xl uppercase tracking-wide">Composition du set ${b.numero}</h2>
            <p class="text-sm">${b.numero > 1 ? `Reprise du départ du set ${b.numero - 1}. ` : ''}Touchez une place, puis un joueur.${b.pointsFin === 15 ? ' Set en 15 points.' : ''}</p></div>
            ${this.terrain(b.postes, { actif: b.active, cliquable: true, service: b.premierService, etiquette: `Composition du set ${b.numero}` })}
            <div><p class="font-semibold">${consigne}</p>${b.active ? `<div class="mt-2 flex flex-wrap gap-2">${banc.map((j) => `<button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#placer" data-feuille-match-joueur-param="${j.id}">${echapper(j.nom)}</button>`).join('')}</div>` : ''}</div>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#tournerBrouillon">Tourner d'un poste</button>
                <button type="button" class="btn btn-ghost btn-danger-discret" data-action="feuille-match#effacer">Tout effacer</button>
            </div>
            <fieldset><legend class="font-semibold">Score de départ <span class="font-normal text-sm">(nous – eux)</span></legend>
                <div class="mt-2 grid grid-cols-3 gap-2">${DEPARTS.map((d, i) => radio('depart', i, d[0] === b.scoreDepart[0] && d[1] === b.scoreDepart[1], `${d[0]}–${d[1]}`)).join('')}</div></fieldset>
            <fieldset><legend class="font-semibold">Premier service <span class="font-normal text-sm">(tiré au gymnase)</span></legend>
                <div class="mt-2 grid grid-cols-2 gap-2">${radio('service', 'nous', b.premierService === 'nous', 'Nous servons')}${radio('service', 'eux', b.premierService === 'eux', 'Nous recevons')}</div></fieldset>
            <fieldset><legend class="font-semibold">Que notez-vous pendant le set ${b.numero} ?</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">${radio('saisie', 'score', b.saisie === 'score', 'Le score seulement <span class="block text-sm font-normal">Un point à chaque échange.</span>')}${radio('saisie', 'touches', b.saisie === 'touches', 'Le score et les touches des joueurs <span class="block text-sm font-normal">Après chaque point : qui a touché le ballon, et comment. Un échange peut rester sans détail.</span>')}</div>
                <p class="mt-2 text-sm">Pour que le set compte dans les classements des joueurs, au moins <strong>${this.feuille.seuil} %</strong> de ses échanges doivent être détaillés.</p></fieldset>
            <div class="flex flex-col gap-2">
                <button type="button" class="btn btn-accent btn-block" data-action="feuille-match#commencer" aria-disabled="${manque.length ? 'true' : 'false'}" aria-describedby="reste-a-faire">Commencer le set ${b.numero}</button>
                <p class="text-sm" id="reste-a-faire">${manque.length ? 'Reste à faire : ' + manque.join(', ') + '.' : 'Prêt : le set commence au score de départ choisi.'}</p>
                ${vides === 1 && banc.length === 0 && b.premierService ? '<button type="button" class="btn btn-ghost" data-action="feuille-match#commencerACinq">Commencer à 5 joueurs</button>' : ''}
            </div></section>`
    }

    vueSetEnCours(set) {
        const c = set.calcul
        const banc = this.feuille.joueurs.filter((j) => !c.postes.includes(j.id))
        const actifs = set.historique.filter((h) => !h.annule)
        const peutSuivant = set.historique.length && set.historique[set.historique.length - 1].annule
        const m = this.modifiableValue
        let outils = ''
        if (m && this.mode === 'remplacement') {
            outils = `<fieldset><legend class="font-semibold">${this.sortant === undefined ? `Touchez le joueur qui sort${c.postes.includes(null) ? ' (ou la place libre)' : ''}.` : `Remplacer ${echapper(this.nom(this.sortant))} par…`}</legend>
                ${this.sortant === undefined ? '' : `<div class="mt-2 flex flex-wrap gap-2">${banc.map((j) => `<button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#remplacer" data-feuille-match-joueur-param="${j.id}">${echapper(j.nom)}</button>`).join('') || '<p>Personne sur le banc.</p>'}</div>`}</fieldset>
                <button type="button" class="btn btn-ghost" data-action="feuille-match#fermerOutil">Annuler</button>`
        } else if (m && this.mode === 'correction') {
            outils = `<form class="flex flex-col gap-3" data-action="submit->feuille-match#corriger">
                <p class="font-semibold">Corriger le score du set</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex flex-col gap-1"><span class="text-sm">${echapper(this.feuille.nous)}</span><input name="nous" type="number" min="0" max="99" inputmode="numeric" class="input w-full min-h-12 text-lg text-center" value="${c.score[0]}" required></label>
                    <label class="flex flex-col gap-1"><span class="text-sm">${echapper(this.feuille.eux)}</span><input name="eux" type="number" min="0" max="99" inputmode="numeric" class="input w-full min-h-12 text-lg text-center" value="${c.score[1]}" required></label>
                </div>
                <fieldset><legend class="font-semibold">Qui sert maintenant ?</legend><div class="mt-2 grid grid-cols-2 gap-2">
                    <label class="choix-carte choix-ligne"><input type="radio" class="radio radio-primary" name="service" value="nous" required ${c.service === 'nous' ? 'checked' : ''}> <span class="font-medium">Nous servons</span></label>
                    <label class="choix-carte choix-ligne"><input type="radio" class="radio radio-primary" name="service" value="eux" ${c.service === 'eux' ? 'checked' : ''}> <span class="font-medium">Nous recevons</span></label></div></fieldset>
                <div class="grid grid-cols-2 gap-2"><button type="button" class="btn btn-ghost" data-action="feuille-match#fermerOutil">Annuler</button><button type="submit" class="btn btn-accent">Valider</button></div></form>`
        } else if (m && this.mode === 'replacer') {
            outils = `<p class="font-semibold">Les joueurs sont-ils à leur place ? Touchez « Tourner d'un poste » autant de fois qu'il faut.</p>
                <div class="grid gap-2 sm:grid-cols-2"><button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#tournerSet">Tourner d'un poste</button><button type="button" class="btn btn-primary" data-action="feuille-match#fermerOutil">C'est bon</button></div>`
        } else if (m) {
            outils = `<div class="grid gap-2 sm:grid-cols-2">
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#ouvrirOutil" data-feuille-match-mode-param="remplacement"${set.saisie === 'touches' && this.echange(set).length ? ' aria-disabled="true"' : ''}>Remplacer un joueur</button>
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#ouvrirOutil" data-feuille-match-mode-param="correction">Corriger le score</button></div>`
        }
        const fin = m && c.finAtteinte
            ? `<div role="status" class="rounded-box border-2 border-(--color-primary) bg-base-200 p-3 flex flex-col gap-2"><p><strong>Fin du set : ${c.score[0]}–${c.score[1]}.</strong> Un point mal noté ? Touchez « Précédent ».</p><button type="button" class="btn btn-accent" data-action="feuille-match#terminer">Terminer le set ${set.numero}</button></div>`
            : ''
        if (set.saisie === 'touches' && m && !['remplacement', 'correction', 'replacer'].includes(this.mode)) {
            return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-2" aria-labelledby="titre-set-cours">
                ${this.vueTouches(set)}${fin}${outils}${this.vueEchanges(set)}</section>`
        }
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-set-cours">
            <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="titre-set-cours" class="text-xl uppercase tracking-wide">Set ${set.numero} en cours</h2>
            <p class="text-sm">en ${set.pointsFin} points · parti de ${set.scoreDepart[0]}–${set.scoreDepart[1]}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wider text-(--color-base-content)/70">Score du set, pour l'équipe seulement</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-center">
                    ${[0, 1].map((i) => `<div class="flex flex-col gap-2 min-w-0"><p class="font-display font-semibold uppercase tracking-wide wrap-anywhere flex-1">${echapper(i ? this.feuille.eux : this.feuille.nous)}</p><p class="score-set">${c.score[i]}</p>
                    ${m ? `<button type="button" class="btn btn-primary" data-action="feuille-match#point" data-feuille-match-pour-param="${i ? 'eux' : 'nous'}" aria-label="Point pour ${echapper(i ? this.feuille.eux : this.feuille.nous)}">+1 point</button>` : ''}</div>`).join('')}
                </div>
                ${m ? `<div class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#precedent" aria-label="Précédent : annuler la dernière saisie" ${actifs.length ? '' : 'aria-disabled="true"'}><span aria-hidden="true">←</span> Précédent</button><button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#suivant" aria-label="Suivant : rétablir la saisie" ${peutSuivant ? '' : 'aria-disabled="true"'}>Suivant <span aria-hidden="true">→</span></button></div>` : ''}
            </div>
            ${fin}
            ${this.terrain(c.postes, { cliquable: m && this.mode === 'remplacement', service: c.service, etiquette: `Set ${set.numero} sur le terrain`, sortant: this.mode === 'remplacement' ? this.sortant : undefined })}
            ${c.service === 'eux' ? '<p class="text-sm font-semibold">Service adverse</p>' : ''}
            <p class="text-sm">Sur le banc : ${banc.length ? banc.map((j) => echapper(j.nom)).join(', ') : 'personne'}</p>
            ${outils}
            ${this.vueHistorique(set)}
        </section>`
    }

    vueHistorique(set) {
        if (!set.historique.length) return ''
        const lignes = [...set.historique].reverse().map((h) => `<li class="flex justify-between gap-3 py-1${h.annule ? ' line-through text-(--color-base-content)/70' : ''}${h.enAttente ? ' italic' : ''}"><span>${echapper(h.texte)}${h.annule ? '<span class="sr-only"> (annulé)</span>' : ''}</span><span class="tabular-nums">${h.score ? `${h.score[0]}–${h.score[1]}` : ''}</span></li>`).join('')
        return `<details class="depliant border-t border-base-300 pt-2"><summary class="cursor-pointer font-semibold min-h-12 flex items-center justify-between">Historique du set (${set.historique.length})<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg></summary><ol class="divide-y divide-(--color-base-300) text-sm">${lignes}</ol></details>`
    }

    vueReleve() {
        const termines = this.feuille.sets.filter((s) => s.etat === 'termine')
        if (!termines.length) return ''
        const derniere = this.feuille.sets[this.feuille.sets.length - 1]
        const cartes = termines.map((s) => {
            const [nous, eux] = s.calcul.score
            const entrees = s.historique.filter((h) => !h.annule && h.type === 'remplacement').map((h) => echapper(h.texte) + (h.score ? ` à ${h.score[0]}–${h.score[1]}` : ''))
            const rouvrir = this.modifiableValue && s === derniere ? `<button type="button" class="btn btn-ghost" data-action="feuille-match#rouvrir" data-feuille-match-numero-param="${s.numero}">Rouvrir le set ${s.numero}</button>` : ''
            return `<article class="border border-base-300 rounded-box p-3 flex flex-col gap-2" aria-labelledby="releve-set-${s.numero}">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3"><h3 id="releve-set-${s.numero}" class="text-lg uppercase tracking-wide">Set ${s.numero}${s.pointsFin === 15 ? ' <span class="font-sans normal-case tracking-normal text-sm font-normal">en 15 points</span>' : ''}</h3>
                <p class="font-display text-xl font-bold text-(--color-primary)">${nous}–${eux} <span class="font-sans text-sm font-semibold text-base-content">${nous > eux ? 'gagné' : 'perdu'}</span></p></div>
                <p class="text-sm">Composition du set ${s.numero} au départ · ${s.premierService === 'nous' ? 'nous servions' : 'nous recevions'} · parti de ${s.scoreDepart[0]}–${s.scoreDepart[1]}</p>
                ${this.terrain(s.postes, { compact: true, etiquette: `Composition de départ du set ${s.numero}` })}
                <p class="text-sm">${entrees.length ? entrees.join(' · ') : 'Aucun remplacement'}</p>${rouvrir}</article>`
        }).join('')
        const officiel = this.matchGagne()
            ? `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-2" aria-labelledby="titre-officiel"><h2 id="titre-officiel" class="text-xl uppercase tracking-wide">Score officiel</h2>
            <p class="text-sm">Il se saisit à part, sur la fiche de l'équipe (ligne du match). Les scores ci-dessus ne le remplissent pas.</p><a class="btn btn-accent btn-block" href="${echapper(this.scoreOfficielValue)}">Aller à la fiche de l'équipe</a></section>`
            : ''
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-releve"><h2 id="titre-releve" class="text-xl uppercase tracking-wide">Relevé des sets</h2>${cartes}${this.vueSetsJoues()}</section>${this.vueStats()}${officiel}`
    }

    // Stats des sets notés « le score et les touches » (D68), visibles de l'équipe et du responsable de son club
    vueStats() {
        const st = this.feuille.stats
        if (!st) return ''
        const pct = (v) => (v === null || v === undefined ? '–' : `${v} %`)
        const lignes = st.joueurs.map((j) => `<tr><th scope="row" class="font-normal whitespace-nowrap">${echapper(j.nom)}</th>
            <td class="tabular-nums text-right">${j.pointsMarques}</td><td class="tabular-nums text-right">${j.services ? `${j.aces} / ${j.fautesService}` : '–'}</td>
            <td class="tabular-nums text-right">${pct(j.receptionSansFaute)}</td><td class="tabular-nums text-right">${j.passes ? `${j.passesDecisives}` : '–'}</td>
            <td class="tabular-nums text-right">${j.attaques ? `${j.attaquesGagnantes} / ${j.attaques}` : '–'}</td><td class="tabular-nums text-right">${pct(j.efficacite)}</td>
            <td class="tabular-nums text-right">${j.blocksGagnants}</td></tr>`).join('')
        const sets = st.sets.map((s) => `Set ${s.numero} : ${s.detailles} échange${s.detailles > 1 ? 's' : ''} détaillé${s.detailles > 1 ? 's' : ''} sur ${s.echanges} (${s.taux} %)${s.taux >= st.seuil ? '' : ` · <strong>sous le seuil de ${st.seuil} % : ce set ne comptera pas dans les classements</strong>`}`).join('<br>')
        const abbr = (court, long) => `<abbr title="${long}" class="no-underline">${court}</abbr>`
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-3" aria-labelledby="titre-stats">
            <h2 id="titre-stats" class="text-xl uppercase tracking-wide">Statistiques du match</h2>
            <p class="text-sm">Visibles de l'équipe et du responsable du club seulement.</p>
            <p class="text-sm">${sets}<br>Aces subis sans contact : ${st.acesSubis}.</p>
            <p class="text-sm sm:hidden">Faites glisser le tableau pour voir toutes les colonnes.</p>
            <div class="overflow-x-auto tableau-defilant" tabindex="0" role="region" aria-label="Statistiques par joueur, faites défiler"><table class="table table-sm"><thead><tr><th scope="col">Joueur</th><th scope="col" class="text-right">Points</th><th scope="col" class="text-right">${abbr('Aces / f. serv.', 'Aces / fautes de service')}</th><th scope="col" class="text-right">${abbr('Récep.', 'Réception sans faute')}</th><th scope="col" class="text-right">${abbr('Passes déc.', 'Passes décisives')}</th><th scope="col" class="text-right">${abbr('Attaques', 'Attaques gagnantes / tentées')}</th><th scope="col" class="text-right">${abbr('Eff. att.', 'Efficacité d\'attaque')}</th><th scope="col" class="text-right">Blocks</th></tr></thead>
            <tbody>${lignes}</tbody></table></div>
            <p class="text-sm text-(--color-base-content)/80">Efficacité d'attaque : (attaques gagnantes − fautes − contrées) ÷ attaques.</p></section>`
    }

    // Sets joués par joueur : composition de départ et entrées en remplacement (D67)
    vueSetsJoues() {
        const compte = new Map()
        for (const s of this.feuille.sets.filter((x) => x.etat !== 'composition')) {
            const ids = new Set(s.postes.filter((id) => id !== null))
            for (const h of s.historique) if (h.type === 'remplacement' && !h.annule) ids.add(h.entre)
            ids.forEach((id) => compte.set(id, (compte.get(id) ?? 0) + 1))
        }
        if (!compte.size) return ''
        const lignes = [...compte.entries()].sort((a, b) => b[1] - a[1] || this.nom(a[0]).localeCompare(this.nom(b[0]))).map(([id, n]) => `<tr><th scope="row" class="font-normal">${echapper(this.nom(id))}</th><td class="text-right tabular-nums">${n}</td></tr>`).join('')
        return `<div><h3 class="text-lg uppercase tracking-wide">Sets joués</h3><table class="table mt-1"><thead><tr><th scope="col">Joueur</th><th scope="col" class="text-right">Sets</th></tr></thead><tbody>${lignes}</tbody></table></div>`
    }

    // --- Saisie des touches (D68 ; maquette docs/volley-stats/maquette-saisie-actions.md) ------------------------------

    // Touches de l'échange en cours (après le dernier point, la dernière balle à rejouer ou la dernière correction)
    echange(set) {
        let touches = []
        for (const h of set.historique) {
            if (h.annule) continue
            if (['point', 'rejouer', 'correction'].includes(h.type)) touches = []
            else if (h.type === 'action') touches.push(h)
        }
        return touches
    }

    // Action proposée pour la prochaine touche, recalculée après chaque touche (jamais imposée)
    predire(set) {
        const touches = this.echange(set)
        if (!touches.length) return set.calcul.service === 'eux' ? 'reception' : 'defense'
        return { reception: 'passe', defense: 'passe', passe: 'attaque' }[touches[touches.length - 1].donnees.action] ?? 'defense'
    }

    // Touches de l'échange en cours et des échanges terminés, corrections appliquées (D68) ; une touche retirée disparaît
    touchesCorrigees(set) {
        const corrections = new Map()
        for (const h of set.historique) {
            if (h.annule || h.type !== 'modification') continue
            const d = h.donnees
            const avant = corrections.get(d.cible) ?? {}
            corrections.set(d.cible, d.retirer ? { retiree: true } : { ...avant, ...(d.action ? { action: d.action } : {}), ...(d.joueurs ? { joueur: d.joueurs[0] } : {}) })
        }
        return corrections
    }

    vueTouches(set) {
        const c = set.calcul
        const touches = this.echange(set)
        const derniere = touches[touches.length - 1]
        this.saisie ??= { etape: 'jeu', choix: null, faute: null }
        const t = this.saisie
        // Touche en attente d'issue : la dernière de l'échange, sauf si « Le jeu continue » a été touché
        const enAttente = derniere && t.continue !== derniere.uuid ? derniere : null
        const action = t.faute ? null : (t.choix ?? this.predire(set))
        const verrou = ['perdu-attaque', 'block-deux'].includes(t.etape)
        const refus = verrou ? ' aria-disabled="true"' : ''
        const premierBloqueur = t.etape === 'block-deux' ? enAttente?.donnees.joueurs[0] : null
        const actions = ACTIONS.map(([v, l]) => `<button type="button" class="btn btn-outline btn-primary btn-bascule" data-action="feuille-match#choisirAction" data-feuille-match-valeur-param="${v}" aria-pressed="${action === v}"${refus}>${l}</button>`).join('')
        const cases = ORDRE_VISUEL.map((p) => {
            const id = c.postes[p - 1]
            if (id === null) return `<div class="terrain-case" data-etat="vide"><span class="terrain-nom">Place libre</span><span class="terrain-poste">${p} · ${COURTS[p]}</span></div>`
            const arriere = (action === 'block' || t.etape === 'block-deux') && !AVANTS.includes(p - 1)
            const actif = (enAttente && enAttente.donnees.joueurs[0] === id && t.etape !== 'block-deux') || id === premierBloqueur
            const motif = actif ? (id === premierBloqueur ? 'block' : ACTION_NOM[enAttente.donnees.action]) : ''
            const refuse = arriere || t.etape === 'perdu-attaque' || id === premierBloqueur
            const raison = t.etape === 'perdu-attaque' ? 'perdu' : (id === premierBloqueur ? 'meme' : 'arriere')
            const service = p === 1 && c.service === 'nous' ? '<span class="terrain-service">Au service</span>' : ''
            const nomAccessible = `${this.nom(id)}${actif ? `, ${motif}, en attente` : ''}, ${p} ${LIBELLES[p]}${t.faute ? ` : ${FAUTE_NOM[t.faute]}` : (action ? ` : noter ${ARTICLE[action]}` : '')}`
            return `<button type="button" class="terrain-case" data-action="feuille-match#toucherJoueur" data-feuille-match-joueur-param="${id}" data-feuille-match-raison-param="${raison}"${actif ? ' aria-current="true"' : ''}${refuse ? ' aria-disabled="true"' : ''} aria-label="${echapper(nomAccessible)}"><span class="terrain-nom">${echapper(this.nom(id))}${motif ? `<span class="terrain-mention">${motif}</span>` : ''}</span><span class="terrain-poste" aria-hidden="true">${p} · ${COURTS[p]}</span>${service}</button>`
        }).join('')
        const nEnvoi = this.file.length
        const etatEnvoi = this.horsLigne ? `<strong class="texte-attention">Hors ligne · ${nEnvoi} gardée${nEnvoi > 1 ? 's' : ''}</strong>` : (nEnvoi ? `<span class="italic">Envoi… (${nEnvoi})</span>` : '<span class="texte-succes">✓ Enregistré</span>')
        const sansDetail = touches.length || verrou ? ' aria-disabled="true"' : ''
        return `<div><h2 id="titre-set-cours" class="text-base uppercase tracking-wide">Set ${set.numero} en cours <span class="normal-case tracking-normal font-sans text-sm font-normal">· en ${set.pointsFin} points</span></h2>
                <p class="mt-1 flex items-baseline justify-center gap-3 font-display"><span class="font-semibold uppercase truncate text-right flex-1 min-w-0" title="${echapper(this.feuille.nous)}">${echapper(this.feuille.nous)}</span><span class="score-set score-set-compact shrink-0"><span class="sr-only">Score : </span>${c.score[0]}<span aria-hidden="true">–</span><span class="sr-only"> à </span>${c.score[1]}</span><span class="font-semibold uppercase truncate flex-1 min-w-0" title="${echapper(this.feuille.eux)}">${echapper(this.feuille.eux)}</span></p>
                <p class="text-sm flex flex-wrap justify-between gap-x-3"><span>Au service : <strong>${c.service === 'nous' ? echapper(this.nom(c.postes[0])) : 'adversaire'}</strong></span>${etatEnvoi}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wider text-(--color-base-content)/80">Prochaine touche${enAttente ? ', si le jeu continue' : ''}</p>
                <div class="mt-1 grid grid-cols-4 gap-1" role="group" aria-label="Action de la prochaine touche">${actions}<button type="button" class="btn btn-outline btn-primary btn-bascule col-span-2" data-action="feuille-match#menuFaute" aria-expanded="${t.etape === 'faute-menu'}" aria-pressed="${!!t.faute}"${refus}>${t.faute ? 'Faute : ' + FAUTE_COURTE[t.faute] : 'Faute de…'}</button></div></div>
            <div class="terrain terrain-jeu"><p class="terrain-filet">Filet</p><div class="terrain-grille" role="group" aria-label="Joueurs sur le terrain, filet en haut : touchez celui qui a joué le ballon">${cases}</div></div>
            <div class="touche-carte" ${enAttente || t.etape !== 'jeu' || t.faute || t.refus ? '' : 'data-etat="consigne"'} aria-live="polite">${t.refus ? `<p class="font-semibold texte-attention">${echapper(t.refus)}</p>` : ''}${this.vueCarteTouche(set, enAttente, action)}</div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-primary" data-action="feuille-match#pointSansTouche" data-feuille-match-pour-param="nous" aria-label="Point pour nous, ${echapper(this.feuille.nous)}"${refus}>Point pour nous</button>
                <button type="button" class="btn btn-primary" data-action="feuille-match#pointSansTouche" data-feuille-match-pour-param="eux" aria-label="Point pour eux, ${echapper(this.feuille.eux)}"${refus}>Point pour eux</button></div>
            <div class="grid grid-cols-3 gap-2 items-center"><span class="text-sm" id="libelle-sans-detail">Point sans détail :</span>
                <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#pointSansDetail" data-feuille-match-pour-param="nous" aria-label="Point sans détail pour ${echapper(this.feuille.nous)}"${sansDetail}>Nous</button>
                <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#pointSansDetail" data-feuille-match-pour-param="eux" aria-label="Point sans détail pour ${echapper(this.feuille.eux)}"${sansDetail}>Eux</button></div>
            <button type="button" class="btn btn-outline btn-primary btn-block" data-action="feuille-match#rejouer"${touches.length && !verrou ? '' : ' aria-disabled="true"'}>Balle à rejouer</button>
            <div class="grid grid-cols-2 gap-2"><button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#precedent" aria-label="Précédent : annuler la dernière saisie" ${set.historique.some((h) => !h.annule) || t.etape !== 'jeu' || t.continue ? '' : 'aria-disabled="true"'}><span aria-hidden="true">←</span> Précédent</button><button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#suivant" aria-label="Suivant : rétablir la saisie" ${set.historique.length && set.historique[set.historique.length - 1].annule ? '' : 'aria-disabled="true"'}>Suivant <span aria-hidden="true">→</span></button></div>
            <p class="text-sm"><strong>Échange en cours :</strong> ${touches.length ? touches.map((h) => `${echapper(this.nom(h.donnees.joueurs[0]))} ${ACTION_NOM[h.donnees.action]}${h.enAttente ? ' <span class="italic">· envoi…</span>' : ''}`).join(' · ') : 'aucune touche notée.'}</p>`
    }

    vueCarteTouche(set, enAttente, action) {
        const t = this.saisie
        if (t.etape === 'faute-menu') {
            const b = (type, [v, l]) => `<button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#${type}" data-feuille-match-valeur-param="${v}">${l}</button>`
            return `<p class="text-sm font-semibold">Faute d'un joueur, puis touchez-le :</p><div class="grid grid-cols-2 gap-1">${FAUTES_JOUEUR.map((f) => b('fauteJoueur', f)).join('')}</div>
                <p class="text-sm font-semibold">Faute de l'équipe, point pour eux tout de suite :</p><div class="grid grid-cols-2 gap-1">${FAUTES_EQUIPE.map((f) => b('fauteEquipe', f)).join('')}</div>
                <button type="button" class="btn btn-ghost btn-sm min-h-11" data-action="feuille-match#menuFaute">Fermer</button>`
        }
        if (t.etape === 'perdu-attaque' && enAttente) {
            return `<p class="font-semibold">${echapper(this.nom(enAttente.donnees.joueurs[0]))} · Attaque perdue : comment ?</p><div class="grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-outline btn-primary h-auto min-h-12 flex-col gap-0 py-1" data-action="feuille-match#issueAttaque" data-feuille-match-valeur-param="contree">Contrée<span class="text-xs font-normal">le contre adverse marque</span></button>
                <button type="button" class="btn btn-outline btn-primary h-auto min-h-12 flex-col gap-0 py-1" data-action="feuille-match#issueAttaque" data-feuille-match-valeur-param="faute">Faute<span class="text-xs font-normal">dehors, filet…</span></button></div>`
        }
        if (t.etape === 'block-deux' && enAttente) {
            return `<p class="font-semibold">Block gagnant de ${echapper(this.nom(enAttente.donnees.joueurs[0]))} : un deuxième bloqueur ? Touchez-le.</p>
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#unSeulBloqueur">Un seul bloqueur</button>`
        }
        if (t.faute) {
            return `<p class="font-semibold">${FAUTE_NOM[t.faute][0].toUpperCase() + FAUTE_NOM[t.faute].slice(1)} : touchez le joueur fautif.</p><button type="button" class="btn btn-ghost btn-sm min-h-11" data-action="feuille-match#annulerFaute">Ce n'était pas une faute</button>`
        }
        if (enAttente) {
            return `<p class="font-semibold"><span class="text-xs uppercase tracking-wider text-(--color-base-content)/80">En attente</span> ${echapper(this.nom(enAttente.donnees.joueurs[0]))} · ${ACTION_NOM[enAttente.donnees.action]}</p>
                <div class="grid grid-cols-3 gap-1">
                    <button type="button" class="btn btn-outline btn-primary btn-sm h-auto min-h-12" data-action="feuille-match#issue" data-feuille-match-valeur-param="gagne">Point gagné</button>
                    <button type="button" class="btn btn-outline btn-primary btn-sm h-auto min-h-12" data-action="feuille-match#issue" data-feuille-match-valeur-param="perdu">Point perdu</button>
                    <button type="button" class="btn btn-outline btn-sm h-auto min-h-12" data-action="feuille-match#issue" data-feuille-match-valeur-param="continue">Le jeu continue</button></div>`
        }
        const qui = { reception: 'qui réceptionne', defense: 'qui défend', passe: 'qui fait la passe', attaque: 'qui attaque', renvoi: 'qui renvoie', block: 'qui contre' }[action]
        return `<p class="font-semibold">Touchez le joueur ${qui}.</p><p class="text-sm">Aucune touche de notre côté ? « Point pour nous / eux ». Échange pas suivi ? « Point sans détail ».</p>`
    }

    // Récit d'un échange terminé, au terme précis (ace, attaque gagnante, réception manquée, faute de filet…)
    recitPoint(h, decisive, serveur, service) {
        const d = h.donnees ?? {}
        const nous = d.pour === 'nous'
        if (d.sansDetail) return 'sans détail'
        if (d.sur === 'service') return `${echapper(this.nom(serveur))} ${nous ? 'ace' : 'faute de service'}`
        if (decisive) {
            const qui = echapper(this.nom(decisive.joueur))
            if (nous) {
                if (decisive.action === 'block') return d.avec ? `${qui} et ${echapper(this.nom(d.avec))} block gagnant` : `${qui} block gagnant`
                return `${qui} ${{ attaque: 'attaque gagnante', renvoi: 'renvoi gagnant', reception: 'point sur réception', passe: 'point sur passe', defense: 'point sur défense' }[decisive.action]}`
            }
            if (d.detail === 'contree') return `${qui} attaque contrée`
            return `${qui} ${{ attaque: 'faute d\'attaque', reception: 'réception manquée', passe: 'passe manquée', defense: 'défense manquée', renvoi: 'renvoi manqué', block: 'faute de block' }[decisive.action]}`
        }
        if (d.fautif) return `${echapper(this.nom(d.fautif))} ${FAUTE_NOM[d.detail] ?? 'faute'}`
        if (d.detail === 'quatre_touches') return 'faute de l\'équipe : quatre touches'
        if (d.detail === 'position') return 'faute de position'
        if (service === 'eux') return nous ? 'faute de service adverse' : 'ace subi'
        return nous ? 'faute adverse' : 'point adverse'
    }

    // Historique regroupé par échange (le plus récent en haut) ; les touches des échanges terminés se corrigent (D68)
    vueEchanges(set) {
        const corrections = this.touchesCorrigees(set)
        const echanges = []
        let touches = []
        let postes = [...set.postes]
        let service = set.premierService
        let points = 0
        let detailles = 0
        for (const h of set.historique) {
            if (h.annule || h.type === 'modification') continue
            if (h.type === 'action') {
                const corr = corrections.get(h.uuid) ?? {}
                if (!corr.retiree) touches.push({ uuid: h.uuid, action: corr.action ?? h.donnees.action, joueur: corr.joueur ?? h.donnees.joueurs[0], enAttente: h.enAttente })
                continue
            }
            if (h.type === 'point' || h.type === 'rejouer') {
                const d = h.donnees ?? {}
                const decisive = touches.find((x) => x.uuid === d.sur)
                const rejoue = h.type === 'rejouer'
                const recit = rejoue ? 'Balle à rejouer · touches non comptées' : `<strong>${d.pour === 'nous' ? 'Point pour nous' : 'Point pour eux'}</strong> · ${this.recitPoint(h, decisive, postes[0], service)}`
                const boutons = !rejoue && !h.enAttente ? touches.filter((x) => x !== decisive && !x.enAttente).map((x) => `<button type="button" class="btn btn-outline btn-primary btn-xs min-h-11" data-action="feuille-match#corrigerTouche" data-feuille-match-valeur-param="${x.uuid}" aria-expanded="${this.correction?.uuid === x.uuid}">${echapper(this.nom(x.joueur))} · ${ACTION_NOM[x.action]}</button>`).join('') : ''
                const autres = touches.filter((x) => x !== decisive).map((x) => `${echapper(this.nom(x.joueur))} ${ACTION_NOM[x.action]}`).join(' · ')
                const panneau = touches.some((x) => x.uuid === this.correction?.uuid) ? this.vueCorrection(touches.find((x) => x.uuid === this.correction.uuid)) : ''
                echanges.push(`<li class="py-2 grid grid-cols-[3rem_minmax(0,1fr)] gap-2"><span class="tabular-nums font-display font-bold ${rejoue ? 'text-(--color-base-content)/70' : 'text-(--color-primary)'}">${h.score ? `${h.score[0]}–${h.score[1]}` : ''}</span>
                    <div class="min-w-0 flex flex-col gap-1"><p>${recit}${autres && !boutons ? `<span class="block text-(--color-base-content)/80${rejoue ? ' line-through' : ''}">${autres}</span>` : ''}</p>${boutons ? `<div class="flex flex-wrap gap-1" role="group" aria-label="Touches de cet échange : touchez-en une pour la corriger">${boutons}</div>` : ''}${panneau}</div></li>`)
                if (!rejoue) {
                    points++
                    if (!d.sansDetail) detailles++
                    if (d.pour === 'nous') {
                        if (service === 'eux') postes = tourner(postes)
                        service = 'nous'
                    } else service = 'eux'
                }
                touches = []
            } else {
                if (h.type === 'remplacement') { const i = postes.indexOf(h.donnees.sort); if (i >= 0) postes[i] = h.donnees.entre }
                if (h.type === 'rotation') postes = tourner(postes)
                if (h.type === 'correction') service = h.donnees.service
                echanges.push(`<li class="py-2 grid grid-cols-[3rem_minmax(0,1fr)] gap-2"><span></span><p>${echapper(h.texte)}</p></li>`)
            }
        }
        if (!echanges.length) return ''
        const taux = points ? Math.round((100 * detailles) / points) : 0
        const seuil = this.feuille.seuil
        return `<details class="depliant border-t border-base-300 pt-2"${this.correction ? ' open' : ''}><summary class="cursor-pointer min-h-12 flex items-center justify-between gap-2"><span><span class="font-semibold">Historique du set</span><span class="block text-sm">${points} échange${points > 1 ? 's' : ''}, ${detailles} détaillé${detailles > 1 ? 's' : ''} (${taux} %), il en faut ${seuil} %</span></span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg></summary><ol class="divide-y divide-(--color-base-300) text-sm">${echanges.reverse().join('')}</ol></details>`
    }

    // Correction d'une touche d'un échange terminé : changer le joueur, l'action, ou la retirer (D68)
    vueCorrection(touche) {
        const c = this.correction
        let choix = ''
        if (c.mode === 'action') choix = `<div class="grid grid-cols-3 gap-1">${ACTIONS.filter(([v]) => v !== touche.action).map(([v, l]) => `<button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#appliquerCorrection" data-feuille-match-valeur-param="action:${v}">${l}</button>`).join('')}</div>`
        if (c.mode === 'joueur') choix = `<div class="grid grid-cols-2 gap-1">${this.feuille.joueurs.filter((j) => j.id !== touche.joueur).map((j) => `<button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#appliquerCorrection" data-feuille-match-valeur-param="joueur:${j.id}">${echapper(j.nom)}</button>`).join('')}</div>`
        return `<div class="border border-base-300 rounded-box p-2 flex flex-col gap-1"><p class="font-semibold">${echapper(this.nom(touche.joueur))} · ${ACTION_NOM[touche.action]}</p>
            <div class="grid grid-cols-2 gap-1"><button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#modeCorrection" data-feuille-match-valeur-param="joueur" aria-pressed="${c.mode === 'joueur'}">Changer le joueur</button>
            <button type="button" class="btn btn-outline btn-primary btn-sm min-h-11" data-action="feuille-match#modeCorrection" data-feuille-match-valeur-param="action" aria-pressed="${c.mode === 'action'}">Changer l'action</button></div>
            ${choix}<button type="button" class="btn btn-ghost btn-danger-discret btn-sm min-h-11" data-action="feuille-match#appliquerCorrection" data-feuille-match-valeur-param="retirer">Retirer cette touche</button>
            <p class="text-xs">L'issue d'un échange terminé ne change pas ici : « Corriger le score ».</p></div>`
    }

    corrigerTouche({ params: { valeur } }) {
        this.correction = this.correction?.uuid === valeur ? null : { uuid: valeur, mode: null }
        this.rendre()
    }

    modeCorrection({ params: { valeur } }) {
        this.correction.mode = valeur
        this.rendre()
    }

    async appliquerCorrection({ params: { valeur } }) {
        const [type, v] = String(valeur).split(':')
        const donnees = { cible: this.correction.uuid, ...(type === 'retirer' ? { retirer: true } : type === 'action' ? { action: v } : { joueurs: [parseInt(v, 10)] }) }
        this.correction = null
        if (await this.evenement('modification', donnees)) this.annoncer('Touche corrigée.')
    }

    // Refus dans la carte, sous le pouce (et annoncé), plutôt qu'en haut de page
    refuser(message) {
        this.saisie.refus = message
        this.rendre()
        this.annoncer(message)
    }

    choisirAction(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return this.refuser('Choisissez d\'abord l\'issue dans la carte.')
        const { valeur } = e.params
        Object.assign(this.saisie, { choix: valeur, faute: null, refus: null })
        if (this.saisie.etape === 'faute-menu') this.saisie.etape = 'jeu'
        this.rendre()
        this.annoncer(`Prochaine touche : ${ACTION_NOM[valeur]}.`)
    }

    menuFaute(e) {
        if (e?.currentTarget?.getAttribute('aria-disabled') === 'true') return this.refuser('Choisissez d\'abord l\'issue dans la carte.')
        this.saisie.refus = null
        this.saisie.etape = this.saisie.etape === 'faute-menu' ? 'jeu' : 'faute-menu'
        this.rendre()
    }

    fauteJoueur({ params: { valeur } }) {
        Object.assign(this.saisie, { faute: valeur, etape: 'jeu', refus: null })
        this.rendre()
        this.annoncer('Touchez le joueur fautif.')
    }

    annulerFaute() {
        this.saisie.faute = null
        this.rendre()
    }

    fauteEquipe({ params: { valeur } }) {
        this.saisie.etape = 'jeu'
        this.noterPoint({ pour: 'eux', detail: valeur })
    }

    toucherJoueur(e) {
        const { joueur, raison } = e.params
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') {
            return this.refuser({ perdu: 'Choisissez d\'abord : contrée ou faute.', meme: 'Ce joueur est déjà noté au block : touchez le deuxième bloqueur.', arriere: 'Un joueur arrière ne peut pas contrer.' }[raison])
        }
        const set = this.setEnCours()
        const touches = this.echange(set)
        const derniere = touches[touches.length - 1]
        const t = this.saisie
        t.refus = null
        if (t.etape === 'block-deux') return this.noterPoint({ pour: 'nous', sur: derniere.uuid, avec: joueur })
        if (t.faute) return this.noterPoint({ pour: 'eux', detail: t.faute, fautif: joueur })
        const action = t.choix ?? this.predire(set)
        Object.assign(t, { choix: null, etape: 'jeu', continue: null })
        this.evenement('action', { action, joueurs: [joueur] })
        this.annoncer(`${this.nom(joueur)}, ${ACTION_NOM[action]}.`)
    }

    issue({ params: { valeur } }) {
        const set = this.setEnCours()
        const touches = this.echange(set)
        const derniere = touches[touches.length - 1]
        if (!derniere) return
        this.saisie.refus = null
        if (valeur === 'continue') {
            this.saisie.continue = derniere.uuid
            this.rendre()
            this.annoncer(`Le jeu continue. Prochaine touche : ${ACTION_NOM[this.predire(set)]}.`)
        } else if (valeur === 'gagne' && derniere.donnees.action === 'block') {
            this.saisie.etape = 'block-deux'
            this.rendre()
        } else if (valeur === 'perdu' && derniere.donnees.action === 'attaque') {
            this.saisie.etape = 'perdu-attaque'
            this.rendre()
        } else {
            this.noterPoint({ pour: valeur === 'gagne' ? 'nous' : 'eux', sur: derniere.uuid })
        }
    }

    issueAttaque({ params: { valeur } }) {
        const touches = this.echange(this.setEnCours())
        this.noterPoint({ pour: 'eux', sur: touches[touches.length - 1].uuid, detail: valeur })
    }

    unSeulBloqueur() {
        const touches = this.echange(this.setEnCours())
        this.noterPoint({ pour: 'nous', sur: touches[touches.length - 1].uuid })
    }

    // « Point pour nous / eux » : point sans faute de nos joueurs ; sans touche à notre service, c'est un ace ou une faute
    // de service de notre serveur (déduit de la rotation)
    pointSansTouche(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return this.refuser('Choisissez d\'abord l\'issue dans la carte.')
        const set = this.setEnCours()
        const sansTouche = !this.echange(set).length
        const donnees = { pour: e.params.pour }
        if (sansTouche && set.calcul.service === 'nous' && set.calcul.postes[0] !== null) donnees.sur = 'service'
        this.noterPoint(donnees)
    }

    pointSansDetail(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return this.refuser('Des touches sont notées : choisissez l\'issue, ou touchez « Précédent ».')
        this.noterPoint({ pour: e.params.pour, sansDetail: true })
    }

    rejouer(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return this.refuser('Rien à rejouer : aucune touche dans cet échange.')
        Object.assign(this.saisie, { etape: 'jeu', choix: null, faute: null, continue: null, refus: null })
        this.evenement('rejouer', {})
        this.annoncer('Balle à rejouer : même service.')
    }

    async noterPoint(donnees) {
        Object.assign(this.saisie, { etape: 'jeu', choix: null, faute: null, continue: null, refus: null })
        const avant = this.setEnCours()
        const decisive = this.echange(avant).find((h) => h.uuid === donnees.sur)
        const recit = this.recitPoint({ donnees }, decisive ? { action: decisive.donnees.action, joueur: decisive.donnees.joueurs[0] } : null, avant.calcul.postes[0], avant.calcul.service)
        const envoi = this.evenement('point', donnees)
        const c = this.dernierSet().calcul
        this.annoncer(`${donnees.pour === 'nous' ? 'Point pour nous' : 'Point pour eux'}, ${recit.replace(/<[^>]+>/g, '')}. ${c.score[0]}–${c.score[1]}.`)
        return envoi
    }

    // --- Composition ---------------------------------------------------------------------------------------------------

    premiereVide() {
        const i = this.brouillon.postes.findIndex((id) => id === null)
        return i < 0 ? null : i + 1
    }

    toucherPlace({ params: { poste } }) {
        const enCours = this.feuille.sets.find((s) => s.etat === 'en_cours')
        if (enCours && this.mode === 'remplacement') {
            this.sortant = enCours.calcul.postes[poste - 1]
            this.rendre()
            return
        }
        this.brouillon.active = this.brouillon.active === poste ? null : poste
        this.rendre()
        this.annoncer(this.brouillon.active ? `Place ${poste}, ${LIBELLES[poste]} : choisissez un joueur.` : '')
    }

    placer({ params: { joueur } }) {
        const b = this.brouillon
        if (!b.active) return
        b.postes[b.active - 1] = joueur
        b.active = this.premiereVide()
        this.annoncer(b.active ? `Place suivante : ${b.active}, ${LIBELLES[b.active]}.` : 'Les six places sont remplies.')
        this.enregistrerComposition()
    }

    tournerBrouillon() {
        this.brouillon.postes = tourner(this.brouillon.postes)
        this.brouillon.active = this.premiereVide()
        this.annoncer('Composition tournée d\'un poste.')
        this.enregistrerComposition()
    }

    effacer() {
        this.brouillon.postes = [null, null, null, null, null, null]
        this.brouillon.active = 1
        this.enregistrerComposition()
    }

    choisir({ params: { champ, valeur } }) {
        if (champ === 'depart') this.brouillon.scoreDepart = [...DEPARTS[valeur]]
        else if (champ === 'saisie') {
            this.brouillon.saisie = valeur // choisi au moment de commencer, rien à enregistrer avant
            this.rendre()
            return
        } else this.brouillon.premierService = valeur
        this.enregistrerComposition()
    }

    // Chaque modification est enregistrée aussitôt (la page se reprend ailleurs) ; deux places libres ou plus restent
    // locales tant qu'elles ne sont pas remplies (le serveur n'en accepte qu'une)
    enregistrerComposition() {
        const b = this.brouillon
        this.rendre()
        if (b.postes.filter((id) => id === null).length > 1) return Promise.resolve(true)
        return this.envoyer(b.numero, 'composition', { composition: { postes: b.postes, premierService: b.premierService, scoreDepart: b.scoreDepart } }, false)
    }

    async commencer(evenement, aCinq = false) {
        if (evenement?.currentTarget?.getAttribute('aria-disabled') === 'true' && !aCinq) {
            this.signaler('Reste à faire : ' + document.getElementById('reste-a-faire')?.textContent.replace('Reste à faire : ', ''))
            return
        }
        const b = this.brouillon
        if (await this.envoyer(b.numero, 'composition', { composition: { postes: b.postes, premierService: b.premierService, scoreDepart: b.scoreDepart } }, false)) {
            // Rechargement : la liste des présents se replie et le score passe en haut de l'écran
            if (await this.envoyer(b.numero, 'commencer', { aCinq, saisie: b.saisie })) window.location.reload()
        }
    }

    commencerACinq(evenement) { return this.commencer(evenement, true) }

    // --- Set en cours --------------------------------------------------------------------------------------------------

    setEnCours() { return this.feuille.sets.find((s) => s.etat === 'en_cours') }

    evenement(type, donnees) {
        const set = this.setEnCours()
        return this.envoyer(set.numero, 'evenement', { uuid: uuid(), type, donnees, dernierOrdre: this.dernierOrdre(set) })
    }

    async point({ params: { pour } }) {
        if (await this.evenement('point', { pour })) {
            const c = this.setEnCours()?.calcul ?? this.dernierSet().calcul
            this.annoncer(`${c.score[0]} – ${c.score[1]}`)
        }
    }

    async precedent(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return
        const t = this.saisie
        if (t && (t.etape !== 'jeu' || t.continue || t.faute)) {
            // Étape en cours sur le téléphone seulement : on revient à la carte en attente, rien n'est envoyé
            Object.assign(t, { etape: 'jeu', continue: null, faute: null, refus: null })
            this.rendre()
            this.annoncer('Retour à la touche en attente.')
            return
        }
        if (this.file.length) {
            this.signaler('Attendez l\'envoi de la dernière saisie, puis touchez « Précédent ».')
            return
        }
        const set = this.setEnCours()
        const actifs = set.historique.filter((h) => !h.annule)
        if (await this.envoyer(set.numero, 'precedent', { uuid: actifs[actifs.length - 1].uuid })) this.annoncer('Dernière saisie annulée.')
    }

    async suivant(e) {
        if (e.currentTarget.getAttribute('aria-disabled') === 'true') return
        const set = this.setEnCours()
        const h = set.historique
        let i = h.length - 1
        while (i > 0 && h[i - 1].annule) i--
        if (await this.envoyer(set.numero, 'suivant', { uuid: h[i].uuid })) this.annoncer('Saisie rétablie.')
    }

    ouvrirOutil({ params: { mode } }) {
        const set = this.setEnCours()
        if (mode === 'remplacement' && set?.saisie === 'touches' && this.echange(set).length) {
            this.signaler('Notez d\'abord le point de l\'échange en cours.')
            return
        }
        this.mode = mode
        this.sortant = undefined
        this.rendre()
    }

    fermerOutil() {
        this.mode = null
        this.sortant = undefined
        this.rendre()
    }

    async remplacer({ params: { joueur } }) {
        if (await this.evenement('remplacement', { sort: this.sortant ?? null, entre: joueur })) {
            this.mode = null
            this.sortant = undefined
            this.rendre()
        }
    }

    async corriger(e) {
        e.preventDefault()
        const f = new FormData(e.target)
        const donnees = { nous: parseInt(f.get('nous'), 10), eux: parseInt(f.get('eux'), 10), service: f.get('service') }
        if (await this.evenement('correction', donnees)) {
            this.mode = 'replacer'
            this.rendre()
        }
    }

    tournerSet() { return this.evenement('rotation', {}) }

    async terminer() {
        const set = this.setEnCours()
        if (await this.envoyer(set.numero, 'terminer', {})) this.annoncer(`Set ${set.numero} terminé.`)
    }

    rouvrir({ params: { numero } }) { return this.envoyer(numero, 'rouvrir', {}) }

    // --- Échanges avec le serveur --------------------------------------------------------------------------------------

    // File d'attente des saisies (D68) : chaque saisie s'affiche tout de suite (un point compte aussitôt à l'écran) et part
    // au serveur l'une après l'autre, dans l'ordre. Sans réseau, elle est renvoyée seule, avec le même uuid (le serveur
    // ignore un envoi déjà reçu). Une saisie faite pendant l'envoi de la précédente n'est donc jamais perdue.
    envoyer(numero, action, donnees, rafraichir = true) {
        return new Promise((resoudre) => {
            this.file.push({ numero, action, donnees, rafraichir, resoudre })
            if (action === 'evenement' && ['point', 'action', 'rejouer'].includes(donnees.type)) this.projeter()
            this.traiter()
        })
    }

    async traiter() {
        if (this.envoiEnCours || !this.file.length) return
        this.envoiEnCours = true
        const op = this.file[0]
        // L'ordre attendu se lit sur le dernier état reçu du serveur, au moment de l'envoi
        const donnees = op.action === 'evenement' ? { ...op.donnees, dernierOrdre: this.dernierOrdreServeur(op.numero) } : op.donnees
        try {
            const reponse = await fetch(`${this.urlValue}/set/${op.numero}/${op.action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.jetonValue },
                body: JSON.stringify(donnees),
            })
            const resultat = await reponse.json().catch(() => null)
            // Session expirée ou portail du wifi : page de connexion au lieu de la réponse. La file s'arrête SANS se vider,
            // les saisies attendent la reconnexion (relecture sécurité)
            if (reponse.redirected || resultat === null || [401, 403, 404].includes(reponse.status)) {
                const n = this.file.length
                if (resultat?.message && reponse.status === 403) {
                    this.signaler(`${resultat.message} ${n > 1 ? `${n} saisies n'ont pas été enregistrées` : 'La dernière saisie n\'a pas été enregistrée'} : notez-les avant de recharger.`)
                    return
                }
                this.signaler(`Votre session a expiré : reconnectez-vous dans un autre onglet, puis rechargez cette page. ${n > 1 ? `${n} saisies n'ont pas été enregistrées` : 'La dernière saisie n\'a pas été enregistrée'} : notez-les avant de recharger.`)
                return // envoiEnCours reste vrai : plus rien ne part tant que la page n'est pas rechargée
            }
            this.file.shift()
            this.attente = 0
            this.horsLigne = false
            if (resultat.feuille) this.serveur = resultat.feuille
            if (!reponse.ok && reponse.status !== 409 && op.action === 'evenement') {
                // Saisie refusée : les suivantes du même set en dépendent (point qui porte sur elle…) ; elles sont abandonnées
                const abandonnees = this.file.filter((autre) => autre.numero === op.numero)
                this.file = this.file.filter((autre) => autre.numero !== op.numero)
                abandonnees.forEach((autre) => autre.resoudre(false))
                this.signaler((resultat.message ?? 'La saisie n\'a pas été enregistrée.') + (abandonnees.length ? ` ${abandonnees.length + 1} saisies n'ont pas été enregistrées : vérifiez le score.` : ''))
            } else if (reponse.status === 409) {
                // Saisie faite sur un autre téléphone : les saisies en attente, fondées sur l'ancien état, sont abandonnées
                this.file.splice(0).forEach((autre) => autre.resoudre(false))
                this.brouillon = null
                this.signaler((resultat.message ?? '') + ' Vérifiez le score : vos dernières saisies n\'ont peut-être pas été enregistrées.')
            } else if (!reponse.ok) {
                this.signaler(resultat.message ?? 'La saisie n\'a pas été enregistrée : rechargez la page.')
            } else if (!this.file.length) {
                this.signaler('')
            }
            this.projeter(op.rafraichir || op.action !== 'composition' || !reponse.ok)
            op.resoudre(reponse.ok)
        } catch {
            // Réseau coupé : la saisie reste en tête de file et repart toute seule
            this.attente = Math.min((this.attente || 1) * 2, 16)
            this.horsLigne = true
            this.projeter()
            this.signaler(`Connexion perdue : ${this.file.length > 1 ? `${this.file.length} saisies seront renvoyées` : 'la saisie sera renvoyée'} automatiquement.`)
            setTimeout(() => { this.envoiEnCours = false; this.traiter() }, this.attente * 1000)
            return
        }
        this.envoiEnCours = false
        this.traiter()
    }

    // Dernier ordre connu du serveur pour ce set (les saisies en attente n'en ont pas encore)
    dernierOrdreServeur(numero) {
        const set = this.serveur.sets.find((s) => s.numero === numero)
        return set ? this.dernierOrdre(set) : null
    }

    // État affiché = dernier état du serveur + points encore en attente (score et rotation recalculés comme EtatSet)
    projeter(rendre = true) {
        const etat = structuredClone(this.serveur)
        for (const op of this.file) {
            if (op.action !== 'evenement') continue
            const set = etat.sets.find((s) => s.numero === op.numero)
            if (!set) continue
            if (['action', 'rejouer'].includes(op.donnees.type)) {
                set.historique.push({ uuid: op.donnees.uuid, ordre: null, type: op.donnees.type, texte: op.donnees.type === 'rejouer' ? 'Balle à rejouer (envoi…)' : 'Touche (envoi…)', annule: false, score: null, entre: null, donnees: op.donnees.donnees, enAttente: true })
                continue
            }
            if (op.donnees.type !== 'point') continue
            const c = set.calcul
            const pour = op.donnees.donnees.pour
            if (pour === 'nous') {
                c.score[0]++
                if (c.service === 'eux') c.postes = tourner(c.postes)
                c.service = 'nous'
            } else {
                c.score[1]++
                c.service = 'eux'
            }
            c.finAtteinte = Math.max(...c.score) >= set.pointsFin
            set.historique.push({ uuid: op.donnees.uuid, ordre: null, type: 'point', texte: `Point pour ${pour === 'nous' ? etat.nous : etat.eux} (envoi…)`, annule: false, score: [...c.score], entre: null, donnees: op.donnees.donnees, enAttente: true })
        }
        this.etatValue = etat
        if (rendre) this.rendre()
    }

    signaler(message) {
        this.erreurTarget.textContent = message
        this.erreurTarget.hidden = message === ''
    }

    annoncer(message) {
        this.annonceTarget.textContent = message
    }
}
