/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'
import { Editor, Extension, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Underline from '@tiptap/extension-underline'
import Highlight from '@tiptap/extension-highlight'
import Subscript from '@tiptap/extension-subscript'
import Superscript from '@tiptap/extension-superscript'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableHeader from '@tiptap/extension-table-header'
import TableCell from '@tiptap/extension-table-cell'
import { Plugin } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'

// Page d'édition du règlement (templates/admin/reglement/edit.html.twig, D55 à D59) : éditeur TipTap, barre d'outils,
// enregistrement (conflit entre deux admins), aperçu, éléments calculés (insérer, copier, voir le résultat), lien, image.

// Code court, comme App\Reglement\RenduReglement::MOTIF
const CODE_COURT = /\[[a-z_]+(?:[  ]+[a-z_]+=[^\s\]<]+)*[  ]*\]/g
const TAILLE_MAX = 2 * 1024 * 1024
const SESSION_EXPIREE = 'Votre session a peut-être expiré : reconnectez-vous dans un autre onglet, puis réessayez.'

// Réponse JSON du serveur ; une page HTML (session expirée : page de connexion) ou une coupure réseau donnent un
// message en français, jamais « Failed to fetch » ni une erreur d'analyse
class ErreurServeur extends Error {}

async function envoyer(url, donnees) {
    let reponse
    try {
        reponse = await fetch(url, { method: 'POST', body: donnees, headers: { Accept: 'application/json' } })
    } catch {
        throw new ErreurServeur('La connexion au site a échoué : vérifiez votre accès à Internet, puis réessayez.')
    }
    if (reponse.redirected || !(reponse.headers.get('Content-Type') ?? '').includes('application/json')) {
        throw new ErreurServeur(SESSION_EXPIREE)
    }
    const resultat = await reponse.json()
    if (!reponse.ok && reponse.status !== 409) {
        throw new ErreurServeur(resultat.message || SESSION_EXPIREE)
    }
    return { reponse, resultat }
}

// HTML de l'éditeur (une seule ligne) présenté un bloc par ligne pour l'onglet HTML
function formaterHtml(html) {
    return html
        // fin d'un bloc, ouverture d'une liste ou d'un tableau, ligne de tableau, image (seule ou dans son lien)
        .replace(/(<\/(?:p|h[2-4]|li|ul|ol|blockquote|tr|tbody|table)>|<(?:ul|ol|table|tbody|tr)(?:\s[^>]*)?>|<hr[^>]*>|<img[^>]*><\/a>|<img[^>]*>(?!<\/a>))/g, '$1\n')
        .replace(/\n+/g, '\n')
        .trim()
}

// Balises et attributs que l'éditeur sait garder ; le reste disparaît quand le code de l'onglet HTML repasse dans l'éditeur
const BALISES_GARDEES = new Set(['H2', 'H3', 'H4', 'P', 'BR', 'HR', 'STRONG', 'B', 'EM', 'I', 'U', 'MARK', 'SUB', 'SUP', 'CODE',
    'BLOCKQUOTE', 'UL', 'OL', 'LI', 'A', 'IMG', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'COLGROUP', 'COL'])
const ATTRIBUTS_GARDES = new Set(['href', 'src', 'alt', 'title', 'start', 'class', 'colspan', 'rowspan'])
// Largeurs que l'éditeur écrit lui-même sur un tableau (retirées par le nettoyage, sans conséquence)
const STYLE_DE_TABLEAU = new Set(['TABLE', 'COLGROUP', 'COL'])

function contientNonPrisEnCharge(html) {
    // Document inerte (DOMParser) : aucun script exécuté, aucune image chargée
    const corps = new DOMParser().parseFromString(html, 'text/html').body
    return [...corps.querySelectorAll('*')].some((element) => !BALISES_GARDEES.has(element.tagName)
        || [...element.attributes].some((attribut) => !ATTRIBUTS_GARDES.has(attribut.name)
            && !(attribut.name === 'style' && STYLE_DE_TABLEAU.has(element.tagName))))
}

// Image cliquable : une marque de lien ne s'applique pas à un nœud image ; le lien est donc un attribut de l'image,
// écrit <a href="…"><img …></a> (forme gardée par le nettoyage côté serveur) et relu depuis le <a> parent
// Comme le nettoyage serveur : images du site seulement (adresse relative, pas d'image externe chargée dans la session de
// l'admin depuis un code collé ou saisi dans l'onglet HTML) ; lien http(s), mailto, ancre ou chemin du site
const ADRESSE_IMAGE = /^\/(?!\/)/
const ADRESSE_LIEN = /^(https?:\/\/|mailto:|#|\/(?!\/))/i
const ImageLien = Image.extend({
    parseHTML() {
        return [{ tag: 'img[src]', getAttrs: (element) => (ADRESSE_IMAGE.test(element.getAttribute('src') ?? '') ? null : false) }]
    },
    addAttributes() {
        return {
            ...this.parent?.(),
            href: {
                default: null,
                parseHTML: (element) => {
                    const href = element.closest('a')?.getAttribute('href') ?? ''
                    return ADRESSE_LIEN.test(href) ? href : null
                },
                renderHTML: () => ({}),
            },
        }
    },
    renderHTML({ node, HTMLAttributes }) {
        const image = ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]
        return node.attrs.href ? ['a', { href: node.attrs.href }, image] : image
    },
})

// Alignement d'un paragraphe, d'un titre (centre, droite, justifié) ou d'une image (centre, droite) : classes
// « aligne-… », les seules gardées par le nettoyage côté serveur (App\Reglement\ClasseAlignement) ; pas de style en
// ligne (l'extension TextAlign de TipTap en écrit, le nettoyage les retire). Sans classe : aligné à gauche.
const ALIGNEMENTS = { centre: 'aligne-centre', droite: 'aligne-droite', justifie: 'aligne-justifie' }
const attributAlignement = (permis) => ({
    alignement: {
        default: null,
        parseHTML: (element) => permis.find((nom) => element.classList.contains(ALIGNEMENTS[nom])) ?? null,
        renderHTML: (attributs) => (permis.includes(attributs.alignement) ? { class: ALIGNEMENTS[attributs.alignement] } : {}),
    },
})
const Alignement = Extension.create({
    name: 'alignement',
    addGlobalAttributes() {
        return [
            { types: ['paragraph', 'heading'], attributes: attributAlignement(['centre', 'droite', 'justifie']) },
            { types: ['image'], attributes: attributAlignement(['centre', 'droite']) },
        ]
    },
})

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
    static targets = ['editeur', 'contenu', 'barre', 'barreTableau', 'alignement', 'etat', 'bouton', 'aucuneModification', 'erreur', 'inapplicables', 'annonce',
        'ongletRediger', 'ongletApercu', 'ongletHtml', 'panneauRediger', 'panneauApercu', 'panneauHtml', 'apercu', 'source', 'fichier', 'element',
        'dialogueLien', 'adresse', 'erreurLien', 'retirerLien', 'dialogueImage', 'vignette', 'description', 'decorative', 'erreurImage',
        'dialogueConflit', 'messageConflit']
    static values = { enregistrerUrl: String, apercuUrl: String, imageUrl: String, elementUrl: String, jeton: String, token: String,
        publie: Boolean, code: String }

    connect() {
        this.modifie = false
        this.positionInsertion = null
        this.onglet = 'rediger'
        this.editor = new Editor({
            element: this.editeurTarget,
            content: this.contenuTarget.innerHTML,
            extensions: [
                StarterKit.configure({ heading: { levels: [2, 3, 4] }, codeBlock: false, strike: false }),
                Link.configure({ openOnClick: false, autolink: false, HTMLAttributes: { rel: null, target: null } }),
                ImageLien,
                Alignement,
                Underline,
                Highlight,
                Subscript,
                Superscript,
                Table.configure({ resizable: false }),
                TableRow,
                TableHeader,
                TableCell,
                CodesCourts,
            ],
            editorProps: {
                attributes: { role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Texte du règlement' },
                handleKeyDown: (_, evenement) => this.raccourci(evenement),
                // Dans l'éditeur, un lien (texte ou image) ne s'ouvre pas : un clic sélectionne, pour modifier ou centrer
                handleDOMEvents: {
                    click: (_, evenement) => {
                        if (evenement.target.closest('a')) {
                            evenement.preventDefault()
                        }
                        return false
                    },
                },
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
            case 'souligne': chaine.toggleUnderline().run(); break
            case 'surligne': chaine.toggleHighlight().run(); break
            case 'exposant': chaine.toggleSuperscript().run(); break
            case 'indice': chaine.toggleSubscript().run(); break
            case 'code': chaine.toggleCode().run(); break
            case 'puces': chaine.toggleBulletList().run(); break
            case 'numeros': chaine.toggleOrderedList().run(); break
            case 'citation': chaine.toggleBlockquote().run(); break
            case 'separation': chaine.setHorizontalRule().run(); break
            case 'saut': chaine.setHardBreak().run(); break
            case 'gauche': this.aligner(null); break
            case 'centrer': this.aligner('centre'); break
            case 'droite': this.aligner('droite'); break
            case 'justifier': this.aligner('justifie'); break
            case 'tableau': chaine.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(); break
            case 'ligne-ajouter': chaine.addRowAfter().run(); break
            case 'colonne-ajouter': chaine.addColumnAfter().run(); break
            case 'ligne-supprimer': chaine.deleteRow().run(); break
            case 'colonne-supprimer': chaine.deleteColumn().run(); break
            case 'entete': chaine.toggleHeaderRow().run(); break
            case 'tableau-supprimer':
                chaine.deleteTable().run()
                this.annonceTarget.textContent = 'Tableau supprimé. Annuler pour le récupérer.'
                break
            case 'annuler': chaine.undo().run(); break
            case 'retablir': chaine.redo().run(); break
            case 'lien': this.ouvrirLien(); break
            case 'image': this.fichierTarget.click(); break
        }
    }

    // Aligne l'image sélectionnée (centre ou droite), sinon les paragraphes et titres de la sélection ; un second clic
    // sur l'alignement en cours revient à gauche
    aligner(valeur) {
        const nouvelle = valeur !== null && this.alignementActuel() === valeur ? null : valeur
        const chaine = this.editor.chain().focus()
        if (this.imageChoisie()) {
            if (nouvelle !== 'justifie') {
                chaine.updateAttributes('image', { alignement: nouvelle }).run()
            }
            return
        }
        chaine.updateAttributes('paragraph', { alignement: nouvelle }).updateAttributes('heading', { alignement: nouvelle }).run()
    }

    alignementActuel() {
        if (this.imageChoisie()) {
            return this.editor.getAttributes('image').alignement ?? null
        }
        return Object.keys(ALIGNEMENTS).find((valeur) => this.editor.isActive({ alignement: valeur })) ?? null
    }

    majBarre() {
        if (!this.editor) {
            return
        }
        const e = this.editor
        const alignement = this.alignementActuel()
        const actif = {
            partie: e.isActive('heading', { level: 2 }), regle: e.isActive('heading', { level: 3 }),
            gras: e.isActive('bold'), italique: e.isActive('italic'), souligne: e.isActive('underline'), surligne: e.isActive('highlight'),
            exposant: e.isActive('superscript'), indice: e.isActive('subscript'), code: e.isActive('code'),
            puces: e.isActive('bulletList'), numeros: e.isActive('orderedList'), citation: e.isActive('blockquote'),
            gauche: alignement === null, centrer: alignement === 'centre', droite: alignement === 'droite', justifier: alignement === 'justifie',
            entete: e.isActive('tableHeader'),
            lien: this.imageChoisie() ? Boolean(e.getAttributes('image').href) : e.isActive('link'),
        }
        this.barreTarget.querySelectorAll('[data-commande]').forEach((bouton) => {
            if (bouton.dataset.commande in actif) {
                bouton.setAttribute('aria-pressed', actif[bouton.dataset.commande] ? 'true' : 'false')
            }
        })
        // Bouton du menu d'alignement : icône et nom de l'alignement en cours
        // (seulement s'il change : remplacer l'icône pendant le clic qui ouvre le menu la sortirait du document, et le menu
        // se refermerait aussitôt, croyant à un clic à l'extérieur)
        const commandeAlignement = { centre: 'centrer', droite: 'droite', justifie: 'justifier' }[alignement] ?? 'gauche'
        if (this.alignementTarget.dataset.affiche !== commandeAlignement) {
            this.alignementTarget.dataset.affiche = commandeAlignement
            const entree = this.barreTarget.querySelector(`[data-icone="${commandeAlignement}"]`)
            this.alignementTarget.querySelector('[data-role="icone-alignement"]').innerHTML = entree.querySelector('svg').outerHTML
            this.alignementTarget.querySelector('[data-role="libelle-alignement"]').textContent = 'Alignement : ' + entree.dataset.libelle
        }
        // Justifier n'a pas de sens pour une image ; les actions de tableau n'apparaissent que dans un tableau
        this.barreTarget.querySelector('[data-commande="justifier"]').disabled = this.imageChoisie()
        this.barreTarget.querySelector('[data-commande="annuler"]').disabled = !e.can().undo()
        this.barreTarget.querySelector('[data-commande="retablir"]').disabled = !e.can().redo()
        this.barreTableauTarget.hidden = !e.isActive('table')
    }

    // Une seule tabulation pour la barre ; flèches, Origine et Fin pour passer d'un bouton à l'autre (boutons visibles et
    // actifs) ; Échap revient au texte
    flecheBarre(evenement) {
        // Le menu d'alignement compte pour un bouton (son summary) ; ses entrées se parcourent au clavier dans le menu ouvert
        if (evenement.target.closest('.dropdown-content')) {
            return
        }
        const boutons = [...this.barreTarget.querySelectorAll('button[data-commande], summary')]
            .filter((b) => b.offsetParent !== null && !b.disabled && !b.closest('.dropdown-content'))
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
        // Centrer : Ctrl+Maj+E (les autres alignements n'ont pas de raccourci : Ctrl+Maj+R, J… sont pris par les navigateurs)
        if (ctrl && evenement.shiftKey && evenement.key.toLowerCase() === 'e') {
            evenement.preventDefault()
            this.aligner('centre')
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
        // Depuis l'onglet HTML : le code saisi passe d'abord dans l'éditeur
        this.appliquerSource()
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
            const { reponse, resultat } = await envoyer(this.enregistrerUrlValue, donnees)
            if (reponse.status === 409) {
                this.etatTarget.textContent = 'Modifications non enregistrées'
                this.messageConflitTarget.textContent = resultat.message
                this.dialogueConflitTarget.showModal()
                return
            }
            this.jetonValue = resultat.jeton
            this.inapplicablesTarget.innerHTML = resultat.inapplicables
            this.marquerModifie(false)
            this.etatTarget.textContent = (this.publieValue ? 'Publié · mis à jour à ' : 'Enregistré à ') + resultat.heure
        } catch (erreur) {
            this.etatTarget.textContent = 'Modifications non enregistrées'
            this.erreurTarget.textContent = erreur instanceof ErreurServeur
                ? erreur.message
                : 'Échec de l\'enregistrement : vos modifications sont toujours dans l\'éditeur. Réessayez.'
        }
    }

    forcer() {
        this.dialogueConflitTarget.close()
        this.enregistrer(null, true)
    }

    // --- Onglets Rédiger / Aperçu / HTML ----------------------------------------------------------------------------

    rediger() {
        this.appliquerSource()
        this.choisirOnglet('rediger')
        this.editor.commands.focus()
    }

    // Code HTML du texte, modifiable ; il repasse dans l'éditeur en quittant l'onglet (et sera nettoyé à l'enregistrement)
    html() {
        if (this.onglet !== 'html') {
            this.sourceAffichee = formaterHtml(this.editor.getHTML())
            this.sourceTarget.value = this.sourceAffichee
        }
        this.choisirOnglet('html')
    }

    sourceModifiee() {
        this.marquerModifie(true)
    }

    // L'éditeur ne garde que ce qu'il sait afficher (titres, paragraphes, listes, mise en forme, liens, images)
    appliquerSource() {
        if (this.onglet !== 'html' || this.sourceTarget.value === this.sourceAffichee) {
            return
        }
        if (contientNonPrisEnCharge(this.sourceTarget.value)) {
            this.annonceTarget.textContent = 'Des éléments non pris en charge (styles, scripts…) ont été retirés.'
        }
        this.editor.commands.setContent(this.sourceTarget.value, true)
        this.sourceAffichee = this.sourceTarget.value
    }

    async apercu() {
        this.appliquerSource()
        this.choisirOnglet('apercu')
        this.apercuTarget.textContent = 'Préparation de l\'aperçu…'
        const donnees = new FormData()
        donnees.append('_token', this.tokenValue)
        donnees.append('contenu', this.editor.getHTML())
        try {
            const { resultat } = await envoyer(this.apercuUrlValue, donnees)
            this.apercuTarget.innerHTML = resultat.html
            this.inapplicablesTarget.innerHTML = resultat.inapplicables
        } catch (erreur) {
            this.apercuTarget.textContent = erreur instanceof ErreurServeur ? erreur.message : 'L\'aperçu n\'a pas pu être préparé, réessayez.'
        }
    }

    onglets() {
        return [
            ['rediger', this.ongletRedigerTarget, this.panneauRedigerTarget],
            ['apercu', this.ongletApercuTarget, this.panneauApercuTarget],
            ['html', this.ongletHtmlTarget, this.panneauHtmlTarget],
        ]
    }

    choisirOnglet(nom) {
        this.onglet = nom
        for (const [cle, onglet, panneau] of this.onglets()) {
            const choisi = cle === nom
            onglet.setAttribute('aria-selected', choisi ? 'true' : 'false')
            onglet.classList.toggle('tab-active', choisi)
            onglet.tabIndex = choisi ? 0 : -1
            panneau.classList.toggle('hidden', !choisi)
        }
    }

    flecheOnglet(evenement) {
        if (evenement.key !== 'ArrowRight' && evenement.key !== 'ArrowLeft') {
            return
        }
        evenement.preventDefault()
        const onglets = this.onglets()
        const index = onglets.findIndex(([, onglet]) => onglet === evenement.currentTarget)
        const [cle, cible] = onglets[(index + (evenement.key === 'ArrowRight' ? 1 : -1) + onglets.length) % onglets.length]
        // Le focus reste sur l'onglet (rediger() le donnerait au texte)
        if (cle === 'apercu') {
            this.apercu()
        } else if (cle === 'html') {
            this.html()
        } else {
            this.appliquerSource()
            this.choisirOnglet('rediger')
        }
        cible.focus()
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
            if (reponse.redirected) {
                throw new ErreurServeur(SESSION_EXPIREE)
            }
            if (!reponse.ok) {
                throw new Error()
            }
            zone.innerHTML = await reponse.text()
        } catch (erreur) {
            zone.textContent = erreur instanceof ErreurServeur ? erreur.message : 'Le résultat n\'a pas pu être chargé, réessayez.'
        }
    }

    // --- Codes inapplicables : aller au code --------------------------------------------------------------------------

    allerAuCode(evenement) {
        this.appliquerSource()
        this.choisirOnglet('rediger')
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
            // Annoncé une seule fois, par la zone role="alert"
            this.erreurTarget.textContent = 'Ce code n\'est plus dans le texte ; enregistrez pour mettre la liste à jour.'
            return
        }
        // Défiler d'abord, puis donner le focus (sur téléphone, le clavier s'ouvre)
        this.editor.commands.setTextSelection({ from: trouve, to: trouve + code.length })
        this.editor.view.domAtPos(trouve).node.parentElement?.scrollIntoView({ block: 'center' })
        setTimeout(() => this.editor.commands.focus(), 300)
    }

    // --- Lien -------------------------------------------------------------------------------------------------------

    // Image sélectionnée (clic sur l'image) : le lien porte sur elle
    imageChoisie() {
        return this.editor.state.selection.node?.type.name === 'image'
    }

    ouvrirLien() {
        const actuel = (this.imageChoisie() ? this.editor.getAttributes('image').href : this.editor.getAttributes('link').href) ?? ''
        this.adresseTarget.value = actuel
        this.erreurLienTarget.textContent = ''
        this.retirerLienTarget.classList.toggle('hidden', actuel === '')
        this.dialogueLienTarget.querySelector('#titre-lien').textContent = this.imageChoisie() ? 'Lien de l\'image' : 'Lien'
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
        if (this.imageChoisie()) {
            this.editor.chain().focus().updateAttributes('image', { href: adresse }).run()
            return
        }
        if (this.editor.state.selection.empty && !this.editor.isActive('link')) {
            // Aucun texte sélectionné : l'adresse devient le texte du lien (sans « mailto: »)
            const texte = adresse.replace(/^mailto:/i, '')
            this.editor.chain().focus().insertContent({ type: 'text', text: texte, marks: [{ type: 'link', attrs: { href: adresse } }] }).run()
            return
        }
        this.editor.chain().focus().extendMarkRange('link').setLink({ href: adresse }).run()
    }

    retirerLien() {
        this.dialogueLienTarget.close()
        if (this.imageChoisie()) {
            this.editor.chain().focus().updateAttributes('image', { href: null }).run()
            return
        }
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
        // L'état d'avant l'envoi revient ensuite, que l'image soit insérée, abandonnée ou refusée
        const etatPrecedent = this.etatTarget.textContent
        this.etatTarget.textContent = 'Envoi de l\'image…'
        const donnees = new FormData()
        donnees.append('_token', this.tokenValue)
        donnees.append('image', fichier)
        try {
            const { resultat } = await envoyer(this.imageUrlValue, donnees)
            this.etatTarget.textContent = etatPrecedent
            this.imageEnvoyee = resultat.url
            this.vignetteTarget.src = resultat.url
            this.descriptionTarget.value = ''
            this.decorativeTarget.checked = false
            this.erreurImageTarget.textContent = ''
            this.dialogueImageTarget.showModal()
            this.descriptionTarget.focus()
        } catch (erreur) {
            this.etatTarget.textContent = etatPrecedent
            this.erreurTarget.textContent = erreur instanceof ErreurServeur ? erreur.message : 'L\'envoi a échoué, réessayez.'
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
