import { Controller } from '@hotwired/stimulus';

/*
 * Menu déroulant fait d'un <details> (ouverture au clavier native) : se ferme sur Échap (focus rendu au <summary>), sur
 * un clic hors du menu, quand le focus le quitte, et au choix d'une entrée. Dans ce dernier cas, le focus est rendu au
 * <summary> AVANT l'action de l'entrée (écoute en phase de capture) : la fenêtre de confirmation ouverte ensuite
 * (confirmation_controller.js) rendra le focus au <summary> à sa fermeture, et non à une entrée masquée.
 *
 * Usage : <details class="dropdown" data-controller="menu-deroulant"><summary>…</summary><ul>…<button>…</button></ul></details>
 */
export default class extends Controller {
    connect() {
        this.surClicDocument = (event) => {
            if (this.element.open && !this.element.contains(event.target)) this.fermer();
        };
        this.surTouche = (event) => {
            if (event.key === 'Escape' && this.element.open) {
                event.preventDefault();
                this.fermer(true);
            }
        };
        this.surChoix = (event) => {
            if (event.target.closest('summary')) return;
            if (event.target.closest('button, a')) this.fermer(true);
        };
        this.surSortieFocus = (event) => {
            if (this.element.open && event.relatedTarget && !this.element.contains(event.relatedTarget)) this.fermer();
        };
        document.addEventListener('click', this.surClicDocument);
        this.element.addEventListener('keydown', this.surTouche);
        this.element.addEventListener('click', this.surChoix, true);
        this.element.addEventListener('focusout', this.surSortieFocus);
    }

    disconnect() {
        document.removeEventListener('click', this.surClicDocument);
        this.element.removeEventListener('keydown', this.surTouche);
        this.element.removeEventListener('click', this.surChoix, true);
        this.element.removeEventListener('focusout', this.surSortieFocus);
    }

    fermer(rendreFocus = false) {
        this.element.open = false;
        if (rendreFocus) this.element.querySelector('summary')?.focus();
    }
}
