import { Controller } from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    static values = {
        multiple: Boolean,
        placeholder: String
    }
    connect() {
        new TomSelect(this.element, {
            plugins: ['remove_button'],
            maxItems: this.multipleValue ? null : 1,
            placeholder: this.placeholderValue || '',
            onItemAdd: function() {
                // Vide le champ de recherche après ajout d'une option
                this.setTextboxValue('');
                this.refreshOptions(false);
            },
            render: {
                option: function (data, escape) {
                    return `<div class="py-1 px-2 hover:bg-base-200 rounded-md">${escape(data.text)}</div>`;
                },
                item: function (data, escape) {
                    return `<div class="badge badge-neutral m-1">${escape(data.text)}</div>`;
                }
            }
        });
    }
}
