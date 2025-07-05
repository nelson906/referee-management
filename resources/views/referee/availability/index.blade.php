@extends('layouts.referee')

@section('title', 'Gestione Disponibilità')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestione Disponibilità</h1>
        <p class="mt-2 text-gray-600">Seleziona i tornei per cui sei disponibile</p>
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
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" action="{{ route('referee.availability.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Zone Filter (only for national referees) --}}
            @if($isNationalReferee)
            <div>
                <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ $zoneId == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Category Filter --}}
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="category_id" id="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                       value="{{ $month }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Submit Button --}}
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition duration-200">
                    Filtra
                </button>
            </div>
        </form>

        {{-- Calendar Link --}}
        <div class="mt-4 text-right">
            <a href="{{ route('referee.availability.calendar') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Vista Calendario
            </a>
        </div>
    </div>

    {{-- Availability Form --}}
    <form method="POST" action="{{ route('referee.availability.save') }}" id="availability-form">
        @csrf

        @if($tournamentsByMonth->isEmpty())
            <div class="bg-white shadow rounded-lg p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-gray-500">Nessun torneo disponibile per i criteri selezionati</p>
            </div>
        @else
            @foreach($tournamentsByMonth as $monthKey => $tournaments)
                <div class="mb-8">
                    {{-- Month Header --}}
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        {{ \Carbon\Carbon::parse($monthKey)->locale('it')->isoFormat('MMMM YYYY') }}
                    </h2>

                    {{-- Tournaments List --}}
                    <div class="bg-white shadow rounded-lg overflow-hidden">
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
                                        Scadenza
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
                                        <div class="text-xs text-gray-500">
                                            {{ $tournament->zone->name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 rounded-full mr-2"
                                                 style="background-color: {{ $tournament->tournamentCategory->calendar_color }}"></div>
                                            <span class="text-sm text-gray-900">
                                                {{ $tournament->tournamentCategory->name }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Min. {{ $tournament->tournamentCategory->min_referees }} arbitri
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ $tournament->availability_deadline->format('d/m/Y') }}
                                        </div>
                                        <div class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                            @if($tournament->days_until_deadline == 0)
                                                Scade oggi!
                                            @elseif($tournament->days_until_deadline == 1)
                                                Scade domani
                                            @else
                                                {{ $tournament->days_until_deadline }} giorni
                                            @endif
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
            e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler lasciare la pagina?';
        }
    });
});
</script>
@endpush
@endsection
