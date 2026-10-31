// Partie commune aux éditeurs TipTap : règlement (reglement_editeur_controller.js, D59) et description du club
// (editeur_texte_controller.js, D63).

// Adresse saisie dans la fenêtre Lien, normalisée : « contact@… » → mailto:, « www.… » → https:// ; null si elle n'est pas
// reconnue. Toujours https://, http:// et mailto: ; #ancre et chemin du site seulement si l'éditeur les accepte.
export function adresseDeLien(saisie, { ancres = false, chemins = false } = {}) {
    let adresse = saisie.trim()
    if (/^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test(adresse)) {
        adresse = 'mailto:' + adresse
    } else if (/^www\.[^\s]+$/i.test(adresse)) {
        adresse = 'https://' + adresse
    }
    const reconnue = /^(https?:\/\/[^\s]+|mailto:[^\s]+)$/i.test(adresse)
        || (ancres && /^#[A-Za-z0-9_-]+$/.test(adresse))
        || (chemins && /^\/(?!\/)[^\s]*$/.test(adresse))

    return reconnue ? adresse : null
}

// Barre d'outils (role="toolbar") : une seule tabulation pour la barre ; flèches, Origine et Fin pour passer d'un bouton à
// l'autre (boutons visibles et actifs) ; Échap revient au texte. Un menu déroulant compte pour un bouton (son summary),
// ses entrées se parcourent au clavier dans le menu ouvert.
export function naviguerDansBarre(evenement, barre, editor) {
    if (evenement.target.closest('.dropdown-content')) {
        return
    }
    const boutons = [...barre.querySelectorAll('button[data-commande], summary')]
        .filter((b) => b.offsetParent !== null && !b.disabled && !b.closest('.dropdown-content'))
    const index = boutons.indexOf(document.activeElement)
    const cible = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: boutons.length - 1 }[evenement.key]
    if (evenement.key === 'Escape') {
        editor.commands.focus()
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
