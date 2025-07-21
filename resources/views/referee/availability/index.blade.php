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

    {{-- Filters (only for national referees) --}}
    @if($isNationalReferee)
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" action="{{ route('referee.availability.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Zone Filter --}}
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

            {{-- Type Filter - FIX: Changed from $categories to $types --}}
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="category_id" id="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le categorie</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" {{ $typeId == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                            @if($type->is_national)
                                (Nazionale)
                            @endif
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

            {{-- Submit --}}
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    Filtra
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Quick Actions --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">I Miei Tornei</h2>
                <p class="text-sm text-gray-600">
                    @if($isNationalReferee)
                        Come arbitro nazionale, puoi accedere a tornei nazionali e della tua zona.
                    @else
                        Puoi accedere ai tornei della tua zona.
                    @endif
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('referee.availability.calendar') }}"
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    📅 Vista Calendario
                </a>
            </div>
        </div>
    </div>

    {{-- Tournaments by Month --}}
    @if($tournamentsByMonth->count() > 0)
        <form method="POST" action="{{ route('referee.availability.save') }}" id="availability-form">
            @csrf

            @foreach($tournamentsByMonth as $monthKey => $tournaments)
                @php
                    $monthDate = \Carbon\Carbon::parse($monthKey . '-01');
                @endphp

                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $monthDate->format('F Y') }}
                            <span class="text-sm text-gray-500">({{ $tournaments->count() }} {{ $tournaments->count() == 1 ? 'torneo' : 'tornei' }})</span>
                        </h3>
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
                                        Zona
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Note
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tournaments as $tournament)
                                <tr class="hover:bg-gray-50 {{ in_array($tournament->id, $userAvailabilities) ? 'bg-blue-50' : '' }}">
                                    {{-- Checkbox --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox"
                                               name="availabilities[]"
                                               value="{{ $tournament->id }}"
                                               {{ in_array($tournament->id, $userAvailabilities) ? 'checked' : '' }}
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </td>

                                    {{-- Tournament Name --}}
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $tournament->name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Status:
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                                                {{ $tournament->status_label }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Dates --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>{{ $tournament->start_date->format('d/m/Y') }}</div>
                                        @if($tournament->start_date->ne($tournament->end_date))
                                            <div class="text-gray-500">{{ $tournament->end_date->format('d/m/Y') }}</div>
                                        @endif
                                    </td>

                                    {{-- Club --}}
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $tournament->club->city }}</div>
                                    </td>

                                    {{-- Category --}}
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $tournament->tournamentType->is_national ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $tournament->tournamentType->name }}
                                            @if($tournament->tournamentType->is_national)
                                                🏆
                                            @endif
                                        </span>
                                    </td>

                                    {{-- Zone --}}
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tournament->zone->name }}
                                    </td>

                                    {{-- Notes --}}
                                    <td class="px-6 py-4">
                                        <textarea name="notes[{{ $tournament->id }}]"
                                                  rows="2"
                                                  placeholder="Note opzionali..."
                                                  class="w-full text-xs border-gray-300 rounded-md focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes.' . $tournament->id) }}</textarea>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- Submit Button --}}
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        Seleziona i tornei per cui sei disponibile e aggiungi eventuali note.
                    </div>
                    <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium">
                        💾 Salva Disponibilità
                    </button>
                </div>
            </div>
        </form>
    @else
        {{-- No Tournaments --}}
        <div class="bg-white shadow rounded-lg p-12 text-center">
            <div class="text-gray-400 text-6xl mb-4">📅</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun torneo disponibile</h3>
            <p class="text-gray-600 mb-4">
                @if($isNationalReferee)
                    Non ci sono tornei aperti per le disponibilità nel periodo selezionato.
                @else
                    Non ci sono tornei aperti nella tua zona per il periodo selezionato.
                @endif
            </p>
            @if($isNationalReferee)
                <a href="{{ route('referee.availability.index') }}" class="text-indigo-600 hover:text-indigo-500">
                    Rimuovi filtri
                </a>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-save on checkbox change (optional)
    const checkboxes = document.querySelectorAll('input[name="availabilities[]"]');
    const form = document.getElementById('availability-form');

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Add visual feedback
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('bg-blue-50');
            } else {
                row.classList.remove('bg-blue-50');
            }
        });
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('input[name="availabilities[]"]:checked');

        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Seleziona almeno un torneo o deseleziona tutti per rimuovere le disponibilità.');
            return false;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Salvando...';
    });
});
</script>
@endpush
@endsection
