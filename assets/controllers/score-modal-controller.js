import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [ "modal", "title", "labelReception", "labelDeplacement", "inputReception", "inputDeplacement" ];

    connect() {
        // Jeton CSRF facultatif : la balise <meta name="csrf-token"> n'est pas présente dans les pages,
        // on ne doit pas planter au démarrage (les visiteurs n'ont de toute façon pas accès à la saisie).
        this.csrfTokenValue = document.querySelector('meta[name="csrf-token"]')?.content ?? null;
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
                ...(this.csrfTokenValue ? { 'X-CSRF-TOKEN': this.csrfTokenValue } : {})
            },
            body: JSON.stringify({
                scoreReception: scoreReception,
                scoreDeplacement: scoreDeplacement
            })
        })
        .then(async response => {
            if (!response.ok) {
                // Message du serveur (score invalide, jeton expiré…) si disponible
                const erreur = await response.json().catch(() => ({}));
                throw new Error(erreur.error || 'Erreur lors de la mise à jour');
            }
            return response.json();
        })
        .then(json => {
            // Mise à jour du score (newScore vaut null quand le score a été effacé)
            const efface = json.newScore === null;
            const scores = efface ? [] : json.newScore.split(' - ');
            document.querySelectorAll('.score_partie-' + this.currentPartieId).forEach(zone => {
                if (efface) {
                    zone.innerHTML = '<span class="opacity-50" aria-hidden="true">–</span><span class="sr-only">Score non saisi</span>';
                } else if (scores.length === 2) {
                    zone.innerHTML = `<strong>${scores[0]}</strong> - <strong>${scores[1]}</strong>`;
                } else {
                    zone.textContent = json.newScore;
                }
            });

            // Score saisi : crayon « Modifier » à la place de « Saisir ».
            // Score effacé : retour au bouton « Saisir » si le match est passé, sinon au simple tiret.
            document.querySelectorAll(`[data-partie-id="${this.currentPartieId}"]`).forEach(btn => {
                const passe = btn.dataset.matchPasse === '1';
                const afficher = efface
                    ? btn.dataset.scoreAction === 'saisir' && passe
                    : btn.dataset.scoreAction === 'modifier';
                btn.classList.toggle('hidden', !afficher);
            });
            document.querySelectorAll('.score_partie-' + this.currentPartieId).forEach(zone => {
                const saisirVisible = efface && document.querySelector(`[data-partie-id="${this.currentPartieId}"][data-score-action="saisir"][data-match-passe="1"]`);
                zone.classList.toggle('hidden', !!saisirVisible);
            });

            this.closeModal(new Event('submit'));
        })
        .catch(err => {
            console.error(err);
            // On garde la fenêtre ouverte pour que la saisie ne soit pas perdue
            alert(`Le score n'a pas été enregistré : ${err.message}`);
        });
    }
}