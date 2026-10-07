import { Controller } from '@hotwired/stimulus';

/*
 * Formulaire de fiche joueur : évite de ressaisir les informations personnelles quand
 * le joueur a déjà un compte utilisateur.
 *  - choisir un compte dans "Compte utilisateur lié" remplit nom, prénom, email et
 *    téléphone (seulement les champs encore vides, pour ne rien écraser) ;
 *  - saisir un email qui correspond à un compte sélectionne ce compte automatiquement
 *    (ce qui déclenche le remplissage ci-dessus).
 *
 * Valeur :
 *  - comptes (Object) : { '_<idUser>': { nom, prenom, email, telephone } } pour les comptes
 *    proposés dans la liste (ceux qui ne sont pas déjà liés à une autre fiche). Les clés
 *    sont préfixées par "_" car le merge Twig réindexerait des clés numériques.
 */
export default class extends Controller {
    static targets = ['nom', 'prenom', 'email', 'telephone', 'user'];
    static values = { comptes: Object };

    remplirDepuisCompte() {
        const compte = this.comptesValue[`_${this.userTarget.value}`];
        if (!compte) return;

        for (const champ of ['nom', 'prenom', 'email', 'telephone']) {
            const input = this[`${champ}Target`];
            if (input.value.trim() === '' && compte[champ]) {
                input.value = compte[champ];
            }
        }
    }

    selectionnerCompteDepuisEmail() {
        if (this.userTarget.value) return;

        const email = this.emailTarget.value.trim().toLowerCase();
        if (email === '') return;

        const cle = Object.keys(this.comptesValue)
            .find((k) => (this.comptesValue[k].email || '').toLowerCase() === email);
        if (!cle) return;
        const id = cle.slice(1);

        // Passe par Tom Select s'il est initialisé, pour que l'affichage suive ;
        // il déclenche lui-même l'événement "change" sur le <select> d'origine.
        if (this.userTarget.tomselect) {
            this.userTarget.tomselect.setValue(id);
        } else {
            this.userTarget.value = id;
            this.userTarget.dispatchEvent(new Event('change'));
        }
    }
}
