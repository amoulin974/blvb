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
 *   data-confirmation-ton-param      'error' ou 'warning'
 *   data-confirmation-irreversible-param  booléen
 *   data-confirmation-champs-param   objet JSON de champs cachés supplémentaires
 * Les textes sont posés avec textContent : aucun HTML n'est interprété.
 */
export default class extends Controller {
    static targets = ['dialogue', 'titre', 'iconeAlerte', 'iconeBlocage', 'consequences', 'blocage', 'definitive',
        'formulaire', 'token', 'champs', 'bouton', 'annuler'];

    ouvrir(event) {
        const p = event.params;
        const bloque = Boolean(p.blocage);
        const ton = p.ton === 'warning' ? 'warning' : 'error';

        this.titreTarget.textContent = p.titre || 'Confirmer ?';
        this.iconeAlerteTarget.classList.toggle('hidden', bloque);
        this.iconeBlocageTarget.classList.toggle('hidden', !bloque);
        this.iconeAlerteTarget.parentElement.className = `mt-0.5 ${ton === 'warning' ? 'text-warning' : 'text-error'}`;

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
        this.boutonTarget.className = `btn ${ton === 'warning' ? 'btn-warning' : 'btn-error'}`;
        this.annulerTarget.textContent = bloque ? 'Fermer' : 'Annuler';

        this.dialogueTarget.showModal();
        // Focus sur « Annuler » : une validation par Entrée ne doit pas supprimer par mégarde
        this.annulerTarget.focus();
    }
}
