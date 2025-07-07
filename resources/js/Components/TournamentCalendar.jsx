import React, { useState, useEffect, useRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import itLocale from '@fullcalendar/core/locales/it';


const TournamentCalendar = ({ initialTournaments, initialZones, initialClubs, initialTypes, initialUserRoles }) => {
    const [filteredTournaments, setFilteredTournaments] = useState(initialTournaments || []);
    const [selectedZone, setSelectedZone] = useState('all');
    const [selectedType, setSelectedType] = useState('all');
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [tournamentToDelete, setTournamentToDelete] = useState(null);
    const calendarRef = useRef(null);

    // Check if user has admin privileges
    const isAdmin = initialUserRoles?.some(role =>
        ['Admin', 'SuperAdmin', 'NationalAdmin'].includes(role)
    ) || false;

    // FUNZIONE HELPER PER GESTIRE I PERCORSI URL
    const getCorrectPath = (path) => {
        // Check dell'ambiente attraverso window.location.origin ed inserimento di /public
        // per evitare errori 404 in fase di sviluppo vs produzione
        let publicPath = (window.location.origin === 'https://www.arbitrigolf.it') ? '/public' : '';
        return publicPath + path;
    };

    // Apply filters whenever selectedZone or selectedType changes
    useEffect(() => {
        filterTournaments();
    }, [selectedZone, selectedType]);

    // Color palette for tournament types - assign colors to each type
    const typeColors = {
        // Default fallback color for unknown types
        default: {
            background: '#10B981', // Green
            text: '#ffffff',      // White
        },
        // Special styling for national tournaments
        national: {
            background: '#4F46E5', // Indigo
            text: '#ffffff',       // White
        },
        // Colors by tournament type (map type.id to colors)
        'T18': { background: '#3B82F6', text: '#ffffff' }, // Blue
        'S14': { background: '#8B5CF6', text: '#ffffff' }, // Purple
        'GN-36': { background: '#EC4899', text: '#ffffff' }, // Pink
        'GN-54': { background: '#EF4444', text: '#ffffff' }, // Red
        'GN-72': { background: '#F59E0B', text: '#ffffff' }, // Amber
        'GN-72/54': { background: '#F97316', text: '#ffffff' }, // Orange
        'CI': { background: '#84CC16', text: '#ffffff' }, // Lime
        'CNZ': { background: '#14B8A6', text: '#ffffff' }, // Teal
        'TNZ': { background: '#06B6D4', text: '#ffffff' }, // Cyan
    };

    // Border colors for different statuses
    const statusBorders = {
        // Default fallback border
        default: '#9CA3AF', // Gray
        // Status-specific borders
        'Pending': '#F59E0B',   // Amber - upcoming tournament
        'In Progress': '#10B981', // Green - active tournament
        'Completed': '#6B7280',  // Gray - past tournament
        'Cancelled': '#EF4444',  // Red - cancelled tournament
        'Postponed': '#F97316',  // Orange - postponed
    };

    // Get the appropriate color for a tournament type
    const getTypeColors = (tournament) => {
        if (!tournament.type) return typeColors.default;

        // If it's a national tournament, use the national style
        if (tournament.type.is_national) return typeColors.national;

        // Get color by type code if available
        const typeCode = tournament.type.code || tournament.type.id;

        if (typeCode && typeColors[typeCode]) {
            return typeColors[typeCode];
        }

        // If we have a type name, try to match it
        const typeName = tournament.type.name;
        if (typeName && typeColors[typeName]) {
            return typeColors[typeName];
        }

        // Fallback to default color
        return typeColors.default;
    };

    // Get border color for a tournament status
    const getStatusBorder = (status) => {
        if (!status) return statusBorders.default;
        return statusBorders[status] || statusBorders.default;
    };

    // Filter tournaments based on selected zone and type
    const filterTournaments = () => {
        if (!initialTournaments || initialTournaments.length === 0) {
            setFilteredTournaments([]);
            return;
        }

        let filtered = [...initialTournaments];

        // Filter by zone
        if (selectedZone !== 'all') {
            filtered = filtered.filter(tournament => {
                if (!tournament.club) return false;

                const zoneId = tournament.club.zone_id;
                return zoneId !== undefined && zoneId !== null && zoneId.toString() === selectedZone;
            });
        }

        // Filter by type with null handling
        if (selectedType !== 'all') {
            filtered = filtered.filter(tournament => {
                if (!tournament.type) return false;

                let typeId = null;

                if (typeof tournament.type === 'object') {
                    typeId = tournament.type.id;
                } else {
                    typeId = tournament.type;
                }

                if (typeId === null || typeId === undefined) return false;

                try {
                    return typeId.toString() === selectedType;
                } catch (e) {
                    console.error("Error comparing type IDs:", e);
                    return false;
                }
            });
        }

        setFilteredTournaments(filtered);
    };

    // Format tournaments for FullCalendar with color coding
    const events = filteredTournaments.map(tournament => {
        // Ensure dates are valid
        let startDate = tournament.start_date;
        let endDate = tournament.end_date;

        try {
            endDate = new Date(new Date(tournament.end_date).getTime() + 86400000);
        } catch (e) {
            console.warn("Error processing date for tournament", tournament.id, e);
            endDate = startDate;
        }

        // Get colors based on tournament type and status
        const typeColor = getTypeColors(tournament);
        const borderColor = getStatusBorder(tournament.status);

        return {
            id: tournament.id,
            title: tournament.name || "Unnamed Tournament",
            start: startDate,
            end: endDate,
            backgroundColor: typeColor.background,
            textColor: typeColor.text,
            borderColor: borderColor,
            // Add a thicker border to make status more visible
            borderWidth: '2px',
            extendedProps: {
                club: tournament.club,
                type: tournament.type,
                status: tournament.status,
                assigned_referees: tournament.assigned_referees || [],
                typeColor: typeColor,
                statusBorder: borderColor,
            }
        };
    });

// Handle edit tournament - USA ROUTE ADMIN
    const handleEditTournament = (tournamentId, e) => {
        e.stopPropagation();
        window.location.href = getCorrectPath(`/admin/tournaments/${tournamentId}/edit`);
    };

    // Handle delete tournament (show confirmation) - USA ROUTE ADMIN
    const handleDeleteClick = (tournamentId, e) => {
        e.stopPropagation();
        setTournamentToDelete(tournamentId);
        setShowDeleteConfirm(true);
    };

    // Confirm and execute tournament deletion - USA ROUTE ADMIN
    const confirmDelete = () => {
        if (!tournamentToDelete) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = getCorrectPath(`/admin/tournaments/${tournamentToDelete}`);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';

        form.appendChild(methodInput);
        document.body.appendChild(form);

        form.submit();
    };

    const cancelDelete = () => {
        setShowDeleteConfirm(false);
        setTournamentToDelete(null);
    };

    return (
        <div className="tournament-calendar-container">
            {/* Filter Controls */}
            <div className="filter-controls mb-4 flex flex-wrap gap-4 bg-white p-4 rounded-lg shadow">
                <div className="zone-filter">
                    <label htmlFor="zone-select" className="block text-sm font-medium text-gray-700 mb-1">
                        Zona
                    </label>
                    <select
                        id="zone-select"
                        className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        value={selectedZone}
                        onChange={(e) => setSelectedZone(e.target.value)}
                    >
                        <option value="all">Tutte le zone</option>
                        {initialZones?.map(zone => (
                            <option key={zone.id} value={zone.id.toString()}>
                                {zone.name}
                            </option>
                        )) || []}
                    </select>
                </div>

                <div className="type-filter">
                    <label htmlFor="type-select" className="block text-sm font-medium text-gray-700 mb-1">
                        Tipo Torneo
                    </label>
                    <select
                        id="type-select"
                        className="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        value={selectedType}
                        onChange={(e) => setSelectedType(e.target.value)}
                    >
                        <option value="all">Tutti i tipi</option>
                        {initialTypes?.map(type => (
                            <option key={type.id} value={type.id.toString()}>
                                {type.name} {type.is_national ? '(Nazionale)' : ''}
                            </option>
                        )) || []}
                    </select>
                </div>
            </div>

            {/* Color Legend */}
            <div className="mb-4 bg-white p-3 rounded-lg shadow">
                <h3 className="text-sm font-medium text-gray-700 mb-2">Legenda</h3>
                <div className="flex flex-wrap gap-2">
                    {/* Type legend */}
                    <div className="mr-4">
                        <h4 className="text-xs text-gray-600 mb-1">Tipi di torneo:</h4>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(typeColors).map(([type, colors]) => (
                                <div key={type} className="flex items-center text-xs">
                                    <div
                                        style={{ backgroundColor: colors.background }}
                                        className="w-4 h-4 rounded-sm mr-1"
                                    ></div>
                                    <span>{type === 'default' ? 'Altro' : type === 'national' ? 'Nazionale' : type}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Status legend */}
                    <div>
                        <h4 className="text-xs text-gray-600 mb-1">Stato torneo:</h4>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(statusBorders).map(([status, color]) => (
                                <div key={status} className="flex items-center text-xs">
                                    <div
                                        style={{ borderColor: color, borderWidth: '2px' }}
                                        className="w-4 h-4 border rounded-sm mr-1 bg-white"
                                    ></div>
                                    <span>{status === 'default' ? 'Altro' : status}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* Calendar */}
            <div className="bg-white p-4 rounded-lg shadow">
                <FullCalendar
                    ref={calendarRef}
                    plugins={[dayGridPlugin, interactionPlugin]}
                    initialView="dayGridMonth"
                    events={events}
                    eventDisplay="block"
                    locale={itLocale}
                    headerToolbar={{
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,dayGridWeek'
                    }}
                    height="auto"
                    eventClick={(info) => {
                        // CORREZIONE PRINCIPALE: Usa getCorrectPath anche per eventClick
                        window.location.href = getCorrectPath(`/tournaments/${info.event.id}`);
                    }}
                    eventContent={(eventInfo) => {
                        // Only show controls on the first day of multi-day events
                        const isFirstDay = eventInfo.isStart;

                        // Get status for display
                        const status = eventInfo.event.extendedProps.status;

                        return (
                            <div className="fc-event-container relative group">
                                <div className="fc-event-title font-medium flex items-center">
                                    {eventInfo.event.title}
                                    {isFirstDay && status && (
                                        <span className="ml-1 text-xs rounded-full px-1 py-0.5 inline-block"
                                            style={{
                                                backgroundColor: eventInfo.event.extendedProps.statusBorder,
                                                color: '#fff',
                                                fontSize: '0.6rem'
                                            }}>
                                            {status}
                                        </span>
                                    )}
                                </div>
                                <div className="fc-event-text text-xs truncate">
                                    {eventInfo.event.extendedProps.club?.name || "Club N/A"}
                                </div>

                                {/* Display tournament type if first day */}
                                {isFirstDay && eventInfo.event.extendedProps.type && (
                                    <div className="fc-event-type text-xs">
                                        <span className="font-medium">
                                            {eventInfo.event.extendedProps.type.name ||
                                                eventInfo.event.extendedProps.type.code ||
                                                ""}
                                        </span>
                                    </div>
                                )}

                                {/* Referee badge */}
                                {eventInfo.event.extendedProps.assigned_referees &&
                                    eventInfo.event.extendedProps.assigned_referees.length > 0 && (
                                        <div className="fc-event-referees text-xs mt-1">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white bg-opacity-30">
                                                {eventInfo.event.extendedProps.assigned_referees.length} arbitri
                                            </span>
                                        </div>
                                    )}

                                {/* Admin controls - only display on first day of event and for admin users */}
                                {isAdmin && isFirstDay && (
                                    <div className="event-controls opacity-0 group-hover:opacity-100 absolute top-0 right-0 bg-white bg-opacity-90 rounded-bl-md p-1 shadow transition-opacity duration-200">
                                        <button
                                            onClick={(e) => handleEditTournament(eventInfo.event.id, e)}
                                            className="text-blue-600 hover:text-blue-800 mr-2 focus:outline-none"
                                            title="Modifica torneo"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            onClick={(e) => handleDeleteClick(eventInfo.event.id, e)}
                                            className="text-red-600 hover:text-red-800 focus:outline-none"
                                            title="Elimina torneo"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                )}
                            </div>
                        );
                    }}
                />
            </div>

            {/* Delete Confirmation Modal */}
            {showDeleteConfirm && (
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md mx-auto">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Conferma eliminazione</h3>
                        <p className="text-gray-600 mb-6">
                            Sei sicuro di voler eliminare questo torneo? Questa azione è irreversibile.
                        </p>
                        <div className="flex justify-end">
                            <button
                                onClick={cancelDelete}
                                className="bg-gray-200 text-gray-800 px-4 py-2 rounded mr-2 hover:bg-gray-300 focus:outline-none"
                            >
                                Annulla
                            </button>
                            <button
                                onClick={confirmDelete}
                                className="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 focus:outline-none"
                            >
                                Elimina
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Results counter */}
            <div className="mt-4 text-sm text-gray-600">
                {filteredTournaments.length} tornei visualizzati
                {(selectedZone !== 'all' || selectedType !== 'all') && (
                    <button
                        onClick={() => {
                            setSelectedZone('all');
                            setSelectedType('all');
                        }}
                        className="ml-2 text-indigo-600 hover:text-indigo-800"
                    >
                        Rimuovi filtri
                    </button>
                )}
            </div>
        </div>
    );
};

export default TournamentCalendar;
