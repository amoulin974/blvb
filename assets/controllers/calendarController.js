import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import multiMonthPlugin from '@fullcalendar/multimonth';

export default class extends Controller {
    static values = {
        eventsUrl: String,
        datedebut: String,
        datefin: String,
        pouleid: String,
        csrfToken: String
    }

    connect() {
        const el = this.element;
        if (!el) return;

        const dateDebut = new Date(this.datedebutValue);
        const dateFin = new Date(this.datefinValue);

        // Ajuste la plage d'affichage
        const validStart = new Date(dateDebut);
        validStart.setDate(validStart.getDate() - 1);
        const validEnd = new Date(dateFin);
        validEnd.setDate(validEnd.getDate() + 1);

        const nbMois = (validEnd.getFullYear() - validStart.getFullYear()) * 12
            + (validEnd.getMonth() - validStart.getMonth()) + 1;

        const calendar = new Calendar(el, {
            plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
            initialView: "multiMonthFourMonth",
            eventColor: '#f59e0b',
            initialDate: new Date(dateDebut.getFullYear(), dateDebut.getMonth(), 1),
            multiMonthMaxColumns: nbMois,
            locale: 'fr',
            timeZone: 'local',
            firstDay: 1,
            editable: true,
            selectable: true,
            events: this.eventsUrlValue,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'multiMonthYear, multiMonth',
            },
            views: {
                multiMonthFourMonth: {
                    type: 'multiMonth',
                    buttonText: 'durée phase',
                    duration: { months: nbMois },
                    validRange: {
                        start: new Date(dateDebut.getFullYear(), dateDebut.getMonth(), 1),
                        end: new Date(dateFin.getFullYear(), dateFin.getMonth() + 1, 0)
                    },
                },
            },
            eventClick: function(info) {
                alert('Événement : ' + info.event.title);
            },
            eventDrop: (info) => {
                const eventId = info.event.id;
                const newStart = info.event.startStr;
                const newEnd = info.event.endStr  ?? info.event.endStr ;

                fetch('/admin/poule/' + this.pouleidValue + '/api/journees/'+eventId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfTokenValue
                    },
                    body: JSON.stringify({
                        datedebut: newStart,
                        datefin: newEnd
                    })
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Erreur lors de la mise à jour');
                        return response.json();
                    })
                    .then(json => console.log('Événement mis à jour :', json))
                    .catch(err => {
                        console.error(err);
                        info.revert(); // annule le déplacement si erreur
                    });
            }
        });

        calendar.render();
    }
}
