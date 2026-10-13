import { Controller } from '@hotwired/stimulus';

/*
 * Étape 1 du wizard de saison : n'affiche que la liste utile au point de départ choisi
 * (saison à copier ou modèle), et l'aperçu de la saison à copier sélectionnée.
 * Sans JavaScript, les deux listes restent visibles.
 */
export default class extends Controller {
    static targets = ['copie', 'modele', 'apercu'];

    connect() {
        this.choisir();
    }

    choisir() {
        const choix = this.element.querySelector('input[type="radio"]:checked')?.value ?? 'copie';
        this.copieTarget.hidden = choix !== 'copie';
        this.modeleTarget.hidden = choix !== 'modele';

        const saison = this.copieTarget.querySelector('select')?.value;
        this.apercuTargets.forEach((apercu) => {
            apercu.hidden = apercu.dataset.saison !== saison;
        });
    }
}
