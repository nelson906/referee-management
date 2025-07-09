import React, { useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import itLocale from '@fullcalendar/core/locales/it';

const RefereeCalendar = ({ calendarData }) => {
    const [selectedEvent, setSelectedEvent] = useState(null);
    const [showModal, setShowModal] = useState(false);

    const handleEventClick = (info) => {
        setSelectedEvent(info.event);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setSelectedEvent(null);
    };

    const getPersonalStatusBadge = (status) => {
        const badges = {
            'assigned': 'bg-green-100 text-green-800',
            'available': 'bg-yellow-100 text-yellow-800',
            'can_apply': 'bg-blue-100 text-blue-800',
            'closed': 'bg-gray-100 text-gray-800'
        };

        const texts = {
            'assigned': 'Assegnato',
            'available': 'Disponibile',
            'can_apply': 'Posso candidarmi',
            'closed': 'Chiuso'
        };

        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${badges[status] || badges.closed}`}>
                {texts[status] || status}
            </span>
        );
    };

    // Future: Toggle availability function (placeholder)
    const handleToggleAvailability = async (tournamentId, isAvailable) => {
        // TODO: Implementare dopo standardizzazione
        console.log('Toggle availability for tournament:', tournamentId, 'isAvailable:', isAvailable);
        alert('Funzione toggle disponibilità - da implementare dopo standardizzazione');
    };

    if (!calendarData?.tournaments) {
        return (
            <div className="text-center py-8 text-gray-600">
                <h3 className="text-lg font-medium">Nessun torneo disponibile</h3>
                <p>Non ci sono tornei nella tua zona al momento.</p>
            </div>
        );
    }

    return (
        <div className="referee-calendar">
            {/* Personal Legend */}
            <div className="mb-6 bg-white rounded-lg shadow p-4">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Legenda Personale</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div>
                        <h4 className="font-medium text-gray-600 mb-2">Colori (Categoria Torneo):</h4>
                        <div className="space-y-1">
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#FF6B6B'}}></div>
                                <span>Categoria A</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#4ECDC4'}}></div>
                                <span>Categoria B</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2" style={{backgroundColor: '#45B7D1'}}></div>
                                <span>Categoria C</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 className="font-medium text-gray-600 mb-2">Bordi (Il Mio Status):</h4>
                        <div className="space-y-1">
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 border-2" style={{borderColor: '#10B981', backgroundColor: '#f0f0f0'}}></div>
                                <span>Assegnato</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 border-2" style={{borderColor: '#F59E0B', backgroundColor: '#f0f0f0'}}></div>
                                <span>Disponibile</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 border-2" style={{borderColor: '#3B82F6', backgroundColor: '#f0f0f0'}}></div>
                                <span>Posso candidarmi</span>
                            </div>
                            <div className="flex items-center">
                                <div className="w-4 h-4 rounded mr-2 border-2" style={{borderColor: '#6B7280', backgroundColor: '#f0f0f0'}}></div>
                                <span>Chiuso</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Personal Stats */}
            <div className="mb-6 bg-white rounded-lg shadow p-4">
                <h3 className="text-sm font-medium text-gray-700 mb-3">Le Mie Statistiche</h3>
                <div className="grid grid-cols-3 gap-4 text-center">
                    <div className="bg-green-50 p-3 rounded">
                        <div className="font-medium text-lg text-green-600">
                            {calendarData.assignments?.length || 0}
                        </div>
                        <div className="text-green-600 text-sm">Assegnazioni</div>
                    </div>
                    <div className="bg-blue-50 p-3 rounded">
                        <div className="font-medium text-lg text-blue-600">
                            {calendarData.availabilities?.length || 0}
                        </div>
                        <div className="text-blue-600 text-sm">Disponibilità</div>
                    </div>
                    <div className="bg-gray-50 p-3 rounded">
                        <div className="font-medium text-lg text-gray-600">
                            {calendarData.tournaments?.length || 0}
                        </div>
                        <div className="text-gray-600 text-sm">Tornei Zona</div>
                    </div>
                </div>
            </div>

            {/* Calendar */}
            <div className="bg-white rounded-lg shadow">
                <div className="p-4">
                    <FullCalendar
                        plugins={[dayGridPlugin, listPlugin]}
                        initialView="dayGridMonth"
                        locale={itLocale}
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,listWeek'
                        }}
                        height="auto"
                        events={calendarData.tournaments || []}
                        eventClick={handleEventClick}
                        eventDidMount={(info) => {
                            const props = info.event.extendedProps;
                            const statusText = props.personal_status || 'can_apply';
                            info.el.title = `${info.event.title} - ${props.club} (${statusText})`;
                        }}
                    />
                </div>
            </div>

            {/* Personal Modal */}
            {showModal && selectedEvent && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen p-4">
                        <div className="fixed inset-0 bg-gray-500 bg-opacity-75" onClick={closeModal}></div>
                        <div className="bg-white rounded-lg shadow-xl max-w-lg w-full z-10">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    {selectedEvent.title}
                                </h3>

                                <div className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div><span className="font-medium">Club:</span> {selectedEvent.extendedProps.club}</div>
                                        <div><span className="font-medium">Categoria:</span> {selectedEvent.extendedProps.category}</div>
                                        <div><span className="font-medium">Scadenza:</span> {selectedEvent.extendedProps.deadline || 'N/A'}</div>
                                        <div>
                                            <span className="font-medium">Il mio stato:</span>
                                            <span className="ml-2">{getPersonalStatusBadge(selectedEvent.extendedProps.personal_status)}</span>
                                        </div>
                                    </div>

                                    {/* Show date range */}
                                    <div className="border-t pt-3 text-sm">
                                        <span className="font-medium">Date:</span>
                                        <span className="ml-2">
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
                                        </span>
                                    </div>

                                    {/* Days until deadline */}
                                    {selectedEvent.extendedProps.days_until_deadline !== undefined && (
                                        <div className="text-sm">
                                            <span className="font-medium">Tempo rimanente:</span>
                                            <span className={`ml-2 ${selectedEvent.extendedProps.days_until_deadline < 0 ? 'text-red-600' : 'text-gray-600'}`}>
                                                {selectedEvent.extendedProps.days_until_deadline < 0
                                                    ? 'Scadenza passata'
                                                    : `${selectedEvent.extendedProps.days_until_deadline} giorni`
                                                }
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <div className="mt-6 flex justify-end space-x-3">
                                    <button
                                        onClick={closeModal}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
                                    >
                                        Chiudi
                                    </button>

                                    {/* Show appropriate action button based on status */}
                                    {selectedEvent.extendedProps.personal_status === 'assigned' ? (
                                        <button
                                            disabled
                                            className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md cursor-not-allowed"
                                        >
                                            Assegnato
                                        </button>
                                    ) : selectedEvent.extendedProps.can_apply && (
                                        <button
                                            onClick={() => {
                                                handleToggleAvailability(
                                                    selectedEvent.id,
                                                    !selectedEvent.extendedProps.is_available
                                                );
                                                closeModal();
                                            }}
                                            className={`px-4 py-2 text-sm font-medium text-white rounded-md ${
                                                selectedEvent.extendedProps.is_available
                                                    ? 'bg-red-600 hover:bg-red-700'
                                                    : 'bg-blue-600 hover:bg-blue-700'
                                            }`}
                                        >
                                            {selectedEvent.extendedProps.is_available
                                                ? 'Rimuovi Disponibilità'
                                                : 'Aggiungi Disponibilità'
                                            }
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Results Counter */}
            <div className="mt-4 text-sm text-gray-600">
                {calendarData.tournaments?.length || 0} tornei nella tua zona
            </div>
        </div>
    );
};

export default RefereeCalendar;
