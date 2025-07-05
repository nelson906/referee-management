@extends('layouts.admin')

@section('title', 'Gestione Tornei')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tornei</h1>
            <p class="mt-2 text-gray-600">Gestisci i tornei della tua zona</p>
        </div>
        <div class="flex space-x-4">
            <a href="{{ route('referee.availability.calendar') }}"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Calendario
            </a>
            <a href="{{ route('admin.tournaments.create') }}"
               class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nuovo Torneo
            </a>
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

    {{-- Filters --}}
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" action="{{ route('admin.tournaments.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Search --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cerca</label>
                <input type="text"
                       name="search"
                       id="search"
                       value="{{ request('search') }}"
                       placeholder="Nome torneo o circolo..."
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Status Filter --}}
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Stato</label>
                <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutti gli stati</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Zone Filter (only for national admins) --}}
            @if($isNationalAdmin)
            <div>
                <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                <select name="zone_id" id="zone_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le zone</option>
                    @foreach($zones as $zone)
                        <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                            {{ $zone->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Category Filter --}}
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="category_id" id="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Tutte le categorie</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
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
                       value="{{ request('month') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- Submit Button --}}
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition duration-200">
                    Filtra
                </button>
                <a href="{{ route('admin.tournaments.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Tournaments Table --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
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
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Arbitri
                    </th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Stato
                    </th>
                    <th scope="col" class="relative px-6 py-3">
                        <span class="sr-only">Azioni</span>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($tournaments as $tournament)
                <tr class="hover:bg-gray-50 transition-colors duration-150">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">
                            {{ $tournament->name }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Scadenza: {{ $tournament->availability_deadline->format('d/m/Y') }}
                            @if($tournament->days_until_deadline >= 0)
                                <span class="text-xs {{ $tournament->days_until_deadline <= 3 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    ({{ $tournament->days_until_deadline }} giorni)
                                </span>
                            @else
                                <span class="text-xs text-gray-500">(scaduta)</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">
                            {{ $tournament->start_date->format('d/m') }} - {{ $tournament->end_date->format('d/m/Y') }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $tournament->start_date->diffInDays($tournament->end_date) + 1 }} giorni
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">{{ $tournament->club->name }}</div>
                        @if($isNationalAdmin)
                            <div class="text-xs text-gray-500">{{ $tournament->zone->name }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2"
                                 style="background-color: {{ $tournament->tournamentCategory->calendar_color }}"></div>
                            <span class="text-sm text-gray-900">
                                {{ $tournament->tournamentCategory->name }}
                            </span>
                        </div>
                        @if($tournament->tournamentCategory->is_national)
                            <span class="text-xs text-blue-600">Nazionale</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <div class="text-sm text-gray-900">
                            {{ $tournament->assignments()->count() }} / {{ $tournament->required_referees }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Disp: {{ $tournament->availabilities()->count() }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            bg-{{ $tournament->status_color }}-100 text-{{ $tournament->status_color }}-800">
                            {{ $tournament->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            <a href="{{ route('admin.tournaments.show', $tournament) }}"
                               class="text-indigo-600 hover:text-indigo-900" title="Visualizza">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>

                            @if($tournament->isEditable())
                            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                               class="text-blue-600 hover:text-blue-900" title="Modifica">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                            @endif

                            @if($tournament->needsReferees())
                            <a href="{{ route('admin.assignments.create', ['tournament_id' => $tournament->id]) }}"
                               class="text-green-600 hover:text-green-900" title="Assegna Arbitri">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                            </a>
                            @endif

                            @if($tournament->status === 'draft')
                            <form action="{{ route('admin.tournaments.destroy', $tournament) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Sei sicuro di voler eliminare questo torneo?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900" title="Elimina">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-gray-500">Nessun torneo trovato</p>
                        <p class="text-sm text-gray-400 mt-1">Prova a modificare i filtri di ricerca</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
        {{ $tournaments->withQueryString()->links() }}
    </div>

    {{-- Summary Stats --}}
    <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-indigo-600">
                {{ $tournaments->total() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Tornei Totali</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-green-600">
                {{ $tournaments->where('status', 'open')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Aperti</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-yellow-600">
                {{ $tournaments->whereIn('status', ['closed', 'assigned'])->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">In Corso</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-3xl font-bold text-gray-600">
                {{ $tournaments->where('status', 'completed')->count() }}
            </div>
            <div class="text-sm text-gray-600 mt-1">Completati</div>
        </div>
    </div>
</div>
@endsection
