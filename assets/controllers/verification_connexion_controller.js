import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
/*
 * Paramètres du site (D62) : demande au serveur de vérifier que la connexion limitée est activable. La requête porte
 * exprès un en-tête X-Forwarded-For inventé (valeur "ipForgee") : le site ne doit pas le croire (App\Security\VerificationConnexion).
 * Le résultat s'affiche au rechargement de la page ; ?verifie=1 y amène le focus, une seule fois.
 */
export default class extends Controller {
    static values = { url: String, jeton: String, ipForgee: String };
    static targets = ['bouton', 'erreur', 'synthese'];

    connect() {
        const adresse = new URL(window.location.href);
        if (adresse.searchParams.has('verifie')) {
            adresse.searchParams.delete('verifie');
            window.history.replaceState(window.history.state, '', adresse);
            if (this.hasSyntheseTarget) {
                this.syntheseTarget.focus();
            }
        }
    }

    async verifier() {
        const libelle = this.boutonTarget.textContent;
        this.boutonTarget.disabled = true;
        this.boutonTarget.textContent = 'Vérification en cours…';
        this.erreurTarget.hidden = true;
        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Forwarded-For': this.ipForgeeValue },
                body: new URLSearchParams({ _token: this.jetonValue }),
            });
            if (!reponse.ok) {
                throw new Error(`Réponse ${reponse.status}`);
            }
            window.location.assign(`${window.location.pathname}?verifie=1`);
        } catch {
            this.boutonTarget.disabled = false;
            this.boutonTarget.textContent = libelle;
            this.erreurTarget.hidden = false;
        }
    }
}
