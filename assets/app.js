import { app } from './stimulus_bootstrap.js';
import CalendarController from './controllers/calendarController.js';
import TocbotController from './controllers/tocbotController.js';

console.log('App.js chargé - version AssetMapper propre ! 🚀');

// On enregistre tes contrôleurs sur l'instance existante
app.register('calendar', CalendarController);
app.register('tocbot', TocbotController);
