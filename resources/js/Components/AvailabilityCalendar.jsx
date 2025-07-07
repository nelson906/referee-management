import React, { useState, useEffect, useRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import itLocale from '@fullcalendar/core/locales/it';

const AvailabilityCalendar = ({ calendarData }) => {
    const [showModal, setShowModal] = useState(false);
    const [selectedEvent, setSelectedEvent] = useState(null);
    const calendarRef = useRef(null);

    // Debug
    console.log('Availability Calendar Data:', calendarData);
    console.log('Total tournaments:', calendarData?.tournaments?.length || 0);

    // Format tournaments for FullCalendar
    const events = (calendarData?.tournaments || []).map(tournament => {
        return {
            id: tournament.id,
            title: tournament.title,
            start: tournament.start,
            end: tournament.end,
            backgroundColor: tournament.color,
            borderColor: tournament.color,
            textColor: '#ffffff',
            extendedProps: {
                ...tournament.extendedProps
            }
        };
    });

    // Handle event click
    const handleEventClick = (info) => {
        setSelectedEvent(info.event);
        setShowModal(true);
    };

    // Close modal
    const closeModal = () => {
        setShowModal(false);
        setSelectedEvent(null);
    };

    // Toggle availability
    const toggleAvailability = async (tournamentId, isAvailable) => {
        try {
            const response = await fetch('/referee/availability/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    tournament_id: tournamentId,
                    available: isAvailable
                })
            });

            const data = await response.json();

            if (data.success) {
                // Reload the page to update the calendar
                window.location.reload();
            } else {
                alert('Errore: ' + (data.message || 'Operazione fallita'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Errore di connessione');
        }
    };

    return (
        <div className="availability-calendar-container">
            {/* Legend */}
            <div className="bg-white shadow rounded-lg p-4 mb-6">
                <h3 className="text-sm font-medium text-gray-900 mb-3">Legenda:</h3>
                <div className="flex flex-wrap gap-4 text-sm">
                    <div className="flex items-center">
                        <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#10B981'}}></div>
                        <span>Assegnato</span>
                    </div>
                    <div className="flex items-center">
                        <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#3B82F6'}}></div>
                        <span>Disponibile</span>
                    </div>
                    <div className="flex items-center">
                        <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#F59E0B'}}></div>
                        <span>Aperto</span>
                    </div>
                    <div className="flex items-center">
                        <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#6B7280'}}></div>
                        <span>Chiuso</span>
                    </div>
                </div>
            </div>

            {/* Calendar */}
            <div className="bg-white shadow rounded-lg p-6">
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
                    eventClick={handleEventClick}
                    eventMouseEnter={(info) => {
                        info.el.style.cursor = 'pointer';
                    }}
                    eventDidMount={(info) => {
                        // Add tooltip on hover
                        const props = info.event.extendedProps;
                        info.el.setAttribute('title',
                            `${info.event.title}\n${props.club}\n${props.zone}`
                        );
                    }}
                />
            </div>

            {/* Modal */}
            {showModal && selectedEvent && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3">
                            <div className="flex justify-between items-center mb-4">
                                <h3 className="text-lg font-medium text-gray-900">
                                    {selectedEvent.title}
                                </h3>
                                <button
                                    type="button"
                                    className="text-gray-400 hover:text-gray-600"
                                    onClick={closeModal}
                                >
                                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            <div className="space-y-3">
                                <div><strong>Club:</strong> {selectedEvent.extendedProps.club}</div>
                                <div><strong>Zona:</strong> {selectedEvent.extendedProps.zone}</div>
                                <div><strong>Categoria:</strong> {selectedEvent.extendedProps.category}</div>
                                <div><strong>Stato:</strong> {selectedEvent.extendedProps.status}</div>
                                <div>
                                    <strong>Date:</strong> {' '}
                                    {new Intl.DateTimeFormat('it-IT', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    }).format(new Date(selectedEvent.start))}
                                    {selectedEvent.end && (
                                        ` - ${new Intl.DateTimeFormat('it-IT', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        }).format(new Date(new Date(selectedEvent.end).getTime() - 86400000))}`
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end mt-6 space-x-3">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200"
                                >
                                    Chiudi
                                </button>

                                {selectedEvent.extendedProps.assigned ? (
                                    <button
                                        type="button"
                                        disabled
                                        className="px-4 py-2 rounded-md bg-green-500 text-white cursor-not-allowed"
                                    >
                                        Assegnato
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => toggleAvailability(
                                            selectedEvent.id,
                                            !selectedEvent.extendedProps.available
                                        )}
                                        className={`px-4 py-2 rounded-md transition duration-200 ${
                                            selectedEvent.extendedProps.available
                                                ? 'bg-red-500 text-white hover:bg-red-600'
                                                : 'bg-blue-500 text-white hover:bg-blue-600'
                                        }`}
                                    >
                                        {selectedEvent.extendedProps.available
                                            ? 'Rimuovi Disponibilità'
                                            : 'Aggiungi Disponibilità'
                                        }
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Results counter */}
            <div className="mt-4 text-sm text-gray-600">
                {events.length} tornei visualizzati
            </div>
        </div>
    );
};

export default AvailabilityCalendar;
