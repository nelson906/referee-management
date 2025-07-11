@extends('layouts.admin')

@section('title', 'Assegna Arbitri - ' . $tournament->name)

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    {{-- Tournament Header --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $tournament->name }}</h1>
                <div class="mt-2 space-y-1 text-sm text-gray-600">
                    <p><strong>Date:</strong> {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}</p>
                    <p><strong>Circolo:</strong> {{ $tournament->club->name }} ({{ $tournament->zone->name }})</p>
                    <p><strong>Categoria:</strong>
                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $tournament->tournamentType->is_national ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $tournament->tournamentType->name }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.assignments.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                    ← Indietro
                </a>
            </div>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            {{ session('error') }}
        </div>
    @endif

    {{-- Currently Assigned Referees --}}
    @if($assignedReferees->count() > 0)
    <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
        <h3 class="text-lg font-medium text-green-900 mb-4">
            Arbitri Assegnati ({{ $assignedReferees->count() }})
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($assignedReferees as $assignment)
            <div class="bg-white p-4 rounded-lg border border-green-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h4 class="font-medium text-gray-900">{{ $assignment->user->name }}</h4>
                        <p class="text-sm text-gray-600">{{ $assignment->user->referee->referee_code ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-600">{{ $assignment->user->referee->level_label ?? 'N/A' }}</p>
                        <p class="text-sm font-medium text-green-600">{{ $assignment->role }}</p>
                        @if($assignment->notes)
                            <p class="text-xs text-gray-500 mt-1">{{ $assignment->notes }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.assignments.destroy', $assignment) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                onclick="return confirm('Rimuovere questo arbitro dal torneo?')"
                                class="text-red-600 hover:text-red-800 text-sm">
                            Rimuovi
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Assignment Form --}}
    <form method="POST" action="{{ route('admin.assignments.bulk-assign') }}" id="assignment-form">
        @csrf
        <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">

        {{-- Available Referees (declared availability) --}}
        @if($availableReferees->count() > 0)
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="bg-green-50 px-6 py-4 border-b border-green-200">
                <h3 class="text-lg font-medium text-green-900">
                    📅 Arbitri Disponibili ({{ $availableReferees->count() }})
                </h3>
                <p class="text-sm text-green-700">Hanno dichiarato disponibilità per questo torneo</p>
            </div>
            <div class="p-6">
                @include('admin.assignments.partials.referee-list', ['referees' => $availableReferees, 'type' => 'available'])
            </div>
        </div>
        @endif

        {{-- Possible Referees (same zone, no availability declared) --}}
        @if($possibleReferees->count() > 0)
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-200">
                <h3 class="text-lg font-medium text-yellow-900">
                    🏃 Arbitri di Zona ({{ $possibleReferees->count() }})
                </h3>
                <p class="text-sm text-yellow-700">Arbitri della stessa zona che non hanno dichiarato disponibilità</p>
            </div>
            <div class="p-6">
                @include('admin.assignments.partials.referee-list', ['referees' => $possibleReferees, 'type' => 'possible'])
            </div>
        </div>
        @endif

        {{-- National Referees (for national tournaments) --}}
        @if($nationalReferees->count() > 0)
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="bg-blue-50 px-6 py-4 border-b border-blue-200">
                <h3 class="text-lg font-medium text-blue-900">
                    🌟 Arbitri Nazionali/Internazionali ({{ $nationalReferees->count() }})
                </h3>
                <p class="text-sm text-blue-700">Arbitri nazionali/internazionali disponibili per questo torneo</p>
            </div>
            <div class="p-6">
                @include('admin.assignments.partials.referee-list', ['referees' => $nationalReferees, 'type' => 'national'])
            </div>
        </div>
        @endif

        {{-- Submit Button --}}
        <div class="sticky bottom-4 text-center">
            <button type="submit"
                    id="submit-btn"
                    class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 font-medium shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                Assegna Arbitri Selezionati (<span id="selected-count">0</span>)
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('assignment-form');
    const submitBtn = document.getElementById('submit-btn');
    const selectedCountSpan = document.getElementById('selected-count');

    function updateSubmitButton() {
        const selectedReferees = form.querySelectorAll('input[name^="referees["]:checked');
        const count = selectedReferees.length;

        selectedCountSpan.textContent = count;
        submitBtn.disabled = count === 0;
    }

    // Listen for checkbox changes
    form.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.name.startsWith('referees[')) {
            updateSubmitButton();
        }
    });

    // Initial update
    updateSubmitButton();
});
</script>
@endpush
@endsection
