@extends('layouts.referee')

@section('title', 'Riassunto Disponibilità')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Riassunto Disponibilità</h1>
            <p class="mt-2 text-gray-600">Panoramica delle tue disponibilità e tornei aperti</p>
        </div>
        {{-- PULSANTE MENO EVIDENTE --}}
<a href="{{ route('referee.availability.calendar') }}"
   class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-6 py-3 rounded-md text-sm font-medium flex items-center border border-blue-500 transition duration-200">
    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
    </svg>
    Seleziona Disponibilità
</a>
    </div>
</div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Successo!</p>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Errore!</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- Filters --}}
@if($isNationalReferee)
<div class="bg-white shadow rounded-lg p-6 mb-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Filtri (applicati automaticamente)</h3>
    <form method="GET" action="{{ route('referee.availability.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Zone Filter (only for national referees) --}}
        <div>
            <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
            <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                <option value="">Tutte le zone</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" {{ $zoneId == $zone->id ? 'selected' : '' }}>
                        {{ $zone->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Category Filter --}}
        <div>
            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
            <select name="category_id" id="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" onchange="this.form.submit()">
                <option value="">Tutte le categorie</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Month Filter --}}
        <div>
            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
            <input type="month"
                   name="month"
                   id="month"
                   value="{{ $month ?? '' }}"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                   onchange="this.form.submit()">
        </div>

        {{-- Clear Filters --}}
        <div class="flex items-end">
            <a href="{{ route('referee.availability.index') }}"
               class="w-full bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition duration-200 text-center">
                Pulisci Filtri
            </a>
        </div>
    </form>
</div>
@else
{{-- MESSAGGIO PER ARBITRI NON NAZIONALI --}}
<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-blue-700">
                <strong>Informazione:</strong> Come arbitro {{ $isNationalReferee ? 'nazionale' : 'zonale' }}, visualizzi automaticamente tutti i tornei della tua zona {{ auth()->user()->zone->name ?? '' }}.
            </p>
        </div>
    </div>
</div>
@endif

    {{-- Availability Form --}}
    <form method="POST" action="{{ route('referee.availability.save') }}" id="availability-form">
        @csrf

        @if($tournamentsByMonth->count() > 0)
            @foreach($tournamentsByMonth as $month => $tournaments)
            <div class="bg-white shadow rounded-lg mb-6 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">
                        {{ Carbon\Carbon::parse($month)->locale('it')->translatedFormat('F Y') }}
                        <span class="text-sm text-gray-500 ml-2">({{ $tournaments->count() }} {{ $tournaments->count() == 1 ? 'torneo' : 'tornei' }})</span>
                    </h2>
                </div>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Disponibile
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Torneo
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Date
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Circolo
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Categoria
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Note
                </th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($tournaments as $tournament)
            <tr class="hover:bg-gray-50 {{ in_array($tournament->id, $userAvailabilities) ? 'bg-blue-50' : '' }}">
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox"
                           name="availabilities[]"
                           value="{{ $tournament->id }}"
                           {{ in_array($tournament->id, $userAvailabilities) ? 'checked' : '' }}
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded availability-checkbox"
                           data-tournament-id="{{ $tournament->id }}">
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">
                        {{ $tournament->name }}
                    </div>
                    @if($tournament->notes)
                    <div class="text-sm text-gray-500">
                        {{ Str::limit($tournament->notes, 50) }}
                    </div>
                    @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m/Y') }}
                    <div class="text-xs text-gray-500">
                        ({{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni)
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ $tournament->club->name }}
                    @if($tournament->club->code)
                        <span class="text-gray-500">({{ $tournament->club->code }})</span>
                    @endif
                    <div class="text-xs text-gray-500">
                        {{ $tournament->zone->name }}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2"
                             style="background-color: {{ $tournament->tournamentCategory->calendar_color ?? '#6B7280' }}"></div>
                        <span class="text-sm text-gray-900">
                            {{ $tournament->tournamentCategory->name }}
                        </span>
                        @if($tournament->tournamentCategory->is_national ?? false)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                Nazionale
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500">
                        Min. {{ $tournament->tournamentCategory->min_referees ?? 1 }} arbitri
                    </div>
                </td>
                <td class="px-6 py-4">
                    <input type="text"
                           name="notes[{{ $tournament->id }}]"
                           placeholder="Note opzionali..."
                           value="{{ old('notes.'.$tournament->id, $tournament->availabilities->where('user_id', auth()->id())->first()->notes ?? '') }}"
                           class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 note-input"
                           data-tournament-id="{{ $tournament->id }}"
                           {{ !in_array($tournament->id, $userAvailabilities) ? 'disabled' : '' }}>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
            </div>
            @endforeach

            {{-- Submit Button --}}
            <div class="sticky bottom-4 flex justify-end">
                <div class="bg-white shadow-lg rounded-lg p-4">
                    <button type="submit"
                            class="bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 transition duration-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Salva Disponibilità
                        <span id="selection-count" class="ml-2 bg-indigo-500 px-2 py-1 rounded text-sm">0</span>
                    </button>
                </div>
            </div>
        @else
            {{-- Empty State --}}
            <div class="bg-white shadow rounded-lg p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">Nessun torneo trovato</h3>
                <p class="mt-2 text-gray-600">
                    Non ci sono tornei che corrispondono ai filtri selezionati.
                    <br>
                    Prova a modificare i filtri o <a href="{{ route('referee.availability.calendar') }}" class="text-blue-600 hover:text-blue-800">vai al calendario</a> per vedere tutti i tornei disponibili.
                </p>
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update selection count
    function updateSelectionCount() {
        const checkedCount = document.querySelectorAll('.availability-checkbox:checked').length;
        document.getElementById('selection-count').textContent = checkedCount;
    }

    // Enable/disable note input based on checkbox
    document.querySelectorAll('.availability-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const tournamentId = this.dataset.tournamentId;
            const noteInput = document.querySelector(`.note-input[data-tournament-id="${tournamentId}"]`);

            if (this.checked) {
                noteInput.disabled = false;
                noteInput.classList.remove('bg-gray-100');
            } else {
                noteInput.disabled = true;
                noteInput.value = '';
                noteInput.classList.add('bg-gray-100');
            }

            updateSelectionCount();
        });
    });

    // Initial count update
    updateSelectionCount();

    // Confirm before leaving if changes made
    let formChanged = false;
    const form = document.getElementById('availability-form');

    form.addEventListener('change', function() {
        formChanged = true;
    });

    form.addEventListener('submit', function() {
        formChanged = false;
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
        }
    });
});
</script>
@endpush

@endsection
