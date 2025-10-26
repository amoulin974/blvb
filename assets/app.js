import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import { Application } from '@hotwired/stimulus';
import CalendarController from './controllers/calendarController.js';

console.log('App.js chargé - welcome to AssetMapper! 🎉');

const application = Application.start();
application.register('calendar', CalendarController);
