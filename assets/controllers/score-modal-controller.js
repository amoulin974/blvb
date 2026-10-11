import { Controller } from '@hotwired/stimulus';

/*
 * Saisie du résultat d'un match (fenêtre _score_modal.html.twig, boutons _score_partie.html.twig).
 *
 * Les boutons « Saisir » / crayon portent : data-partie-id, data-equipe-recoit, data-equipe-deplace,
 * data-score-reception / data-score-deplacement (score actuel, -1 = forfait, vide = non joué), data-match-passe,
 * data-bareme (points de la saison du match, pour le résumé).
 *
 * Envoi à FrontController::api_score_update (PUT, jeton CSRF de la balise <meta name="csrf-token">) :
 *   match joué → nombres ; forfait → « F » pour l'équipe forfait et vide pour l'autre ; effacer → deux vides.
 * La réponse newScore vaut « 3 - 1 », « F - 3 », « 3 - F » ou null (score effacé).
 *
 * Règle de validité d'un match joué (3 sets gagnants) : une équipe exactement à 3, l'autre entre 0 et 2.
 * Les erreurs ne s'affichent qu'à l'enregistrement ou une fois les deux champs remplis, jamais avant la saisie.
 */
export default class extends Controller {
    static targets = ['modal', 'title', 'typeJoue', 'typeForfaitReception', 'typeForfaitDeplacement',
        'nomForfaitReception', 'nomForfaitDeplacement', 'sets', 'labelReception', 'labelDeplacement',
        'inputReception', 'inputDeplacement', 'resume', 'erreur', 'effacer', 'enregistrer'];

    connect() {
        // Jeton CSRF : la balise n'existe que pour un utilisateur connecté (les visiteurs n'ont pas accès à la saisie)
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? null;
        this.partieId = null;
        this.fermerSurEchap = (event) => {
            if (event.key === 'Escape' && this.modalTarget.classList.contains('modal-open')) this.closeModal(event);
        };
        document.addEventListener('keydown', this.fermerSurEchap);
    }

    disconnect() {
        document.removeEventListener('keydown', this.fermerSurEchap);
    }

    openModal(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        this.declencheur = btn;
        this.partieId = btn.dataset.partieId;
        this.recoit = btn.dataset.equipeRecoit;
        this.deplace = btn.dataset.equipeDeplace;
        try {
            this.bareme = JSON.parse(btn.dataset.bareme || '{}');
        } catch (e) {
            this.bareme = {};
        }

        this.titleTarget.textContent = `${this.recoit} contre ${this.deplace}`;
        this.labelReceptionTarget.textContent = this.recoit;
        this.labelDeplacementTarget.textContent = this.deplace;
        this.nomForfaitReceptionTarget.textContent = this.recoit;
        this.nomForfaitDeplacementTarget.textContent = this.deplace;

        const r = btn.dataset.scoreReception ?? '';
        const d = btn.dataset.scoreDeplacement ?? '';
        const dejaSaisi = r !== '' && d !== '';
        this.typeForfaitReceptionTarget.checked = r === '-1';
        this.typeForfaitDeplacementTarget.checked = d === '-1';
        this.typeJoueTarget.checked = r !== '-1' && d !== '-1';
        this.inputReceptionTarget.value = dejaSaisi && r !== '-1' && d !== '-1' ? r : '';
        this.inputDeplacementTarget.value = dejaSaisi && r !== '-1' && d !== '-1' ? d : '';
        this.effacerTarget.classList.toggle('hidden', !dejaSaisi);

        this.masquerErreur();
        this.changerType();
        this.modalTarget.classList.add('modal-open');
        (this.type() === 'joue' ? this.inputReceptionTarget : this.radioCoche()).focus();
    }

    closeModal(event) {
        event?.preventDefault?.();
        this.modalTarget.classList.remove('modal-open');
        this.declencheur?.focus();
    }

    type() {
        if (this.typeForfaitReceptionTarget.checked) return 'forfait-reception';
        if (this.typeForfaitDeplacementTarget.checked) return 'forfait-deplacement';
        return 'joue';
    }

    radioCoche() {
        return [this.typeJoueTarget, this.typeForfaitReceptionTarget, this.typeForfaitDeplacementTarget].find((r) => r.checked);
    }

    changerType() {
        // En cas de forfait, les sets ne s'appliquent pas : le bloc est masqué (valeurs gardées si l'on revient à « Match joué »)
        const forfait = this.type() !== 'joue';
        this.setsTarget.disabled = forfait;
        this.setsTarget.classList.toggle('hidden', forfait);
        this.masquerErreur();
        this.actualiser();
    }

    // Résumé en direct ; une erreur n'est montrée en direct que lorsque les deux champs sont remplis
    actualiser() {
        const analyse = this.analyser();
        this.resumeTarget.textContent = analyse.resume ?? '';
        if (analyse.erreur && analyse.complet) {
            this.afficherErreur(analyse.erreur);
        } else {
            this.masquerErreur();
        }
    }

    analyser() {
        const b = this.bareme;
        switch (this.type()) {
            case 'forfait-reception':
                return { valeurs: ['F', ''], resume: `Forfait de ${this.recoit} : ${this.deplace} ${this.points(b.victoireForte)}, ${this.recoit} ${this.points(b.forfait)}.` };
            case 'forfait-deplacement':
                return { valeurs: ['', 'F'], resume: `Forfait de ${this.deplace} : ${this.recoit} ${this.points(b.victoireForte)}, ${this.deplace} ${this.points(b.forfait)}.` };
        }

        const brutR = this.inputReceptionTarget.value.trim();
        const brutD = this.inputDeplacementTarget.value.trim();
        const complet = brutR !== '' && brutD !== '';
        if (!complet) {
            return { complet, erreur: 'Indiquez le nombre de sets gagnés par chaque équipe.' };
        }
        const r = Number(brutR);
        const d = Number(brutD);
        const valide = Number.isInteger(r) && Number.isInteger(d) && r >= 0 && d >= 0 && r <= 3 && d <= 3
            && (r === 3) !== (d === 3);
        if (!valide) {
            return { complet, erreur: 'Score impossible : une des deux équipes doit avoir gagné 3 sets, l\'autre 0, 1 ou 2.' };
        }

        const [gagnant, perdant, sg, sp] = r === 3 ? [this.recoit, this.deplace, r, d] : [this.deplace, this.recoit, d, r];
        const [pg, pp] = sp === 2 ? [b.victoireFaible, b.defaiteForte] : [b.victoireForte, b.defaiteFaible];
        return { complet, valeurs: [String(r), String(d)], resume: `${gagnant} gagne ${sg}–${sp} : ${gagnant} ${this.points(pg)}, ${perdant} ${this.points(pp)}.` };
    }

    points(n) {
        if (n === undefined || n === null) return '';
        return `${n} point${Math.abs(n) >= 2 ? 's' : ''}`;
    }

    submitForm(event) {
        event?.preventDefault?.();
        const analyse = this.analyser();
        if (!analyse.valeurs) {
            this.afficherErreur(analyse.erreur);
            (this.inputReceptionTarget.value.trim() === '' ? this.inputReceptionTarget : this.inputDeplacementTarget).focus();
            return;
        }
        this.envoyer(analyse.valeurs[0], analyse.valeurs[1]);
    }

    effacer(event) {
        event.preventDefault();
        this.envoyer('', '');
    }

    envoyer(scoreReception, scoreDeplacement) {
        this.enregistrerTarget.disabled = true;
        fetch('/front/partie/' + this.partieId + '/api/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {}),
            },
            body: JSON.stringify({ scoreReception, scoreDeplacement }),
        })
            .then(async (response) => {
                const json = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(json.error || 'Erreur lors de l\'enregistrement.');
                return json;
            })
            .then((json) => {
                this.mettreAJourPage(json.newScore);
                this.closeModal();
            })
            .catch((err) => {
                // La fenêtre reste ouverte : la saisie n'est pas perdue
                this.afficherErreur(`Le résultat n'a pas été enregistré : ${err.message}`);
            })
            .finally(() => {
                this.enregistrerTarget.disabled = false;
            });
    }

    // Met à jour toutes les cases de ce match (tableau ordinateur et cartes mobiles) et les boutons
    mettreAJourPage(newScore) {
        const efface = newScore === null || newScore === undefined;
        const [r, d] = efface ? ['', ''] : newScore.split(' - ');
        const forfait = r === 'F' ? this.recoit : (d === 'F' ? this.deplace : null);

        document.querySelectorAll('.score_partie-' + this.partieId).forEach((zone) => {
            zone.replaceChildren();
            if (efface) {
                zone.append(Object.assign(document.createElement('span'), { className: 'opacity-50', textContent: '–', ariaHidden: 'true' }),
                    Object.assign(document.createElement('span'), { className: 'sr-only', textContent: 'Score non saisi' }));
            } else {
                zone.append(Object.assign(document.createElement('strong'), { textContent: r }), '–',
                    Object.assign(document.createElement('strong'), { textContent: d }));
                if (forfait) zone.append(Object.assign(document.createElement('span'), { className: 'sr-only', textContent: ` (forfait de ${forfait})` }));
            }
        });

        // Score saisi : crayon « Modifier » à la place de « Saisir ».
        // Score effacé : retour au bouton « Saisir » si le match est passé, sinon au simple tiret.
        const brut = (v) => (v === 'F' ? '-1' : v);
        document.querySelectorAll(`[data-partie-id="${this.partieId}"]`).forEach((btn) => {
            btn.dataset.scoreReception = brut(r);
            btn.dataset.scoreDeplacement = brut(d);
            const passe = btn.dataset.matchPasse === '1';
            const afficher = efface ? btn.dataset.scoreAction === 'saisir' && passe : btn.dataset.scoreAction === 'modifier';
            btn.classList.toggle('hidden', !afficher);
        });
        const saisirVisible = efface && document.querySelector(`[data-partie-id="${this.partieId}"][data-score-action="saisir"][data-match-passe="1"]`);
        document.querySelectorAll('.score_partie-' + this.partieId).forEach((zone) => zone.classList.toggle('hidden', !!saisirVisible));

        // Le focus revient sur le bouton visible de la ligne d'origine
        const ligne = this.declencheur?.parentElement;
        this.declencheur = ligne?.querySelector(`[data-partie-id="${this.partieId}"]:not(.hidden)`) ?? this.declencheur;
    }

    afficherErreur(message) {
        this.erreurTarget.textContent = message;
        this.erreurTarget.classList.remove('hidden');
    }

    masquerErreur() {
        this.erreurTarget.textContent = '';
        this.erreurTarget.classList.add('hidden');
    }
}
