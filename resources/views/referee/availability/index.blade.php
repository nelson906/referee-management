@extends('layouts.referee')

@section('title', 'Le Mie Disponibilità')

@section('content')
<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Le Mie Disponibilità</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Seleziona i tornei per cui sei disponibile
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('referee.availability.calendar') }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Vista Calendario
                    </a>
                    <a href="{{ route('referee.assignments.index') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Le Mie Assegnazioni
                    </a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="mb-6 bg-white rounded-lg shadow p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                    <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutte le zone</option>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ $zoneId == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type_id" class="block text-sm font-medium text-gray-700 mb-1">Tipo Torneo</label>
                    <select name="type_id" id="type_id" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Tutti i tipi</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" {{ $typeId == $type->id ? 'selected' : '' }}>
                                {{ $type->short_name ?? $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mese</label>
                    <input type="month" name="month" id="month" value="{{ $month }}"
                           class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Filtra
                    </button>
                </div>

                <div class="flex items-end">
                    <a href="{{ route('referee.availability.index') }}"
                       class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Tournaments Table --}}
        @if($tournaments->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nessun torneo trovato</h3>
                <p class="text-gray-600">Non ci sono tornei corrispondenti ai filtri selezionati.</p>
            </div>
        @else
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <form method="POST" action="{{ route('referee.availability.save') }}">
                    @csrf

                    {{-- Table Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    Tornei ({{ $tournaments->total() }})
                                </h3>
                                <div class="text-sm text-gray-600">
                                    Pagina {{ $tournaments->currentPage() }} di {{ $tournaments->lastPage() }}
                                </div>
                            </div>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Salva Disponibilità
                            </button>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
                                        ✓
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Torneo
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                        Tipo
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Circolo / Zona
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                        Date
                                    </th>
                                    <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                        Giorni
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tournaments as $tournament)
                                    @php
                                        $daysUntilDeadline = $tournament->days_until_deadline;
                                        $deadlineClass = '';
                                        $deadlineText = '';

                                        if ($daysUntilDeadline !== null) {
                                            if ($daysUntilDeadline < 0) {
                                                // Deadline passata - vuoto
                                                $deadlineClass = '';
                                                $deadlineText = '';
                                            } elseif ($daysUntilDeadline <= 7) {
                                                // Entro 7 giorni - rosso
                                                $deadlineClass = 'text-red-600 font-medium';
                                                $deadlineText = $daysUntilDeadline;
                                            } else {
                                                // Oltre 7 giorni - verde
                                                $deadlineClass = 'text-green-600';
                                                $deadlineText = $daysUntilDeadline;
                                            }
                                        }
                                    @endphp

                                    <tr class="hover:bg-gray-50">
                                        {{-- ✅ SEMPLIFICATO: Checkbox sempre presente --}}
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <input type="checkbox"
                                                   name="availabilities[]"
                                                   value="{{ $tournament->id }}"
                                                   {{ $tournament->user_has_availability ? 'checked' : '' }}
                                                   class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                        </td>

                                        {{-- Nome Torneo --}}
                                        <td class="px-3 py-4 text-sm">
                                            <div class="font-medium text-gray-900">
                                                {{ $tournament->name }}
                                            </div>
                                        </td>

                                        {{-- Tipo Torneo --}}
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div>{{ $tournament->tournamentType->short_name ?? $tournament->tournamentType->name ?? 'N/A' }}</div>
                                            @if($tournament->tournamentType->is_national ?? false)
                                                <div class="text-xs text-blue-600 font-medium">🌍 Nazionale</div>
                                            @endif
                                        </td>

                                        {{-- Circolo / Zona --}}
                                        <td class="px-3 py-4 text-sm text-gray-500">
                                            <div>{{ $tournament->club->name ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-400">
                                                {{ $tournament->zone->name ?? 'N/A' }}
                                                @auth
                                                    @if($tournament->zone_id != auth()->user()->zone_id && ($tournament->tournamentType->is_national ?? false))
                                                        <span class="text-blue-600 font-medium">(Fuori zona)</span>
                                                    @endif
                                                @endauth
                                            </div>
                                        </td>

                                        {{-- Date --}}
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div>{{ $tournament->start_date->format('d/m/Y') }}</div>
                                            @if($tournament->start_date->format('d/m/Y') !== $tournament->end_date->format('d/m/Y'))
                                                <div class="text-xs">{{ $tournament->end_date->format('d/m/Y') }}</div>
                                            @endif
                                        </td>

                                        {{-- Giorni Deadline --}}
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm {{ $deadlineClass }}">
                                            {{ $deadlineText }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Submit Button & Pagination --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium text-green-600">Verde:</span> deadline oltre 7 giorni •
                                <span class="font-medium text-red-600">Rosso:</span> deadline entro 7 giorni •
                                <span class="text-gray-500">Vuoto:</span> deadline scaduta
                            </div>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Salva Disponibilità
                            </button>
                        </div>

                        {{-- Pagination --}}
                        @if($tournaments->hasPages())
                            <div class="mt-4 flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Risultati {{ $tournaments->firstItem() }}-{{ $tournaments->lastItem() }} di {{ $tournaments->total() }}
                                </div>

                                <div class="flex items-center space-x-2">
                                    {{-- Previous Page Link --}}
                                    @if ($tournaments->onFirstPage())
                                        <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-300 rounded-md">
                                            ‹ Prec
                                        </span>
                                    @else
                                        <a href="{{ $tournaments->appends(request()->query())->previousPageUrl() }}"
                                           class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            ‹ Prec
                                        </a>
                                    @endif

                                    {{-- Page Numbers --}}
                                    @foreach($tournaments->getUrlRange(max(1, $tournaments->currentPage() - 2), min($tournaments->lastPage(), $tournaments->currentPage() + 2)) as $page => $url)
                                        @if ($page == $tournaments->currentPage())
                                            <span class="px-3 py-2 text-sm bg-blue-600 text-white border border-blue-600 rounded-md">
                                                {{ $page }}
                                            </span>
                                        @else
                                            <a href="{{ $url }}"
                                               class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                                {{ $page }}
                                            </a>
                                        @endif
                                    @endforeach

                                    {{-- Next Page Link --}}
                                    @if ($tournaments->hasMorePages())
                                        <a href="{{ $tournaments->appends(request()->query())->nextPageUrl() }}"
                                           class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            Succ ›
                                        </a>
                                    @else
                                        <span class="px-3 py-2 text-sm text-gray-400 bg-white border border-gray-300 rounded-md">
                                            Succ ›
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
