import { Controller } from '@hotwired/stimulus';

/*
 * Tri des équipes d'une poule (page « Les équipes »), validé par l'ergonome (octobre 2026).
 *
 * Deux clés : « nom » (ordre par défaut, alphabétique) et « classement » (du 1er au dernier). Un premier clic trie
 * en ordre croissant, un second inverse le sens. Les équipes non classées restent toujours en fin de liste ; à égalité,
 * l'ordre alphabétique départage. Le tri ne vaut que pour la poule (un contrôleur par onglet) et ne se mémorise pas.
 *
 * Les éléments triés portent data-tri-nom (nom enregistré, en minuscules) et data-tri-classement (position, vide si
 * non classée). Ils sont déplacés, jamais recréés : la mise en évidence « Mon équipe » (classe mon-equipe) est conservée.
 *
 * Cibles : liste (tbody du tableau et conteneur des cartes mobiles), entete (<th> triable, data-cle ; reçoit aria-sort
 * seulement s'il est la colonne triée), fleche (<span aria-hidden> dans le bouton d'en-tête, data-cle),
 * bouton (boutons « Trier : Nom | Classement » des cartes mobiles, data-cle ; aria-pressed).
 */
const FLECHES = { asc: '▲', desc: '▼', inactive: '⇅' };

/** Comparaison de deux éléments pour une clé et un sens ; exportée pour être vérifiable sans navigateur. */
export function comparer(a, b, cle, sens) {
    const parNom = a.nom.localeCompare(b.nom, 'fr', { sensitivity: 'base', numeric: true });
    if (cle === 'classement') {
        // Non classées toujours à la fin, quel que soit le sens
        if (a.classement === null || b.classement === null) {
            if (a.classement === b.classement) return parNom;
            return a.classement === null ? 1 : -1;
        }
        const ecart = a.classement - b.classement;
        return ecart !== 0 ? (sens === 'asc' ? ecart : -ecart) : parNom;
    }

    return sens === 'asc' ? parNom : -parNom;
}

export default class extends Controller {
    static targets = ['liste', 'entete', 'fleche', 'bouton'];

    connect() {
        this.cle = 'nom';
        this.sens = 'asc';
        this.afficherEtat();
    }

    trier(event) {
        const cle = event.params.cle;
        this.sens = cle === this.cle && this.sens === 'asc' ? 'desc' : 'asc';
        this.cle = cle;

        this.listeTargets.forEach((liste) => {
            const elements = Array.from(liste.children).map((element) => ({
                element,
                nom: element.dataset.triNom ?? '',
                classement: element.dataset.triClassement ? Number(element.dataset.triClassement) : null,
            }));
            elements.sort((a, b) => comparer(a, b, this.cle, this.sens));
            elements.forEach(({ element }) => liste.appendChild(element)); // déplacement, pas de recréation
        });
        this.afficherEtat();
    }

    afficherEtat() {
        const ordre = this.sens === 'asc' ? 'ascending' : 'descending';
        this.enteteTargets.forEach((th) => {
            if (th.dataset.cle === this.cle) {
                th.setAttribute('aria-sort', ordre);
            } else {
                th.removeAttribute('aria-sort');
            }
        });
        this.flecheTargets.forEach((fleche) => {
            fleche.textContent = fleche.dataset.cle === this.cle ? FLECHES[this.sens] : FLECHES.inactive;
            fleche.classList.toggle('opacity-40', fleche.dataset.cle !== this.cle);
        });
        this.boutonTargets.forEach((bouton) => {
            const actif = bouton.dataset.cle === this.cle;
            bouton.setAttribute('aria-pressed', actif ? 'true' : 'false');
            bouton.classList.toggle('btn-primary', actif);
            bouton.classList.toggle('btn-ghost', !actif);
        });
    }
}
