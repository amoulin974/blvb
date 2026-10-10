import { Controller } from '@hotwired/stimulus';

// Équivalent léger de DataTables (tri par colonne, recherche texte, pagination) appliqué
// à un tableau HTML déjà rendu côté serveur, sans dépendance externe : aucun appel
// réseau, tout se passe sur les lignes déjà présentes dans le DOM.
//  - perPage = 0 : pas de pagination, toutes les lignes restent affichées (et trouvables par Ctrl+F) ;
//  - cible "compteur" (facultative) : « 177 joueurs » ou, pendant une recherche, « 3 joueurs sur 177 »,
//    avec les valeurs singulier / pluriel ; à placer dans un élément aria-live pour les lecteurs d'écran.
export default class extends Controller {
    static targets = ['body', 'pagination', 'search', 'compteur'];
    static values = {
        perPage: { type: Number, default: 10 },
        singulier: { type: String, default: 'ligne' },
        pluriel: { type: String, default: 'lignes' },
    };

    connect() {
        this.rows = Array.from(this.bodyTarget.querySelectorAll('tr'));
        this.headers = Array.from(this.element.querySelectorAll('thead th'));
        this.headers.forEach((th) => {
            th.dataset.label = th.textContent.trim();
            if (th.dataset.datatableNoSort === undefined) {
                th.classList.add('cursor-pointer', 'select-none', 'hover:underline');
                th.addEventListener('click', () => this.sortBy(th));
                // Tri accessible au clavier
                th.tabIndex = 0;
                th.setAttribute('aria-sort', 'none');
                th.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        this.sortBy(th);
                    }
                });
            }
        });

        this.sortColumn = null;
        this.sortDir = 'asc';
        this.page = 1;

        this.render();
    }

    filter() {
        this.page = 1;
        this.render();
    }

    sortBy(th) {
        const index = this.headers.indexOf(th);
        if (this.sortColumn === index) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = index;
            this.sortDir = 'asc';
        }
        this.page = 1;
        this.render();
    }

    goToPage(event) {
        const page = parseInt(event.currentTarget.dataset.page, 10);
        if (!Number.isNaN(page)) {
            this.page = page;
            this.render();
        }
    }

    render() {
        const query = this.hasSearchTarget ? this.searchTarget.value.trim().toLowerCase() : '';

        let visible = this.rows.filter((row) => !query || row.textContent.toLowerCase().includes(query));

        if (this.sortColumn !== null) {
            const index = this.sortColumn;
            const dir = this.sortDir === 'asc' ? 1 : -1;
            visible = [...visible].sort((a, b) => {
                const va = this.cellValue(a, index);
                const vb = this.cellValue(b, index);
                if (va.isNumeric && vb.isNumeric) {
                    return (va.number - vb.number) * dir;
                }
                return va.text.localeCompare(vb.text, 'fr', { sensitivity: 'base' }) * dir;
            });
        }

        const perPage = this.perPageValue > 0 ? this.perPageValue : Math.max(1, visible.length);
        const totalPages = Math.max(1, Math.ceil(visible.length / perPage));
        if (this.page > totalPages) {
            this.page = totalPages;
        }
        const start = (this.page - 1) * perPage;
        const pageRows = visible.slice(start, start + perPage);

        this.rows.forEach((row) => {
            row.style.display = 'none';
        });
        pageRows.forEach((row) => {
            this.bodyTarget.appendChild(row);
            row.style.display = '';
        });

        this.updateHeaderIndicators();
        this.renderCompteur(visible.length, query !== '');
        if (this.perPageValue > 0) {
            this.renderPagination(visible.length, totalPages, start);
        }
    }

    renderCompteur(nombre, filtre) {
        if (!this.hasCompteurTarget) {
            return;
        }
        const total = this.rows.length;
        const mot = (n) => (n > 1 ? this.plurielValue : this.singulierValue);
        this.compteurTarget.textContent = filtre
            ? (nombre === 0 ? `Aucun résultat sur ${total} ${mot(total)}` : `${nombre} ${mot(nombre)} sur ${total}`)
            : `${total} ${mot(total)}`;
    }

    cellValue(row, index) {
        const cell = row.children[index];
        const text = cell ? cell.textContent.trim() : '';
        const numeric = /^-?\d+([.,]\d+)?$/.test(text);

        return {
            text: text.toLowerCase(),
            isNumeric: numeric,
            number: numeric ? parseFloat(text.replace(',', '.')) : 0,
        };
    }

    updateHeaderIndicators() {
        this.headers.forEach((th, index) => {
            // Colonne non triable : contenu laissé tel quel (ex. libellé réservé aux lecteurs d'écran)
            if (th.dataset.datatableNoSort !== undefined) return;
            if (index === this.sortColumn) {
                th.textContent = `${th.dataset.label} ${this.sortDir === 'asc' ? '▲' : '▼'}`;
                th.setAttribute('aria-sort', this.sortDir === 'asc' ? 'ascending' : 'descending');
            } else {
                th.textContent = th.dataset.label;
                th.setAttribute('aria-sort', 'none');
            }
        });
    }

    renderPagination(total, totalPages, start) {
        if (!this.hasPaginationTarget) {
            return;
        }

        if (total === 0) {
            this.paginationTarget.innerHTML = '<span class="text-sm opacity-60">Aucun résultat</span>';

            return;
        }

        const end = Math.min(start + this.perPageValue, total);
        const info = `<span class="text-sm opacity-60">${start + 1}-${end} sur ${total}</span>`;

        let buttons = '';
        for (let p = 1; p <= totalPages; p++) {
            buttons += `<button type="button" data-action="datatable#goToPage" data-page="${p}" class="btn btn-xs ${p === this.page ? 'btn-primary' : 'btn-ghost'}">${p}</button>`;
        }

        this.paginationTarget.innerHTML = `
            <div class="flex items-center gap-2">${info}</div>
            <div class="flex items-center gap-1 flex-wrap">${buttons}</div>
        `;
    }
}
