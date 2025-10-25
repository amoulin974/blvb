import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

import CalendarController from './controllers/calendarController';

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.querySelector('#calendar');
    const pouleId = calendarEl.dataset.pouleId;
    new CalendarController('#calendar', '/admin/poule/'+pouleId+'/api/journees', {
        initialView: 'dayGridMonth',
        eventColor: '#f59e0b',
        firstDay: 1,
        locale: 'fr',
        eventClick: function(info) {
            alert('Événement : ' + info.event.title);
        }
    }).init();
});
