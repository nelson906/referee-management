@extends('layouts.referee')

@section('title', 'Le Mie Assegnazioni')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-7xl">
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">📋 Le Mie Assegnazioni</h1>
        <p class="mt-2 text-gray-600">Visualizza i tornei a cui sei stato assegnato</p>
    </div>

    {{-- Statistiche --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $stats['total'] }}</h3>
                    <p class="text-sm text-gray-600">Totali {{ $year }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $stats['confirmed'] }}</h3>
                    <p class="text-sm text-gray-600">Confermate</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $stats['upcoming'] }}</h3>
                    <p class="text-sm text-gray-600">In Arrivo</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <div class="p-3 bg-gray-100 rounded-full">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $stats['completed'] }}</h3>
                    <p class="text-sm text-gray-600">Completate</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtri --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anno</label>
                <select name="year" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    @foreach($availableYears as $availableYear)
                        <option value="{{ $availableYear }}" {{ $year == $availableYear ? 'selected' : '' }}>
                            {{ $availableYear }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    <option value="">Tutti</option>
                    <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>In Arrivo</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completati</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confermati</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>In Attesa</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Filtra
                </button>
                <a href="{{ route('referee.assignments.index') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Lista Assegnazioni --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if($assignments->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Torneo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ruolo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stato</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($assignments as $assignment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $assignment->tournament->name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            🏌️ {{ $assignment->tournament->club->name ?? 'Club N/A' }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            📍 {{ $assignment->tournament->zone->name ?? 'Zona N/A' }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        📅 {{ $assignment->tournament->start_date->format('d/m/Y') }}
                                        @if($assignment->tournament->end_date && $assignment->tournament->end_date != $assignment->tournament->start_date)
                                            - {{ $assignment->tournament->end_date->format('d/m/Y') }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        @if($assignment->tournament->start_date->isFuture())
                                            🕒 {{ $assignment->tournament->start_date->diffForHumans() }}
                                        @elseif($assignment->tournament->start_date->isPast())
                                            ✅ {{ $assignment->tournament->start_date->diffForHumans() }}
                                        @else
                                            🔥 In corso
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $assignment->role ?? 'Arbitro' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($assignment->is_confirmed)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            ✅ Confermata
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            ⏳ In Attesa
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('referee.assignments.show', $assignment) }}"
                                       class="text-blue-600 hover:text-blue-900 mr-3"
                                       title="Visualizza dettagli">
                                        <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Paginazione --}}
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $assignments->links() }}
            </div>
        @else
            {{-- Nessuna assegnazione --}}
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Nessuna assegnazione</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Non hai ancora assegnazioni per {{ $year }}.
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
