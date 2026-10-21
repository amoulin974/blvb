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
            // Saut natif du navigateur vers l'ancre : il respecte la marge des titres (scroll-mt-24, 96 px, sous l'en-tête
            // collant). Le défilement animé de tocbot s'arrêtait avant le titre sous Firefox et ne marchait pas sous Safari.
            scrollSmooth: false,
            // Titre « atteint » à moins de 100 + 10 px du haut : un titre posé à 96 px par le saut compte comme atteint
            headingsOffset: 100,
            extraLinkClasses: 'link',
            // Le sommaire défile seul sur grand écran (nav à hauteur bornée) : le lien marqué y reste visible
            tocScrollingWrapper: tocContainer.closest('nav'),
            // Lien choisi, remarqué même si la page ne défile pas (titre déjà à sa place)
            onClick: (event) => {
                this.choisi = event.target.closest('a')?.getAttribute('href')
                this.finDefilement()
            },
        })
        // En bas de page, un titre suivi de peu de texte ne peut pas monter jusqu'en haut de l'écran : tocbot marque alors
        // le titre précédent. Après un clic, le lien choisi est remarqué à la fin du défilement (après la mise à jour de
        // tocbot), jusqu'à ce que l'utilisateur fasse défiler lui-même la page.
        this.finDefilement = () => {
            clearTimeout(this.minuteur)
            this.minuteur = setTimeout(() => this.marquerChoisi(tocContainer), 120)
        }
        this.defilement = () => { this.choisi = null }
        window.addEventListener('scroll', this.finDefilement, { passive: true })
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
        // Le lien reste visible dans le sommaire : seul le sommaire défile (scrollIntoView ferait défiler toute la page
        // sous Firefox et Safari, le sommaire étant collant)
        const nav = toc.closest('nav')
        if (nav && nav.scrollHeight > nav.clientHeight) {
            const cadre = nav.getBoundingClientRect()
            const position = lien.getBoundingClientRect()
            if (position.top < cadre.top || position.bottom > cadre.bottom) {
                nav.scrollTop += position.top - cadre.top - nav.clientHeight / 2
            }
        }
    }

    disconnect() {
        // Important si la page change via Turbo
        tocbot.destroy()
        clearTimeout(this.minuteur)
        window.removeEventListener('scroll', this.finDefilement)
        window.removeEventListener('wheel', this.defilement)
        window.removeEventListener('touchmove', this.defilement)
        window.removeEventListener('keydown', this.defilement)
        this.bouton?.removeEventListener('click', this.basculer)
        this.toc?.removeEventListener('click', this.fermer)
    }
}
