// In resources/js/app.js
import './bootstrap.js';
import Alpine from 'alpinejs'

Alpine.start()

// If you want Alpine's instance to be available globally
window.Alpine = Alpine

import React from 'react';
import { createRoot } from 'react-dom/client';
import TournamentCalendar from './Components/TournamentCalendar.jsx';
import AvailabilityCalendar from './Components/AvailabilityCalendar.jsx';

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the tournament calendar if the container exists
    const calendarContainer = document.getElementById('tournament-calendar-root');
    if (calendarContainer) {
        const calendarData = window.calendarData || {};
        const tournaments = calendarData.tournaments || [];
        const zones = calendarData.zones || [];
        const clubs = calendarData.clubs || [];
        const types = calendarData.types || [];
        const userRoles = calendarData.userRoles || ['Referee'];

        const root = createRoot(calendarContainer);
        root.render(React.createElement(TournamentCalendar, {
            initialTournaments: tournaments,
            initialZones: zones,
            initialClubs: clubs,
            initialTypes: types,
            initialUserRoles: userRoles
        }));
    }

    // Initialize the availability calendar if the container exists
    const availabilityContainer = document.getElementById('availability-calendar-root');
    if (availabilityContainer) {
        const calendarData = window.availabilityCalendarData || {};

        console.log('Initializing Availability Calendar with data:', calendarData);

        const root = createRoot(availabilityContainer);
        root.render(React.createElement(AvailabilityCalendar, {
            calendarData: calendarData
        }));
    }
});
