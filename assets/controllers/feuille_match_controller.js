/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Page du match d'une équipe (Volley Stats, D67 ; maquette docs/volley-stats/maquette-page-match.md) : composition de chaque
// set sur un terrain, set en cours (points, remplacements, correction du score, « Précédent » et « Suivant »), historique et
// relevé des sets. L'état vient du serveur (App\VolleyStats\VueFeuille) et y retourne à chaque saisie : rien n'est gardé
// dans le navigateur, la page se reprend sur un autre téléphone.

const LIBELLES = { 1: 'Arrière droit', 2: 'Avant droit', 3: 'Avant centre', 4: 'Avant gauche', 5: 'Arrière gauche', 6: 'Arrière centre' }
const ORDRE_VISUEL = [4, 3, 2, 5, 6, 1] // filet en haut : avant puis arrière, de gauche à droite
const DEPARTS = [[0, 0], [0, 8], [8, 0]]

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
        const h = set.historique
        return h.length ? h[h.length - 1].ordre : null
    }

    // --- Rendu --------------------------------------------------------------------------------------------------------

    rendre() {
        const morceaux = []
        const enCours = this.feuille.sets.find((s) => s.etat === 'en_cours')
        const aComposer = enCours ? null : this.setACompose()
        if (enCours) {
            morceaux.push(this.vueSetEnCours(enCours))
        } else if (aComposer && this.modifiableValue) {
            if (!this.brouillon || this.brouillon.numero !== aComposer.numero) {
                this.brouillon = { numero: aComposer.numero, postes: [...aComposer.postes], premierService: aComposer.premierService, scoreDepart: [...aComposer.scoreDepart], pointsFin: aComposer.pointsFin, active: null }
                this.brouillon.active = this.premiereVide()
            }
            morceaux.push(this.vueComposition())
        } else if (this.matchGagne()) {
            const [nous, eux] = this.feuille.setsGagnes
            morceaux.push(`<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4"><h2 class="text-xl uppercase tracking-wide">Match terminé</h2><p class="mt-1">${echapper(this.feuille.nous)} ${nous} – ${eux} ${echapper(this.feuille.eux)}, d'après les sets notés ici.</p></section>`)
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
        const consigne = b.active ? `Joueur pour la place ${b.active} · ${LIBELLES[b.active]} :` : (banc.length ? 'Sur le banc (touchez une place pour la changer) :' : 'Tous les joueurs présents sont placés.')
        const radio = (nom, valeur, coche, libelle) => `<label class="choix-carte choix-ligne"><input type="radio" class="radio radio-primary" name="${nom}-${b.numero}" ${coche ? 'checked' : ''} data-action="feuille-match#choisir" data-feuille-match-champ-param="${nom}" data-feuille-match-valeur-param="${valeur}"> <span class="${nom === 'depart' ? 'font-display text-lg font-bold' : 'font-medium'}">${libelle}</span></label>`
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-compo">
            <div><h2 id="titre-compo" class="text-xl uppercase tracking-wide">Composition du set ${b.numero}</h2>
            <p class="text-sm">${b.numero > 1 ? `Reprise du départ du set ${b.numero - 1}. ` : ''}Touchez une place, puis un joueur.${b.pointsFin === 15 ? ' Set en 15 points.' : ''}</p></div>
            ${this.terrain(b.postes, { actif: b.active, cliquable: true, service: b.premierService, etiquette: `Composition du set ${b.numero}` })}
            <div><p class="font-semibold">${consigne}</p><div class="mt-2 flex flex-wrap gap-2">${banc.map((j) => `<button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#placer" data-feuille-match-joueur-param="${j.id}"${b.active ? '' : ' aria-disabled="true"'}>${echapper(j.nom)}</button>`).join('')}</div></div>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#tournerBrouillon">Tourner d'un poste</button>
                <button type="button" class="btn btn-ghost btn-danger-discret" data-action="feuille-match#effacer">Tout effacer</button>
            </div>
            <fieldset><legend class="font-semibold">Score de départ <span class="font-normal text-sm">(nous – eux)</span></legend>
                <div class="mt-2 grid grid-cols-3 gap-2">${DEPARTS.map((d, i) => radio('depart', i, d[0] === b.scoreDepart[0] && d[1] === b.scoreDepart[1], `${d[0]}–${d[1]}`)).join('')}</div></fieldset>
            <fieldset><legend class="font-semibold">Premier service <span class="font-normal text-sm">(tiré au gymnase)</span></legend>
                <div class="mt-2 grid grid-cols-2 gap-2">${radio('service', 'nous', b.premierService === 'nous', 'Nous servons')}${radio('service', 'eux', b.premierService === 'eux', 'Nous recevons')}</div></fieldset>
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
            outils = `<fieldset><legend class="font-semibold">${this.sortant === undefined ? 'Touchez le joueur qui sort (ou la place libre).' : `Remplacer ${echapper(this.nom(this.sortant))} par…`}</legend>
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
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#ouvrirOutil" data-feuille-match-mode-param="remplacement">Remplacer un joueur</button>
                <button type="button" class="btn btn-outline btn-primary" data-action="feuille-match#ouvrirOutil" data-feuille-match-mode-param="correction">Corriger le score</button></div>`
        }
        const fin = m && c.finAtteinte
            ? `<div role="status" class="alert alert-info alert-soft flex flex-col items-stretch gap-2"><p><strong>Fin du set : ${c.score[0]}–${c.score[1]}.</strong> Un point mal noté ? Touchez « Précédent ».</p><button type="button" class="btn btn-accent" data-action="feuille-match#terminer">Terminer le set ${set.numero}</button></div>`
            : ''
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-set-cours">
            <div class="flex flex-wrap items-baseline justify-between gap-2"><h2 id="titre-set-cours" class="text-xl uppercase tracking-wide">Set ${set.numero} en cours</h2>
            <p class="text-sm">en ${set.pointsFin} points · parti de ${set.scoreDepart[0]}–${set.scoreDepart[1]}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wider text-(--color-base-content)/70">Score du set, pour l'équipe seulement</p>
                <div class="mt-2 grid grid-cols-2 gap-2 text-center">
                    ${[0, 1].map((i) => `<div class="flex flex-col gap-2 min-w-0"><p class="font-display font-semibold uppercase tracking-wide wrap-anywhere">${echapper(i ? this.feuille.eux : this.feuille.nous)}</p><p class="score-set">${c.score[i]}</p>
                    ${m ? `<button type="button" class="btn btn-primary" data-action="feuille-match#point" data-feuille-match-pour-param="${i ? 'eux' : 'nous'}" aria-label="Point pour ${echapper(i ? this.feuille.eux : this.feuille.nous)}">+1 point</button>` : ''}</div>`).join('')}
                </div>
                ${m ? `<div class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn btn-ghost" data-action="feuille-match#precedent" ${actifs.length ? '' : 'aria-disabled="true"'}>← Précédent</button><button type="button" class="btn btn-ghost" data-action="feuille-match#suivant" ${peutSuivant ? '' : 'aria-disabled="true"'}>Suivant →</button></div>` : ''}
            </div>
            ${fin}
            ${this.terrain(c.postes, { cliquable: m && this.mode === 'remplacement', service: c.service, etiquette: `Set ${set.numero} sur le terrain`, sortant: this.mode === 'remplacement' ? this.sortant : undefined })}
            <p class="text-sm">${c.service === 'eux' ? 'Service adverse · ' : ''}Sur le banc : ${banc.length ? banc.map((j) => echapper(j.nom)).join(', ') : 'personne'}</p>
            ${outils}
            ${this.vueHistorique(set)}
        </section>`
    }

    vueHistorique(set) {
        if (!set.historique.length) return ''
        const lignes = [...set.historique].reverse().map((h) => `<li class="flex justify-between gap-3 py-1${h.annule ? ' line-through opacity-60' : ''}"><span>${echapper(h.texte)}${h.annule ? '<span class="sr-only"> (annulé)</span>' : ''}</span><span class="tabular-nums">${h.score ? `${h.score[0]}–${h.score[1]}` : ''}</span></li>`).join('')
        return `<details class="border-t border-base-300 pt-2"><summary class="cursor-pointer font-semibold min-h-12 flex items-center">Historique du set (${set.historique.length})</summary><ol class="divide-y divide-(--color-base-300) text-sm">${lignes}</ol></details>`
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
        return `<section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-4" aria-labelledby="titre-releve"><h2 id="titre-releve" class="text-xl uppercase tracking-wide">Relevé des sets</h2>${cartes}${this.vueSetsJoues()}</section>
            <section class="bg-base-100 border border-base-300 rounded-box p-3 md:p-4 flex flex-col gap-2" aria-labelledby="titre-officiel"><h2 id="titre-officiel" class="text-xl uppercase tracking-wide">Score officiel</h2>
            <p class="text-sm">Il se saisit à part, dans la fenêtre habituelle du site ; les scores ci-dessus ne le remplissent pas.</p><a class="btn btn-accent btn-block" href="${echapper(this.scoreOfficielValue)}">Saisir le score officiel</a></section>`
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
        else this.brouillon.premierService = valeur
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
            if (await this.envoyer(b.numero, 'commencer', { aCinq })) this.annoncer(`Set ${b.numero} commencé.`)
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

    async envoyer(numero, action, donnees, rafraichir = true) {
        if (this.envoiEnCours && action === 'evenement') return false
        this.envoiEnCours = true
        this.signaler('')
        try {
            const reponse = await fetch(`${this.urlValue}/set/${numero}/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.jetonValue },
                body: JSON.stringify(donnees),
            })
            const resultat = await reponse.json().catch(() => ({ message: 'Réponse inattendue : rechargez la page.' }))
            if (resultat.feuille) this.etatValue = resultat.feuille
            if (!reponse.ok) {
                if (reponse.status === 409) this.brouillon = null
                this.signaler(resultat.message ?? 'La saisie n\'a pas été enregistrée : rechargez la page.')
                this.rendre()
                return false
            }
            if (rafraichir || action !== 'composition') this.rendre()
            return true
        } catch {
            this.signaler('La connexion au site a échoué : vérifiez le réseau, puis recommencez.')
            return false
        } finally {
            this.envoiEnCours = false
        }
    }

    signaler(message) {
        this.erreurTarget.textContent = message
        this.erreurTarget.hidden = message === ''
    }

    annoncer(message) {
        this.annonceTarget.textContent = message
    }
}
