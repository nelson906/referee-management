import React from 'react';
import { createRoot } from 'react-dom/client';
import TournamentCalendar from './Components/TournamentCalendar';

document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('tournament-calendar-root');

  if (container) {
    try {
      // Get data from window object
      const calendarData = window.calendarData || {};
      const tournaments = calendarData.tournaments || [];
      const zones = calendarData.zones || [];
      const clubs = calendarData.clubs || [];
      const types = calendarData.types || [];
      const userRoles = calendarData.userRoles || ['Referee'];

      const root = createRoot(container);
      root.render(
        <TournamentCalendar
          initialTournaments={tournaments}
          initialZones={zones}
          initialClubs={clubs}
          initialTypes={types}
          initialUserRoles={userRoles}
        />
      );
    } catch (error) {
      console.error('Error rendering Tournament Calendar:', error);
      container.innerHTML = '<div class="p-4 bg-red-100 text-red-700 rounded">Error loading calendar component</div>';
    }
  }
});
