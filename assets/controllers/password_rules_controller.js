import { Controller } from '@hotwired/stimulus';

// Règle de mot de passe du site : au moins 12 caractères, une minuscule,
// une majuscule, un chiffre et un caractère spécial (cf. App\Validator\PasswordPolicy côté serveur).
const PASSWORD_RULES = [
    { key: 'length', label: 'Au moins 12 caractères', test: (value) => value.length >= 12 },
    { key: 'lower', label: 'Au moins une lettre minuscule', test: (value) => /[a-z]/.test(value) },
    { key: 'upper', label: 'Au moins une lettre majuscule', test: (value) => /[A-Z]/.test(value) },
    { key: 'digit', label: 'Au moins un chiffre', test: (value) => /\d/.test(value) },
    { key: 'special', label: 'Au moins un caractère spécial', test: (value) => /[^A-Za-z0-9]/.test(value) },
];

/*
 * Affiche sous le(s) champ(s) mot de passe une liste de contraintes (croix rouge / coche verte)
 * et empêche la validation du formulaire tant qu'elles ne sont pas toutes respectées.
 *
 * Cibles :
 *  - password     : champ "nouveau mot de passe"
 *  - confirm       : champ "confirmer le mot de passe" (optionnel)
 *  - rules         : conteneur vide où la checklist du champ password est injectée
 *  - matchRules    : conteneur vide où la règle "mots de passe identiques" est injectée (si confirm présent)
 *  - submit        : bouton de soumission à activer/désactiver
 *
 * Valeur :
 *  - optional (bool) : si vrai, un champ password vide est considéré valide (ex: admin qui
 *    laisse le mot de passe vide pour ne pas le modifier).
 */
export default class extends Controller {
    static targets = ['password', 'confirm', 'rules', 'matchRules', 'submit'];
    static values = { optional: { type: Boolean, default: false } };

    connect() {
        this.ruleRows = {};
        this.matchRow = null;
        this.renderRules();
        this.renderMatchRule();
        this.update();
    }

    renderRules() {
        if (!this.hasRulesTarget) {
            return;
        }

        const list = document.createElement('ul');
        list.className = 'mt-2 space-y-1 text-xs';

        PASSWORD_RULES.forEach((rule) => {
            const row = this.createRuleRow(rule.label);
            list.appendChild(row);
            this.ruleRows[rule.key] = row;
        });

        this.rulesTarget.replaceChildren(list);
    }

    renderMatchRule() {
        if (!this.hasMatchRulesTarget) {
            return;
        }

        const list = document.createElement('ul');
        list.className = 'mt-2 space-y-1 text-xs';

        this.matchRow = this.createRuleRow('Les mots de passe sont identiques');
        list.appendChild(this.matchRow);

        this.matchRulesTarget.replaceChildren(list);
    }

    createRuleRow(label) {
        const row = document.createElement('li');
        row.className = 'password-rule flex items-center gap-1.5 text-error';

        const icon = document.createElement('span');
        icon.className = 'password-rule-icon font-bold';
        icon.textContent = '✗';

        const text = document.createElement('span');
        text.textContent = label;

        row.append(icon, text);

        return row;
    }

    setRuleState(row, valid) {
        if (!row) {
            return;
        }

        row.classList.toggle('text-error', !valid);
        row.classList.toggle('text-success', valid);
        row.querySelector('.password-rule-icon').textContent = valid ? '✓' : '✗';
    }

    update() {
        const password = this.hasPasswordTarget ? this.passwordTarget.value : '';
        const skipRules = this.optionalValue && password === '';

        if (this.hasRulesTarget) {
            this.rulesTarget.classList.toggle('hidden', skipRules);
        }

        let passwordValid = true;
        PASSWORD_RULES.forEach((rule) => {
            const valid = skipRules || rule.test(password);
            this.setRuleState(this.ruleRows[rule.key], valid);
            if (!valid) {
                passwordValid = false;
            }
        });

        let matchValid = true;
        if (this.hasConfirmTarget) {
            const confirm = this.confirmTarget.value;
            const skipMatch = skipRules && confirm === '';

            if (this.hasMatchRulesTarget) {
                this.matchRulesTarget.classList.toggle('hidden', skipMatch);
            }

            if (!skipMatch) {
                matchValid = password !== '' && password === confirm;
                this.setRuleState(this.matchRow, matchValid);
            }
        }

        const allValid = passwordValid && matchValid;

        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = !allValid;
            this.submitTarget.classList.toggle('btn-disabled', !allValid);
            this.submitTarget.classList.toggle('opacity-50', !allValid);
            this.submitTarget.classList.toggle('cursor-not-allowed', !allValid);
        }

        return allValid;
    }

    preventInvalidSubmit(event) {
        if (!this.update()) {
            event.preventDefault();
        }
    }
}
