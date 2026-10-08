import { Controller } from '@hotwired/stimulus';

/*
 * « Mon équipe » : le visiteur choisit l'équipe qu'il suit. Le choix est gardé dans le navigateur
 * (localStorage), sans compte ni donnée côté serveur.
 *
 * Choisir son équipe :
 *  - bouton de la fiche équipe (cible "bouton", paramètres id / nom) ;
 *  - liste de choix (fragment _choisir_mon_equipe) : invitation de l'accueil et fenêtre ouverte depuis le menu.
 * Capitaine connecté : son équipe (valeur "defaut", fournie par MonEquipeExtension) est utilisée tant que
 * le visiteur n'a rien choisi lui-même ; « retirer » l'écarte explicitement.
 *
 * Effets sur les pages qui listent les poules (accueil, calendrier, équipes, classement) :
 *  - l'onglet de la poule qui contient l'équipe est ouvert : input.tab[data-equipes="id id …"] ;
 *  - ses lignes et cartes reçoivent la classe "mon-equipe" : éléments [data-equipe], [data-recoit] ou [data-deplace].
 *
 * Filtres liés à l'équipe : select[data-filtre-mon-equipe] dont une option[data-equipes] contient l'équipe
 * (ex. son gymnase dans le calendrier par gymnase) → option sélectionnée automatiquement.
 *
 * Cibles : menu / menuChoisir (entrées du menu selon qu'une équipe est choisie ou non), bouton (fiche équipe),
 * invitation (bandeau de l'accueil), dialogue (fenêtre de choix), confirmation / confirmationTexte (message après choix).
 */
const CLE = 'blvb.monEquipe';
const CLE_INVITATION = 'blvb.invitationMonEquipeFermee';
const AUCUNE = { aucune: true };

export default class extends Controller {
    static targets = ['menu', 'menuChoisir', 'bouton', 'invitation', 'dialogue', 'confirmation', 'confirmationTexte'];
    static values = { defaut: Object };

    connect() {
        this.appliquer();
    }

    // Bouton de la fiche équipe : définir / retirer cette équipe
    basculer(event) {
        const { id, nom } = event.params;
        const actuelle = this.equipe();
        if (actuelle && actuelle.id === String(id)) {
            this.ecrire(CLE, AUCUNE);
            this.appliquer();
        } else {
            this.definir({ id: String(id), nom });
        }
    }

    // Liste de choix (accueil ou fenêtre du menu)
    choisir(event) {
        const select = event.currentTarget.closest('[data-mon-equipe-choix]').querySelector('select');
        if (!select.value) {
            select.focus();
            return;
        }
        this.definir({ id: select.value, nom: select.options[select.selectedIndex].text });
        if (this.hasDialogueTarget && this.dialogueTarget.open) {
            this.dialogueTarget.close();
        }
    }

    ouvrirChoix(event) {
        event.preventDefault();
        // Sur mobile, on referme d'abord le menu latéral
        const tiroir = document.getElementById('my-drawer-2');
        if (tiroir) tiroir.checked = false;
        this.dialogueTarget.showModal();
    }

    fermerInvitation() {
        this.ecrire(CLE_INVITATION, true);
        this.appliquer();
    }

    definir(equipe) {
        this.ecrire(CLE, equipe);
        this.appliquer();
        this.confirmer(equipe.nom);
    }

    confirmer(nom) {
        if (!this.hasConfirmationTarget) return;
        this.confirmationTexteTarget.textContent =
            `${nom} est maintenant votre équipe : sa poule s'ouvrira directement et ses matchs seront surlignés.`;
        this.confirmationTarget.classList.remove('hidden');
        clearTimeout(this.minuteur);
        this.minuteur = setTimeout(() => this.confirmationTarget.classList.add('hidden'), 6000);
    }

    // Équipe effective : choix du visiteur, sinon équipe du capitaine connecté, sinon aucune
    equipe() {
        const choix = this.lire(CLE);
        if (choix && choix.aucune) return null;
        if (choix && choix.id) return choix;
        if (this.hasDefautValue && this.defautValue && this.defautValue.id) {
            return { id: String(this.defautValue.id), nom: this.defautValue.nom };
        }
        return null;
    }

    appliquer() {
        const equipe = this.equipe();
        const id = equipe ? equipe.id : null;

        // Onglet de la poule de l'équipe
        if (id) {
            document.querySelectorAll('input.tab[data-equipes]').forEach((onglet) => {
                if (onglet.dataset.equipes.split(' ').includes(id)) {
                    onglet.checked = true;
                }
            });
        }

        // Filtres qui proposent un choix lié à l'équipe (ex. gymnase du calendrier par gymnase) :
        // option[data-equipes="id id …"] de l'équipe sélectionnée automatiquement, sans écraser un choix manuel.
        document.querySelectorAll('select[data-filtre-mon-equipe]').forEach((select) => {
            const option = id ? [...select.options].find((o) => (o.dataset.equipes || '').split(' ').includes(id)) : null;
            const automatique = select.dataset.choixAutomatique === '1';
            if (option && (select.value === 'all' || automatique) && select.value !== option.value) {
                select.value = option.value;
                select.dataset.choixAutomatique = '1';
                select.dispatchEvent(new Event('change'));
            } else if (!option && automatique) {
                select.value = 'all';
                delete select.dataset.choixAutomatique;
                select.dispatchEvent(new Event('change'));
            }
        });
        // Un choix manuel dans le filtre n'est plus considéré comme automatique
        document.querySelectorAll('select[data-filtre-mon-equipe]').forEach((select) => {
            if (!select.dataset.ecouteChoix) {
                select.dataset.ecouteChoix = '1';
                select.addEventListener('change', (event) => {
                    if (event.isTrusted) delete select.dataset.choixAutomatique;
                });
            }
        });

        // Mise en évidence des lignes et cartes
        document.querySelectorAll('[data-equipe], [data-recoit], [data-deplace]').forEach((el) => {
            const concerne = id !== null && [el.dataset.equipe, el.dataset.recoit, el.dataset.deplace].includes(id);
            el.classList.toggle('mon-equipe', concerne);
        });

        // Entrées du menu
        this.menuTargets.forEach((li) => {
            li.classList.toggle('hidden', !id);
            const lien = li.querySelector('a');
            if (lien && id) {
                lien.href = `/equipe/${id}`;
                lien.textContent = `★ ${equipe.nom}`;
                lien.title = 'Mon équipe';
            }
        });
        this.menuChoisirTargets.forEach((li) => li.classList.toggle('hidden', !!id));

        // Invitation de l'accueil : tant qu'aucune équipe n'est choisie et qu'elle n'a pas été fermée
        this.invitationTargets.forEach((el) => el.classList.toggle('hidden', !!id || this.lire(CLE_INVITATION) === true));

        // Bouton de la fiche équipe
        this.boutonTargets.forEach((bouton) => {
            const choisie = id !== null && bouton.dataset.monEquipeIdParam === id;
            bouton.textContent = choisie ? '★ Mon équipe · retirer' : "☆ C'est mon équipe";
            bouton.setAttribute('aria-pressed', choisie ? 'true' : 'false');
        });
    }

    lire(cle) {
        try {
            return JSON.parse(localStorage.getItem(cle));
        } catch (e) {
            return null;
        }
    }

    ecrire(cle, valeur) {
        try {
            localStorage.setItem(cle, JSON.stringify(valeur));
        } catch (e) {
            // Stockage indisponible (navigation privée…) : la fonctionnalité est simplement inactive
        }
    }
}
