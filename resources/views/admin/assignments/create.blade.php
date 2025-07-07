@extends('layouts.admin')

@section('title', 'Assegna Arbitro')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Assegna Arbitro</h1>
                @if($tournament)
                    <p class="mt-1 text-gray-600">
                        Torneo: {{ $tournament->name }} - {{ $tournament->club->name }}
                    </p>
                @endif
            </div>
            <div class="flex space-x-4">
                <a href="{{ $tournament ? route('admin.tournaments.show', $tournament) : route('admin.assignments.index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Indietro
                </a>
            </div>
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

    {{-- Assignment Form --}}
    <div class="bg-white shadow rounded-lg p-6">
        <form action="{{ route('admin.assignments.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Tournament Selection --}}
            <div>
                <label for="tournament_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Torneo *
                </label>
                @if($tournament)
                    <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                    <div class="p-3 bg-gray-50 rounded-md border">
                        <div class="text-sm font-medium text-gray-900">{{ $tournament->name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ $tournament->club->name }} - {{ $tournament->date_range }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Arbitri: {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                        </div>
                    </div>
                @else
                    <select name="tournament_id" id="tournament_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required>
                        <option value="">Seleziona un torneo</option>
                        @foreach($tournaments as $t)
                            <option value="{{ $t->id }}">
                                {{ $t->name }} - {{ $t->club->name }} ({{ $t->assignments()->count() }}/{{ $t->required_referees }})
                            </option>
                        @endforeach
                    </select>
                @endif
                @error('tournament_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Referee Selection --}}
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Arbitro *
                </label>
                <select name="user_id" id="user_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    <option value="">Prima seleziona un torneo</option>
                </select>
                @error('user_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                    Ruolo *
                </label>
                <select name="role" id="role"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required>
                    <option value="">Seleziona un ruolo</option>
                    <option value="Arbitro">Arbitro</option>
                    <option value="Direttore di Torneo">Direttore di Torneo</option>
                    <option value="Osservatore">Osservatore</option>
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes --}}
            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                    Note
                </label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Note aggiuntive per l'assegnazione...">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Submit Buttons --}}
            <div class="flex justify-end space-x-4">
                <a href="{{ $tournament ? route('admin.tournaments.show', $tournament) : route('admin.assignments.index') }}"
                   class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annulla
                </a>
                <button type="submit"
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Assegna Arbitro
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tournamentSelect = document.getElementById('tournament_id');
    const refereeSelect = document.getElementById('user_id');

    // Load referees when tournament changes
    if (tournamentSelect && refereeSelect) {
        tournamentSelect.addEventListener('change', function() {
            const tournamentId = this.value;

            // Clear referee options
            refereeSelect.innerHTML = '<option value="">Caricamento...</option>';

            if (tournamentId) {
                // Load available referees for this tournament
                fetch(`/admin/tournaments/${tournamentId}/available-referees`)
                    .then(response => response.json())
                    .then(data => {
                        refereeSelect.innerHTML = '<option value="">Seleziona un arbitro</option>';

                        data.forEach(referee => {
                            const option = document.createElement('option');
                            option.value = referee.id;
                            option.textContent = `${referee.name} (${referee.referee_code}) - ${referee.level}`;
                            refereeSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        refereeSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
                    });
            } else {
                refereeSelect.innerHTML = '<option value="">Prima seleziona un torneo</option>';
            }
        });
    }

    // If tournament is pre-selected, load referees
    @if($tournament)
        const event = new Event('change');
        tournamentSelect.dispatchEvent(event);
    @endif
});
</script>
@endpush
@endsection
