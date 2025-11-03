import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import multiMonthPlugin from '@fullcalendar/multimonth';

export default class extends Controller {
    static values = {
        eventsUrl: String,
        datatype: String,
        datedebut: String,
        datefin: String,
        pouleid: String,
        initialview: String,
        buttons: String,
        duration: String,
        csrfToken: String,
        initialDate:String
    }

    connect() {
        const el = this.element;
        if (!el) return;
        console.log(this.datatypeValue);

        const dateDebut = new Date(this.datedebutValue);
        const dateFin = new Date(this.datefinValue);

        // Ajuste la plage d'affichage
        const validStart = new Date(dateDebut);
        validStart.setDate(validStart.getDate() - 1);
        const validEnd = new Date(dateFin);
        validEnd.setDate(validEnd.getDate() + 1);

        const nbMois = (validEnd.getFullYear() - validStart.getFullYear()) * 12
            + (validEnd.getMonth() - validStart.getMonth()) + 1;

        switch (this.datatypeValue){
            case "journee":
              this.initialDate=new Date(dateDebut.getFullYear(), dateDebut.getMonth(), 1);
            case "partie":
                this.initialDate=dateDebut;
        }
        console.log(this.initialDate);
        this.calendar = new Calendar(el, {
            plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
            initialView: this.initialviewValue,
            eventColor: '#f59e0b',
            initialDate: this.initialDate,
            multiMonthMaxColumns: nbMois,
            locale: 'fr',
            timeZone: 'local',
            firstDay: 1,
            editable: true,
            selectable: true,
            // events: this.eventsUrlJourneeValue,
            eventSources: [
                {
                    url: this.eventsUrlValue,
                    color: 'orange',   // an option!
                    textColor: 'black' // an option!
                },

            ],
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'multiMonthFourMonth, dayGridFourWeek',
            },
            views: {
                multiMonthFourMonth: {
                    type: 'multiMonth',
                    buttonText: 'Phase',
                    duration: { months: nbMois },
                    validRange: {
                        start: new Date(dateDebut.getFullYear(), dateDebut.getMonth(), 1),
                        end: new Date(dateFin.getFullYear(), dateFin.getMonth() + 1, 0)
                    },
                },
                dayGridFourWeek: {
                    type: 'dayGrid',
                    buttonText: 'Journée',
                    duration: { week: this.durationValue },
                    validRange: {
                        start: this.initialDate,

                    },
                },
            },
            //Click sur un événement
            eventClick: this.onEventClick.bind(this),

            //Redimensionnement d'un événement
            eventResize:this.onEventDrop.bind(this),

            //Déplacement d'un événement
            eventDrop: this.onEventDrop.bind(this),

            //Click sur une date vide ou sélection de dates entraine la création d'une nouvelle journée
            // dateClick: this.onDateClick.bind(this),
            select: this.onDateClick.bind(this),
        });

        this.calendar.render();
    }

    //Méthode appelée lors du déplacement d'un événement pour mettre à jour la date dans la BDD
    onEventDrop(info) {
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
        .then(json => {
            console.log('Événement mis à jour :', json);
            //On recharche tous les événements pour tenir compte de la régularisation des numéros de journée
            this.calendar.refetchEvents();
        })
        .catch(err => {
            console.error(err);
            info.revert(); // annule le déplacement si erreur
        });
    }

    //Méthode appelée sur le click d'un événement qui affiche une modale avec les infos de l'événement et un bouton de suppression
    onEventClick(info) {
        // Remplir la modale
        document.getElementById('eventTitle').textContent = info.event.title;
        document.getElementById('eventDate').textContent = info.event.start.toLocaleDateString('fr-FR');

        // Afficher la modale (DaisyUI ouvre une modal en ajoutant 'modal-open' sur <body>)
        document.getElementById('eventModal').classList.add('modal-open');

        // Bouton de suppression
        document.getElementById('supprimer').onclick = () => {

            fetch('/admin/poule/' + this.pouleidValue + '/api/journees/' + info.event.id, {
                method: 'DELETE',
                headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfTokenValue
            },
            body: JSON.stringify({
                id: info.event.id,
            })
        })
            .then(response => {
                if (!response.ok) throw new Error('Erreur lors de la mise à jour');
                return response.json();
            })
            .then(json => {
                console.log('Suppression de la journée :', info.event.id);
                //On recharche tous les événements pour supprimer la journée de l'UI et tenir compte de la régularisation des numéros de journée
                 this.calendar.refetchEvents();
                document.getElementById('eventModal').classList.remove('modal-open');
            })
            .catch(err => {
                console.error(err);
                document.getElementById('eventModal').classList.remove('modal-open');

            });

        }
        // Bouton de fermeture
        document.getElementById('closeModal').onclick = function() {
            document.getElementById('eventModal').classList.remove('modal-open');
        }
    }

    //Méthode appelée sur le click d'une date vide pour créer une nouvelle journée
    onDateClick(info) {
        console.log("clickdate");
        if (this.datatypeValue==="journee"){
            this.onDateClickJournee(info);
        }

    }

    onDateClickJournee(info){
        fetch('/admin/poule/' + this.pouleidValue + '/api/journees', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfTokenValue
            },
            body: JSON.stringify({
                datedebut: info.startStr,
                datefin: info.endStr  ?? info.startStr
            })
        })
            .then(response => {
                if (!response.ok) throw new Error('Erreur lors de la création');
                return response.json();
            })
            .then(json => {
                console.log('Nouvelle journée créée :', json);
                //On recharche tous les événements pour afficher la nouvelle journée et tenir compte de la régularisation des numéros de journée
                this.calendar.refetchEvents();
            })
            .catch(err => {
                console.error(err);
            });
    }

    onDateClickPartie(info){
        fetch('/admin/poule/' + this.pouleidValue + '/api/parties', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfTokenValue
            },
            body: JSON.stringify({
                datedebut: info.startStr,
                datefin: info.endStr  ?? info.startStr
            })
        })
            .then(response => {
                if (!response.ok) throw new Error('Erreur lors de la création');
                return response.json();
            })
            .then(json => {
                console.log('Nouvelle partie créée :', json);
                //On recharche tous les événements pour afficher la nouvelle journée et tenir compte de la régularisation des numéros de journée
                this.calendar.refetchEvents();
            })
            .catch(err => {
                console.error(err);
            });
    }
}
