import { Controller } from "@hotwired/stimulus"
import tocbot from "tocbot"

// Sommaire du règlement (templates/front/reglement.html.twig) : parties (h2) et règles (h3) du texte, sans les titres
// des éléments calculés (.js-toc-ignore), toujours entièrement déplié ; bouton « Sommaire » sur téléphone, refermé au
// choix d'un lien
export default class extends Controller {
    connect() {
        const tocContainer = document.querySelector('.js-toc')
        const contentContainer = document.querySelector('.js-toc-content')

        if (!tocContainer || !contentContainer) {
            return
        }

        tocbot.init({
            tocSelector: '.js-toc',
            contentSelector: '.js-toc-content',
            headingSelector: 'h2, h3',
            ignoreSelector: '.js-toc-ignore',
            // Tout le sommaire déplié (titres h2 et h3)
            collapseDepth: 6,
            scrollSmooth: true,
            headingsOffset: 80,
            scrollSmoothOffset: -80,
            extraLinkClasses: 'link',
            // Le sommaire défile seul sur grand écran (nav à hauteur bornée) : le lien marqué y reste visible
            tocScrollingWrapper: tocContainer.closest('nav'),
            onClick: (event) => { this.choisi = event.target.closest('a')?.getAttribute('href') },
            // En bas de page, un titre suivi de peu de texte ne peut pas monter jusqu'en haut de l'écran : tocbot marque
            // alors le titre précédent. Après un clic, le lien choisi reste marqué jusqu'au prochain défilement.
            scrollEndCallback: () => setTimeout(() => this.marquerChoisi(tocContainer), 100),
        })
        // Défilement par l'utilisateur : tocbot reprend la main
        this.defilement = () => { this.choisi = null }
        window.addEventListener('wheel', this.defilement, { passive: true })
        window.addEventListener('touchmove', this.defilement, { passive: true })
        window.addEventListener('keydown', this.defilement)

        this.bouton = document.getElementById('toc-toggle')
        this.basculer = () => {
            const ferme = tocContainer.classList.toggle('hidden')
            this.bouton.setAttribute('aria-expanded', ferme ? 'false' : 'true')
        }
        this.fermer = (event) => {
            if (event.target.closest('a') && this.bouton?.offsetParent) {
                tocContainer.classList.add('hidden')
                this.bouton.setAttribute('aria-expanded', 'false')
            }
        }
        this.bouton?.addEventListener('click', this.basculer)
        tocContainer.addEventListener('click', this.fermer)
        this.toc = tocContainer
    }

    marquerChoisi(toc) {
        const lien = this.choisi && [...toc.querySelectorAll('a.toc-link')].find((a) => a.getAttribute('href') === this.choisi)
        if (!lien) {
            return
        }
        toc.querySelectorAll('.is-active-link').forEach((a) => a.classList.remove('is-active-link'))
        toc.querySelectorAll('.is-active-li').forEach((li) => li.classList.remove('is-active-li'))
        lien.classList.add('is-active-link')
        lien.closest('li')?.classList.add('is-active-li')
        lien.scrollIntoView({ block: 'nearest' })
    }

    disconnect() {
        // Important si la page change via Turbo
        tocbot.destroy()
        window.removeEventListener('wheel', this.defilement)
        window.removeEventListener('touchmove', this.defilement)
        window.removeEventListener('keydown', this.defilement)
        this.bouton?.removeEventListener('click', this.basculer)
        this.toc?.removeEventListener('click', this.fermer)
    }
}
