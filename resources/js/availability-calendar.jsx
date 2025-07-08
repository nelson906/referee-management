// resources/js/availability-calendar.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import AvailabilityCalendar from './Components/AvailabilityCalendar';  // ✅ GIUSTO!

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('referee-availability-calendar');  // ✅ ID GIUSTO!

    if (container) {
        try {
            // Get data from data attribute (come nelle viste che hai creato)
            const calendarData = JSON.parse(container.getAttribute('data-calendar'));

            const root = createRoot(container);
            root.render(
                React.createElement(AvailabilityCalendar, {
                    calendarData: calendarData  // ✅ PROPS GIUSTO!
                })
            );
        } catch (error) {
            console.error('Error rendering Availability Calendar:', error);
            container.innerHTML = '<div class="p-4 bg-red-100 text-red-700 rounded">Error loading availability calendar component</div>';
        }
    }
});
