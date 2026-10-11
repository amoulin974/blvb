import { Controller } from '@hotwired/stimulus';

/*
 * Calendrier : au chargement, fait défiler jusqu'à la journée en cours de la poule affichée
 * (titre marqué [data-journee-courante] dans _calendrier_full.html.twig).
 * Attend que mon_equipe_controller.js ait éventuellement ouvert l'onglet de la poule suivie,
 * et ne fait rien si la page est ouverte sur une ancre ou déjà défilée.
 */
export default class extends Controller {
    connect() {
        if (window.location.hash || window.scrollY > 0) return;
        setTimeout(() => {
            const titre = [...this.element.querySelectorAll('[data-journee-courante]')]
                .find((el) => el.offsetParent !== null);
            if (titre) titre.scrollIntoView({ block: 'start' });
        }, 0);
    }
}
