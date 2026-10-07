import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [ "modal", "title", "labelReception", "labelDeplacement", "inputReception", "inputDeplacement" ];

    connect() {
        this.csrfTokenValue = document.querySelector('meta[name="csrf-token"]').content;
        this.currentPartieId = null;
    }

    openModal(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        this.currentPartieId = btn.dataset.partieId;

        this.titleTarget.textContent = `Saisir le score : ${btn.dataset.equipeRecoit} vs ${btn.dataset.equipeDeplace}`;
        this.labelReceptionTarget.textContent = `Nombre sets gagnants ${btn.dataset.equipeRecoit} :`;
        this.labelDeplacementTarget.textContent = `Nombre sets gagnants ${btn.dataset.equipeDeplace} :`;

        // En modification, on pré-remplit avec le score affiché ("3 - 1") ; en saisie, champs vides.
        const zone = document.querySelector('.score_partie-' + this.currentPartieId);
        const scores = btn.dataset.scoreAction === 'modifier' && zone ? zone.textContent.trim().split(/\s*-\s*/) : [];
        this.inputReceptionTarget.value = scores.length === 2 ? scores[0] : '';
        this.inputDeplacementTarget.value = scores.length === 2 ? scores[1] : '';

        this.modalTarget.classList.add('modal-open');
    }

    closeModal(event) {
        event.preventDefault();
        this.modalTarget.classList.remove('modal-open');
    }

    submitForm() {
        const scoreReception = this.inputReceptionTarget.value;
        const scoreDeplacement = this.inputDeplacementTarget.value;

        // Envoi du résultat au serveur
        fetch('/front/partie/' + this.currentPartieId + '/api/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfTokenValue
            },
            body: JSON.stringify({
                scoreReception: scoreReception,
                scoreDeplacement: scoreDeplacement
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Erreur lors de la mise à jour');
            return response.json();
        })
        .then(json => {
            // Mise à jour du score
            const scoreZones = document.querySelectorAll('.score_partie-' + this.currentPartieId);
            const scores = json.newScore.split(' - ');
            scoreZones.forEach(zone => {
                zone.classList.remove('hidden');
                if (scores.length === 2) {
                    zone.innerHTML = `<strong>${scores[0]}</strong> - <strong>${scores[1]}</strong>`;
                } else {
                    zone.textContent = json.newScore;
                }
            });

            // Le score est saisi : on remplace le bouton « Saisir » par le crayon « Modifier »
            document.querySelectorAll(`[data-partie-id="${this.currentPartieId}"]`).forEach(btn => {
                btn.classList.toggle('hidden', btn.dataset.scoreAction === 'saisir');
            });

            this.closeModal(new Event('submit'));
        })
        .catch(err => {
            console.error(err);
            this.closeModal(new Event('submit'));
        });
    }
}