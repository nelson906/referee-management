@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Amministratore</h1>
        <p class="mt-2 text-gray-600">
            Benvenuto {{ auth()->user()->name }} -
            @if($isNationalAdmin)
                Amministratore CRC (Comitato Regionale Campania)
            @else
                Amministratore Zona {{ auth()->user()->zone->name }}
            @endif
        </p>
    </div>

    {{-- Alerts --}}
    @if(count($alerts) > 0)
        <div class="mb-6 space-y-3">
            @foreach($alerts as $alert)
                <div class="bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-50 border-l-4 border-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400 p-4 rounded-r-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-700">
                                {{ $alert['message'] }}
                                @if(isset($alert['action_url']))
                                    <a href="{{ $alert['action_url'] }}" class="font-medium underline hover:no-underline">
                                        {{ $alert['action_text'] ?? 'Visualizza' }} →
                                    </a>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Tornei Totali --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Tornei Totali</dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ $stats['total_tournaments'] }}
                                <span class="text-sm text-green-600">{{ $stats['active_tournaments'] }} attivi</span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Arbitri Totali --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Arbitri Totali</dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ $stats['total_referees'] }}
                                <span class="text-sm text-green-600">{{ $stats['active_referees'] }} attivi</span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Assegnazioni --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Assegnazioni</dt>
                            <dd class="text-lg font-medium text-gray-900">
                                {{ $stats['total_assignments'] }}
                                <span class="text-sm text-yellow-600">{{ $stats['unconfirmed_assignments'] }} da confermare</span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tornei Futuri --}}
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Tornei Futuri</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['upcoming_tournaments'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Tornei che Necessitano Arbitri --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Tornei che Necessitano Arbitri</h3>
            </div>
            <div class="p-6">
                @if($tournamentsNeedingReferees->count() > 0)
                    <div class="space-y-4">
                        @foreach($tournamentsNeedingReferees as $tournament)
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900">{{ $tournament->name }}</h4>
                                    <p class="text-sm text-gray-500">
                                        {{ $tournament->club->name ?? 'N/A' }} •
                                        {{ $tournament->start_date ? $tournament->start_date->format('d/m/Y') : 'Data TBD' }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        Arbitri: {{ $tournament->assignments()->count() }}/{{ $tournament->tournamentCategory->min_referees ?? 1 }}
                                    </p>
                                </div>
                                <div class="ml-4">
                                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                                       class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                        Gestisci
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">Tutti i tornei hanno arbitri sufficienti</p>
                @endif
            </div>
        </div>

        {{-- Assegnazioni Recenti --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Assegnazioni Recenti</h3>
                <a href="{{ route('admin.assignments.index') }}" class="text-sm text-blue-600 hover:text-blue-900">
                    Vedi tutte →
                </a>
            </div>
            <div class="p-6">
                @if($recentAssignments->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentAssignments as $assignment)
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <p class="text-sm font-medium text-gray-900">{{ $assignment->user->name }}</p>
                                        <span class="ml-2 text-xs text-gray-500">{{ $assignment->user->referee_code }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600">{{ $assignment->tournament->name }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $assignment->tournament->club->name ?? 'N/A' }} •
                                        {{ $assignment->tournament->start_date ? $assignment->tournament->start_date->format('d/m/Y') : 'TBD' }}
                                    </p>
                                </div>
                                <div class="ml-4 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $assignment->is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $assignment->is_confirmed ? 'Confermata' : 'Da confermare' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">Nessuna assegnazione recente</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
