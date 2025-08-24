@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
    @php
        $isNationalAdmin = in_array(auth()->user()->user_type, ['national_admin', 'super_admin']);
        // Assicurati che tutte le chiavi esistano
        $stats['pending_confirmations'] = $stats['pending_confirmations'] ?? ($stats['pending_assignments'] ?? 0);
        $stats['active_referees'] = $stats['active_referees'] ?? 0;
        $stats['total_referees'] = $stats['total_referees'] ?? 0;
        $stats['total_availabilities'] = $stats['total_availabilities'] ?? 0;

    @endphp

    <div class="container mx-auto px-4 py-8">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard Amministratore</h1>
            <p class="mt-2 text-gray-600">
                Benvenuto {{ auth()->user()->name }} -
                @if ($isNationalAdmin)
                    Amministratore CRC (Comitato Regionale Campania)
                @else
                    Amministratore Zona {{ auth()->user()->zone->name ?? 'N/A' }}
                @endif
            </p>
        </div>

        {{-- Alerts --}}
        @if (count($alerts) > 0)
            <div class="mb-6 space-y-3">
                @foreach ($alerts as $alert)
                    <div
                        class="bg-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-50 border-l-4 border-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-400"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-{{ $alert['type'] === 'warning' ? 'yellow' : 'blue' }}-700">
                                    {{ $alert['message'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Tornei Totali</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_tournaments'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Arbitri Attivi</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['active_referees'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Assegnazioni Totali</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_assignments'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Da Confermare</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['pending_confirmations'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Tornei che necessitano arbitri --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Tornei che necessitano arbitri</h2>
                </div>

                <div class="p-6">
                    @forelse($tournamentsNeedingReferees as $tournament)
                        <div class="flex items-center justify-between py-3 border-b border-gray-200 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $tournament->name }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ $tournament->start_date ? \Carbon\Carbon::parse($tournament->start_date)->format('d/m/Y') : 'Data N/A' }}
                                    @if ($tournament->club_name)
                                        - {{ $tournament->club_name }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-right">
<span class="text-sm {{ $tournament->assigned_count < ($tournament->required_referees ?? 2) ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
    {{ $tournament->assigned_count ?? 0 }} / {{ $tournament->required_referees ?? 2 }}
</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-8">Tutti i tornei hanno arbitri sufficienti</p>
                    @endforelse
                </div>
            </div>

            {{-- Assegnazioni recenti --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Assegnazioni Recenti</h2>
                </div>
                <div class="p-6">
                    @forelse($recentAssignments as $assignment)
                        <div class="flex items-center justify-between py-3 border-b border-gray-200 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $assignment->user->name ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600">
                                    {{ $assignment->tournament->name ?? 'Torneo N/A' }}
                                    - {{ $assignment->created_at ? \Carbon\Carbon::parse($assignment->created_at)->format('d/m/Y') : 'Data N/A' }}
                                </p>
                            </div>
                            <div>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ (property_exists($assignment, 'is_confirmed') && $assignment->is_confirmed) ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ (property_exists($assignment, 'is_confirmed') && $assignment->is_confirmed) ? 'Confermato' : 'Da Confermare' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-8">Nessuna assegnazione recente</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="mt-8 bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Azioni Rapide</h2>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <a href="#" class="bg-blue-50 p-4 rounded-lg text-center hover:bg-blue-100 transition">
                    <div class="text-blue-600 mb-2">
                        <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-blue-900">Gestisci Tornei</p>
                </a>

                <a href="#" class="bg-green-50 p-4 rounded-lg text-center hover:bg-green-100 transition">
                    <div class="text-green-600 mb-2">
                        <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-green-900">Gestisci Arbitri</p>
                </a>

                <a href="#" class="bg-purple-50 p-4 rounded-lg text-center hover:bg-purple-100 transition">
                    <div class="text-purple-600 mb-2">
                        <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-purple-900">Assegnazioni</p>
                </a>

                <a href="#" class="bg-orange-50 p-4 rounded-lg text-center hover:bg-orange-100 transition">
                    <div class="text-orange-600 mb-2">
                        <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-orange-900">Report</p>
                </a>
            </div>
        </div>
    </div>
@endsection
