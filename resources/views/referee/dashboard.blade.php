@extends('layouts.referee')

@section('title', 'Dashboard Arbitro')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard Arbitro</h1>
        <p class="mt-2 text-gray-600">
            Benvenuto {{ $user->name }}
            @if($user->level)
                - Livello: {{ ucfirst(str_replace('_', ' ', $user->level)) }}
            @endif
        </p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div class="ml-5">
                    <p class="text-sm font-medium text-gray-500">Assegnazioni Totali</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats->total_assignments ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5">
                    <p class="text-sm font-medium text-gray-500">Quest'Anno</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats->assignments_this_year ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-5">
                    <p class="text-sm font-medium text-gray-500">Da Confermare</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats->pending_assignments ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="ml-5">
                    <p class="text-sm font-medium text-gray-500">Confermati</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $stats->confirmed_assignments ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Prossime Assegnazioni --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Prossime Assegnazioni</h2>
            </div>
            <div class="p-6">
                @forelse($upcomingAssignments as $assignment)
                    <div class="flex items-center justify-between py-3 border-b border-gray-200 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $assignment->tournament->name ?? 'Torneo N/A' }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $assignment->tournament->start_date ? $assignment->tournament->start_date->format('d/m/Y') : 'Data N/A' }}
                                @if($assignment->tournament->club)
                                    - {{ $assignment->tournament->club->name }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $assignment->is_confirmed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $assignment->is_confirmed ? 'Confermato' : 'Da Confermare' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-8">Nessuna assegnazione futura</p>
                @endforelse
            </div>
        </div>

        {{-- Tornei Aperti --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Tornei Aperti per Disponibilità</h2>
            </div>
            <div class="p-6">
                @forelse($openTournaments as $tournament)
                    <div class="flex items-center justify-between py-3 border-b border-gray-200 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $tournament->name }}</p>
                            <p class="text-sm text-gray-600">
                                Scadenza: {{ $tournament->availability_deadline ? $tournament->availability_deadline->format('d/m/Y') : 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <a href="#" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                Dichiara Disponibilità
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-8">Nessun torneo aperto</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-8 bg-white shadow rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Azioni Rapide</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="{{ route('profile.edit') }}" class="bg-blue-50 p-4 rounded-lg text-center hover:bg-blue-100 transition">
                <div class="text-blue-600 mb-2">
                    <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-blue-900">Gestisci Profilo</p>
            </a>

            <a href="#" class="bg-green-50 p-4 rounded-lg text-center hover:bg-green-100 transition">
                <div class="text-green-600 mb-2">
                    <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-green-900">Disponibilità</p>
            </a>

            <a href="#" class="bg-purple-50 p-4 rounded-lg text-center hover:bg-purple-100 transition">
                <div class="text-purple-600 mb-2">
                    <svg class="h-8 w-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-purple-900">Le Mie Assegnazioni</p>
            </a>
        </div>
    </div>
</div>
@endsection
