import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        addLabel: String,
        deleteLabel: String
    }

    connect() {
        this.index = this.element.childElementCount;

        // 1. Création du bouton "Ajouter"
        const btn = document.createElement('button');
        btn.setAttribute('type', 'button');
        btn.className = 'btn btn-sm btn-primary mt-2 w-full md:w-auto';
        btn.innerText = this.addLabelValue || 'Ajouter une période';
        btn.addEventListener('click', this.addElement.bind(this));

        this.element.append(btn);

        // 2. Traitement des éléments existants (si édition)
        this.element.childNodes.forEach(child => {
            if (child.tagName === 'DIV') {
                // On s'assure que le conteneur a bien la classe relative pour positionner la croix
                child.classList.add('relative', 'border', 'border-base-300', 'p-4', 'rounded-box', 'mb-2', 'bg-base-100');
                this.addDeleteButton(child);
            }
        });
    }

    addElement(e) {
        e.preventDefault();

        const prototype = this.element.dataset.prototype;
        if (!prototype) return;

        const newForm = prototype.replace(/__name__/g, this.index);
        this.index++;

        // --- CHANGEMENT ICI : Le conteneur ---
        // 'relative' est indispensable pour que le bouton 'absolute' se place par rapport à cette div
        const div = document.createElement('div');
        div.className = 'relative border border-base-300 p-4 rounded-box mb-2 bg-base-100 animate-fade-in-down';
        div.innerHTML = newForm;

        this.addDeleteButton(div);

        e.target.before(div);
    }

    addDeleteButton(item) {
        if (item.querySelector('.btn-delete-collection')) return;


        const btn = document.createElement('button');
        btn.setAttribute('type', 'button');

        btn.className = 'btn btn-circle btn-xs btn-ghost text-error absolute top-2 right-2 btn-delete-collection';

        // On met une belle icône SVG au lieu du texte "Supprimer"
        btn.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        `;

        btn.onclick = () => item.remove();

        item.append(btn);
    }
}
