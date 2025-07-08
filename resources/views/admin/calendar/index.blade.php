@extends('layouts.admin')

@section('title', 'Calendario Admin - DEBUG')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-2xl font-bold text-gray-900 mb-6">🔧 Calendario Admin - MODE DEBUG</h1>

        {{-- DEBUG INFO --}}
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
            <h3 class="font-bold">🔍 DEBUG - Dati dal Controller:</h3>
            <div class="mt-2 text-sm">
                <p><strong>User:</strong> {{ auth()->user()->name }} ({{ auth()->user()->user_type }})</p>
                <p><strong>Zone ID:</strong> {{ auth()->user()->zone_id ?? 'NULL' }}</p>
                <p><strong>Is National Admin:</strong> {{ $isNationalAdmin ? 'SI' : 'NO' }}</p>
                <p><strong>Tornei dal Controller:</strong> {{ $calendarData['tournaments']->count() }}</p>

                @if($calendarData['tournaments']->count() > 0)
                    <details class="mt-2">
                        <summary class="cursor-pointer font-medium">📋 Mostra primi 3 tornei</summary>
                        <pre class="bg-gray-100 p-2 mt-1 text-xs overflow-auto max-h-40">{{ json_encode($calendarData['tournaments']->take(3)->toArray(), JSON_PRETTY_PRINT) }}</pre>
                    </details>
                @else
                    <p class="text-red-600 font-medium">❌ NESSUN TORNEO TROVATO!</p>
                @endif
            </div>
        </div>

        {{-- SIMPLE CALENDAR --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-medium mb-4">📅 Calendario FullCalendar</h2>
            <div id="calendar"></div>
        </div>

        {{-- RAW DATA DISPLAY --}}
        @if($calendarData['tournaments']->count() > 0)
        <div class="mt-6 bg-gray-50 rounded-lg p-4">
            <h3 class="font-medium mb-3">📊 Lista Tornei (Raw Data)</h3>
            <div class="space-y-2">
                @foreach($calendarData['tournaments'] as $tournament)
                <div class="bg-white p-3 rounded border text-sm">
                    <strong>{{ $tournament['title'] }}</strong>
                    <span class="text-gray-600">
                        | {{ $tournament['start'] }} → {{ $tournament['end'] }}
                        | Colore: <span style="background-color: {{ $tournament['color'] }}; padding: 2px 8px; border-radius: 3px; color: white;">{{ $tournament['color'] }}</span>
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/it.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendarData = @json($calendarData);

    console.log('🔍 DEBUG - Calendar Data:', calendarData);
    console.log('🔍 DEBUG - Tournaments:', calendarData.tournaments);

    if (!calendarData.tournaments || calendarData.tournaments.length === 0) {
        calendarEl.innerHTML = '<div class="text-center py-8 text-red-600"><h3>❌ Nessun torneo da mostrare</h3><p>Verifica i dati del controller.</p></div>';
        return;
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'it',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        height: 'auto',
        events: calendarData.tournaments,
        eventClick: function(info) {
            alert('Clicked: ' + info.event.title + '\nStart: ' + info.event.start + '\nColor: ' + info.event.backgroundColor);
        },
        eventDidMount: function(info) {
            console.log('✅ Event mounted:', info.event.title);
        },
        eventContent: function(arg) {
            return { html: '<b>' + arg.event.title + '</b>' };
        }
    });

    try {
        calendar.render();
        console.log('✅ Calendar rendered successfully');
    } catch (error) {
        console.error('❌ Calendar render error:', error);
        calendarEl.innerHTML = '<div class="text-center py-8 text-red-600"><h3>❌ Errore nel caricamento calendario</h3><p>' + error.message + '</p></div>';
    }
});
</script>
@endpush
