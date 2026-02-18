import { app } from './stimulus_bootstrap.js';
import CalendarController from './controllers/calendarController.js';
import TocbotController from './controllers/tocbotController.js';
import ScoreModalController from './controllers/score-modal-controller.js';

console.log('App.js chargé - version AssetMapper propre ! 🚀');

// On enregistre tes contrôleurs sur l'instance existante
app.register('calendar', CalendarController);
app.register('tocbot', TocbotController);
app.register('score-modal', ScoreModalController);
