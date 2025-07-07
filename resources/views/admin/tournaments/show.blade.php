@extends('layouts.admin')

@section('title', 'Dettagli Torneo: ' . $tournament->name)

@section('content')
<div class="container mx-auto px-4 py-8 max-w-7xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $tournament->name }}</h1>
                <p class="mt-1 text-gray-600">
                    {{ $tournament->club->name }} - {{ $tournament->date_range }}
                </p>
            </div>
            <div class="flex space-x-4">
                @if($tournament->isEditable())
                <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifica
                </a>
                @endif
                <a href="{{ route('admin.tournaments.admin-index') }}"
                   class="text-gray-600 hover:text-gray-900 flex items-center px-4 py-2">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Torna all'elenco
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

    {{-- Status and Actions --}}
    <div class="mb-6 flex items-center justify-between bg-white rounded-lg shadow p-4">
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-500">Stato:</span>
            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                {{ $tournament->status_label }}
            </span>

            {{-- Status Change Buttons --}}
            @if($tournament->status === 'draft')
                <button onclick="updateStatus('open')"
                        class="text-sm text-green-600 hover:text-green-800">
                    → Apri torneo
                </button>
            @elseif($tournament->status === 'open')
                <button onclick="updateStatus('closed')"
                        class="text-sm text-yellow-600 hover:text-yellow-800">
                    → Chiudi disponibilità
                </button>
            @elseif($tournament->status === 'closed')
                @if($tournament->assignments()->count() >= $tournament->required_referees)
                    <button onclick="updateStatus('assigned')"
                            class="text-sm text-blue-600 hover:text-blue-800">
                        → Segna come assegnato
                    </button>
                @endif
                <button onclick="updateStatus('open')"
                        class="text-sm text-gray-600 hover:text-gray-800">
                    ← Riapri disponibilità
                </button>
            @elseif($tournament->status === 'assigned')
                <button onclick="updateStatus('completed')"
                        class="text-sm text-gray-600 hover:text-gray-800">
                    → Completa torneo
                </button>
            @endif
        </div>

        <div class="flex space-x-2">
            @if($tournament->needsReferees())
            <a href="{{ route('admin.assignments.create', ['tournament_id' => $tournament->id]) }}"
               class="bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition duration-200">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                Assegna Arbitri
            </a>
            @endif

<a href="{{ route('referee.availability.index') }}"
   class="bg-gray-600 text-white px-4 py-2 rounded text-sm hover:bg-gray-700 transition duration-200">
    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
    </svg>
    Gestisci Disponibilità
</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column - Tournament Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Basic Information --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Informazioni Torneo</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Categoria</dt>
                        <dd class="mt-1 text-sm text-gray-900 flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2"
                                 style="background-color: {{ $tournament->tournamentCategory->calendar_color }}"></div>
                            {{ $tournament->tournamentCategory->name }}
                            @if($tournament->tournamentCategory->is_national)
                                <span class="ml-2 text-xs text-blue-600">(Nazionale)</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Zona</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $tournament->zone->name }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Date Torneo</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tournament->start_date->format('d/m/Y') }} - {{ $tournament->end_date->format('d/m/Y') }}
                            <span class="text-gray-500">({{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni)</span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Scadenza Disponibilità</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tournament->availability_deadline->format('d/m/Y') }}
                            @if($tournament->days_until_deadline >= 0)
                                <span class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    ({{ $tournament->days_until_deadline }} giorni rimanenti)
                                </span>
                            @else
                                <span class="text-xs text-gray-500">(scaduta)</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Circolo</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $tournament->club->name }}
                            <div class="text-xs text-gray-500">{{ $tournament->club->full_address }}</div>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Contatti Circolo</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($tournament->club->contact_person)
                                <div>{{ $tournament->club->contact_person }}</div>
                            @endif
                            @if($tournament->club->email)
                                <div><a href="mailto:{{ $tournament->club->email }}" class="text-blue-600 hover:text-blue-800">{{ $tournament->club->email }}</a></div>
                            @endif
                            @if($tournament->club->phone)
                                <div>{{ $tournament->club->phone }}</div>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($tournament->notes)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <dt class="text-sm font-medium text-gray-500">Note</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $tournament->notes }}</dd>
                </div>
                @endif
            </div>

            {{-- Assigned Referees --}}
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">
                        Arbitri Assegnati ({{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }})
                    </h2>
                    @if($tournament->assignments()->count() > 0 && $tournament->status === 'assigned')
                    <div class="flex space-x-2">
                        <button onclick="generateDocuments('convocation')"
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Genera Convocazioni
                        </button>
                        <button onclick="generateDocuments('club')"
                                class="text-sm text-green-600 hover:text-green-800">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Lettera Circolo
                        </button>
                    </div>
                    @endif
                </div>

                @if($assignedReferees->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Arbitro
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Ruolo
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Assegnato
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Confermato
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Azioni</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($assignedReferees as $referee)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $referee->name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $referee->referee_code }} - {{ ucfirst($referee->level) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900">{{ $referee->pivot->role }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($referee->pivot->assigned_at)->format('d/m/Y H:i') }}
                                <div class="text-xs">da {{ $tournament->assignments->where('user_id', $referee->id)->first()->assignedBy->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($referee->pivot->is_confirmed)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Confermato
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        In attesa
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($tournament->status !== 'completed')
                                <form action="{{ route('admin.assignments.remove', $tournament->assignments->where('user_id', $referee->id)->first()) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Sei sicuro di voler rimuovere questa assegnazione?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-6 py-12 text-center text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p>Nessun arbitro ancora assegnato</p>
                    @if($tournament->needsReferees())
                    <a href="{{ route('admin.assignments.create', ['tournament_id' => $tournament->id]) }}"
                       class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Assegna Arbitri
                    </a>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Right Column - Statistics --}}
        <div class="space-y-6">
            {{-- Stats Overview --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Riepilogo</h3>

                <div class="space-y-4">
                    {{-- Referees Progress --}}
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Arbitri Assegnati</span>
                            <span class="font-medium">{{ $stats['total_assignments'] }} / {{ $stats['required_referees'] }}</span>
                        </div>
                        <div class="mt-2 bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full"
                                 style="width: {{ min(100, ($stats['total_assignments'] / $stats['required_referees']) * 100) }}%"></div>
                        </div>
                    </div>

                    {{-- Availabilities --}}
                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-600">Disponibilità Ricevute</span>
                            <span class="font-medium">{{ $stats['total_availabilities'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Non ancora assegnati</span>
                            <span class="font-medium">{{ $availableReferees->count() }}</span>
                        </div>
                    </div>

                    {{-- Category Requirements --}}
                    <div class="pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Requisiti Categoria</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Livello minimo:</span>
                                <span class="font-medium">{{ ucfirst($tournament->tournamentCategory->required_referee_level) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Arbitri richiesti:</span>
                                <span class="font-medium">
                                    @if($tournament->required_referees == $tournament->max_referees)
                                        {{ $tournament->required_referees }}
                                    @else
                                        {{ $tournament->required_referees }} - {{ $tournament->max_referees }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Azioni Rapide</h3>
                <div class="space-y-2">
                    <a href="{{ route('admin.tournaments.availabilities', $tournament) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Gestisci Disponibilità
                    </a>

                    @if($tournament->needsReferees())
                    <a href="{{ route('admin.assignments.create', ['tournament_id' => $tournament->id]) }}"
                       class="block w-full text-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                        Assegna Arbitri
                    </a>
                    @endif

                    @if($tournament->assignments()->count() > 0)
                    <button onclick="sendNotifications()"
                            class="block w-full text-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Invia Notifiche
                    </button>
                    @endif

                    <a href="{{ route('reports.tournament.show', $tournament) }}"
                       class="block w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Visualizza Report
                    </a>
                </div>
            </div>

            {{-- Recent Availabilities --}}
            @if($tournament->availabilities()->count() > 0)
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Ultime Disponibilità
                </h3>
                <div class="space-y-3">
                    @foreach($tournament->availabilities()->with('user')->latest()->limit(5)->get() as $availability)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <span class="font-medium">{{ $availability->user->name }}</span>
                            <span class="text-gray-500 text-xs block">
                                {{ $availability->submitted_at->diffForHumans() }}
                            </span>
                        </div>
                        @if(!$tournament->assignments()->where('user_id', $availability->user_id)->exists())
                        <a href="{{ route('admin.assignments.create', ['tournament_id' => $tournament->id, 'referee_id' => $availability->user_id]) }}"
                           class="text-green-600 hover:text-green-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </a>
                        @endif
                    </div>
                    @endforeach
                </div>

@if($tournament->availabilities()->count() > 5)
<a href="{{ route('referee.availability.index') }}"
   class="block mt-4 text-center text-sm text-indigo-600 hover:text-indigo-800">
    Vedi tutte ({{ $tournament->availabilities()->count() }})
</a>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
// Update tournament status
function updateStatus(newStatus) {
    if (confirm('Sei sicuro di voler cambiare lo stato del torneo?')) {
        fetch('{{ route("admin.tournaments.update-status", $tournament) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Errore durante l\'aggiornamento dello stato');
            }
        });
    }
}

// Generate documents
function generateDocuments(type) {
    if (type === 'convocation') {
        if (confirm('Generare le lettere di convocazione per tutti gli arbitri assegnati?')) {
            // Implementation for generating convocation letters
            alert('Funzionalità in sviluppo');
        }
    } else if (type === 'club') {
        if (confirm('Generare la lettera per il circolo con l\'elenco degli arbitri?')) {
            // Implementation for generating club letter
            alert('Funzionalità in sviluppo');
        }
    }
}

// Send notifications
function sendNotifications() {
    if (confirm('Inviare le notifiche a tutti gli arbitri assegnati?')) {
        // Implementation for sending notifications
        alert('Funzionalità in sviluppo');
    }
}
</script>
@endpush
@endsection
