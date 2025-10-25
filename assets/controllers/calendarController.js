// controllers/calendar_controller.js
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import multiMonthPlugin from '@fullcalendar/multimonth';

export default class CalendarController {
    constructor(selector, eventsUrl, options = {}) {
        this.selector = selector;
        this.eventsUrl = eventsUrl;
        this.options = options;
    }

    init() {
        const el = document.querySelector(this.selector);
        if (!el) return;

        const defaultOptions = {
            plugins: [dayGridPlugin, interactionPlugin, multiMonthPlugin],
            initialView: 'multiMonthYear',
            multiMonthMaxColumns: 3,
            locale: 'fr',
            editable: true,
            selectable: true,
            events: this.eventsUrl,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'multiMonthYear,dayGridMonth,timeGridWeek'
            }
        };

        const calendar = new Calendar(el, { ...defaultOptions, ...this.options });
        calendar.render();
    }
}
