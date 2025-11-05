import { Controller } from "@hotwired/stimulus"
import tocbot from "tocbot"

export default class extends Controller {
    connect() {
        console.log("Tocbot: connect")
        const tocContainer = document.querySelector('.js-toc')
        const contentContainer = document.querySelector('.js-toc-content')

        if (!tocContainer || !contentContainer) {
            console.warn("Tocbot: éléments .js-toc ou .js-toc-content non trouvés.")
            return
        }

        tocbot.init({
            tocSelector: '.js-toc',
            contentSelector: '.js-toc-content',
            headingSelector: 'h2',
            collapseDepth: 3,
            scrollSmooth: true,
            headingsOffset: 80,
            scrollSmoothOffset: -80,
            extraLinkClasses: 'link',
        })
    }

    disconnect() {
        // Important si la page change via Turbo
        tocbot.destroy()
    }
}
