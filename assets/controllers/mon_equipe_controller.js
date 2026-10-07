import { Controller } from '@hotwired/stimulus';

/*
 * « Mon équipe » : le visiteur choisit l'équipe qu'il suit (bouton sur la fiche équipe).
 * Le choix est gardé dans le navigateur (localStorage), sans compte ni donnée côté serveur.
 *
 * Sur les pages qui listent les poules (accueil, calendrier, équipes, classement) :
 *  - l'onglet de la poule qui contient l'équipe est ouvert : input.tab[data-equipes="id id …"] ;
 *  - les lignes et cartes de l'équipe reçoivent la classe "mon-equipe" :
 *    éléments [data-equipe], [data-recoit] ou [data-deplace] portant son id.
 * Dans le menu, les cibles "menu" (masquées par défaut) deviennent un lien vers sa fiche.
 *
 * Cibles : menu (éléments <li> du menu), bouton (bouton Suivre de la fiche équipe).
 */
const CLE = 'blvb.monEquipe';

export default class extends Controller {
    static targets = ['menu', 'bouton'];

    connect() {
        this.appliquer();
    }

    // Bouton de la fiche équipe : suivre / ne plus suivre cette équipe
    basculer(event) {
        const { id, nom } = event.params;
        const actuelle = this.lire();
        this.ecrire(actuelle && actuelle.id === String(id) ? null : { id: String(id), nom });
        this.appliquer();
    }

    appliquer() {
        const equipe = this.lire();
        const id = equipe ? equipe.id : null;

        // Onglet de la poule de l'équipe
        if (id) {
            document.querySelectorAll('input.tab[data-equipes]').forEach((onglet) => {
                if (onglet.dataset.equipes.split(' ').includes(id)) {
                    onglet.checked = true;
                }
            });
        }

        // Mise en évidence des lignes et cartes
        document.querySelectorAll('[data-equipe], [data-recoit], [data-deplace]').forEach((el) => {
            const concerne = id !== null && [el.dataset.equipe, el.dataset.recoit, el.dataset.deplace].includes(id);
            el.classList.toggle('mon-equipe', concerne);
        });

        // Lien « Mon équipe » dans le menu
        this.menuTargets.forEach((li) => {
            li.classList.toggle('hidden', !id);
            const lien = li.querySelector('a');
            if (lien && id) {
                lien.href = `/equipe/${id}`;
                lien.textContent = `★ ${equipe.nom}`;
                lien.title = 'Mon équipe';
            }
        });

        // Bouton de la fiche équipe
        this.boutonTargets.forEach((bouton) => {
            const suivie = id !== null && bouton.dataset.monEquipeIdParam === id;
            bouton.textContent = suivie ? '★ Mon équipe (ne plus suivre)' : '☆ Suivre cette équipe';
            bouton.setAttribute('aria-pressed', suivie ? 'true' : 'false');
        });
    }

    lire() {
        try {
            return JSON.parse(localStorage.getItem(CLE));
        } catch (e) {
            return null;
        }
    }

    ecrire(valeur) {
        try {
            if (valeur) {
                localStorage.setItem(CLE, JSON.stringify(valeur));
            } else {
                localStorage.removeItem(CLE);
            }
        } catch (e) {
            // Stockage indisponible (navigation privée…) : la fonctionnalité est simplement inactive
        }
    }
}
