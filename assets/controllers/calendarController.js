import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import multiMonthPlugin from '@fullcalendar/multimonth';
import moment from "moment";
import "moment/locale/fr";

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
        const dateDebut = moment(this.datedebutValue, "YYYY-MM-DD");
        const dateFin = moment(this.datefinValue, "YYYY-MM-DD");

        // 1er jour du mois précédent
        const validStart = dateDebut.clone().subtract(1, 'month').startOf('month');
        // dernier jour du mois précédent

        const validEnd = dateFin.clone().add(1, 'month');

        let initialDate = dateDebut.clone();
        while (initialDate.isoWeekday() !== 1) { // 1 = lundi
            initialDate.subtract(1, 'day');
        }

        const nbMois = dateFin.diff(dateDebut, 'months') + 1;



        this.calendar = new Calendar(el, {
            plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
            initialView: this.initialviewValue,
            eventColor: '#f59e0b',
            initialDate: initialDate.format("YYYY-MM-DD"),
            multiMonthMaxColumns: nbMois,
            locale: 'fr',
            timeZone: 'UTC',
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
                left: 'prev,next today',  // boutons visibles
                center: 'title',
                right: 'multiMonthFourMonth, dayGridSixDay'
            },
            datesSet: (info) => {
                const buttons = document.querySelectorAll('.fc-prev-button, .fc-next-button');
                if(info.view.type === 'multiMonthFourMonth'){
                    buttons.forEach(btn => btn.disabled = true);
                } else {
                    buttons.forEach(btn => btn.disabled = false);
                }
            },
            views: {
                multiMonthFourMonth: {
                    type: 'multiMonth',
                    buttonText: 'Phase',
                    duration: { months: nbMois },
                    validRange: {
                        start: validStart.format("YYYY-MM-DD"),
                        end: validEnd.format("YYYY-MM-DD"),
                    },
                },
                dayGridSixDay: {
                    type: 'dayGrid',
                    buttonText: 'Journée',
                    duration: { days: 7 },
                    validRange: {
                        start: validStart.format("YYYY-MM-DD"),
                        end: validEnd.format("YYYY-MM-DD"),
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
        if (this.datatypeValue==="journee"){
            this.onEventDropJournee(info);
        }else if (this.datatypeValue==="partie"){
            this.onEventDropPartie(info);
        }else{
            console.log('aucun événement au clic sur une date créé pour '+ this.datatypeValue);
        }

    }

    //Méthode appelée sur le click d'un événement qui affiche une modale avec les infos de l'événement et un bouton de suppression
    onEventClick(info) {
        if (this.datatypeValue==="journee"){
            this.onEventClickJournee(info);
        }else if (this.datatypeValue==="partie"){
            this.onEventClickPartie(info);
        }else{
            console.log('aucun événement au clic créé pour '+ this.datatypeValue);
        }

    }

    //Méthode appelée sur le click d'une date vide pour créer une nouvelle journée
    onDateClick(info) {
        console.log("clickdate");
        if (this.datatypeValue==="journee"){
            this.onDateClickJournee(info);
        }else if (this.datatypeValue==="partie"){
            this.onDateClickPartie(info);
        }else{
            console.log('aucun événement au clic sur une date créé pour '+ this.datatypeValue);
        }

    }

    onEventDropJournee(info){
        const eventId = info.event.id;
        const newStart = info.event.startStr;
        const newEnd = info.event.endStr  ?? info.event.endStr ;

        fetch('/admin/journee/' + this.pouleidValue + '/api/'+eventId, {
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
    onEventClickJournee(info){
        // Remplir la modale
        document.getElementById('eventTitle').textContent = info.event.title;
        document.getElementById('eventDate').textContent = info.event.start.toLocaleDateString('fr-FR');

        // Afficher la modale (DaisyUI ouvre une modal en ajoutant 'modal-open' sur <body>)
        document.getElementById('eventModal').classList.add('modal-open');

        // Bouton de suppression
        document.getElementById('supprimer').onclick = () => {

            fetch('/admin/journee/' + this.pouleidValue + '/api/' + info.event.id, {
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

    onDateClickJournee(info){
        fetch('/admin/journee/' + this.pouleidValue + '/api', {
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

    //-------------------------------------------------------Partie eventPartie
    onEventClickPartie(info){


        // ouvrir la modale
        const modal = document.querySelector('#partieModal');
        modal.classList.add('modal-open');

        const modalTitle=modal.querySelector('#eventTitle')
        const modalBody=modal.querySelector('#modalBody');
        // changer le titre
        modalTitle.textContent = info.event.title;
        console.log(info.event.id);
        let url="/admin/partie/"+this.pouleidValue+"/api/"+info.event.id+"/getmodal"
        // charger le formulaire via fetch
        fetch(url)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
                const form = modalBody.querySelector('form');
                if(form) {
                    form.addEventListener('submit', (e) =>{
                        e.preventDefault();
                        const data = new FormData(form);
                        fetch(url, {
                            method: "POST",
                            body: data
                        })
                            .then(resp => resp.json())
                            .then(json => {
                                if(json.success){
                                    modal.classList.remove('modal-open'); // fermer la modal
                                    this.calendar.refetchEvents();            // rafraîchir le calendrier
                                }
                            });
                    });
                }

                const suprrimer = modal.querySelector('#supprimer')
                if (suprrimer){
                    suprrimer.addEventListener('click', (e)=>{
                        fetch('/admin/partie/'+ this.pouleidValue + '/api/'+info.event.id,{
                            method: "DELETE"
                        })
                            .then(resp => resp.json())
                            .then(json => {
                                console.log(json)
                                if(json.success){
                                    modal.classList.remove('modal-open'); // fermer la modal
                                    this.calendar.refetchEvents();            // rafraîchir le calendrier
                                    console.log("suppression")
                                }
                            });
                    })
                }
            });
    }

    onDateClickPartie(info){
        const modal = document.querySelector('#partieModal');
        modal.classList.add('modal-open');

        const modalTitle=modal.querySelector('#eventTitle')
        const modalBody=modal.querySelector('#modalBody');
        let url="/admin/partie/"+this.pouleidValue+"/api/getmodal/new"
        // charger le formulaire via fetch
        fetch(url)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
                const form = modalBody.querySelector('form');
                if(form) {
                    console.log(info)

                    //Défini la date dans le formulaire
                    const modal=document.querySelector('#partieModal');
                    const champdate=form.querySelector('#partie_calendar_date');
                    let date = new Date(info.startStr);
                    date.setUTCHours(20, 30);
                    let localDateTime = date.toISOString().slice(0, 16);
                    champdate.value=localDateTime;

                    //On cache le bouton supprimer
                    const buttonsuppr=modal.querySelector('#supprimer')
                    buttonsuppr.classList.add('hidden');
                    form.addEventListener('submit', (e) =>{
                        e.preventDefault();
                        const data = new FormData(form);
                        fetch(url, {
                            method: "POST",
                            body: data
                        })
                            .then(resp => resp.json())
                            .then(json => {
                                if(json.success){
                                    modal.classList.remove('modal-open'); // fermer la modal
                                    buttonsuppr.classList.remove('hidden');
                                    this.calendar.refetchEvents();            // rafraîchir le calendrier
                                }
                            });
                    });
                }
            });
    }
    onEventDropPartie(info){


        console.log(info.event)
        fetch('/admin/partie/'  +this.pouleidValue+  '/api/'+info.event.id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfTokenValue
            },
            body: JSON.stringify({
                datedebut: info.event.startStr,
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

    //
}
