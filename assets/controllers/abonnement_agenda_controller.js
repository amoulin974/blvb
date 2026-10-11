import { Controller } from '@hotwired/stimulus';

/*
 * Fiche équipe : fenêtre « Ajouter à mon agenda » (abonnement ICS).
 * Cibles : dialogue (<dialog>), confirmation (message « adresse copiée »).
 * Valeur : adresse (adresse https de l'agenda, copiée dans le presse-papiers).
 */
export default class extends Controller {
    static targets = ['dialogue', 'confirmation'];
    static values = { adresse: String };

    ouvrir(event) {
        event.preventDefault();
        this.dialogueTarget.showModal();
    }

    async copier() {
        try {
            await navigator.clipboard.writeText(this.adresseValue);
            this.confirmationTarget.textContent = 'Adresse copiée.';
        } catch (e) {
            // Presse-papiers indisponible (page non sécurisée, navigateur ancien) : on sélectionne le champ pour une copie manuelle
            const champ = this.dialogueTarget.querySelector('input[readonly]');
            champ?.select();
            this.confirmationTarget.textContent = 'Sélectionnez l\'adresse puis copiez-la (Ctrl+C).';
        }
    }
}
