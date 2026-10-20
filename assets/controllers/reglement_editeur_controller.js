/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'
import { Editor, Extension } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import { Plugin } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'

// Page d'édition du règlement (templates/admin/reglement/edit.html.twig, D55 à D59) : éditeur TipTap, barre d'outils,
// enregistrement (conflit entre deux admins), aperçu, éléments calculés (insérer, copier, voir le résultat), lien, image.

// Code court, comme App\Reglement\RenduReglement::MOTIF
const CODE_COURT = /\[[a-z_]+(?:[  ]+[a-z_]+=[^\s\]<]+)*[  ]*\]/g
const TAILLE_MAX = 2 * 1024 * 1024

// Codes courts reconnus mis en évidence pendant la frappe (sauf ceux mis en forme « code », qui restent écrits tels quels)
const CodesCourts = Extension.create({
    name: 'codesCourts',
    addProseMirrorPlugins() {
        const decorer = (doc) => {
            const decorations = []
            doc.descendants((noeud, position) => {
                if (!noeud.isText || noeud.marks.some((marque) => marque.type.name === 'code')) {
                    return
                }
                for (const trouve of noeud.text.matchAll(CODE_COURT)) {
                    decorations.push(Decoration.inline(position + trouve.index, position + trouve.index + trouve[0].length, { class: 'reglement-code-court' }))
                }
            })
            return DecorationSet.create(doc, decorations)
        }
        return [new Plugin({
            state: {
                init: (_, { doc }) => decorer(doc),
                apply: (transaction, ancien) => (transaction.docChanged ? decorer(transaction.doc) : ancien),
            },
            props: { decorations(state) { return this.getState(state) } },
        })]
    },
})

export default class extends Controller {
    static targets = ['editeur', 'contenu', 'barre', 'etat', 'bouton', 'aucuneModification', 'erreur', 'inapplicables', 'annonce',
        'ongletRediger', 'ongletApercu', 'panneauRediger', 'panneauApercu', 'apercu', 'fichier', 'element',
        'dialogueLien', 'adresse', 'erreurLien', 'retirerLien', 'dialogueImage', 'vignette', 'description', 'decorative', 'erreurImage',
        'dialogueConflit', 'messageConflit']
    static values = { enregistrerUrl: String, apercuUrl: String, imageUrl: String, elementUrl: String, jeton: String, token: String,
        publie: Boolean, code: String }

    connect() {
        this.modifie = false
        this.positionInsertion = null
        this.editor = new Editor({
            element: this.editeurTarget,
            content: this.contenuTarget.innerHTML,
            extensions: [
                StarterKit.configure({ heading: { levels: [2, 3, 4] }, codeBlock: false, strike: false }),
                Link.configure({ openOnClick: false, autolink: false, HTMLAttributes: { rel: null, target: null } }),
                Image,
                CodesCourts,
            ],
            editorProps: {
                attributes: { role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Texte du règlement' },
                handleKeyDown: (_, evenement) => this.raccourci(evenement),
            },
            onUpdate: () => this.marquerModifie(true),
            onSelectionUpdate: () => this.majBarre(),
            onTransaction: () => this.majBarre(),
            onBlur: ({ editor }) => { this.positionInsertion = editor.state.selection.$from },
        })
        this.barreTarget.addEventListener('click', (evenement) => {
            const bouton = evenement.target.closest('[data-commande]')
            if (bouton) {
                this.commande(bouton.dataset.commande)
            }
        })
        this.avantDepart = (evenement) => {
            if (this.modifie) {
                evenement.preventDefault()
                evenement.returnValue = ''
            }
        }
        this.avantVisite = (evenement) => {
            if (this.modifie && !window.confirm('Quitter sans enregistrer ? Vos modifications seront perdues.')) {
                evenement.preventDefault()
            }
        }
        window.addEventListener('beforeunload', this.avantDepart)
        document.addEventListener('turbo:before-visit', this.avantVisite)
        this.majBarre()
        if (this.codeValue) {
            requestAnimationFrame(() => this.trouverCode(this.codeValue))
        }
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.avantDepart)
        document.removeEventListener('turbo:before-visit', this.avantVisite)
        this.editor?.destroy()
    }

    // --- Barre d'outils -------------------------------------------------------------------------------------------

    commande(nom) {
        const chaine = this.editor.chain().focus()
        switch (nom) {
            case 'partie': chaine.toggleHeading({ level: 2 }).run(); break
            case 'regle': chaine.toggleHeading({ level: 3 }).run(); break
            case 'gras': chaine.toggleBold().run(); break
            case 'italique': chaine.toggleItalic().run(); break
            case 'code': chaine.toggleCode().run(); break
            case 'puces': chaine.toggleBulletList().run(); break
            case 'numeros': chaine.toggleOrderedList().run(); break
            case 'lien': this.ouvrirLien(); break
            case 'image': this.fichierTarget.click(); break
        }
    }

    majBarre() {
        if (!this.editor) {
            return
        }
        const actif = {
            partie: this.editor.isActive('heading', { level: 2 }), regle: this.editor.isActive('heading', { level: 3 }),
            gras: this.editor.isActive('bold'), italique: this.editor.isActive('italic'), code: this.editor.isActive('code'),
            puces: this.editor.isActive('bulletList'), numeros: this.editor.isActive('orderedList'), lien: this.editor.isActive('link'),
        }
        this.barreTarget.querySelectorAll('[data-commande]').forEach((bouton) => {
            if (bouton.dataset.commande in actif) {
                bouton.setAttribute('aria-pressed', actif[bouton.dataset.commande] ? 'true' : 'false')
            }
        })
    }

    // Une seule tabulation pour la barre ; flèches, Origine et Fin pour passer d'un bouton à l'autre ; Échap revient au texte
    flecheBarre(evenement) {
        const boutons = [...this.barreTarget.querySelectorAll('button[data-commande]')]
        const index = boutons.indexOf(document.activeElement)
        const cible = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: boutons.length - 1 }[evenement.key]
        if (evenement.key === 'Escape') {
            this.editor.commands.focus()
            return
        }
        if (cible === undefined || index < 0) {
            return
        }
        evenement.preventDefault()
        const suivant = boutons[(cible + boutons.length) % boutons.length]
        boutons.forEach((b) => b.setAttribute('tabindex', b === suivant ? '0' : '-1'))
        suivant.focus()
    }

    raccourci(evenement) {
        const ctrl = evenement.ctrlKey || evenement.metaKey
        if (ctrl && evenement.key === 's') {
            evenement.preventDefault()
            this.enregistrer()
            return true
        }
        if (ctrl && evenement.key === 'k') {
            evenement.preventDefault()
            this.ouvrirLien()
            return true
        }
        return false
    }

    // --- Enregistrement -------------------------------------------------------------------------------------------

    marquerModifie(modifie) {
        this.modifie = modifie
        this.boutonTarget.setAttribute('aria-disabled', modifie ? 'false' : 'true')
        this.aucuneModificationTarget.textContent = modifie ? '' : 'Aucune modification'
        if (modifie) {
            this.etatTarget.textContent = 'Modifications non enregistrées'
        }
    }

    async enregistrer(evenement, forcer = false) {
        if (!this.modifie && !forcer) {
            return
        }
        this.erreurTarget.textContent = ''
        this.etatTarget.textContent = 'Enregistrement…'
        const donnees = new FormData()
        donnees.append('_token', this.tokenValue)
        donnees.append('contenu', this.editor.getHTML())
        donnees.append('jeton', this.jetonValue)
        if (forcer) {
            donnees.append('forcer', '1')
        }
        try {
            const reponse = await fetch(this.enregistrerUrlValue, { method: 'POST', body: donnees, headers: { Accept: 'application/json' } })
            const resultat = await reponse.json()
            if (reponse.status === 409) {
                this.etatTarget.textContent = 'Modifications non enregistrées'
                this.messageConflitTarget.textContent = resultat.message
                this.dialogueConflitTarget.showModal()
                return
            }
            if (!reponse.ok) {
                throw new Error(resultat.message)
            }
            this.jetonValue = resultat.jeton
            this.inapplicablesTarget.innerHTML = resultat.inapplicables
            this.marquerModifie(false)
            this.etatTarget.textContent = (this.publieValue ? 'Mis à jour et publié à ' : 'Enregistré à ') + resultat.heure
        } catch (erreur) {
            this.etatTarget.textContent = 'Modifications non enregistrées'
            this.erreurTarget.textContent = erreur.message && erreur.message !== 'Failed to fetch'
                ? erreur.message
                : 'Échec de l\'enregistrement : vos modifications sont toujours dans l\'éditeur. Réessayez.'
        }
    }

    forcer() {
        this.dialogueConflitTarget.close()
        this.enregistrer(null, true)
    }

    // --- Onglets Rédiger / Aperçu -----------------------------------------------------------------------------------

    rediger() {
        this.choisirOnglet(false)
        this.editor.commands.focus()
    }

    async apercu() {
        this.choisirOnglet(true)
        this.apercuTarget.textContent = 'Préparation de l\'aperçu…'
        const donnees = new FormData()
        donnees.append('_token', this.tokenValue)
        donnees.append('contenu', this.editor.getHTML())
        try {
            const reponse = await fetch(this.apercuUrlValue, { method: 'POST', body: donnees, headers: { Accept: 'application/json' } })
            const resultat = await reponse.json()
            if (!reponse.ok) {
                throw new Error(resultat.message)
            }
            this.apercuTarget.innerHTML = resultat.html
            this.inapplicablesTarget.innerHTML = resultat.inapplicables
        } catch (erreur) {
            this.apercuTarget.textContent = erreur.message || 'L\'aperçu n\'a pas pu être préparé, réessayez.'
        }
    }

    choisirOnglet(apercu) {
        this.ongletRedigerTarget.setAttribute('aria-selected', apercu ? 'false' : 'true')
        this.ongletApercuTarget.setAttribute('aria-selected', apercu ? 'true' : 'false')
        this.ongletRedigerTarget.classList.toggle('tab-active', !apercu)
        this.ongletApercuTarget.classList.toggle('tab-active', apercu)
        this.ongletRedigerTarget.tabIndex = apercu ? -1 : 0
        this.ongletApercuTarget.tabIndex = apercu ? 0 : -1
        this.panneauRedigerTarget.classList.toggle('hidden', apercu)
        this.panneauApercuTarget.classList.toggle('hidden', !apercu)
    }

    flecheOnglet(evenement) {
        if (evenement.key !== 'ArrowRight' && evenement.key !== 'ArrowLeft') {
            return
        }
        evenement.preventDefault()
        const versApercu = evenement.currentTarget === this.ongletRedigerTarget
        const cible = versApercu ? this.ongletApercuTarget : this.ongletRedigerTarget
        cible.focus()
        versApercu ? this.apercu() : this.rediger()
    }

    // --- Éléments calculés -----------------------------------------------------------------------------------------

    codeDe(element) {
        const variante = element.querySelector('select')?.value
        return '[' + element.dataset.code + (variante ? ' phase=' + variante : '') + ']'
    }

    choisirVariante(evenement) {
        const element = evenement.target.closest('[data-code]')
        element.querySelector('[data-role="code"]').textContent = this.codeDe(element)
        const resultat = element.querySelector('details[data-action*="voirResultat"]')
        if (resultat?.open) {
            this.chargerResultat(element)
        }
    }

    // Le code seul dans un nouveau paragraphe, après le bloc du curseur (un élément calculé est un bloc), sinon à la fin
    inserer(evenement) {
        const code = this.codeDe(evenement.target.closest('[data-code]'))
        // Position du curseur à la dernière sortie de l'éditeur ; jamais entré dans l'éditeur : à la fin du texte
        const position = this.editor.isFocused ? this.editor.state.selection.$from : this.positionInsertion
        const apres = position && position.depth > 0 ? position.after(1) : this.editor.state.doc.content.size
        this.editor.chain().insertContentAt(apres, { type: 'paragraph', content: [{ type: 'text', text: code }] })
            .setTextSelection(apres + 1 + code.length).scrollIntoView().focus().run()
        this.annonceTarget.textContent = 'Code ' + code + ' inséré.'
    }

    async copier(evenement) {
        const element = evenement.target.closest('[data-code]')
        const libelle = element.querySelector('[data-role="libelle-copier"]')
        const code = this.codeDe(element)
        try {
            await navigator.clipboard.writeText(code)
            libelle.textContent = 'Copié'
            this.annonceTarget.textContent = 'Code ' + code + ' copié.'
        } catch {
            const selection = window.getSelection()
            const plage = document.createRange()
            plage.selectNodeContents(element.querySelector('[data-role="code"]'))
            selection.removeAllRanges()
            selection.addRange(plage)
            libelle.textContent = 'Sélectionné : Ctrl+C'
        }
        setTimeout(() => { libelle.textContent = 'Copier' }, 2000)
    }

    voirResultat(evenement) {
        if (evenement.target.open) {
            this.chargerResultat(evenement.target.closest('[data-code]'))
        }
    }

    async chargerResultat(element) {
        const zone = element.querySelector('[data-role="resultat"]')
        const variante = element.querySelector('select')?.value
        zone.textContent = 'Chargement…'
        try {
            const url = this.elementUrlValue.replace('CODE', element.dataset.code) + (variante ? '?phase=' + encodeURIComponent(variante) : '')
            const reponse = await fetch(url)
            if (!reponse.ok) {
                throw new Error()
            }
            zone.innerHTML = await reponse.text()
        } catch {
            zone.textContent = 'Le résultat n\'a pas pu être chargé, réessayez.'
        }
    }

    // --- Codes inapplicables : aller au code --------------------------------------------------------------------------

    allerAuCode(evenement) {
        this.choisirOnglet(false)
        this.trouverCode(evenement.params.code)
    }

    trouverCode(code) {
        let trouve = null
        this.editor.state.doc.descendants((noeud, position) => {
            if (trouve === null && noeud.isText) {
                const index = noeud.text.replace(/ /g, ' ').indexOf(code)
                if (index >= 0) {
                    trouve = position + index
                }
            }
        })
        if (trouve === null) {
            this.annonceTarget.textContent = 'Ce code n\'est plus dans le texte ; enregistrez pour mettre la liste à jour.'
            this.erreurTarget.textContent = this.annonceTarget.textContent
            return
        }
        // Défiler d'abord, puis donner le focus (sur téléphone, le clavier s'ouvre)
        this.editor.commands.setTextSelection({ from: trouve, to: trouve + code.length })
        this.editor.view.domAtPos(trouve).node.parentElement?.scrollIntoView({ block: 'center' })
        setTimeout(() => this.editor.commands.focus(), 300)
    }

    // --- Lien -------------------------------------------------------------------------------------------------------

    ouvrirLien() {
        const actuel = this.editor.getAttributes('link').href ?? ''
        this.adresseTarget.value = actuel
        this.erreurLienTarget.textContent = ''
        this.retirerLienTarget.classList.toggle('hidden', actuel === '')
        this.dialogueLienTarget.showModal()
        this.adresseTarget.focus()
    }

    // https://, mailto:, #ancre, chemin du site ; « contact@… » → mailto:, « www.… » → https://
    validerLien(evenement) {
        evenement.preventDefault()
        let adresse = this.adresseTarget.value.trim()
        if (/^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test(adresse)) {
            adresse = 'mailto:' + adresse
        } else if (/^www\.[^\s]+$/i.test(adresse)) {
            adresse = 'https://' + adresse
        }
        if (!/^(https?:\/\/[^\s]+|mailto:[^\s]+|#[A-Za-z0-9_-]+|\/(?!\/)[^\s]*)$/i.test(adresse)) {
            this.erreurLienTarget.textContent = 'Adresse non reconnue. Exemples : https://www.ffvolley.org, contact@blvb.fr ou #regle-1r.'
            return
        }
        this.dialogueLienTarget.close()
        this.editor.chain().focus().extendMarkRange('link').setLink({ href: adresse }).run()
    }

    retirerLien() {
        this.dialogueLienTarget.close()
        this.editor.chain().focus().extendMarkRange('link').unsetLink().run()
    }

    // --- Image ------------------------------------------------------------------------------------------------------

    async envoyerImage() {
        const fichier = this.fichierTarget.files[0]
        this.fichierTarget.value = ''
        if (!fichier) {
            return
        }
        this.erreurTarget.textContent = ''
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(fichier.type)) {
            this.erreurTarget.textContent = 'Format non accepté : JPG, PNG ou WebP.'
            return
        }
        if (fichier.size > TAILLE_MAX) {
            this.erreurTarget.textContent = 'L\'image pèse ' + (fichier.size / 1024 / 1024).toFixed(1).replace('.', ',') + ' Mo, le maximum est 2 Mo. Réduisez-la avant de l\'envoyer.'
            return
        }
        this.etatTarget.textContent = 'Envoi de l\'image…'
        const donnees = new FormData()
        donnees.append('_token', this.tokenValue)
        donnees.append('image', fichier)
        try {
            const reponse = await fetch(this.imageUrlValue, { method: 'POST', body: donnees, headers: { Accept: 'application/json' } })
            const resultat = await reponse.json()
            if (!reponse.ok) {
                throw new Error(resultat.message)
            }
            this.imageEnvoyee = resultat.url
            this.vignetteTarget.src = resultat.url
            this.descriptionTarget.value = ''
            this.decorativeTarget.checked = false
            this.erreurImageTarget.textContent = ''
            this.etatTarget.textContent = this.modifie ? 'Modifications non enregistrées' : this.etatTarget.textContent
            this.dialogueImageTarget.showModal()
            this.descriptionTarget.focus()
        } catch (erreur) {
            this.etatTarget.textContent = this.modifie ? 'Modifications non enregistrées' : ''
            this.erreurTarget.textContent = erreur.message || 'L\'envoi a échoué, réessayez.'
        }
    }

    validerImage(evenement) {
        evenement.preventDefault()
        const description = this.descriptionTarget.value.trim()
        if (description === '' && !this.decorativeTarget.checked) {
            this.erreurImageTarget.textContent = 'Décrivez l\'image, ou cochez « Image décorative ».'
            this.descriptionTarget.focus()
            return
        }
        this.dialogueImageTarget.close()
        this.editor.chain().focus().setImage({ src: this.imageEnvoyee, alt: this.decorativeTarget.checked ? '' : description }).run()
    }

    fermerDialogue(evenement) {
        evenement.target.closest('dialog').close()
        this.editor.commands.focus()
    }
}
