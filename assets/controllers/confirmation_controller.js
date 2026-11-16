import { Controller } from '@hotwired/stimulus';

/*
 * Back-office : fenêtre de confirmation partagée (une seule par page, dans base_admin.html.twig).
 * Les boutons déclencheurs (fragment admin/_confirmation.html.twig) passent leurs informations en paramètres :
 *   data-action="confirmation#ouvrir"
 *   data-confirmation-action-param   URL du formulaire POST
 *   data-confirmation-token-param    jeton CSRF
 *   data-confirmation-titre-param    question (« Supprimer l'équipe X ? »)
 *   data-confirmation-consequences-param  tableau JSON de phrases (ce qui sera perdu)
 *   data-confirmation-blocage-param  si non vide : action impossible, explication affichée, pas de bouton de confirmation
 *   data-confirmation-bouton-param   libellé du bouton de confirmation
 *   data-confirmation-ton-param      'error' (défaut), 'warning' ou 'primary' (action sans perte)
 *   data-confirmation-irreversible-param  booléen
 *   data-confirmation-champs-param   objet JSON de champs cachés supplémentaires
 *   data-confirmation-attente-param  texte du bouton pendant l'envoi (calcul long), facultatif
 * Les textes sont posés avec textContent : aucun HTML n'est interprété.
 */
export default class extends Controller {
    static targets = ['dialogue', 'titre', 'iconeAlerte', 'iconeBlocage', 'consequences', 'blocage', 'definitive',
        'formulaire', 'token', 'champs', 'bouton', 'annuler', 'statut'];

    // Envoi du formulaire : si le déclencheur annonce une attente (calcul long), la fenêtre se verrouille (bouton,
    // « Annuler » et Échap) pour éviter un double envoi, et dit l'attente dans une zone role="status" présente dès
    // l'ouverture (annoncée par les lecteurs d'écran).
    envoyer() {
        if (!this.attente) {
            return;
        }
        this.envoiEnCours = true;
        this.boutonTarget.disabled = true;
        this.boutonTarget.replaceChildren(
            Object.assign(document.createElement('span'), { className: 'loading loading-spinner loading-sm' }),
            document.createTextNode(' ' + this.attente),
        );
        this.annulerTarget.disabled = true;
        this.statutTarget.textContent = this.attente + '. Ne fermez pas la page.';
        this.statutTarget.classList.remove('hidden');
    }

    // Échap ne ferme pas la fenêtre pendant un envoi
    echap(event) {
        if (this.envoiEnCours) {
            event.preventDefault();
        }
    }

    ouvrir(event) {
        const p = event.params;
        const bloque = Boolean(p.blocage);
        const ton = ['warning', 'primary'].includes(p.ton) ? p.ton : 'error';

        this.titreTarget.textContent = p.titre || 'Confirmer ?';
        this.iconeAlerteTarget.classList.toggle('hidden', bloque);
        this.iconeBlocageTarget.classList.toggle('hidden', !bloque);
        this.iconeAlerteTarget.parentElement.className = `mt-0.5 text-${ton === 'primary' ? 'primary' : ton}`;

        this.consequencesTarget.replaceChildren(...(bloque ? [] : (Array.isArray(p.consequences) ? p.consequences : []))
            .map((texte) => Object.assign(document.createElement('li'), { textContent: texte })));
        this.consequencesTarget.classList.toggle('hidden', this.consequencesTarget.children.length === 0);

        this.blocageTarget.textContent = bloque ? p.blocage : '';
        this.blocageTarget.classList.toggle('hidden', !bloque);
        this.definitiveTarget.classList.toggle('hidden', bloque || p.irreversible === false);

        this.formulaireTarget.classList.toggle('hidden', bloque);
        this.formulaireTarget.action = bloque ? '' : p.action;
        this.tokenTarget.value = p.token || '';
        this.champsTarget.replaceChildren(...Object.entries(p.champs && typeof p.champs === 'object' ? p.champs : {})
            .map(([nom, valeur]) => Object.assign(document.createElement('input'), { type: 'hidden', name: nom, value: valeur })));
        this.boutonTarget.textContent = p.bouton || 'Supprimer définitivement';
        this.boutonTarget.className = `btn ${{ warning: 'btn-warning', primary: 'btn-primary', error: 'btn-error' }[ton]}`;
        this.attente = p.attente || '';
        this.boutonTarget.disabled = false;
        this.annulerTarget.disabled = false;
        this.envoiEnCours = false;
        this.statutTarget.textContent = '';
        this.statutTarget.classList.add('hidden');
        this.annulerTarget.textContent = bloque ? 'Fermer' : 'Annuler';

        this.dialogueTarget.showModal();
        // Focus sur « Annuler » : une validation par Entrée ne doit pas supprimer par mégarde
        this.annulerTarget.focus();
    }
}
