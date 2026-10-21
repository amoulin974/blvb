import { Controller } from '@hotwired/stimulus';

/*
 * Garde la position de défilement quand une action du wizard réaffiche la même étape : ajouter une poule, un match ou
 * une règle, supprimer, monter, descendre, « Enregistrer »… Sans lui, la page revient en haut : Turbo envoie le
 * formulaire en arrière-plan et l'ancre de la redirection (#phase-…, #poule-…) se perd.
 *
 * Placé sur le conteneur du formulaire de l'étape (layout du wizard) : data-action="submit->position-defilement#memoriser".
 * La position n'est mémorisée que pour un bouton « action », ou « aller » = enregistrer, et n'est rétablie que sur la même
 * adresse (sans tenir compte de la query ni de l'ancre), dans les 30 secondes. Navigateur sans sessionStorage : rien ne change.
 */
const CLE = 'wizard-position-defilement';

export default class extends Controller {
    connect() {
        let memo = null;
        try {
            memo = JSON.parse(sessionStorage.getItem(CLE) || 'null');
            sessionStorage.removeItem(CLE);
        } catch (e) {
            return;
        }
        if (!memo || memo.chemin !== window.location.pathname || Date.now() - memo.quand > 30000) {
            return;
        }
        // Turbo fait défiler en haut après l'affichage : on rétablit la position une fois la visite terminée
        const retablir = () => window.scrollTo(0, memo.y);
        document.addEventListener('turbo:load', retablir, { once: true });
        requestAnimationFrame(retablir);
    }

    memoriser(event) {
        const bouton = event.submitter;
        const resteSurEtape = bouton && (bouton.name === 'action' || (bouton.name === 'aller' && bouton.value === 'enregistrer'));
        if (!resteSurEtape) {
            return;
        }
        try {
            sessionStorage.setItem(CLE, JSON.stringify({ chemin: window.location.pathname, y: window.scrollY, quand: Date.now() }));
        } catch (e) {
            // stockage indisponible (navigation privée stricte) : la page reviendra en haut, comme avant
        }
    }
}
