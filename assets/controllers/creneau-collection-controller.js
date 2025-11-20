import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["list"];

    connect() {
        this.index = parseInt(this.listTarget.dataset.index) || 0;
    }

    add(event) {
        event.preventDefault();

        // Récupère le prototype complet
        const prototype = this.listTarget.dataset.prototype;

        // Remplace __name__ par l'index courant
        const newForm = prototype.replace(/__name__/g, this.index);

        // Crée un <li> avec le même style que les existants
        const li = document.createElement('li');
        li.className = 'card bg-base-200 shadow-sm p-4 relative mt-4';
        li.innerHTML = newForm;

        // Ajouter le bouton remove au nouveau li
        const removeBtn = li.querySelector('button.remove-creneau');
        if (removeBtn) {
            removeBtn.setAttribute('data-action', 'click->creneau-collection#remove');
        }

        // Ajouter au DOM
        this.listTarget.appendChild(li);

        // Incrémenter l'index
        this.index++;
    }

    remove(event) {
        event.preventDefault();
        event.target.closest('li').remove();
    }
}
