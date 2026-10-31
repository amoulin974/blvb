/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'
import { Editor } from '@tiptap/core'
import { StarterKit } from '@tiptap/starter-kit'
import { adresseDeLien, naviguerDansBarre } from '../editeur/commun.js'

// Éditeur à barre réduite d'un champ de formulaire (description du club, D63 ; front/club/_editeur_description.html.twig) :
// titres, gras, italique, listes, liens. Le HTML est recopié dans le champ caché à chaque modification et nettoyé par le
// serveur (App\Service\DescriptionClub). Sans JavaScript, le champ reste une zone de texte ordinaire.
export default class extends Controller {
    static targets = ['champ', 'zone', 'editeur', 'barre', 'dialogueLien', 'titreLien', 'adresse', 'erreurLien', 'retirerLien']

    connect() {
        this.editor = new Editor({
            element: this.editeurTarget,
            content: this.champTarget.value,
            extensions: [
                // Seulement ce que garde le nettoyage serveur (sanitizer app.club) : un texte collé perd le reste
                StarterKit.configure({
                    heading: { levels: [3, 4] }, blockquote: false, code: false, codeBlock: false, horizontalRule: false,
                    strike: false, underline: false, trailingNode: false,
                    link: { openOnClick: false, autolink: true, HTMLAttributes: { rel: null, target: null } },
                }),
            ],
            editorProps: {
                attributes: {
                    role: 'textbox', 'aria-multiline': 'true',
                    'aria-labelledby': this.champTarget.labels[0]?.id ?? '',
                    'aria-describedby': this.champTarget.getAttribute('aria-describedby') ?? '',
                },
                handleKeyDown: (_, evenement) => this.raccourci(evenement),
                handleDOMEvents: {
                    click: (_, evenement) => {
                        if (evenement.target.closest('a')) {
                            evenement.preventDefault()
                        }
                        return false
                    },
                },
            },
            onUpdate: ({ editor }) => { this.champTarget.value = editor.isEmpty ? '' : editor.getHTML() },
            onSelectionUpdate: () => this.majBarre(),
            onTransaction: () => this.majBarre(),
        })
        // Le libellé du champ mène à l'éditeur
        this.versEditeur = (evenement) => {
            evenement.preventDefault()
            this.editor.commands.focus()
        }
        this.champTarget.labels.forEach((libelle) => libelle.addEventListener('click', this.versEditeur))
        this.champTarget.hidden = true
        this.zoneTarget.hidden = false
        this.majBarre()
    }

    disconnect() {
        this.champTarget.labels.forEach((libelle) => libelle.removeEventListener('click', this.versEditeur))
        this.editor?.destroy()
        this.champTarget.hidden = false
        this.zoneTarget.hidden = true
    }

    commande(evenement) {
        const chaine = this.editor.chain().focus()
        switch (evenement.currentTarget.dataset.commande) {
            case 'titre': chaine.toggleHeading({ level: 3 }).run(); break
            case 'sous-titre': chaine.toggleHeading({ level: 4 }).run(); break
            case 'gras': chaine.toggleBold().run(); break
            case 'italique': chaine.toggleItalic().run(); break
            case 'puces': chaine.toggleBulletList().run(); break
            case 'numeros': chaine.toggleOrderedList().run(); break
            case 'lien': this.ouvrirLien(); break
            case 'annuler': chaine.undo().run(); break
            case 'retablir': chaine.redo().run(); break
        }
    }

    majBarre() {
        const e = this.editor
        const actif = {
            titre: e.isActive('heading', { level: 3 }), 'sous-titre': e.isActive('heading', { level: 4 }),
            gras: e.isActive('bold'), italique: e.isActive('italic'),
            puces: e.isActive('bulletList'), numeros: e.isActive('orderedList'), lien: e.isActive('link'),
        }
        this.barreTarget.querySelectorAll('[data-commande]').forEach((bouton) => {
            if (bouton.dataset.commande in actif) {
                bouton.setAttribute('aria-pressed', actif[bouton.dataset.commande] ? 'true' : 'false')
            }
        })
        this.barreTarget.querySelector('[data-commande="annuler"]').disabled = !e.can().undo()
        this.barreTarget.querySelector('[data-commande="retablir"]').disabled = !e.can().redo()
    }

    // Clic sur un bouton de la barre : le texte garde le focus et la sélection (la frappe qui suit n'est pas perdue)
    garderFocus(evenement) {
        if (evenement.target.closest('button')) {
            evenement.preventDefault()
        }
    }

    flecheBarre(evenement) {
        naviguerDansBarre(evenement, this.barreTarget, this.editor)
    }

    raccourci(evenement) {
        if ((evenement.ctrlKey || evenement.metaKey) && evenement.key === 'k') {
            evenement.preventDefault()
            this.ouvrirLien()
            return true
        }
        return false
    }

    // --- Lien : page web ou adresse e-mail ----------------------------------------------------------------------------

    ouvrirLien() {
        const actuel = this.editor.getAttributes('link').href ?? ''
        this.adresseTarget.value = actuel
        this.signalerErreurLien('')
        this.titreLienTarget.textContent = actuel === '' ? 'Ajouter un lien' : 'Modifier le lien'
        this.retirerLienTarget.classList.toggle('hidden', actuel === '')
        this.dialogueLienTarget.showModal()
        this.adresseTarget.focus()
    }

    validerLien(evenement) {
        evenement.preventDefault()
        const adresse = adresseDeLien(this.adresseTarget.value)
        if (adresse === null) {
            this.signalerErreurLien('Adresse non reconnue. Écrivez par exemple www.monclub.fr ou contact@monclub.fr.')
            return
        }
        this.dialogueLienTarget.close()
        if (this.editor.state.selection.empty && !this.editor.isActive('link')) {
            // Aucun texte sélectionné : l'adresse devient le texte du lien (sans « mailto: »)
            const texte = adresse.replace(/^mailto:/i, '')
            this.editor.chain().focus().insertContent({ type: 'text', text: texte, marks: [{ type: 'link', attrs: { href: adresse } }] }).run()
            return
        }
        this.editor.chain().focus().extendMarkRange('link').setLink({ href: adresse }).run()
    }

    signalerErreurLien(message) {
        this.erreurLienTarget.textContent = message
        if (message === '') {
            this.adresseTarget.removeAttribute('aria-invalid')
        } else {
            this.adresseTarget.setAttribute('aria-invalid', 'true')
        }
    }

    retirerLien() {
        this.dialogueLienTarget.close()
        this.editor.chain().focus().extendMarkRange('link').unsetLink().run()
    }

    fermerDialogue() {
        this.dialogueLienTarget.close()
        this.editor.commands.focus()
    }
}
