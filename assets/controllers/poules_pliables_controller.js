import { Controller } from '@hotwired/stimulus';

/*
 * Étape « Poules » de la mise en place.
 * - À partir de 1024 px, toutes les cartes de poule sont dépliées (l'attribut name, qui rend les <details>
 *   exclusifs, est retiré). En dessous, une seule carte reste ouverte à la fois.
 * - Après une action, l'ancre #poule-N de l'adresse ouvre la carte concernée et lui donne le focus.
 * Sans JavaScript, les cartes restent indépendantes et la première est ouverte.
 */
export default class extends Controller {
    connect() {
        this.requete = window.matchMedia('(min-width: 64rem)');
        this.adapter = () => {
            const large = this.requete.matches;
            this.element.querySelectorAll('details[data-poule]').forEach((carte) => {
                if (large) {
                    carte.removeAttribute('name');
                    carte.open = true;
                } else {
                    carte.setAttribute('name', 'poules');
                }
            });
        };
        this.adapter();
        this.requete.addEventListener('change', this.adapter);
        this.cibler();
    }

    disconnect() {
        this.requete?.removeEventListener('change', this.adapter);
    }

    cibler() {
        const id = window.location.hash.slice(1).replace(/[^\w-]/g, '');
        const cible = id === '' ? null : this.element.querySelector(`details[data-poule][id="${id}"]`);
        if (!cible) {
            return;
        }
        cible.open = true;
        cible.querySelector('summary')?.focus();
    }
}
