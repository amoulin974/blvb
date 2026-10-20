import { Controller } from "@hotwired/stimulus"
import tocbot from "tocbot"

// Sommaire du règlement (templates/front/reglement.html.twig) : parties (h2) et règles (h3) du texte, sans les titres
// des éléments calculés (.js-toc-ignore) ; bouton « Sommaire » sur téléphone, refermé au choix d'un lien
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
            collapseDepth: 1,
            scrollSmooth: true,
            headingsOffset: 80,
            scrollSmoothOffset: -80,
            extraLinkClasses: 'link',
        })

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

    disconnect() {
        // Important si la page change via Turbo
        tocbot.destroy()
        this.bouton?.removeEventListener('click', this.basculer)
        this.toc?.removeEventListener('click', this.fermer)
    }
}
