@extends('layouts.referee')

@section('title', 'Dettagli Assegnazione')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center space-x-3">
            <a href="{{ route('referee.assignments.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-900">📋 Dettagli Assegnazione</h1>
        </div>
        <p class="mt-2 text-gray-600">{{ $assignment->tournament->name }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Colonna principale --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Informazioni Torneo --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">🏌️ Informazioni Torneo</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome Torneo</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Club</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->club->name ?? 'N/A' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data Inizio</label>
                        <p class="mt-1 text-sm text-gray-900">
                            📅 {{ $assignment->tournament->start_date->format('d/m/Y') }}
                            @if($assignment->tournament->start_date->format('H:i') !== '00:00')
                                alle {{ $assignment->tournament->start_date->format('H:i') }}
                            @endif
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data Fine</label>
                        <p class="mt-1 text-sm text-gray-900">
                            @if($assignment->tournament->end_date)
                                📅 {{ $assignment->tournament->end_date->format('d/m/Y') }}
                                @if($assignment->tournament->end_date->format('H:i') !== '00:00')
                                    alle {{ $assignment->tournament->end_date->format('H:i') }}
                                @endif
                            @else
                                <span class="text-gray-500">Non specificata</span>
                            @endif
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Zona</label>
                        <p class="mt-1 text-sm text-gray-900">📍 {{ $assignment->tournament->zone->name ?? 'N/A' }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tipo</label>
                        <p class="mt-1 text-sm text-gray-900">{{ ucfirst($assignment->tournament->type ?? 'Standard') }}</p>
                    </div>
                </div>

                @if($assignment->tournament->description)
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $assignment->tournament->description }}</p>
                    </div>
                @endif
            </div>

            {{-- Comitato di Gara --}}
            @if($assignment->tournament->assignments->count() > 1)
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">👥 Comitato di Gara</h2>

                    <div class="space-y-3">
                        @foreach($assignment->tournament->assignments as $memberAssignment)
                            <div class="flex items-center justify-between p-3 {{ $memberAssignment->id === $assignment->id ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50' }} rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ substr($memberAssignment->user->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $memberAssignment->user->name }}
                                            @if($memberAssignment->id === $assignment->id)
                                                <span class="text-blue-600">(Tu)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $memberAssignment->user->referee->referee_code ?? 'N/A' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $memberAssignment->role ?? 'Arbitro' }}
                                    </span>
                                    @if($memberAssignment->is_confirmed)
                                        <div class="text-xs text-green-600 mt-1">✅ Confermato</div>
                                    @else
                                        <div class="text-xs text-yellow-600 mt-1">⏳ In attesa</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Stato Assegnazione --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Il Mio Stato</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ruolo</label>
                        <span class="mt-1 px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                            {{ $assignment->role ?? 'Arbitro' }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stato Conferma</label>
                        @if($assignment->is_confirmed)
                            <span class="mt-1 px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                ✅ Confermata
                            </span>
                        @else
                            <span class="mt-1 px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                ⏳ In Attesa di Conferma
                            </span>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data Assegnazione</label>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ $assignment->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                </div>

                @if($assignment->notes)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <label class="block text-sm font-medium text-gray-700">Note</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $assignment->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Azioni Rapide --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ Azioni</h3>

                <div class="space-y-3">
                    <a href="{{ route('referee.assignments.index') }}"
                       class="w-full bg-gray-100 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-200 transition text-center block">
                        📋 Tutte le Assegnazioni
                    </a>

                    @if($assignment->tournament->start_date->isFuture())
                        <a href="{{ route('referee.availability.index') }}"
                           class="w-full bg-blue-100 text-blue-700 px-4 py-2 rounded-md hover:bg-blue-200 transition text-center block">
                            📅 Gestisci Disponibilità
                        </a>
                    @endif
                </div>
            </div>

            {{-- Informazioni Temporali --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">⏰ Timeline</h3>

                <div class="space-y-3 text-sm">
                    @if($assignment->tournament->start_date->isFuture())
                        <div class="flex items-center text-blue-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Inizia {{ $assignment->tournament->start_date->diffForHumans() }}</span>
                        </div>
                    @elseif($assignment->tournament->start_date->isToday())
                        <div class="flex items-center text-red-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>🔥 In corso oggi</span>
                        </div>
                    @else
                        <div class="flex items-center text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Completato {{ $assignment->tournament->start_date->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
