// In resources/js/app.js
import './bootstrap.js';
import Alpine from 'alpinejs'

Alpine.start()

// If you want Alpine's instance to be available globally
window.Alpine = Alpine

import React from 'react';
import { createRoot } from 'react-dom/client';
import TournamentCalendar from './Components/TournamentCalendar.jsx';

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the tournament calendar if the container exists
    const calendarContainer = document.getElementById('tournament-calendar-root');
    if (calendarContainer) {
        const root = createRoot(calendarContainer);
        root.render(React.createElement(TournamentCalendar));
    }
});
